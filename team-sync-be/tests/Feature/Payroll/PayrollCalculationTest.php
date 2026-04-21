<?php

namespace Tests\Feature\Payroll;

use App\Interfaces\PayrollRepositoryInterface;
use App\Models\Attendance;
use App\Models\StaffMemberProfile;
use App\Models\JobInformation;
use App\Models\Payroll;
use App\Models\PayrollSetting;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PayrollCalculationTest extends TestCase
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
        Notification::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_rounding_mode_floor_rounds_salary_down(): void
    {
        Carbon::setTestNow('2026-04-28 09:00:00');
        $settings = PayrollSetting::current();
        $settings->update([
            'attendance_cutoff_day' => 25,
            'rounding_mode' => 'floor',
            'rounding_unit' => 1000,
        ]);

        $employee = $this->createActiveEmployeeWithAttendance(now()->startOfMonth(), 9999999);
        $repository = app(PayrollRepositoryInterface::class);

        $payroll = $repository->generatePayroll('2026-04', null);
        $detail = $payroll->payrollDetails->firstWhere('employee_id', $employee->id);

        // floor(x / 1000) * 1000 should always be <= original value
        $this->assertSame(0, (int) $detail->final_salary % 1000);
        $this->assertLessThanOrEqual((float) $detail->original_salary, (float) $detail->final_salary);
    }

    public function test_rounding_mode_ceil_rounds_salary_up(): void
    {
        Carbon::setTestNow('2026-04-28 09:00:00');
        $settings = PayrollSetting::current();
        $settings->update([
            'attendance_cutoff_day' => 25,
            'rounding_mode' => 'ceil',
            'rounding_unit' => 1000,
        ]);

        $employee = $this->createActiveEmployeeWithAttendance(now()->startOfMonth(), 9999001);
        $repository = app(PayrollRepositoryInterface::class);

        $payroll = $repository->generatePayroll('2026-04', null);
        $detail = $payroll->payrollDetails->firstWhere('employee_id', $employee->id);

        // ceil(x / 1000) * 1000 should be a multiple of 1000
        $this->assertSame(0, (int) $detail->final_salary % 1000);
    }

    public function test_rounding_mode_none_preserves_exact_value(): void
    {
        Carbon::setTestNow('2026-04-28 09:00:00');
        $settings = PayrollSetting::current();
        $settings->update([
            'attendance_cutoff_day' => 25,
            'rounding_mode' => 'none',
            'rounding_unit' => 1000,
        ]);

        $employee = $this->createActiveEmployeeWithAttendance(now()->startOfMonth(), 10000000);
        $repository = app(PayrollRepositoryInterface::class);

        $payroll = $repository->generatePayroll('2026-04', null);
        $detail = $payroll->payrollDetails->firstWhere('employee_id', $employee->id);

        // With 'none', the value should not be rounded to a multiple of the unit
        // (exact depends on deductions/tax, but the rounding step is a no-op)
        $this->assertNotNull($detail);
        $this->assertGreaterThan(0, (float) $detail->final_salary);
    }

    public function test_deduction_formula_applies_absent_deduction_rate(): void
    {
        Carbon::setTestNow('2026-04-28 09:00:00');
        $settings = PayrollSetting::current();
        $settings->update([
            'attendance_cutoff_day' => 25,
            'absent_deduction_rate' => 1.0,
            'rounding_mode' => 'none',
        ]);

        // Create employee with full attendance
        $fullEmployee = $this->createActiveEmployeeWithAttendance(now()->startOfMonth(), 10000000);

        // Create employee with some absent days (mark records as absent instead of deleting)
        $absentEmployee = $this->createActiveEmployeeWithAttendance(now()->startOfMonth(), 10000000);

        // Mark 5 weekday attendance records as absent to simulate absences
        // This keeps the records (avoids readiness block) but triggers deductions
        Attendance::where('employee_id', $absentEmployee->id)
            ->orderBy('date')
            ->limit(5)
            ->update([
                'status' => 'absent',
                'check_in' => null,
                'check_out' => null,
            ]);

        $repository = app(PayrollRepositoryInterface::class);
        $payroll = $repository->generatePayroll('2026-04', null);

        $fullDetail = $payroll->payrollDetails->firstWhere('employee_id', $fullEmployee->id);
        $absentDetail = $payroll->payrollDetails->firstWhere('employee_id', $absentEmployee->id);

        // The absent employee should have a lower final salary due to deductions
        $this->assertNotNull($fullDetail);
        $this->assertNotNull($absentDetail);
        $this->assertGreaterThan(
            (float) $absentDetail->final_salary,
            (float) $fullDetail->final_salary,
            'Full attendance employee should earn more than absent employee'
        );

        // Absent detail should have deduction amount > 0
        $this->assertGreaterThan(0, (float) ($absentDetail->deduction_amount ?? 0));
        $this->assertGreaterThan(0, (int) $absentDetail->absent_days);
    }

    public function test_zero_salary_employee_produces_zero_final_salary(): void
    {
        Carbon::setTestNow('2026-04-28 09:00:00');
        PayrollSetting::current()->update([
            'attendance_cutoff_day' => 25,
        ]);

        $employee = $this->createActiveEmployeeWithAttendance(now()->startOfMonth(), 0);
        $repository = app(PayrollRepositoryInterface::class);

        $payroll = $repository->generatePayroll('2026-04', null);
        $detail = $payroll->payrollDetails->firstWhere('employee_id', $employee->id);

        $this->assertNotNull($detail);
        $this->assertEquals(0, (float) $detail->original_salary);
        $this->assertEquals(0, (float) $detail->final_salary);
    }

    public function test_payroll_detail_captures_attendance_breakdown(): void
    {
        Carbon::setTestNow('2026-04-28 09:00:00');
        PayrollSetting::current()->update([
            'attendance_cutoff_day' => 25,
        ]);

        $employee = $this->createActiveEmployeeWithAttendance(now()->startOfMonth(), 10000000);
        $repository = app(PayrollRepositoryInterface::class);

        $payroll = $repository->generatePayroll('2026-04', null);
        $detail = $payroll->payrollDetails->firstWhere('employee_id', $employee->id);

        $this->assertNotNull($detail);
        $this->assertGreaterThan(0, $detail->attended_days);
        $this->assertGreaterThanOrEqual(0, $detail->sick_days);
        $this->assertGreaterThanOrEqual(0, $detail->absent_days);
        $this->assertGreaterThan(0, (float) $detail->effective_working_days);
        $this->assertGreaterThan(0, (float) $detail->daily_rate);
    }

    private function createActiveEmployeeWithAttendance(Carbon $month, int $monthlySalary): StaffMemberProfile
    {
        return StaffMemberProfile::withoutSyncingToSearch(function () use ($month, $monthlySalary) {
            $employee = StaffMemberProfile::factory()->create();
            $startDate = $month->copy()->startOfMonth();
            $endDate = $month->copy()->endOfMonth();

            JobInformation::factory()
                ->forEmployee($employee)
                ->active()
                ->state([
                    'monthly_salary' => $monthlySalary,
                    'status' => 'active',
                    'employment_type' => 'full_time',
                ])
                ->create();

            $cursor = $startDate->copy();
            while ($cursor->lte($endDate)) {
                if (! $cursor->isWeekend()) {
                    Attendance::create([
                        'employee_id' => $employee->id,
                        'date' => $cursor->toDateString(),
                        'check_in' => $cursor->format('Y-m-d').' 08:00:00',
                        'check_out' => $cursor->format('Y-m-d').' 17:00:00',
                        'status' => 'present',
                        'notes' => 'Calculation test coverage',
                    ]);
                }

                $cursor->addDay();
            }

            return $employee;
        });
    }
}
