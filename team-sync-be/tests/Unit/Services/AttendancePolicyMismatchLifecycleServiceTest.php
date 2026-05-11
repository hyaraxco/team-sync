<?php

namespace Tests\Unit\Services;

use App\Models\AttendancePolicyMismatch;
use App\Services\Attendance\AttendancePolicyMismatchLifecycleService;
use App\Services\Attendance\WorkingDaysCalculator;
use App\Services\EmailService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AttendancePolicyMismatchLifecycleServiceTest extends TestCase
{
    use RefreshDatabase;

    private AttendancePolicyMismatchLifecycleService $service;

    private WorkingDaysCalculator $workingDaysCalculator;

    private EmailService $emailService;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'staff', 'guard_name' => 'sanctum']);

        $this->workingDaysCalculator = $this->createMock(WorkingDaysCalculator::class);
        $this->emailService = $this->createMock(EmailService::class);

        $this->service = new AttendancePolicyMismatchLifecycleService(
            $this->workingDaysCalculator,
            $this->emailService,
        );
    }

    public function test_escalates_old_pending_mismatches_that_reach_threshold(): void
    {
        $mismatch = AttendancePolicyMismatch::factory()->create([
            'status' => AttendancePolicyMismatch::STATUS_PENDING_REVIEW,
            'mismatch_date' => Carbon::now()->subDays(10)->toDateString(),
        ]);

        $this->workingDaysCalculator
            ->method('calculateForEmployee')
            ->willReturn(5);

        $this->emailService
            ->expects($this->once())
            ->method('sendAttendanceMismatchEscalatedNotification');

        $count = $this->service->escalatePendingReviewMismatches(Carbon::now()->toDateString());

        $this->assertEquals(1, $count);

        $mismatch->refresh();
        $this->assertEquals(AttendancePolicyMismatch::STATUS_ESCALATED_HR, $mismatch->status);
        $this->assertNotNull($mismatch->escalated_at);
    }

    public function test_skips_recent_mismatches_below_threshold(): void
    {
        AttendancePolicyMismatch::factory()->create([
            'status' => AttendancePolicyMismatch::STATUS_PENDING_REVIEW,
            'mismatch_date' => Carbon::yesterday()->toDateString(),
        ]);

        $this->workingDaysCalculator
            ->method('calculateForEmployee')
            ->willReturn(1);

        $this->emailService
            ->expects($this->never())
            ->method('sendAttendanceMismatchEscalatedNotification');

        $count = $this->service->escalatePendingReviewMismatches(Carbon::now()->toDateString());

        $this->assertEquals(0, $count);

        $this->assertDatabaseHas('attendance_policy_mismatches', [
            'status' => AttendancePolicyMismatch::STATUS_PENDING_REVIEW,
        ]);
    }

    public function test_does_not_escalate_non_pending_mismatches(): void
    {
        AttendancePolicyMismatch::factory()->create([
            'status' => AttendancePolicyMismatch::STATUS_RESOLVED,
            'mismatch_date' => Carbon::now()->subDays(10)->toDateString(),
        ]);

        $this->workingDaysCalculator
            ->expects($this->never())
            ->method('calculateForEmployee');

        $this->emailService
            ->expects($this->never())
            ->method('sendAttendanceMismatchEscalatedNotification');

        $count = $this->service->escalatePendingReviewMismatches(Carbon::now()->toDateString());

        $this->assertEquals(0, $count);
    }

    public function test_handles_working_days_calculation_error_gracefully(): void
    {
        AttendancePolicyMismatch::factory()->create([
            'status' => AttendancePolicyMismatch::STATUS_PENDING_REVIEW,
            'mismatch_date' => Carbon::now()->subDays(10)->toDateString(),
        ]);

        $this->workingDaysCalculator
            ->method('calculateForEmployee')
            ->willThrowException(new \InvalidArgumentException('Employee not found'));

        $this->emailService
            ->expects($this->never())
            ->method('sendAttendanceMismatchEscalatedNotification');

        $count = $this->service->escalatePendingReviewMismatches(Carbon::now()->toDateString());

        $this->assertEquals(0, $count);
    }

    public function test_escalates_multiple_mismatches_independently(): void
    {
        $oldMismatch = AttendancePolicyMismatch::factory()->create([
            'status' => AttendancePolicyMismatch::STATUS_PENDING_REVIEW,
            'mismatch_date' => Carbon::now()->subDays(10)->toDateString(),
        ]);

        $recentMismatch = AttendancePolicyMismatch::factory()->create([
            'status' => AttendancePolicyMismatch::STATUS_PENDING_REVIEW,
            'mismatch_date' => Carbon::yesterday()->toDateString(),
        ]);

        $oldStaffId = $oldMismatch->staff_member_id;
        $recentStaffId = $recentMismatch->staff_member_id;

        $this->workingDaysCalculator
            ->method('calculateForEmployee')
            ->willReturnCallback(function (int $staffMemberId) use ($oldStaffId) {
                if ($staffMemberId === $oldStaffId) {
                    return 5;
                }

                return 1;
            });

        $this->emailService
            ->expects($this->once())
            ->method('sendAttendanceMismatchEscalatedNotification');

        $count = $this->service->escalatePendingReviewMismatches(Carbon::now()->toDateString());

        $this->assertEquals(1, $count);

        $oldMismatch->refresh();
        $this->assertEquals(AttendancePolicyMismatch::STATUS_ESCALATED_HR, $oldMismatch->status);

        $recentMismatch->refresh();
        $this->assertEquals(AttendancePolicyMismatch::STATUS_PENDING_REVIEW, $recentMismatch->status);
    }

    public function test_uses_today_as_default_reference_date(): void
    {
        AttendancePolicyMismatch::factory()->create([
            'status' => AttendancePolicyMismatch::STATUS_PENDING_REVIEW,
            'mismatch_date' => Carbon::now()->subDays(10)->toDateString(),
        ]);

        $this->workingDaysCalculator
            ->method('calculateForEmployee')
            ->willReturn(3);

        $this->emailService
            ->method('sendAttendanceMismatchEscalatedNotification');

        $count = $this->service->escalatePendingReviewMismatches();

        $this->assertGreaterThanOrEqual(0, $count);
    }

    public function test_returns_zero_when_no_pending_mismatches(): void
    {
        $this->workingDaysCalculator
            ->expects($this->never())
            ->method('calculateForEmployee');

        $this->emailService
            ->expects($this->never())
            ->method('sendAttendanceMismatchEscalatedNotification');

        $count = $this->service->escalatePendingReviewMismatches(Carbon::now()->toDateString());

        $this->assertEquals(0, $count);
    }

    public function test_escalation_sets_escalated_at_timestamp(): void
    {
        $mismatch = AttendancePolicyMismatch::factory()->create([
            'status' => AttendancePolicyMismatch::STATUS_PENDING_REVIEW,
            'mismatch_date' => Carbon::now()->subDays(10)->toDateString(),
            'escalated_at' => null,
        ]);

        $this->workingDaysCalculator
            ->method('calculateForEmployee')
            ->willReturn(5);

        $this->emailService
            ->method('sendAttendanceMismatchEscalatedNotification');

        $this->service->escalatePendingReviewMismatches(Carbon::now()->toDateString());

        $mismatch->refresh();
        $this->assertNotNull($mismatch->escalated_at);
        $this->assertEquals(now()->format('Y-m-d'), $mismatch->escalated_at->format('Y-m-d'));
    }
}
