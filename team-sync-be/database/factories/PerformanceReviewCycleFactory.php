<?php

namespace Database\Factories;

use App\Models\PerformanceReviewCycle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PerformanceReviewCycle>
 */
class PerformanceReviewCycleFactory extends Factory
{
    protected $model = PerformanceReviewCycle::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('now', '+1 month');
        $endDate = fake()->dateTimeBetween($startDate, '+1 year');
        $reviewStart = fake()->dateTimeBetween($startDate, $endDate);
        $reviewEnd = fake()->dateTimeBetween($reviewStart, $endDate);
        
        return [
            'name' => fake()->randomElement([
                'Q1 2026 Performance Review',
                'Q2 2026 Performance Review',
                'Annual Review 2026',
                'Mid-Year Review 2026',
                'End of Year Review 2026',
            ]),
            'cycle_type' => fake()->randomElement(['quarterly', 'semi_annual', 'annual', 'probation']),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'review_period_start' => $reviewStart,
            'review_period_end' => $reviewEnd,
            'self_assessment_deadline' => fake()->optional()->dateTimeBetween($reviewStart, $reviewEnd),
            'manager_assessment_deadline' => fake()->optional()->dateTimeBetween($reviewStart, $reviewEnd),
            'calibration_deadline' => fake()->optional()->dateTimeBetween($reviewStart, $reviewEnd),
            'status' => fake()->randomElement(['draft', 'active', 'completed', 'cancelled']),
            'created_by' => \App\Models\User::factory(),
        ];
    }

    /**
     * Indicate that the cycle is in draft status.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'draft',
        ]);
    }

    /**
     * Indicate that the cycle is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }

    /**
     * Indicate that the cycle is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
        ]);
    }
}
