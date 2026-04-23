<?php

namespace Database\Seeders;

use App\Models\ReviewerRule;
use Illuminate\Database\Seeder;

class ReviewerRuleSeeder extends Seeder
{
    /**
     * Default reviewer chain:
     *   staff    → reviewed by manager (priority 1)
     *   manager  → reviewed by hr      (priority 1)
     *   finance  → reviewed by hr      (priority 1)
     *   hr       → reviewed by hr      (priority 1, self-team/cross review)
     */
    public function run(): void
    {
        $rules = [
            ['reviewee_role' => 'staff',   'reviewer_role' => 'manager', 'priority' => 1],
            ['reviewee_role' => 'manager', 'reviewer_role' => 'hr',      'priority' => 1],
            ['reviewee_role' => 'finance', 'reviewer_role' => 'hr',      'priority' => 1],
            ['reviewee_role' => 'hr',      'reviewer_role' => 'hr',      'priority' => 1],
        ];

        foreach ($rules as $rule) {
            ReviewerRule::updateOrCreate(
                [
                    'reviewee_role' => $rule['reviewee_role'],
                    'reviewer_role' => $rule['reviewer_role'],
                ],
                [
                    'priority' => $rule['priority'],
                    'is_active' => true,
                ]
            );
        }
    }
}
