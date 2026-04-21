<?php

namespace Tests\Feature\Attendance;

use App\Models\Attendance;
use App\Models\AttendancePolicyMismatch;
use App\Models\StaffMemberProfile;
use App\Models\HolidayCalendar;
use App\Models\HybridWorkSchedule;
use App\Models\JobInformation;
use App\Models\LeaveRequest;
use App\Services\Attendance\AttendanceClassifier;
use Database\Seeders\AttendancePolicySeeder;
use Database\Seeders\LeaveEntitlementSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceClassifierTest extends TestCase
{
    use RefreshDatabase;

    private AttendanceClassifier $classifier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RoleSeeder::class,
            AttendancePolicySeeder::class,
            LeaveEntitlementSeeder::class,
        ]);

        $this->classifier = app(AttendanceClassifier::class);
    }

    public function test_holiday_has_higher_priority_than_attendance(): void
    {
        $employee = $this->createEmployeeWithEmploymentType('full_time');

        HolidayCalendar::create([
            'date' => '2026-04-06',
            'name' => 'National Holiday',
            'type' => 'national',
            'applies_to' => null,
        ]);

        $this->createAttendance($employee, '2026-04-06', [
            'check_in' => '2026-04-06 08:50:00',
            'check_out' => '2026-04-06 17:00:00',
            'worked_minutes' => 490,
        ]);

        $classified = $this->classifier->classify($employee->id, '2026-04-06');

        $this->assertSame('holiday', $classified['status']);
        $this->assertSame('holiday', $classified['source']);
    }

    public function test_approved_valid_paid_leave_is_classified_as_leave_type(): void
    {
        $employee = $this->createEmployeeWithEmploymentType('full_time');

        LeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_type' => 'annual_leave',
            'start_date' => '2026-04-08',
            'end_date' => '2026-04-08',
            'total_days' => 1,
            'reason' => 'Family event',
            'status' => 'approved',
        ]);

        $classified = $this->classifier->classify($employee->id, '2026-04-08');

        $this->assertSame('annual_leave', $classified['status']);
        $this->assertSame('leave', $classified['source']);
        $this->assertTrue($classified['is_paid_leave']);
    }

    public function test_ineligible_leave_falls_back_to_absent(): void
    {
        $employee = $this->createEmployeeWithEmploymentType('intern');

        LeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_type' => 'maternity_leave',
            'start_date' => '2026-04-08',
            'end_date' => '2026-04-08',
            'total_days' => 1,
            'reason' => 'Request that is not eligible for intern',
            'status' => 'approved',
        ]);

        $classified = $this->classifier->classify($employee->id, '2026-04-08');

        $this->assertSame('absent', $classified['status']);
        $this->assertSame('absent', $classified['source']);
    }

    public function test_on_time_check_in_is_classified_as_present(): void
    {
        $employee = $this->createEmployeeWithEmploymentType('full_time');

        $this->createAttendance($employee, '2026-04-06', [
            'check_in' => '2026-04-06 09:00:00',
            'check_out' => '2026-04-06 17:00:00',
            'worked_minutes' => 480,
        ]);

        $classified = $this->classifier->classify($employee->id, '2026-04-06');

        $this->assertSame('present', $classified['status']);
        $this->assertSame('attendance', $classified['source']);
    }

    public function test_check_in_within_grace_period_is_classified_as_late(): void
    {
        $employee = $this->createEmployeeWithEmploymentType('full_time');

        $this->createAttendance($employee, '2026-04-07', [
            'check_in' => '2026-04-07 09:20:00',
            'check_out' => '2026-04-07 17:00:00',
            'worked_minutes' => 460,
        ]);

        $classified = $this->classifier->classify($employee->id, '2026-04-07');

        $this->assertSame('late', $classified['status']);
        $this->assertSame('attendance', $classified['source']);
    }

    public function test_check_in_beyond_grace_with_enough_minutes_is_half_day(): void
    {
        $employee = $this->createEmployeeWithEmploymentType('full_time');

        $this->createAttendance($employee, '2026-04-08', [
            'check_in' => '2026-04-08 10:10:00',
            'check_out' => '2026-04-08 16:10:00',
            'worked_minutes' => 240,
        ]);

        $classified = $this->classifier->classify($employee->id, '2026-04-08');

        $this->assertSame('half_day', $classified['status']);
        $this->assertSame('attendance', $classified['source']);
    }

    public function test_check_in_beyond_grace_with_null_worked_minutes_is_absent(): void
    {
        $employee = $this->createEmployeeWithEmploymentType('full_time');

        $this->createAttendance($employee, '2026-04-09', [
            'check_in' => '2026-04-09 10:20:00',
            'check_out' => '2026-04-09 14:20:00',
            'worked_minutes' => null,
        ]);

        $classified = $this->classifier->classify($employee->id, '2026-04-09');

        $this->assertSame('absent', $classified['status']);
        $this->assertSame('attendance', $classified['source']);
    }

    public function test_hybrid_mismatch_sets_flag_and_creates_mismatch_record(): void
    {
        $employee = $this->createEmployeeWithEmploymentType('full_time', 'hybrid');

        HybridWorkSchedule::create([
            'employee_id' => $employee->id,
            'effective_from' => '2026-04-01',
            'effective_until' => null,
            'monday' => 'office',
            'tuesday' => 'remote',
            'wednesday' => 'office',
            'thursday' => 'remote',
            'friday' => 'office',
        ]);

        $attendance = $this->createAttendance($employee, '2026-04-06', [
            'check_in' => '2026-04-06 09:00:00',
            'check_out' => '2026-04-06 17:00:00',
            'worked_minutes' => 480,
            'actual_work_mode' => 'remote',
            'policy_mismatch_flag' => false,
        ]);

        $classified = $this->classifier->classify($employee->id, '2026-04-06');

        $this->assertSame('present', $classified['status']);
        $this->assertTrue($classified['policy_mismatch_flag']);
        $this->assertTrue($attendance->fresh()->policy_mismatch_flag);

        $this->assertDatabaseHas('attendance_policy_mismatches', [
            'attendance_id' => $attendance->id,
            'employee_id' => $employee->id,
            'planned_work_mode' => 'office',
            'actual_work_mode' => 'remote',
            'status' => 'pending_review',
        ]);

        $this->assertTrue(
            AttendancePolicyMismatch::query()
                ->where('attendance_id', $attendance->id)
                ->whereDate('mismatch_date', '2026-04-06')
                ->exists()
        );

        $this->assertSame(1, AttendancePolicyMismatch::query()->where('attendance_id', $attendance->id)->count());
    }

    private function createEmployeeWithEmploymentType(string $employmentType, string $workLocation = 'office'): StaffMemberProfile
    {
        $employee = StaffMemberProfile::withoutSyncingToSearch(function () {
            return StaffMemberProfile::factory()->create();
        });

        JobInformation::factory()
            ->forEmployee($employee)
            ->active()
            ->state([
                'employment_type' => $employmentType,
                'work_location' => $workLocation,
                'status' => 'active',
                'monthly_salary' => 10000000,
            ])
            ->create();

        return $employee;
    }

    private function createAttendance(StaffMemberProfile $employee, string $date, array $overrides = []): Attendance
    {
        return Attendance::create(array_merge([
            'employee_id' => $employee->id,
            'date' => $date,
            'check_in' => $date.' 09:00:00',
            'check_out' => $date.' 17:00:00',
            'worked_minutes' => 480,
            'status' => 'present',
            'notes' => 'Classifier branch test',
        ], $overrides));
    }
}