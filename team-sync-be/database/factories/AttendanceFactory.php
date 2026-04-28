<?php

namespace Database\Factories;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\AttendancePeriod;
use App\Models\StaffMemberProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Attendance>
 */
class AttendanceFactory extends Factory
{
    protected $model = Attendance::class;

    public function definition(): array
    {
        $date = fake()->dateTimeBetween('-2 months', 'now');
        $checkIn = (clone $date)->setTime(fake()->numberBetween(7, 10), fake()->numberBetween(0, 59));
        $workedMinutes = fake()->numberBetween(240, 600);
        $checkOut = (clone $checkIn)->modify("+{$workedMinutes} minutes");
        $actualWorkMode = fake()->randomElement(['office', 'remote']);

        return [
            'staff_member_id' => StaffMemberProfile::factory(),
            'date' => $date->format('Y-m-d'),
            'attendance_period_id' => AttendancePeriod::factory(),
            'check_in' => $checkIn,
            'check_in_lat' => fake()->latitude(-11, 6),
            'check_in_long' => fake()->longitude(95, 141),
            'check_out' => $checkOut,
            'worked_minutes' => $workedMinutes,
            'actual_work_mode' => $actualWorkMode,
            'policy_mismatch_flag' => fake()->boolean(20),
            'check_out_lat' => fake()->latitude(-11, 6),
            'check_out_long' => fake()->longitude(95, 141),
            'status' => fake()->randomElement(array_column(AttendanceStatus::cases(), 'value')),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
