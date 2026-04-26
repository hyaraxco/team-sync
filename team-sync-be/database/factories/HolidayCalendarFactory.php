<?php

namespace Database\Factories;

use App\Models\HolidayCalendar;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HolidayCalendar>
 */
class HolidayCalendarFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'date' => $this->faker->date(),
            'name' => $this->faker->sentence(3),
            'type' => $this->faker->randomElement(['national_holiday', 'collective_leave']),
            'applies_to' => ['all'],
        ];
    }
}
