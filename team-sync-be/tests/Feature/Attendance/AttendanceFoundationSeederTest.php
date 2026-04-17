<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendancePolicy;
use App\Models\LeaveEntitlement;
use Database\Seeders\AttendancePolicySeeder;
use Database\Seeders\LeaveEntitlementSeeder;
use Database\Seeders\MinimalPayrollE2ESeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceFoundationSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_attendance_policy_seeder_creates_default_policy_rows(): void
    {
        $this->seed(AttendancePolicySeeder::class);

        $this->assertSame(4, AttendancePolicy::count());

        $partTime = AttendancePolicy::query()
            ->where('employment_type', 'part_time')
            ->firstOrFail();

        $this->assertSame('09:00:00', $partTime->work_start_time);
        $this->assertSame('13:00:00', $partTime->work_end_time);
        $this->assertSame(3, $partTime->work_days_per_week);
        $this->assertSame(['monday', 'wednesday', 'friday'], $partTime->default_working_weekdays);
        $this->assertSame('2.00', $partTime->half_day_min_hours);
        $this->assertSame('20.00', $partTime->warning_absent_pct);
    }

    public function test_leave_entitlement_seeder_creates_expected_matrix(): void
    {
        $this->seed(LeaveEntitlementSeeder::class);

        $this->assertSame(28, LeaveEntitlement::count());

        $fullTimeSick = LeaveEntitlement::query()
            ->where('employment_type', 'full_time')
            ->where('leave_type', 'sick_leave')
            ->firstOrFail();

        $this->assertTrue($fullTimeSick->is_eligible);
        $this->assertTrue($fullTimeSick->is_paid);
        $this->assertTrue($fullTimeSick->requires_attachment);
        $this->assertSame(['application/pdf', 'image/jpeg', 'image/png'], $fullTimeSick->allowed_mime_types);
        $this->assertSame(5120, $fullTimeSick->max_attachment_size_kb);

        $internMaternity = LeaveEntitlement::query()
            ->where('employment_type', 'intern')
            ->where('leave_type', 'maternity_leave')
            ->firstOrFail();

        $this->assertFalse($internMaternity->is_eligible);
        $this->assertFalse($internMaternity->is_paid);
        $this->assertSame('0.00', $internMaternity->quota_days);

        $partTimeAnnual = LeaveEntitlement::query()
            ->where('employment_type', 'part_time')
            ->where('leave_type', 'annual_leave')
            ->firstOrFail();

        $this->assertSame('7.00', $partTimeAnnual->quota_days);
        $this->assertSame(5, $partTimeAnnual->carry_over_max_days);
    }

    public function test_minimal_payroll_e2e_seeder_also_bootstraps_attendance_foundation_data(): void
    {
        $this->seed(MinimalPayrollE2ESeeder::class);

        $this->assertSame(4, AttendancePolicy::count());
        $this->assertSame(28, LeaveEntitlement::count());
    }
}
