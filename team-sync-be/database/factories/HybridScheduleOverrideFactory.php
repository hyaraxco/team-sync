<?php

namespace Database\Factories;

use App\Models\HybridScheduleOverride;
use App\Models\StaffMemberProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

class HybridScheduleOverrideFactory extends Factory
{
    protected $model = HybridScheduleOverride::class;

    public function definition(): array
    {
        return [
            'staff_member_id' => StaffMemberProfile::factory(),
            'date' => $this->faker->date(),
            'planned_work_mode' => $this->faker->randomElement(['WFH', 'WFO', 'REMOTE']),
            'reason' => $this->faker->sentence(),
            'status' => 'pending',
            'requested_by' => StaffMemberProfile::factory(),
        ];
    }
}
