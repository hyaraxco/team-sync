<?php

namespace Database\Seeders;

use App\Models\PerformanceOutcomeRule;
use Illuminate\Database\Seeder;

class PerformanceOutcomeRuleSeeder extends Seeder
{
    public function run(): void
    {
        $rules = [
            [
                'label' => 'Outstanding',
                'min_rating' => 4.50,
                'max_rating' => 5.00,
                'bonus_months' => 3.00,
                'salary_increase_pct' => 10.00,
                'promotion_eligible' => true,
                'pip_required' => false,
                'description' => 'Top performer — eligible for promotion and maximum bonus.',
            ],
            [
                'label' => 'Exceeds Expectations',
                'min_rating' => 3.50,
                'max_rating' => 4.49,
                'bonus_months' => 2.00,
                'salary_increase_pct' => 7.00,
                'promotion_eligible' => false,
                'pip_required' => false,
                'description' => 'Above average performer — eligible for bonus and salary increase.',
            ],
            [
                'label' => 'Meets Expectations',
                'min_rating' => 2.50,
                'max_rating' => 3.49,
                'bonus_months' => 1.00,
                'salary_increase_pct' => 4.00,
                'promotion_eligible' => false,
                'pip_required' => false,
                'description' => 'Satisfactory performer — standard bonus and salary increase.',
            ],
            [
                'label' => 'Needs Improvement',
                'min_rating' => 1.50,
                'max_rating' => 2.49,
                'bonus_months' => 0.00,
                'salary_increase_pct' => 0.00,
                'promotion_eligible' => false,
                'pip_required' => true,
                'description' => 'Below expectations — Performance Improvement Plan required.',
            ],
            [
                'label' => 'Unsatisfactory',
                'min_rating' => 1.00,
                'max_rating' => 1.49,
                'bonus_months' => 0.00,
                'salary_increase_pct' => 0.00,
                'promotion_eligible' => false,
                'pip_required' => true,
                'description' => 'Significantly below expectations — immediate PIP required.',
            ],
        ];

        foreach ($rules as $rule) {
            PerformanceOutcomeRule::updateOrCreate(
                ['label' => $rule['label']],
                $rule
            );
        }
    }
}
