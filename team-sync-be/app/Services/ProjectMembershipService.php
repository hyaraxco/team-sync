<?php

namespace App\Services;

use App\Models\Project;
use App\Models\TeamMember;
use App\Models\User;

class ProjectMembershipService
{
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

        // Project leader is always a member
        if ($project->project_leader_id === $profile->id) {
            return true;
        }

        // Check team membership
        $jobInfoTeamId = $profile->jobInformation->team_id ?? null;
        $teamMemberIds = TeamMember::where('staff_member_id', $profile->id)
            ->whereNull('left_at')
            ->pluck('team_id')
            ->toArray();

        $teamIds = array_unique(array_filter(array_merge(
            $jobInfoTeamId ? [$jobInfoTeamId] : [],
            $teamMemberIds
        )));

        $projectTeamIds = $project->teams->pluck('id')->toArray();

        return ! empty(array_intersect($teamIds, $projectTeamIds));
    }

    /**
     * Check if a staff member profile ID is a member of a project.
     */
    public function isMemberById(int $profileId, ?Project $project): bool
    {
        if (! $project) {
            return false;
        }

        // Project leader is always a member
        if ($project->project_leader_id === $profileId) {
            return true;
        }

        // Check team membership
        $teamMemberIds = TeamMember::where('staff_member_id', $profileId)
            ->whereNull('left_at')
            ->pluck('team_id')
            ->toArray();

        $projectTeamIds = $project->teams->pluck('id')->toArray();

        return ! empty(array_intersect($teamMemberIds, $projectTeamIds));
    }
}
