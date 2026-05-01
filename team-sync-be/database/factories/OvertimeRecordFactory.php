<?php

namespace Database\Factories;

use App\Models\OvertimeRecord;
use App\Models\StaffMemberProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OvertimeRecord>
 */
class OvertimeRecordFactory extends Factory
{
    protected $model = OvertimeRecord::class;

    public function definition(): array
    {
        $startHour = fake()->numberBetween(17, 19);
        $hours = fake()->randomFloat(2, 1, 4);
        $endHour = $startHour + (int) ceil($hours);
        $endMinute = (int) (($hours - floor($hours)) * 60);

        return [
            'staff_member_id' => StaffMemberProfile::factory(),
            'attendance_id' => null,
            'date' => fake()->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'start_time' => sprintf('%02d:00', $startHour),
            'end_time' => sprintf('%02d:%02d', min($endHour, 23), $endMinute),
            'hours' => $hours,
            'overtime_type' => fake()->randomElement(['workday', 'weekend', 'holiday']),
            'status' => OvertimeRecord::STATUS_PENDING,
            'approved_by' => null,
            'approved_at' => null,
            'notes' => fake()->optional()->sentence(),
            'rejection_reason' => null,
            'company_id' => null,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OvertimeRecord::STATUS_APPROVED,
            'approved_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OvertimeRecord::STATUS_REJECTED,
            'rejection_reason' => fake()->sentence(5),
        ]);
    }

    public function workday(): static
    {
        return $this->state(fn (array $attributes) => [
            'overtime_type' => OvertimeRecord::TYPE_WORKDAY,
        ]);
    }

    public function weekend(): static
    {
        return $this->state(fn (array $attributes) => [
            'overtime_type' => OvertimeRecord::TYPE_WEEKEND,
        ]);
    }

    public function holiday(): static
    {
        return $this->state(fn (array $attributes) => [
            'overtime_type' => OvertimeRecord::TYPE_HOLIDAY,
        ]);
    }

    public function forEmployee(StaffMemberProfile $employee): static
    {
        return $this->state(fn (array $attributes) => [
            'staff_member_id' => $employee->id,
        ]);
    }

    public function hours(float $hours): static
    {
        return $this->state(fn (array $attributes) => [
            'hours' => $hours,
            'start_time' => '17:00',
            'end_time' => sprintf('%02d:%02d', 17 + (int) floor($hours), (int) (($hours - floor($hours)) * 60)),
        ]);
    }
}
