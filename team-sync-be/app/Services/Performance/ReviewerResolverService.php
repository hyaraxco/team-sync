<?php

namespace App\Services\Performance;

use App\Models\ReviewerRule;
use App\Models\StaffMemberProfile;
use Illuminate\Support\Collection;

class ReviewerResolverService
{
    /**
     * Resolve the reviewer for a given staff member based on reviewer rules.
     *
     * Strategy (Hybrid — Opsi C):
     * 1. Determine the reviewee's Spatie role
     * 2. Look up reviewer_rules for matching reviewee_role (ordered by priority)
     * 3. Find an active staff member with the reviewer_role
     * 4. Prefer same-team lead, fall back to any user with that role
     * 5. Return null if no match (HR must assign manually)
     *
     * @param  StaffMemberProfile  $staffMember  The employee being reviewed
     * @return StaffMemberProfile|null The suggested reviewer, or null for manual assignment
     */
    public function resolve(StaffMemberProfile $staffMember): ?StaffMemberProfile
    {
        $user = $staffMember->user;
        if (! $user) {
            return null;
        }

        $revieweeRole = $user->getRoleNames()->first();
        if (! $revieweeRole) {
            return null;
        }

        // Get matching rules ordered by priority
        $rules = ReviewerRule::active()
            ->where('reviewee_role', $revieweeRole)
            ->orderBy('priority')
            ->get();

        foreach ($rules as $rule) {
            $reviewer = $this->findReviewer($staffMember, $rule->reviewer_role);
            if ($reviewer) {
                return $reviewer;
            }
        }

        return null;
    }

    /**
     * Find a reviewer with the given role.
     *
     * Priority order:
     * 1. Team lead of the same team (if they have the reviewer_role)
     * 2. Any active user with the reviewer_role (excluding the reviewee themselves)
     */
    private function findReviewer(StaffMemberProfile $staffMember, string $reviewerRole): ?StaffMemberProfile
    {
        // 1. Try same-team member with the reviewer role first
        $teamIds = $staffMember->teams()->pluck('teams.id');
        if ($teamIds->isNotEmpty()) {
            $teamReviewer = StaffMemberProfile::whereHas('user', function ($q) use ($reviewerRole, $staffMember) {
                $q->role($reviewerRole)
                    ->where('id', '!=', $staffMember->user_id);
            })
                ->whereHas('teams', function ($q) use ($teamIds) {
                    $q->whereIn('teams.id', $teamIds);
                })
                ->first();

            if ($teamReviewer) {
                return $teamReviewer;
            }
        }

        // 2. Fallback: any user with the reviewer role
        return StaffMemberProfile::whereHas('user', function ($q) use ($reviewerRole, $staffMember) {
            $q->role($reviewerRole)
                ->where('id', '!=', $staffMember->user_id);
        })->first();
    }

    /**
     * Batch-resolve reviewers for multiple staff members.
     *
     * @param  Collection  $staffMembers  Collection of StaffMemberProfile
     * @return array<int, int|null> Map of staff_member_id => reviewer_staff_member_id (or null)
     */
    public function resolveMany($staffMembers): array
    {
        $assignments = [];
        foreach ($staffMembers as $staffMember) {
            $reviewer = $this->resolve($staffMember);
            $assignments[$staffMember->id] = $reviewer?->id;
        }

        return $assignments;
    }
}
