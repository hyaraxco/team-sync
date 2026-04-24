<?php

namespace App\Services\Performance;

use App\Models\PerformanceOutcomeRule;
use App\Models\PerformanceReview;

class PerformanceOutcomeService
{
    public function applyOutcome(PerformanceReview $review): PerformanceReview
    {
        if ($review->final_rating === null) {
            return $review;
        }

        $rule = PerformanceOutcomeRule::findForRating((float) $review->final_rating);

        if (! $rule) {
            return $review;
        }

        $review->update([
            'outcome_rule_id' => $rule->id,
            'bonus_months' => $rule->bonus_months,
            'salary_increase_pct' => $rule->salary_increase_pct,
            'promotion_eligible' => $rule->promotion_eligible,
            'pip_required' => $rule->pip_required,
            'outcome_applied_at' => now(),
        ]);

        return $review->fresh();
    }
}
