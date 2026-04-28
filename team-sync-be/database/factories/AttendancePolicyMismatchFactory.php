<?php

namespace Database\Factories;

use App\Models\Attendance;
use App\Models\AttendancePolicyMismatch;
use App\Models\StaffMemberProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AttendancePolicyMismatch>
 */
class AttendancePolicyMismatchFactory extends Factory
{
    protected $model = AttendancePolicyMismatch::class;

    public function definition(): array
    {
        $status = fake()->randomElement([
            AttendancePolicyMismatch::STATUS_PENDING_REVIEW,
            AttendancePolicyMismatch::STATUS_ACKNOWLEDGED,
            AttendancePolicyMismatch::STATUS_ESCALATED_HR,
            AttendancePolicyMismatch::STATUS_RESOLVED,
        ]);

        $acknowledgedAt = in_array($status, [
            AttendancePolicyMismatch::STATUS_ACKNOWLEDGED,
            AttendancePolicyMismatch::STATUS_ESCALATED_HR,
            AttendancePolicyMismatch::STATUS_RESOLVED,
        ], true) ? fake()->dateTimeBetween('-7 days', 'now') : null;

        $escalatedAt = in_array($status, [
            AttendancePolicyMismatch::STATUS_ESCALATED_HR,
            AttendancePolicyMismatch::STATUS_RESOLVED,
        ], true) ? fake()->dateTimeBetween($acknowledgedAt ?: '-3 days', 'now') : null;

        $resolvedAt = $status === AttendancePolicyMismatch::STATUS_RESOLVED
            ? fake()->dateTimeBetween($escalatedAt ?: '-2 days', 'now')
            : null;

        return [
            'attendance_id' => Attendance::factory(),
            'staff_member_id' => StaffMemberProfile::factory(),
            'mismatch_date' => fake()->date(),
            'planned_work_mode' => fake()->randomElement(['office', 'remote']),
            'actual_work_mode' => fake()->randomElement(['office', 'remote']),
            'status' => $status,
            'acknowledged_by' => $acknowledgedAt ? StaffMemberProfile::factory() : null,
            'acknowledged_at' => $acknowledgedAt,
            'escalated_at' => $escalatedAt,
            'resolved_by' => $resolvedAt ? StaffMemberProfile::factory() : null,
            'resolved_at' => $resolvedAt,
            'resolution_notes' => $resolvedAt ? fake()->sentence() : null,
        ];
    }
}
