<?php

namespace Database\Factories;

use App\Models\AttendancePolicy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendancePolicy>
 */
class AttendancePolicyFactory extends Factory
{
    protected $model = AttendancePolicy::class;

    public function definition(): array
    {
        $employmentType = fake()->randomElement(['full_time', 'contract', 'intern', 'part_time']);

        return [
            'employment_type' => $employmentType,
            'work_start_time' => fake()->randomElement(['08:00:00', '08:30:00', '09:00:00']),
            'work_end_time' => fake()->randomElement(['16:00:00', '16:30:00', '17:00:00']),
            'work_days_per_week' => fake()->numberBetween(3, 6),
            'default_working_weekdays' => fake()->randomElement([
                ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
                ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'],
                ['monday', 'wednesday', 'friday'],
            ]),
            'late_grace_minutes' => fake()->randomElement([10, 15, 20, 30]),
            'half_day_min_hours' => fake()->randomElement([2.00, 3.00, 4.00]),
            'warning_absent_pct' => fake()->randomElement([10.00, 15.00, 20.00]),
        ];
    }
}
