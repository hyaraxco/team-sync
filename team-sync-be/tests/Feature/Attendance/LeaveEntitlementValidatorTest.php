<?php

namespace Tests\Feature\Attendance;

use App\Models\StaffMemberProfile;
use App\Models\JobInformation;
use App\Models\LeaveEntitlement;
use App\Models\LeaveRequest;
use App\Services\Attendance\LeaveEntitlementValidator;
use Database\Seeders\AttendancePolicySeeder;
use Database\Seeders\LeaveEntitlementSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveEntitlementValidatorTest extends TestCase
{
    use RefreshDatabase;

    private LeaveEntitlementValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RoleSeeder::class,
            AttendancePolicySeeder::class,
            LeaveEntitlementSeeder::class,
        ]);

        $this->validator = app(LeaveEntitlementValidator::class);
    }

    public function test_annual_leave_exceeding_quota_is_invalid(): void
    {
        $employee = $this->createEmployeeWithEmploymentType('full_time');

        LeaveEntitlement::query()
            ->where('employment_type', 'full_time')
            ->where('leave_type', 'annual_leave')
            ->update([
                'quota_scope' => 'annual',
                'quota_days' => 1.00,
            ]);

        $leaveRequest = $this->createLeaveRequest($employee, [
            'leave_type' => 'annual_leave',
            'start_date' => '2026-04-06',
            'end_date' => '2026-04-07',
            'total_days' => 2,
            'reason' => 'Family plan',
        ]);

        $result = $this->validator->validate($leaveRequest, '2026-04-06');

        $this->assertFalse($result['valid']);
        $this->assertContains('quota_exceeded_annual', $result['errors']);
    }

    public function test_sick_leave_without_attachment_is_invalid(): void
    {
        $employee = $this->createEmployeeWithEmploymentType('full_time');

        $leaveRequest = $this->createLeaveRequest($employee, [
            'leave_type' => 'sick_leave',
            'start_date' => '2026-04-08',
            'end_date' => '2026-04-08',
            'total_days' => 1,
            'reason' => 'Fever',
        ]);

        $result = $this->validator->validate($leaveRequest, '2026-04-08');

        $this->assertFalse($result['valid']);
        $this->assertContains('sick_leave_proof_required', $result['errors']);
    }

    public function test_leave_type_not_eligible_for_employment_type_is_invalid(): void
    {
        $employee = $this->createEmployeeWithEmploymentType('intern');

        $leaveRequest = $this->createLeaveRequest($employee, [
            'leave_type' => 'maternity_leave',
            'start_date' => '2026-04-08',
            'end_date' => '2026-04-08',
            'total_days' => 1,
            'reason' => 'Eligibility check',
        ]);

        $result = $this->validator->validate($leaveRequest, '2026-04-08');

        $this->assertFalse($result['valid']);
        $this->assertContains('leave_type_not_eligible', $result['errors']);
    }

    public function test_emergency_leave_without_reason_is_invalid(): void
    {
        $employee = $this->createEmployeeWithEmploymentType('full_time');

        $leaveRequest = $this->createLeaveRequest($employee, [
            'leave_type' => 'emergency_leave',
            'start_date' => '2026-04-09',
            'end_date' => '2026-04-09',
            'total_days' => 1,
            'reason' => '   ',
        ]);

        $result = $this->validator->validate($leaveRequest, '2026-04-09');

        $this->assertFalse($result['valid']);
        $this->assertContains('emergency_leave_reason_required', $result['errors']);
    }

    public function test_valid_leave_passes_validation(): void
    {
        $employee = $this->createEmployeeWithEmploymentType('full_time');

        $leaveRequest = $this->createLeaveRequest($employee, [
            'leave_type' => 'annual_leave',
            'start_date' => '2026-04-10',
            'end_date' => '2026-04-10',
            'total_days' => 1,
            'reason' => 'Family event',
        ]);

        $result = $this->validator->validate($leaveRequest, '2026-04-10');

        $this->assertTrue($result['valid']);
        $this->assertSame([], $result['errors']);
        $this->assertTrue($result['is_paid_leave']);
    }

    private function createEmployeeWithEmploymentType(string $employmentType): StaffMemberProfile
    {
        $employee = StaffMemberProfile::withoutSyncingToSearch(function () {
            return StaffMemberProfile::factory()->create();
        });

        JobInformation::factory()
            ->forEmployee($employee)
            ->active()
            ->state([
                'employment_type' => $employmentType,
                'work_location' => 'office',
                'status' => 'active',
                'monthly_salary' => 10000000,
            ])
            ->create();

        return $employee;
    }

    private function createLeaveRequest(StaffMemberProfile $employee, array $overrides = []): LeaveRequest
    {
        return LeaveRequest::query()->create(array_merge([
            'employee_id' => $employee->id,
            'leave_type' => 'annual_leave',
            'start_date' => '2026-04-06',
            'end_date' => '2026-04-06',
            'total_days' => 1,
            'reason' => 'Validator branch test',
            'status' => 'approved',
        ], $overrides));
    }
}
