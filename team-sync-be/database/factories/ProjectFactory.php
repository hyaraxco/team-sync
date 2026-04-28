<?php

namespace Database\Factories;

use App\Enums\ProjectPriority;
use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Models\Project;
use App\Models\StaffMemberProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('-3 months', '+1 month');
        $hasEndDate = fake()->boolean(80);

        return [
            'name' => fake()->company() . ' Project',
            'type' => fake()->randomElement(array_column(ProjectType::cases(), 'value')),
            'priority' => fake()->randomElement(array_column(ProjectPriority::cases(), 'value')),
            'status' => fake()->randomElement(array_column(ProjectStatus::cases(), 'value')),
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $hasEndDate ? fake()->dateTimeBetween($startDate, '+9 months')->format('Y-m-d') : null,
            'description' => fake()->optional()->paragraph(),
            'photo' => fake()->optional()->randomElement(['projects/alpha.webp', 'projects/beta.webp', 'projects/gamma.webp']),
            'budget' => fake()->optional(0.7)->randomFloat(2, 1000000, 500000000),
            'project_leader_id' => fake()->optional(0.8)->passthrough(StaffMemberProfile::factory()),
        ];
    }
}
