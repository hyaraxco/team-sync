<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\StaffMemberProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendanceCorrection>
 */
class AttendanceCorrectionFactory extends Factory
{
    protected $model = AttendanceCorrection::class;

    public function definition(): array
    {
        $status = fake()->randomElement(['pending', 'approved', 'rejected']);
        $originalCheckIn = fake()->dateTimeBetween('-2 months', '-1 day');
        $originalCheckOut = (clone $originalCheckIn)->modify('+8 hours');
        $requestedCheckIn = (clone $originalCheckIn)->modify((string) fake()->randomElement(['-30 minutes', '-15 minutes', '+15 minutes']));
        $requestedCheckOut = (clone $originalCheckOut)->modify((string) fake()->randomElement(['-30 minutes', '+15 minutes', '+30 minutes']));

        return [
            'attendance_id' => Attendance::factory(),
            'staff_member_id' => StaffMemberProfile::factory(),
            'original_check_in' => $originalCheckIn,
            'original_check_out' => $originalCheckOut,
            'requested_check_in' => $requestedCheckIn,
            'requested_check_out' => $requestedCheckOut,
            'reason' => fake()->sentence(),
            'status' => $status,
            'reviewed_by' => $status === 'pending' ? null : User::factory(),
            'review_notes' => $status === 'pending' ? null : fake()->optional()->sentence(),
        ];
    }
}
