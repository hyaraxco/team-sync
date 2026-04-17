<?php

namespace Tests\Feature\Attendance;

use App\Interfaces\PayrollRepositoryInterface;
use App\Models\Attendance;
use App\Models\AttendancePeriod;
use App\Models\EmployeeProfile;
use App\Models\JobInformation;
use App\Models\PayrollSetting;
use App\Services\Attendance\AttendancePeriodService;
use Carbon\Carbon;
use Database\Seeders\AttendancePolicySeeder;
use Database\Seeders\LeaveEntitlementSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendancePeriodLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RoleSeeder::class,
            AttendancePolicySeeder::class,
            LeaveEntitlementSeeder::class,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_sync_command_creates_open_period_for_current_month_with_default_cutoff(): void
    {
        Carbon::setTestNow('2026-04-01 00:15:00');

        $this->artisan('attendance-periods:sync')
            ->assertExitCode(0);

        $period = AttendancePeriod::query()
            ->whereDate('start_date', '2026-04-01')
            ->firstOrFail();

        $this->assertTrue(
            AttendancePeriod::query()
                ->whereKey($period->id)
                ->whereDate('end_date', '2026-04-30')
                ->whereDate('cutoff_date', '2026-04-25')
                ->exists()
        );
        $this->assertSame(AttendancePeriod::STATUS_OPEN, $period->status);
        $this->assertNull($period->locked_at);
    }

    public function test_sync_command_moves_open_period_to_review_after_cutoff(): void
    {
        AttendancePeriod::create([
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'cutoff_date' => '2026-04-25',
            'status' => AttendancePeriod::STATUS_OPEN,
        ]);

        Carbon::setTestNow('2026-04-26 09:00:00');

        $this->artisan('attendance-periods:sync')
            ->assertExitCode(0);

        $this->assertTrue(
            AttendancePeriod::query()
                ->whereDate('start_date', '2026-04-01')
                ->where('status', AttendancePeriod::STATUS_REVIEW)
                ->exists()
        );
    }

    public function test_generate_payroll_locks_period_and_assigns_attendance_period_references(): void
    {
        Carbon::setTestNow('2026-04-28 09:00:00');

        PayrollSetting::current()->update([
            'attendance_cutoff_day' => 25,
        ]);

        $employee = $this->createActiveEmployee();
        $month = Carbon::createFromFormat('Y-m', '2026-04')->startOfMonth();
        $this->seedFullMonthAttendance($employee, $month);

        $attendance = Attendance::query()
            ->where('employee_id', $employee->id)
            ->whereDate('date', $month->copy()->startOfMonth()->toDateString())
            ->firstOrFail();

        $payroll = app(PayrollRepositoryInterface::class)->generatePayroll('2026-04');

        $this->assertNotNull($payroll->attendance_period_id);

        $period = AttendancePeriod::query()->findOrFail($payroll->attendance_period_id);

        $this->assertSame(AttendancePeriod::STATUS_LOCKED, $period->status);
        $this->assertNotNull($period->locked_at);
        $this->assertSame($period->id, $attendance->fresh()->attendance_period_id);

        $periodService = app(AttendancePeriodService::class);
        $this->assertFalse($periodService->canSubmitCorrection('2026-04-10'));
    }

    private function createActiveEmployee(): EmployeeProfile
    {
        $employee = EmployeeProfile::withoutSyncingToSearch(function () {
            return EmployeeProfile::factory()->create();
        });

        JobInformation::factory()
            ->forEmployee($employee)
            ->active()
            ->state([
                'employment_type' => 'full_time',
                'work_location' => 'office',
                'status' => 'active',
                'monthly_salary' => 10000000,
            ])
            ->create();

        return $employee;
    }

    private function seedFullMonthAttendance(EmployeeProfile $employee, Carbon $month): void
    {
        $cursor = $month->copy()->startOfMonth();
        $monthEnd = $month->copy()->endOfMonth();

        while ($cursor->lte($monthEnd)) {
            if (! $cursor->isWeekend()) {
                Attendance::create([
                    'employee_id' => $employee->id,
                    'date' => $cursor->toDateString(),
                    'check_in' => $cursor->format('Y-m-d').' 08:50:00',
                    'check_out' => $cursor->format('Y-m-d').' 17:00:00',
                    'worked_minutes' => 490,
                    'status' => 'present',
                    'notes' => 'Lifecycle payroll lock test',
                ]);
            }

            $cursor->addDay();
        }
    }
}
