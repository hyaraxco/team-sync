<?php

namespace App\Services;

use App\Enums\JobStatus;
use App\Interfaces\ProjectRepositoryInterface;
use App\Models\Project;
use App\Models\StaffMemberProfile;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use RuntimeException;

class ProjectMembershipService
{
    /**
     * Resolve the team IDs a staff member profile belongs to.
     * Combines jobInformation.team_id and active team_members rows.
     *
     * @return array<int>
     */
    private function resolveProfileTeamIds(int $profileId): array
    {
        $profile = StaffMemberProfile::with('jobInformation')->find($profileId);
        $jobInfoTeamId = $profile?->jobInformation?->team_id;

        $teamMemberIds = TeamMember::where('staff_member_id', $profileId)
            ->whereNull('left_at')
            ->pluck('team_id')
            ->toArray();

        return array_values(array_unique(array_filter(array_merge(
            $jobInfoTeamId ? [$jobInfoTeamId] : [],
            $teamMemberIds,
        ))));
    }

    /**
     * Check if a user is a member of a project.
     * A user is a member if:
     * - They are the project leader, OR
     * - Their team (via job information or team membership) is assigned to the project
     */
    public function isMember(User $user, ?Project $project): bool
    {
        if (! $project) {
            return false;
        }

        $profile = $user->staffMemberProfile;
        if (! $profile) {
            return false;
        }

        return $this->isMemberById($profile->id, $project);
    }

    /**
     * Check if a staff member profile ID is a member of a project.
     * Aligned with isMember(): checks jobInformation.team_id AND team_members.
     */
    public function isMemberById(int $profileId, ?Project $project): bool
    {
        if (! $project) {
            return false;
        }

        // Project leader is always a member
        if ((int) $project->project_leader_id === (int) $profileId) {
            return true;
        }

        $project->loadMissing('teams');
        $projectTeamIds = $project->teams->pluck('id')->toArray();
        if (empty($projectTeamIds)) {
            return false;
        }

        $teamIds = $this->resolveProfileTeamIds($profileId);

        return ! empty(array_intersect($teamIds, $projectTeamIds));
    }

    /**
     * Get all active staff members assigned to a project's teams.
     * Project leader is included even if not on a team.
     */
    public function getProjectMembers(Project $project): Collection
    {
        $project->loadMissing('teams');
        $projectTeamIds = $project->teams->pluck('id')->toArray();

        $query = StaffMemberProfile::query()
            ->with(['user', 'jobInformation.team', 'teams'])
            ->whereHas('jobInformation', function ($q) {
                $q->where('status', JobStatus::ACTIVE->value);
            });

        if (! empty($projectTeamIds)) {
            $query->where(function ($q) use ($projectTeamIds, $project) {
                $q->whereHas('teamMembers', function ($qq) use ($projectTeamIds) {
                    $qq->whereIn('team_id', $projectTeamIds)
                        ->whereNull('left_at');
                });

                if ($project->project_leader_id) {
                    $q->orWhere('id', $project->project_leader_id);
                }
            });
        } elseif ($project->project_leader_id) {
            $query->where('id', $project->project_leader_id);
        } else {
            return new Collection;
        }

        return $query->orderBy('id')->get()->unique('id')->values();
    }

    /**
     * Get eligible project leader candidates.
     * Filters by active project members.
     * Optionally filters by seniority_level (case-insensitive match).
     * Falls back to all active members if filter eliminates everyone.
     *
     * @return array{members: Collection, warning: ?string}
     */
    public function getEligibleLeaders(Project $project, ?string $seniorityLevel = null): array
    {
        $members = $this->getProjectMembers($project);
        $warning = null;

        if ($seniorityLevel !== null && $seniorityLevel !== '') {
            $needle = strtolower(trim($seniorityLevel));
            $filtered = $members->filter(function (StaffMemberProfile $member) use ($needle) {
                $level = strtolower((string) ($member->seniority_level ?? ''));

                return $level !== '' && $level === $needle;
            })->values();

            if ($filtered->isEmpty() && $members->isNotEmpty()) {
                $warning = 'No staff matched the seniority filter. Showing all active project members.';
            } else {
                $members = $filtered;
            }
        }

        return [
            'members' => $members,
            'warning' => $warning,
        ];
    }

    /**
     * Reassign the project leader.
     *
     * Validates:
     * - Candidate exists.
     * - Candidate is an active member of one of the project's teams.
     *
     * Throws InvalidProjectLeaderException with a 422-friendly message on validation failure.
     */
    public function reassignLeader(int $projectId, int $newLeaderId): Project
    {
        /** @var ProjectRepositoryInterface $projectRepository */
        $projectRepository = app(ProjectRepositoryInterface::class);

        $project = $projectRepository->findById((string) $projectId);
        $project->loadMissing('teams');

        $candidate = StaffMemberProfile::with('jobInformation')->find($newLeaderId);
        if (! $candidate) {
            throw new ModelNotFoundException('Candidate staff profile not found.');
        }

        $jobStatus = $candidate->jobInformation?->status;
        if ($jobStatus !== JobStatus::ACTIVE->value) {
            throw new InvalidProjectLeaderException('Project leader must be an active staff member.');
        }

        if (! $this->isMemberById($newLeaderId, $project)) {
            throw new InvalidProjectLeaderException(
                'Staff member must be an active member of one of the project teams.'
            );
        }

        return $projectRepository->update((string) $projectId, [
            'project_leader_id' => $newLeaderId,
        ]);
    }
}

class InvalidProjectLeaderException extends RuntimeException {}
