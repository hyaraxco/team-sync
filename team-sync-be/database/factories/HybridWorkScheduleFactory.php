<?php

namespace Database\Factories;

use App\Models\HybridWorkSchedule;
use App\Models\StaffMemberProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

class HybridWorkScheduleFactory extends Factory
{
    protected $model = HybridWorkSchedule::class;

    public function definition(): array
    {
        return [
            'staff_member_id' => StaffMemberProfile::factory(),
            'effective_from' => $this->faker->date(),
            'effective_until' => null,
            'monday' => 'WFO',
            'tuesday' => 'WFH',
            'wednesday' => 'WFO',
            'thursday' => 'WFH',
            'friday' => 'WFO',
        ];
    }
}
