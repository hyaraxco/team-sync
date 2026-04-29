<?php

namespace Database\Factories;

use App\Enums\Department;
use App\Models\Meeting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Meeting>
 */
class MeetingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $departmentValues = array_map(
            static fn (Department $department) => $department->value,
            Department::cases()
        );
        $departments = null;

        if (fake()->boolean()) {
            $selectedKeys = array_rand($departmentValues, rand(1, 3));
            $selectedKeys = is_array($selectedKeys) ? $selectedKeys : [$selectedKeys];

            $departments = array_values(array_map(
                static fn (int $key) => $departmentValues[$key],
                $selectedKeys
            ));
        }

        return [
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->paragraph(),
            'scheduled_at' => fake()->dateTimeBetween('+1 hour', '+30 days'),
            'duration_minutes' => fake()->randomElement([15, 30, 45, 60, 90, 120]),
            'location' => fake()->optional()->url(),
            'departments' => $departments,
            'created_by' => User::factory(),
            'reminder_sent_at' => null,
        ];
    }
}
