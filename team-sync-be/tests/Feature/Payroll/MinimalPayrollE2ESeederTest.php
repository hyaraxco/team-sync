<?php

namespace Tests\Feature\Payroll;

use App\Interfaces\PayrollRepositoryInterface;
use App\Models\Attendance;
use App\Models\StaffMemberProfile;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\MinimalPayrollE2ESeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\ActivatesLicense;
use Tests\TestCase;

class MinimalPayrollE2ESeederTest extends TestCase
{
    use ActivatesLicense, RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_minimal_payroll_e2e_seeder_prepares_a_payroll_ready_dataset(): void
    {
        $this->activateTestLicense();

        // Freeze time past the attendance cutoff day (seeder sets cutoff_day=1)
        Carbon::setTestNow('2026-05-02 09:00:00');

        $this->seed(MinimalPayrollE2ESeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $hr = User::where('email', 'tasyia@teamsync.com')->firstOrFail();
        $finance = User::where('email', 'dwimeta@teamsync.com')->firstOrFail();
        $manager = User::where('email', 'yudhis@teamsync.com')->firstOrFail();
        $employee = StaffMemberProfile::where('code', 'EMP001')->firstOrFail();
        $payrollMonth = now()->startOfMonth();

        // PRD: Finance owns payroll operations; HR has readiness-view only
        $this->assertFalse($hr->hasPermissionTo('payroll-create', 'sanctum'));
        $this->assertTrue($hr->hasPermissionTo('payroll-readiness-view', 'sanctum'));
        $this->assertFalse($hr->hasPermissionTo('payroll-process', 'sanctum'));
        $this->assertTrue($finance->hasPermissionTo('payroll-create', 'sanctum'));
        $this->assertTrue($finance->hasPermissionTo('payroll-process', 'sanctum'));
        $this->assertFalse($manager->hasPermissionTo('payroll-menu', 'sanctum'));
        $this->assertTrue($manager->hasPermissionTo('leave-request-create', 'sanctum'));
        $this->assertTrue($manager->hasPermissionTo('leave-request-menu', 'sanctum'));
        $this->assertTrue($manager->hasPermissionTo('payslip-view', 'sanctum'));

        $expectedBusinessDays = $this->resolveBusinessDaysInMonth($payrollMonth);

        $this->assertSame($expectedBusinessDays, Attendance::where('staff_member_id', $employee->id)
            ->whereBetween('date', [
                $payrollMonth->copy()->startOfMonth()->startOfDay(),
                $payrollMonth->copy()->endOfMonth()->endOfDay(),
            ])
            ->count());

        $payroll = app(PayrollRepositoryInterface::class)->generatePayroll($payrollMonth->format('Y-m'));

        $this->assertSame('pending', $payroll->status->value);
        $this->assertGreaterThan(0, $payroll->payrollDetails()->count());
        $this->assertContains($employee->id, $payroll->payrollDetails->pluck('staff_member_id')->all());

        $this->assertNull(User::where('email', 'manager@gmail.com')->first());
        $this->assertNull(User::where('email', 'employee@gmail.com')->first());
        $this->assertNull(User::where('email', 'hr@gmail.com')->first());
        $this->assertNull(User::where('email', 'finance@gmail.com')->first());

        $this->postJson('/api/v1/login', [
            'email' => 'tasyia@teamsync.com',
            'password' => 'teamsync',
        ])
            ->assertOk()
            ->assertJsonPath('data.email', 'tasyia@teamsync.com');
    }

    public function test_minimal_payroll_e2e_seeder_can_be_run_twice_without_duplicate_attendance_errors(): void
    {
        $this->activateTestLicense();

        // Freeze time past the attendance cutoff day (seeder sets cutoff_day=1)
        Carbon::setTestNow('2026-05-02 09:00:00');

        $this->seed(MinimalPayrollE2ESeeder::class);
        $this->seed(MinimalPayrollE2ESeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $employee = StaffMemberProfile::where('code', 'EMP001')->firstOrFail();
        $payrollMonth = now()->startOfMonth();
        $expectedBusinessDays = $this->resolveBusinessDaysInMonth($payrollMonth);

        $this->assertSame($expectedBusinessDays, Attendance::where('staff_member_id', $employee->id)
            ->whereRaw('date(date) >= ?', [$payrollMonth->copy()->startOfMonth()->toDateString()])
            ->whereRaw('date(date) <= ?', [$payrollMonth->copy()->endOfMonth()->toDateString()])
            ->count());
    }

    private function resolveBusinessDaysInMonth(Carbon $month): int
    {
        $cursor = $month->copy()->startOfMonth();
        $monthEnd = $month->copy()->endOfMonth();
        $count = 0;

        while ($cursor->lte($monthEnd)) {
            if (! $cursor->isWeekend()) {
                $count++;
            }

            $cursor->addDay();
        }

        return $count;
    }
}
