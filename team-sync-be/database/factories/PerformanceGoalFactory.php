<?php

namespace Database\Factories;

use App\Models\PerformanceGoal;
use App\Models\StaffMemberProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PerformanceGoal>
 */
class PerformanceGoalFactory extends Factory
{
    protected $model = PerformanceGoal::class;

    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('-1 month', 'now');
        $dueDate = fake()->dateTimeBetween($startDate, '+6 months');
        $status = fake()->randomElement(['not_started', 'in_progress', 'at_risk', 'completed', 'cancelled']);
        $completionPercentage = $status === 'completed' ? 100 : fake()->numberBetween(0, 95);

        return [
            'staff_member_id' => StaffMemberProfile::factory(),
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'goal_type' => fake()->randomElement(['okr', 'kpi', 'development', 'project']),
            'category' => fake()->optional()->randomElement(['productivity', 'quality', 'leadership', 'delivery']),
            'target_value' => fake()->optional()->numberBetween(1, 100) . '%',
            'current_value' => fake()->optional()->numberBetween(0, 100) . '%',
            'unit' => fake()->optional()->randomElement(['%', 'points', 'milestone', 'tasks']),
            'weight' => fake()->randomFloat(2, 5, 40),
            'start_date' => $startDate->format('Y-m-d'),
            'due_date' => $dueDate->format('Y-m-d'),
            'status' => $status,
            'completion_percentage' => $completionPercentage,
            'completed_at' => $status === 'completed' ? fake()->dateTimeBetween($startDate, 'now') : null,
            'created_by' => User::factory(),
            'assigned_by' => rand(1, 10) <= 7 ? StaffMemberProfile::factory() : null,
            'linked_review_id' => null,
        ];
    }
}
