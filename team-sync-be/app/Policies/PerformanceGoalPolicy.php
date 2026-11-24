<?php

namespace App\Policies;

use App\Models\PerformanceGoal;
use App\Models\StaffMemberProfile;
use App\Models\User;

class PerformanceGoalPolicy
{
    public function create(User $user, int $staffMemberId): bool
    {
        return $user->can('goal-create-own') && $this->canAccessStaffMember($user, $staffMemberId);
    }

    public function view(User $user, PerformanceGoal $goal): bool
    {
        return $user->can('goal-create-own') && $this->canAccessStaffMember($user, $goal->staff_member_id);
    }

    public function update(User $user, PerformanceGoal $goal): bool
    {
        return $this->view($user, $goal);
    }

    public function delete(User $user, PerformanceGoal $goal): bool
    {
        return $this->view($user, $goal);
    }

    private function canAccessStaffMember(User $user, int $staffMemberId): bool
    {
        if ($user->staffMemberProfile && $user->staffMemberProfile->id === $staffMemberId) {
            return true;
        }

        if (! $user->can('goal-assign-team')) {
            return false;
        }

        if ($user->hasAnyRole(['hr', 'superadmin'])) {
            return true;
        }

        $teamIds = $user->leadTeams()->select('teams.id');

        return StaffMemberProfile::whereKey($staffMemberId)
            ->where(function ($query) use ($teamIds) {
                $query->whereHas('teamMembers', fn ($members) => $members
                    ->whereNull('left_at')->whereIn('team_id', $teamIds))
                    ->orWhereHas('jobInformation', fn ($job) => $job->whereIn('team_id', $teamIds));
            })->exists();
    }
}
