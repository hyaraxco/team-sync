<?php

namespace Database\Factories;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\StaffMemberProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectTask>
 */
class ProjectTaskFactory extends Factory
{
    protected $model = ProjectTask::class;

    public function definition(): array
    {
        $status = fake()->randomElement(array_column(TaskStatus::cases(), 'value'));
        $isRejected = $status === TaskStatus::REJECTED->value;

        return [
            'project_id' => Project::factory(),
            'name' => fake()->sentence(3),
            'description' => fake()->optional()->paragraph(),
            'assignee_id' => rand(1, 10) <= 8 ? StaffMemberProfile::factory() : null,
            'priority' => fake()->randomElement(array_column(TaskPriority::cases(), 'value')),
            'status' => $status,
            'rejected_reason' => $isRejected ? fake()->sentence() : null,
            'rejected_by' => $isRejected ? StaffMemberProfile::factory() : null,
            'rejected_at' => $isRejected ? fake()->dateTimeBetween('-14 days', 'now') : null,
            'due_date' => fake()->boolean(90) ? fake()->dateTimeBetween('now', '+3 months')->format('Y-m-d') : null,
        ];
    }
}
