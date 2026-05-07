<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TeamPolicy
{
    /**
     * Determine if the user can view any teams.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['team-list', 'team-create', 'team-edit', 'team-delete']);
    }

    /**
     * Determine if the user can view a specific team.
     */
    public function view(User $user, Team $team): Response
    {
        if ($user->hasAnyPermission(['team-list', 'team-create', 'team-edit', 'team-delete'])) {
            return Response::allow();
        }

        // Staff can view their own team
        $profile = $user->staffMemberProfile;
        if ($profile) {
            $userTeamId = $profile->jobInformation->team_id ?? null;
            if ($userTeamId && $userTeamId === $team->id) {
                return Response::allow();
            }
        }

        return Response::deny('You do not have permission to view this team.');
    }

    /**
     * Determine if the user can create teams.
     */
    public function create(User $user): Response
    {
        if ($user->hasPermissionTo('team-create')) {
            return Response::allow();
        }

        return Response::deny('You do not have permission to create teams.');
    }

    /**
     * Determine if the user can update a team.
     */
    public function update(User $user, Team $team): Response
    {
        if ($user->hasPermissionTo('team-edit')) {
            return Response::allow();
        }

        return Response::deny('You do not have permission to edit teams.');
    }

    /**
     * Determine if the user can delete a team.
     */
    public function delete(User $user, Team $team): Response
    {
        if ($user->hasPermissionTo('team-delete')) {
            return Response::allow();
        }

        return Response::deny('You do not have permission to delete teams.');
    }

    /**
     * Determine if the user can view team statistics.
     */
    public function viewStatistics(User $user): Response
    {
        if ($user->hasPermissionTo('team-statistic')) {
            return Response::allow();
        }

        return Response::deny('You do not have permission to view team statistics.');
    }

    /**
     * Determine if the user can manage team members (add/remove).
     */
    public function manageMember(User $user, Team $team): Response
    {
        if ($user->hasPermissionTo('team-edit')) {
            return Response::allow();
        }

        return Response::deny('You do not have permission to manage team members.');
    }
}
