<?php

namespace Database\Factories;

use App\Models\AttendancePeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttendancePeriodFactory extends Factory
{
    protected $model = AttendancePeriod::class;

    public function definition(): array
    {
        return [
            'start_date' => $this->faker->dateTimeBetween('-1 month', 'now')->format('Y-m-d'),
            'end_date' => $this->faker->dateTimeBetween('now', '+1 month')->format('Y-m-d'),
            'cutoff_date' => clone $this->faker->dateTimeBetween('now', '+1 month'),
            'status' => AttendancePeriod::STATUS_OPEN,
            'locked_at' => null,
        ];
    }
}
