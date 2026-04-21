<?php

namespace Tests\Feature\Payroll;

use App\Jobs\GeneratePayrollJob;
use App\Interfaces\PayrollRepositoryInterface;
use App\Models\Attendance;
use App\Models\AttendancePeriod;
use App\Models\AttendancePolicyMismatch;
use App\Models\StaffMemberProfile;
use App\Models\LeaveRequest;
use App\Models\Payroll;
use App\Models\PayrollSetting;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Bus\UniqueLock;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PayrollGenerateRulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_hr_cannot_generate_payroll_for_a_future_month(): void
    {
        Queue::fake();
        Carbon::setTestNow('2026-04-28 09:00:00');
        $this->actingAsRole('hr');

        $response = $this->postJson('/api/v1/payrolls/generate', [
            'salary_month' => '2026-05',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('data.reason_code', 'future_month');

        Queue::assertNothingPushed();
    }

    public function test_hr_cannot_generate_duplicate_payroll_period(): void
    {
        Queue::fake();
        Carbon::setTestNow('2026-04-28 09:00:00');
        $this->actingAsRole('hr');

        Payroll::create([
            'salary_month' => '2026-04-01',
            'status' => 'pending',
        ]);

        $response = $this->postJson('/api/v1/payrolls/generate', [
            'salary_month' => '2026-04',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('data.reason_code', 'duplicate_period');

        Queue::assertNothingPushed();
    }

    public function test_hr_cannot_generate_current_month_before_cutoff(): void
    {
        Queue::fake();
        Carbon::setTestNow('2026-04-07 09:00:00');
        PayrollSetting::current()->update([
            'attendance_cutoff_day' => 25,
        ]);
        $this->createActiveEmployeeWithAttendance(now()->startOfMonth());
        $this->actingAsRole('hr');

        $response = $this->postJson('/api/v1/payrolls/generate', [
            'salary_month' => '2026-04',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('data.reason_code', 'cutoff_not_reached');

        Queue::assertNothingPushed();
    }

    public function test_hr_cannot_generate_payroll_when_attendance_is_not_ready(): void
    {
        Queue::fake();
        Carbon::setTestNow('2026-04-28 09:00:00');
        PayrollSetting::current()->update([
            'attendance_cutoff_day' => 25,
        ]);
        StaffMemberProfile::withoutSyncingToSearch(function () {
            $employee = StaffMemberProfile::factory()->create();
            $employee->jobInformation()->create([
                'employee_id' => $employee->id,
                'job_title' => 'QA Engineer',
                'years_experience' => 3,
                'status' => 'active',
                'employment_type' => 'full_time',
                'work_location' => 'remote',
                'start_date' => '2024-01-01',
                'monthly_salary' => 9000000,
                'skill_level' => 'intermediate',
            ]);
        });
        $this->actingAsRole('hr');

        $response = $this->postJson('/api/v1/payrolls/generate', [
            'salary_month' => '2026-04',
        ]);

        $response
            ->assertStatus(422)
            ->assertJsonPath('data.reason_code', 'attendance_not_ready');

        Queue::assertNothingPushed();
    }

    public function test_hr_can_generate_payroll_for_a_valid_month_after_cutoff(): void
    {
        Queue::fake();
        Carbon::setTestNow('2026-04-28 09:00:00');
        PayrollSetting::current()->update([
            'attendance_cutoff_day' => 25,
        ]);
        $this->createActiveEmployeeWithAttendance(now()->startOfMonth());
        $this->actingAsRole('hr');

        $this->postJson('/api/v1/payrolls/generate', [
            'salary_month' => '2026-04',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'processing');

        Queue::assertPushed(GeneratePayrollJob::class);
    }

    public function test_generate_endpoint_dispatches_single_unique_job_when_requests_overlap(): void
    {
        $originalQueueDriver = config('queue.default');
        config(['queue.default' => 'database']);

        try {
            Carbon::setTestNow('2026-04-28 09:00:00');
            PayrollSetting::current()->update([
                'attendance_cutoff_day' => 25,
            ]);
            $this->createActiveEmployeeWithAttendance(now()->startOfMonth());
            $this->actingAsRole('hr');

            $payload = ['salary_month' => '2026-04'];

            $this->postJson('/api/v1/payrolls/generate', $payload)
                ->assertOk()
                ->assertJsonPath('data.status', 'processing');

            $this->postJson('/api/v1/payrolls/generate', $payload)
                ->assertOk()
                ->assertJsonPath('data.status', 'processing');

            $this->assertSame(
                1,
                DB::table('jobs')
                    ->where('payload', 'like', '%GeneratePayrollJob%')
                    ->count()
            );
        } finally {
            config(['queue.default' => $originalQueueDriver]);
        }
    }

    public function test_generate_endpoint_dispatches_single_unique_job_for_same_month_from_different_hr_users(): void
    {
        $originalQueueDriver = config('queue.default');
        config(['queue.default' => 'database']);

        try {
            Carbon::setTestNow('2026-04-28 09:00:00');
            PayrollSetting::current()->update([
                'attendance_cutoff_day' => 25,
            ]);
            $this->createActiveEmployeeWithAttendance(now()->startOfMonth());

            $payload = ['salary_month' => '2026-04'];

            $this->actingAsRole('hr');
            $this->postJson('/api/v1/payrolls/generate', $payload)
                ->assertOk()
                ->assertJsonPath('data.status', 'processing');

            $this->actingAsRole('hr');
            $this->postJson('/api/v1/payrolls/generate', $payload)
                ->assertOk()
                ->assertJsonPath('data.status', 'processing');

            $this->assertSame(
                1,
                DB::table('jobs')
                    ->where('payload', 'like', '%GeneratePayrollJob%')
                    ->count()
            );
        } finally {
            config(['queue.default' => $originalQueueDriver]);
        }
    }

    public function test_same_month_job_can_be_redispatched_after_unique_lock_is_released(): void
    {
        $originalQueueDriver = config('queue.default');
        config(['queue.default' => 'database']);

        try {
            $firstAttempt = new GeneratePayrollJob('2026-04', 1001);

            GeneratePayrollJob::dispatch('2026-04', 1001);
            GeneratePayrollJob::dispatch('2026-04', 1002);

            $this->assertSame(
                1,
                DB::table('jobs')
                    ->where('payload', 'like', '%GeneratePayrollJob%')
                    ->count()
            );

            // Simulate worker completion/failure cleanup before redispatching the same unique job.
            DB::table('jobs')
                ->where('payload', 'like', '%GeneratePayrollJob%')
                ->delete();

            app('cache')
                ->lock(UniqueLock::getKey($firstAttempt))
                ->forceRelease();

            GeneratePayrollJob::dispatch('2026-04', 1003);

            $this->assertSame(
                1,
                DB::table('jobs')
                    ->where('payload', 'like', '%GeneratePayrollJob%')
                    ->count()
            );
        } finally {
            config(['queue.default' => $originalQueueDriver]);
        }
    }

    public function test_generate_payroll_job_has_deterministic_unique_key_per_salary_month(): void
    {
        $aprilJob = new GeneratePayrollJob('2026-04');
        $sameAprilJob = new GeneratePayrollJob('2026-04');
        $mayJob = new GeneratePayrollJob('2026-05');

        $this->assertContains(ShouldBeUnique::class, class_implements($aprilJob));
        $this->assertSame('generate-payroll:2026-04', $aprilJob->uniqueId());
        $this->assertSame($aprilJob->uniqueId(), $sameAprilJob->uniqueId());
        $this->assertNotSame($aprilJob->uniqueId(), $mayJob->uniqueId());
    }

    public function test_generate_payroll_job_unique_key_does_not_vary_by_initiated_by_user(): void
    {
        $firstHrJob = new GeneratePayrollJob('2026-04', 101);
        $secondHrJob = new GeneratePayrollJob('2026-04', 202);

        $this->assertSame('generate-payroll:2026-04', $firstHrJob->uniqueId());
        $this->assertSame($firstHrJob->uniqueId(), $secondHrJob->uniqueId());
    }

    public function test_generate_payroll_job_logs_error_context_and_rethrows_exception_on_failure(): void
    {
        Log::spy();

        $repository = $this->createMock(PayrollRepositoryInterface::class);
        $repository->expects($this->once())
            ->method('generatePayroll')
            ->with('2026-04', 777)
            ->willThrowException(new \RuntimeException('queue processing failed'));

        $job = new GeneratePayrollJob('2026-04', 777);

        try {
            $job->handle($repository);
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('queue processing failed', $exception->getMessage());
        }

        Log::shouldHaveReceived('error')
            ->once()
            ->withArgs(function (string $message, array $context) {
                return $message === 'Payroll generation failed'
                    && ($context['salary_month'] ?? null) === '2026-04'
                    && ($context['initiated_by'] ?? null) === 777
                    && ($context['error'] ?? null) === 'queue processing failed'
                    && is_string($context['trace'] ?? null)
                    && ($context['trace'] ?? '') !== '';
            });
    }

    public function test_generate_readiness_endpoint_returns_reason_for_invalid_period(): void
    {
        Carbon::setTestNow('2026-04-07 09:00:00');
        PayrollSetting::current()->update([
            'attendance_cutoff_day' => 25,
        ]);
        $this->createActiveEmployeeWithAttendance(now()->startOfMonth());
        $this->actingAsRole('hr');

        $this->getJson('/api/v1/payrolls/generate-readiness?salary_month=2026-04')
            ->assertOk()
            ->assertJsonPath('data.can_generate', false)
            ->assertJsonPath('data.reason_code', 'cutoff_not_reached');
    }

    public function test_hr_cannot_generate_payroll_when_period_is_not_in_review(): void
    {
        Queue::fake();
        Carbon::setTestNow('2026-04-28 09:00:00');
        PayrollSetting::current()->update([
            'attendance_cutoff_day' => 25,
        ]);
        $this->createActiveEmployeeWithAttendance(now()->startOfMonth());

        AttendancePeriod::create([
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'cutoff_date' => '2026-04-25',
            'status' => AttendancePeriod::STATUS_LOCKED,
            'locked_at' => now(),
        ]);

        $this->actingAsRole('hr');

        $this->postJson('/api/v1/payrolls/generate', [
            'salary_month' => '2026-04',
        ])
            ->assertStatus(422)
            ->assertJsonPath('data.reason_code', 'period_not_in_review');

        Queue::assertNothingPushed();
    }

    public function test_generate_readiness_returns_period_not_in_review_when_period_is_locked(): void
    {
        Carbon::setTestNow('2026-04-28 09:00:00');
        PayrollSetting::current()->update([
            'attendance_cutoff_day' => 25,
        ]);
        $this->createActiveEmployeeWithAttendance(now()->startOfMonth());

        AttendancePeriod::create([
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'cutoff_date' => '2026-04-25',
            'status' => AttendancePeriod::STATUS_LOCKED,
            'locked_at' => now(),
        ]);

        $this->actingAsRole('hr');

        $this->getJson('/api/v1/payrolls/generate-readiness?salary_month=2026-04')
            ->assertOk()
            ->assertJsonPath('data.can_generate', false)
            ->assertJsonPath('data.reason_code', 'period_not_in_review');
    }

    public function test_generate_readiness_returns_employees_blocked_when_pending_leave_exists(): void
    {
        Carbon::setTestNow('2026-04-28 09:00:00');
        PayrollSetting::current()->update([
            'attendance_cutoff_day' => 25,
        ]);

        $employee = $this->createActiveEmployeeWithAttendance(now()->startOfMonth());

        AttendancePeriod::create([
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'cutoff_date' => '2026-04-25',
            'status' => AttendancePeriod::STATUS_REVIEW,
        ]);

        LeaveRequest::create([
            'employee_id' => $employee->id,
            'leave_type' => 'annual_leave',
            'start_date' => '2026-04-14',
            'end_date' => '2026-04-14',
            'total_days' => 1,
            'reason' => 'Pending approval should block readiness',
            'status' => 'pending',
        ]);

        $this->actingAsRole('hr');

        $response = $this->getJson('/api/v1/payrolls/generate-readiness?salary_month=2026-04')
            ->assertOk()
            ->assertJsonPath('data.can_generate', false)
            ->assertJsonPath('data.reason_code', 'employees_blocked');

        $this->assertContains(
            $employee->id,
            $response->json('data.meta.blocked_employee_ids') ?? []
        );
    }

    public function test_generate_readiness_blocks_when_scheduled_days_are_uncovered(): void
    {
        Carbon::setTestNow('2026-04-28 09:00:00');
        PayrollSetting::current()->update([
            'attendance_cutoff_day' => 25,
        ]);

        $employee = $this->createActiveEmployeeWithAttendance(now()->startOfMonth());
        $firstAttendanceDate = Carbon::parse((string) Attendance::query()
            ->where('employee_id', $employee->id)
            ->min('date'))
            ->toDateString();

        Attendance::query()
            ->where('employee_id', $employee->id)
            ->whereDate('date', '!=', $firstAttendanceDate)
            ->delete();

        AttendancePeriod::create([
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'cutoff_date' => '2026-04-25',
            'status' => AttendancePeriod::STATUS_REVIEW,
        ]);

        $this->actingAsRole('hr');

        $response = $this->getJson('/api/v1/payrolls/generate-readiness?salary_month=2026-04')
            ->assertOk()
            ->assertJsonPath('data.can_generate', false)
            ->assertJsonPath('data.reason_code', 'employees_blocked');

        $this->assertContains(
            $employee->id,
            $response->json('data.meta.blocked_reasons.missing_attendance_or_valid_leave') ?? []
        );
    }

    public function test_readiness_dashboard_returns_ready_warning_and_blocked_employee_breakdown(): void
    {
        Carbon::setTestNow('2026-04-28 09:00:00');
        PayrollSetting::current()->update([
            'attendance_cutoff_day' => 25,
        ]);

        $readyEmployee = $this->createActiveEmployeeWithAttendance(now()->startOfMonth());

        $blockedEmployee = $this->createActiveEmployeeWithAttendance(now()->startOfMonth());
        $blockedFirstAttendanceDate = Carbon::parse((string) Attendance::query()
            ->where('employee_id', $blockedEmployee->id)
            ->min('date'))
            ->toDateString();

        Attendance::query()
            ->where('employee_id', $blockedEmployee->id)
            ->whereDate('date', '!=', $blockedFirstAttendanceDate)
            ->delete();

        $warningEmployee = $this->createActiveEmployeeWithAttendance(now()->startOfMonth());
        $warningAttendance = Attendance::query()
            ->where('employee_id', $warningEmployee->id)
            ->firstOrFail();

        AttendancePolicyMismatch::create([
            'attendance_id' => $warningAttendance->id,
            'employee_id' => $warningEmployee->id,
            'mismatch_date' => '2026-04-10',
            'planned_work_mode' => 'office',
            'actual_work_mode' => 'remote',
            'status' => 'pending_review',
        ]);

        AttendancePeriod::create([
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'cutoff_date' => '2026-04-25',
            'status' => AttendancePeriod::STATUS_REVIEW,
        ]);

        $this->actingAsRole('hr');

        $response = $this->getJson('/api/v1/payrolls/readiness-dashboard?salary_month=2026-04')
            ->assertOk();

        $response
            ->assertJsonPath('data.summary.total_employees', 3)
            ->assertJsonPath('data.summary.ready_employees', 1)
            ->assertJsonPath('data.summary.warning_employees', 1)
            ->assertJsonPath('data.summary.blocked_employees', 1)
            ->assertJsonPath('data.generation.can_generate', false)
            ->assertJsonPath('data.generation.reason_code', 'employees_blocked');

        $employeeRows = collect($response->json('data.employees') ?? []);
        $rowsByEmployeeId = $employeeRows->keyBy('employee_id');

        $this->assertSame('ready', $rowsByEmployeeId->get($readyEmployee->id)['status'] ?? null);
        $this->assertSame('warning', $rowsByEmployeeId->get($warningEmployee->id)['status'] ?? null);
        $this->assertSame('blocked', $rowsByEmployeeId->get($blockedEmployee->id)['status'] ?? null);
    }

    private function actingAsRole(string $roleName): User
    {
        $user = User::factory()->create();
        $role = Role::findByName($roleName, 'sanctum');
        $user->assignRole($role);

        Sanctum::actingAs($user);

        return $user;
    }

    private function createActiveEmployeeWithAttendance(Carbon $month): StaffMemberProfile
    {
        return StaffMemberProfile::withoutSyncingToSearch(function () use ($month) {
            $employee = StaffMemberProfile::factory()->create();
            $startDate = $month->copy()->startOfMonth();
            $endDate = $month->copy()->endOfMonth();

            $employee->jobInformation()->create([
                'employee_id' => $employee->id,
                'job_title' => 'Software Engineer',
                'years_experience' => 5,
                'status' => 'active',
                'employment_type' => 'full_time',
                'work_location' => 'remote',
                'start_date' => '2024-01-01',
                'monthly_salary' => 10000000,
                'skill_level' => 'expert',
            ]);

            $cursor = $startDate->copy();
            while ($cursor->lte($endDate)) {
                if (! $cursor->isWeekend()) {
                    Attendance::create([
                        'employee_id' => $employee->id,
                        'date' => $cursor->toDateString(),
                        'check_in' => $cursor->format('Y-m-d').' 08:00:00',
                        'check_out' => $cursor->format('Y-m-d').' 17:00:00',
                        'status' => 'present',
                        'notes' => 'Generate rules coverage',
                    ]);
                }

                $cursor->addDay();
            }

            return $employee;
        });
    }
}
