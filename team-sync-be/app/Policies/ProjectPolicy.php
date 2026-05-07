<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class ProjectPolicy
{
    /**
     * Determine if the user can view any projects.
     * Actual scoping is done in the repository.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['project-list', 'project-create', 'project-edit', 'project-delete']);
    }

    /**
     * Determine if the user can view a specific project.
     * Staff must be a member of the project.
     */
    public function view(User $user, Project $project): Response
    {
        if ($this->isPrivilegedRole($user)) {
            return Response::allow();
        }

        if (! $this->isProjectMember($user, $project)) {
            return Response::deny('You can only view projects you are a member of.');
        }

        return Response::allow();
    }

    /**
     * Determine if the user can create projects.
     * Only manager/HR can create projects.
     */
    public function create(User $user): Response
    {
        if ($user->hasAnyPermission(['project-create'])) {
            return Response::allow();
        }

        return Response::deny('You do not have permission to create projects.');
    }

    /**
     * Determine if the user can update a project.
     * Manager/HR or project leader.
     */
    public function update(User $user, Project $project): Response
    {
        if ($this->isPrivilegedRole($user)) {
            return Response::allow();
        }

        $profile = $user->staffMemberProfile;
        if ($profile && $project->project_leader_id === $profile->id) {
            return Response::allow();
        }

        return Response::deny('You do not have permission to update this project.');
    }

    /**
     * Determine if the user can delete a project.
     * Only manager/HR with project-delete permission.
     */
    public function delete(User $user, Project $project): Response
    {
        if ($user->hasPermissionTo('project-delete')) {
            return Response::allow();
        }

        return Response::deny('You do not have permission to delete projects.');
    }

    /**
     * Determine if the user can view project statistics.
     * Requires project-statistic permission.
     */
    public function viewStatistics(User $user): Response
    {
        if ($user->hasPermissionTo('project-statistic')) {
            return Response::allow();
        }

        return Response::deny('You do not have permission to view project statistics.');
    }

    /**
     * Determine if the user can view squad summary for a project.
     * Requires project-statistic permission + project membership for staff.
     */
    public function viewSquadSummary(User $user, Project $project): Response
    {
        if (! $user->hasPermissionTo('project-statistic')) {
            return Response::deny('You do not have permission to view squad summary.');
        }

        // Privileged roles can see any project's squad summary
        if ($this->isPrivilegedRole($user)) {
            return Response::allow();
        }

        // Others must be project members
        if (! $this->isProjectMember($user, $project)) {
            return Response::deny('You can only view squad summary for projects you are a member of.');
        }

        return Response::allow();
    }

    // ─── Helpers ────────────────────────────────────────────────────────

    private function isPrivilegedRole(User $user): bool
    {
        return $user->hasRole('manager') || $user->hasRole('hr');
    }

    private function isProjectMember(User $user, Project $project): bool
    {
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

        return ! empty(array_intersect($projectTeamIds, $teamIds));
    }
}
