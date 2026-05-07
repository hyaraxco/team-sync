<?php

namespace App\Policies;

use App\Models\StaffMemberProfile;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class StaffMemberProfilePolicy
{
    /**
     * Determine if the user can view any staff member profiles.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasAnyPermission(['staff-member-list', 'staff-member-create', 'staff-member-edit', 'staff-member-delete']);
    }

    /**
     * Determine if the user can view a specific staff member profile.
     */
    public function view(User $user, StaffMemberProfile $profile): Response
    {
        if ($user->hasAnyPermission(['staff-member-list', 'staff-member-create', 'staff-member-edit', 'staff-member-delete'])) {
            return Response::allow();
        }

        // Staff can view their own profile
        if ($user->staffMemberProfile?->id === $profile->id) {
            return Response::allow();
        }

        return Response::deny('You do not have permission to view this profile.');
    }

    /**
     * Determine if the user can create staff member profiles.
     */
    public function create(User $user): Response
    {
        if ($user->hasPermissionTo('staff-member-create')) {
            return Response::allow();
        }

        return Response::deny('You do not have permission to create staff members.');
    }

    /**
     * Determine if the user can update a staff member profile.
     */
    public function update(User $user, StaffMemberProfile $profile): Response
    {
        if ($user->hasPermissionTo('staff-member-edit')) {
            return Response::allow();
        }

        return Response::deny('You do not have permission to edit staff members.');
    }

    /**
     * Determine if the user can delete a staff member profile.
     */
    public function delete(User $user, StaffMemberProfile $profile): Response
    {
        if ($user->hasPermissionTo('staff-member-delete')) {
            return Response::allow();
        }

        return Response::deny('You do not have permission to delete staff members.');
    }

    /**
     * Determine if the user can view staff member statistics.
     */
    public function viewStatistics(User $user): Response
    {
        if ($user->hasPermissionTo('staff-member-statistic')) {
            return Response::allow();
        }

        return Response::deny('You do not have permission to view staff member statistics.');
    }

    /**
     * Determine if the user can view their own profile (self-service).
     */
    public function viewOwn(User $user): Response
    {
        if ($user->hasPermissionTo('profile-view')) {
            return Response::allow();
        }

        return Response::deny('You do not have permission to view your profile.');
    }

    /**
     * Determine if the user can view their own team info (self-service).
     */
    public function viewOwnTeam(User $user): Response
    {
        if ($user->hasPermissionTo('team-view')) {
            return Response::allow();
        }

        return Response::deny('You do not have permission to view your team.');
    }
}
