<?php

namespace Tests\Feature\Leave;

use App\Models\AttendancePeriod;
use App\Models\LeaveRequest;
use App\Models\Payroll;
use App\Models\PayrollAdjustment;
use App\Models\PayrollDetail;
use App\Models\StaffMemberProfile;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\ActivatesLicense;
use Tests\TestCase;

class LeaveRequestProofReviewAdjustmentTest extends TestCase
{
    use ActivatesLicense, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,
        ]);

        $this->activateTestLicense();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_hr_approved_sick_proof_creates_post_lock_adjustment_without_duplicates(): void
    {
        Carbon::setTestNow('2026-04-28 09:00:00');

        $employee = $this->createActiveEmployee('full_time');

        $sourcePeriod = AttendancePeriod::create([
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'cutoff_date' => '2026-04-25',
            'status' => AttendancePeriod::STATUS_LOCKED,
            'locked_at' => now(),
        ]);

        $sourcePayroll = Payroll::create([
            'salary_month' => '2026-04-01',
            'attendance_period_id' => $sourcePeriod->id,
            'status' => 'pending',
        ]);

        PayrollDetail::create([
            'payroll_id' => $sourcePayroll->id,
            'staff_member_id' => $employee->id,
            'original_salary' => 8000000,
            'final_salary' => 7900000,
            'effective_working_days' => 20,
            'daily_rate' => 120000,
            'attended_days' => 19,
            'present_days' => 19,
            'late_days' => 0,
            'half_day_count' => 0,
            'paid_leave_days' => 0,
            'unpaid_leave_days' => 0,
            'holiday_days' => 0,
            'sick_days' => 0,
            'absent_days' => 1,
            'deduction_days' => 1,
            'deduction_amount' => 100000,
            'policy_mismatch_days' => 0,
            'warning_flags' => null,
            'notes' => 'source payroll',
        ]);

        $leaveRequest = LeaveRequest::create([
            'staff_member_id' => $employee->id,
            'leave_type' => 'sick_leave',
            'start_date' => '2026-04-10',
            'end_date' => '2026-04-11',
            'total_days' => 2,
            'reason' => 'Sick with doctor note',
            'status' => 'approved',
            'proof_file_path' => 'proof/sick-note.pdf',
            'proof_file_name' => 'sick-note.pdf',
            'proof_mime_type' => 'application/pdf',
            'proof_size_kb' => 128,
            'proof_uploaded_at' => now()->subDay(),
        ]);

        $hr = $this->actingAsHrWithStaffMemberProfile();

        $this->postJson("/api/v1/leave-requests/{$leaveRequest->id}/proof-review", [
            'proof_review_status' => 'approved',
            'proof_review_notes' => 'Validated by HR',
        ])
            ->assertOk()
            ->assertJsonPath('data.proof_review_status', 'approved');

        $targetPeriod = AttendancePeriod::query()
            ->whereDate('start_date', '2026-05-01')
            ->first();

        $this->assertNotNull($targetPeriod);

        $adjustment = PayrollAdjustment::query()
            ->where('staff_member_id', $employee->id)
            ->where('source_period_id', $sourcePeriod->id)
            ->where('source_reference_type', 'leave_request')
            ->where('source_reference_id', $leaveRequest->id)
            ->first();

        $this->assertNotNull($adjustment);
        $this->assertSame($targetPeriod->id, $adjustment->target_period_id);
        $this->assertSame(PayrollAdjustment::KIND_ABSENCE_CORRECTION_CREDIT, $adjustment->adjustment_kind);
        $this->assertSame(PayrollAdjustment::STATUS_APPROVED, $adjustment->status);
        $this->assertEquals(2.0, (float) $adjustment->days_delta);
        $this->assertEquals(240000.0, (float) $adjustment->amount_delta);

        $this->assertDatabaseHas('leave_requests', [
            'id' => $leaveRequest->id,
            'proof_review_status' => 'approved',
            'proof_reviewed_by' => $hr->staffMemberProfile->id,
        ]);

        // Idempotency check: re-approving should not create a duplicate adjustment.
        $this->postJson("/api/v1/leave-requests/{$leaveRequest->id}/proof-review", [
            'proof_review_status' => 'approved',
            'proof_review_notes' => 'Re-approved safely',
        ])->assertOk();

        $this->assertSame(
            1,
            PayrollAdjustment::query()
                ->where('source_reference_type', 'leave_request')
                ->where('source_reference_id', $leaveRequest->id)
                ->count()
        );
    }

    public function test_hr_approved_sick_proof_does_not_create_adjustment_when_source_period_not_locked(): void
    {
        Carbon::setTestNow('2026-04-20 09:00:00');

        $employee = $this->createActiveEmployee('full_time');

        AttendancePeriod::create([
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'cutoff_date' => '2026-04-25',
            'status' => AttendancePeriod::STATUS_REVIEW,
        ]);

        $leaveRequest = LeaveRequest::create([
            'staff_member_id' => $employee->id,
            'leave_type' => 'sick_leave',
            'start_date' => '2026-04-10',
            'end_date' => '2026-04-10',
            'total_days' => 1,
            'reason' => 'Sick follow-up',
            'status' => 'approved',
            'proof_file_path' => 'proof/sick-note-2.pdf',
            'proof_file_name' => 'sick-note-2.pdf',
            'proof_mime_type' => 'application/pdf',
            'proof_size_kb' => 256,
            'proof_uploaded_at' => now()->subHours(8),
        ]);

        $this->actingAsHrWithStaffMemberProfile();

        $this->postJson("/api/v1/leave-requests/{$leaveRequest->id}/proof-review", [
            'proof_review_status' => 'approved',
            'proof_review_notes' => 'Reviewed while period still review',
        ])
            ->assertOk()
            ->assertJsonPath('data.proof_review_status', 'approved');

        $this->assertSame(
            0,
            PayrollAdjustment::query()
                ->where('source_reference_type', 'leave_request')
                ->where('source_reference_id', $leaveRequest->id)
                ->count()
        );
    }

    public function test_hr_approved_sick_proof_skips_locked_default_target_period(): void
    {
        Carbon::setTestNow('2026-05-28 09:00:00');

        $employee = $this->createActiveEmployee('full_time');

        $sourcePeriod = AttendancePeriod::create([
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'cutoff_date' => '2026-04-25',
            'status' => AttendancePeriod::STATUS_LOCKED,
            'locked_at' => now(),
        ]);

        $sourcePayroll = Payroll::create([
            'salary_month' => '2026-04-01',
            'attendance_period_id' => $sourcePeriod->id,
            'status' => 'pending',
        ]);

        PayrollDetail::create([
            'payroll_id' => $sourcePayroll->id,
            'staff_member_id' => $employee->id,
            'original_salary' => 8000000,
            'final_salary' => 7900000,
            'effective_working_days' => 20,
            'daily_rate' => 120000,
            'attended_days' => 19,
            'present_days' => 19,
            'late_days' => 0,
            'half_day_count' => 0,
            'paid_leave_days' => 0,
            'unpaid_leave_days' => 0,
            'holiday_days' => 0,
            'sick_days' => 0,
            'absent_days' => 1,
            'deduction_days' => 1,
            'deduction_amount' => 100000,
            'policy_mismatch_days' => 0,
            'warning_flags' => null,
            'notes' => 'source payroll for locked target skip test',
        ]);

        $lockedDefaultTargetPeriod = AttendancePeriod::create([
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-31',
            'cutoff_date' => '2026-05-25',
            'status' => AttendancePeriod::STATUS_LOCKED,
            'locked_at' => now(),
        ]);

        $nextAdjustablePeriod = AttendancePeriod::create([
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-30',
            'cutoff_date' => '2026-06-25',
            'status' => AttendancePeriod::STATUS_REVIEW,
        ]);

        $leaveRequest = LeaveRequest::create([
            'staff_member_id' => $employee->id,
            'leave_type' => 'sick_leave',
            'start_date' => '2026-04-10',
            'end_date' => '2026-04-10',
            'total_days' => 1,
            'reason' => 'Sick proof approved after default target already locked',
            'status' => 'approved',
            'proof_file_path' => 'proof/sick-note-skip-locked-target.pdf',
            'proof_file_name' => 'sick-note-skip-locked-target.pdf',
            'proof_mime_type' => 'application/pdf',
            'proof_size_kb' => 128,
            'proof_uploaded_at' => now()->subDay(),
        ]);

        $this->actingAsHrWithStaffMemberProfile();

        $this->postJson("/api/v1/leave-requests/{$leaveRequest->id}/proof-review", [
            'proof_review_status' => 'approved',
            'proof_review_notes' => 'Approved and should skip locked default target period',
        ])
            ->assertOk()
            ->assertJsonPath('data.proof_review_status', 'approved');

        $adjustment = PayrollAdjustment::query()
            ->where('staff_member_id', $employee->id)
            ->where('source_period_id', $sourcePeriod->id)
            ->where('source_reference_type', 'leave_request')
            ->where('source_reference_id', $leaveRequest->id)
            ->first();

        $this->assertNotNull($adjustment);
        $this->assertSame($nextAdjustablePeriod->id, $adjustment->target_period_id);
        $this->assertNotSame($lockedDefaultTargetPeriod->id, $adjustment->target_period_id);
        $this->assertSame(PayrollAdjustment::STATUS_APPROVED, $adjustment->status);
        $this->assertEquals(1.0, (float) $adjustment->days_delta);
        $this->assertEquals(120000.0, (float) $adjustment->amount_delta);
    }

    public function test_hr_approved_sick_proof_keeps_existing_applied_adjustment_immutable(): void
    {
        Carbon::setTestNow('2026-04-28 09:00:00');

        $employee = $this->createActiveEmployee('full_time');

        $sourcePeriod = AttendancePeriod::create([
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'cutoff_date' => '2026-04-25',
            'status' => AttendancePeriod::STATUS_LOCKED,
            'locked_at' => now(),
        ]);

        $sourcePayroll = Payroll::create([
            'salary_month' => '2026-04-01',
            'attendance_period_id' => $sourcePeriod->id,
            'status' => 'pending',
        ]);

        PayrollDetail::create([
            'payroll_id' => $sourcePayroll->id,
            'staff_member_id' => $employee->id,
            'original_salary' => 8000000,
            'final_salary' => 7900000,
            'effective_working_days' => 20,
            'daily_rate' => 120000,
            'attended_days' => 19,
            'present_days' => 19,
            'late_days' => 0,
            'half_day_count' => 0,
            'paid_leave_days' => 0,
            'unpaid_leave_days' => 0,
            'holiday_days' => 0,
            'sick_days' => 0,
            'absent_days' => 1,
            'deduction_days' => 1,
            'deduction_amount' => 100000,
            'policy_mismatch_days' => 0,
            'warning_flags' => null,
            'notes' => 'immutable adjustment source payroll',
        ]);

        $targetPeriod = AttendancePeriod::create([
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-31',
            'cutoff_date' => '2026-05-25',
            'status' => AttendancePeriod::STATUS_REVIEW,
        ]);

        $leaveRequest = LeaveRequest::create([
            'staff_member_id' => $employee->id,
            'leave_type' => 'sick_leave',
            'start_date' => '2026-04-10',
            'end_date' => '2026-04-10',
            'total_days' => 1,
            'reason' => 'Sick with post-lock correction',
            'status' => 'approved',
            'proof_file_path' => 'proof/sick-note-immutable.pdf',
            'proof_file_name' => 'sick-note-immutable.pdf',
            'proof_mime_type' => 'application/pdf',
            'proof_size_kb' => 128,
            'proof_uploaded_at' => now()->subDay(),
        ]);

        $adjustment = PayrollAdjustment::create([
            'staff_member_id' => $employee->id,
            'source_period_id' => $sourcePeriod->id,
            'target_period_id' => $targetPeriod->id,
            'source_reference_type' => 'leave_request',
            'source_reference_id' => $leaveRequest->id,
            'adjustment_kind' => PayrollAdjustment::KIND_ABSENCE_CORRECTION_CREDIT,
            'days_delta' => 1,
            'amount_delta' => 12345,
            'reason' => 'already applied and immutable',
            'status' => PayrollAdjustment::STATUS_APPLIED,
        ]);

        $this->actingAsHrWithStaffMemberProfile();

        $this->postJson("/api/v1/leave-requests/{$leaveRequest->id}/proof-review", [
            'proof_review_status' => 'approved',
            'proof_review_notes' => 'Re-approved should not mutate applied adjustment',
        ])
            ->assertOk()
            ->assertJsonPath('data.proof_review_status', 'approved');

        $this->assertSame(
            1,
            PayrollAdjustment::query()
                ->where('source_reference_type', 'leave_request')
                ->where('source_reference_id', $leaveRequest->id)
                ->count()
        );

        $freshAdjustment = $adjustment->fresh();

        $this->assertNotNull($freshAdjustment);
        $this->assertSame(PayrollAdjustment::STATUS_APPLIED, $freshAdjustment->status);
        $this->assertEquals(1.0, (float) $freshAdjustment->days_delta);
        $this->assertEquals(12345.0, (float) $freshAdjustment->amount_delta);
        $this->assertSame('already applied and immutable', $freshAdjustment->reason);
    }

    private function actingAsHrWithStaffMemberProfile(): User
    {
        $employee = StaffMemberProfile::withoutSyncingToSearch(function () {
            return StaffMemberProfile::factory()->create();
        });

        $employee->jobInformation()->create([
            'staff_member_id' => $employee->id,
            'job_title' => 'HR Specialist',
            'years_experience' => 4,
            'status' => 'active',
            'employment_type' => 'full_time',
            'work_location' => 'office',
            'start_date' => '2024-01-01',
            'monthly_salary' => 12000000,
            'skill_level' => 'expert',
        ]);

        $user = $employee->user;
        $user->syncRoles([Role::findByName('hr', 'sanctum')]);

        Sanctum::actingAs($user);

        return $user;
    }

    private function createActiveEmployee(string $employmentType): StaffMemberProfile
    {
        return StaffMemberProfile::withoutSyncingToSearch(function () use ($employmentType) {
            $employee = StaffMemberProfile::factory()->create();

            $employee->jobInformation()->create([
                'staff_member_id' => $employee->id,
                'job_title' => 'Engineer',
                'years_experience' => 3,
                'status' => 'active',
                'employment_type' => $employmentType,
                'work_location' => 'remote',
                'start_date' => '2024-01-01',
                'monthly_salary' => 8500000,
                'skill_level' => 'intermediate',
            ]);

            return $employee;
        });
    }
}
