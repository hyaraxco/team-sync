<?php

namespace Tests\Feature\Payroll;

use App\Interfaces\PayrollRepositoryInterface;
use App\Models\Attendance;
use App\Models\BpjsRate;
use App\Models\EmployeeProfile;
use App\Models\JobInformation;
use App\Models\PayrollSetting;
use App\Models\PtkpAmount;
use App\Models\TaxBracket;
use Carbon\Carbon;
use Database\Seeders\BpjsRateSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PtkpAmountSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TaxBracketSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Integration tests: payroll generation correctly includes PPh 21 and BPJS
 * in payroll_details rows.
 */
class PayrollTaxBpjsIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,
            TaxBracketSeeder::class,
            BpjsRateSeeder::class,
            PtkpAmountSeeder::class,
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Notification::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Integration: payroll generation includes tax & BPJS in payroll_details
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function test_payroll_detail_includes_pph21_amount(): void
    {
        Carbon::setTestNow('2026-04-28 09:00:00');
        PayrollSetting::current()->update(['attendance_cutoff_day' => 25]);

        // Use salary high enough to trigger PPh 21 (>4.5jt/bulan -> PKP > 0)
        $employee = $this->createEmployeeWithAttendance(10_000_000);

        $payroll = app(PayrollRepositoryInterface::class)->generatePayroll('2026-04', null);
        $detail = $payroll->payrollDetails->firstWhere('employee_id', $employee->id);

        $this->assertNotNull($detail, 'PayrollDetail should exist for employee');
        $this->assertGreaterThan(0, (float) $detail->pph21_amount, 'PPh 21 should be non-zero for 10jt salary');
    }

    /** @test */
    public function test_payroll_detail_includes_bpjs_tk_employee(): void
    {
        Carbon::setTestNow('2026-04-28 09:00:00');
        PayrollSetting::current()->update(['attendance_cutoff_day' => 25]);

        $employee = $this->createEmployeeWithAttendance(8_000_000);

        $payroll = app(PayrollRepositoryInterface::class)->generatePayroll('2026-04', null);
        $detail = $payroll->payrollDetails->firstWhere('employee_id', $employee->id);

        $this->assertNotNull($detail);
        // JHT(2%) + JP(1%) employee = 3% of salary (under cap)
        $this->assertGreaterThan(0, (float) $detail->bpjs_tk_employee);
    }

    /** @test */
    public function test_payroll_detail_includes_bpjs_tk_employer(): void
    {
        Carbon::setTestNow('2026-04-28 09:00:00');
        PayrollSetting::current()->update(['attendance_cutoff_day' => 25]);

        $employee = $this->createEmployeeWithAttendance(8_000_000);

        $payroll = app(PayrollRepositoryInterface::class)->generatePayroll('2026-04', null);
        $detail = $payroll->payrollDetails->firstWhere('employee_id', $employee->id);

        $this->assertNotNull($detail);
        // JHT(3.7%) + JKK(0.24%) + JKM(0.3%) + JP(2%) employer = > 5%
        $this->assertGreaterThan(
            (float) $detail->bpjs_tk_employee,
            (float) $detail->bpjs_tk_employer,
            'Employer BPJS TK share should be greater than employee share'
        );
    }

    /** @test */
    public function test_payroll_detail_includes_bpjs_kesehatan(): void
    {
        Carbon::setTestNow('2026-04-28 09:00:00');
        PayrollSetting::current()->update(['attendance_cutoff_day' => 25]);

        $employee = $this->createEmployeeWithAttendance(8_000_000);

        $payroll = app(PayrollRepositoryInterface::class)->generatePayroll('2026-04', null);
        $detail = $payroll->payrollDetails->firstWhere('employee_id', $employee->id);

        $this->assertNotNull($detail);
        $this->assertGreaterThan(0, (float) $detail->bpjs_kes_employee);
        $this->assertGreaterThan(0, (float) $detail->bpjs_kes_employer);

        // Employer Kes (4%) > Employee Kes (1%)
        $this->assertGreaterThan(
            (float) $detail->bpjs_kes_employee,
            (float) $detail->bpjs_kes_employer
        );
    }

    /** @test */
    public function test_pph21_zero_for_very_low_salary_employee(): void
    {
        Carbon::setTestNow('2026-04-28 09:00:00');
        PayrollSetting::current()->update(['attendance_cutoff_day' => 25]);

        // TK/0 PTKP = 4.5jt/bulan. Salary below PTKP threshold → PPh = 0
        $employee = $this->createEmployeeWithAttendance(3_500_000);

        $payroll = app(PayrollRepositoryInterface::class)->generatePayroll('2026-04', null);
        $detail = $payroll->payrollDetails->firstWhere('employee_id', $employee->id);

        $this->assertNotNull($detail);
        $this->assertEquals(0.0, (float) $detail->pph21_amount, 'PPh 21 should be 0 for salary below PTKP');
    }

    /** @test */
    public function test_tax_calculation_meta_is_stored(): void
    {
        Carbon::setTestNow('2026-04-28 09:00:00');
        PayrollSetting::current()->update(['attendance_cutoff_day' => 25]);

        $employee = $this->createEmployeeWithAttendance(10_000_000);

        $payroll = app(PayrollRepositoryInterface::class)->generatePayroll('2026-04', null);
        $detail = $payroll->payrollDetails->firstWhere('employee_id', $employee->id);

        $this->assertNotNull($detail);
        $meta = is_string($detail->tax_calculation_meta)
            ? json_decode($detail->tax_calculation_meta, true)
            : $detail->tax_calculation_meta;

        // Meta snapshot should contain the key calculation figures
        $this->assertNotNull($meta, 'tax_calculation_meta should be stored');
        $this->assertArrayHasKey('pph21_monthly', $meta);
    }

    /** @test */
    public function test_bpjs_jp_component_respects_salary_cap_in_detail(): void
    {
        Carbon::setTestNow('2026-04-28 09:00:00');
        PayrollSetting::current()->update(['attendance_cutoff_day' => 25]);

        // Salary above JP cap (10_042_300). BPJS TK employee contribution
        // should be capped, not proportional to 20jt salary.
        $highEarner = $this->createEmployeeWithAttendance(20_000_000);
        $normalEarner = $this->createEmployeeWithAttendance(10_000_000);

        $payroll = app(PayrollRepositoryInterface::class)->generatePayroll('2026-04', null);

        $highDetail = $payroll->payrollDetails->firstWhere('employee_id', $highEarner->id);
        $normalDetail = $payroll->payrollDetails->firstWhere('employee_id', $normalEarner->id);

        $this->assertNotNull($highDetail);
        $this->assertNotNull($normalDetail);

        // Due to JP cap at ~10jt, the BPJS TK should be very close (same JP portion)
        // The only uncapped difference would be JHT (2%) on full salary
        $jhtDiff = abs((float) $highDetail->bpjs_tk_employee - (float) $normalDetail->bpjs_tk_employee);

        // JHT difference: (20jt - 10jt) * 2% = ~200k; JP cap at 10.04jt adds ~4.2 difference
        // Allow 1000 margin for JP boundary effect
        $this->assertEqualsWithDelta(200_000, $jhtDiff, 1000, 'Difference should be ~200k (JHT 2% uncapped)');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Existing payroll E2E must not break
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function test_payroll_generation_still_produces_final_salary(): void
    {
        Carbon::setTestNow('2026-04-28 09:00:00');
        PayrollSetting::current()->update(['attendance_cutoff_day' => 25]);

        $employee = $this->createEmployeeWithAttendance(8_000_000);

        $payroll = app(PayrollRepositoryInterface::class)->generatePayroll('2026-04', null);
        $detail = $payroll->payrollDetails->firstWhere('employee_id', $employee->id);

        // Core payroll fields should still be populated
        $this->assertNotNull($detail);
        $this->assertGreaterThan(0, (float) $detail->original_salary);
        $this->assertGreaterThan(0, (float) $detail->final_salary);
        $this->assertGreaterThan(0, $detail->attended_days);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helper
    // ─────────────────────────────────────────────────────────────────────────

    private function createEmployeeWithAttendance(int $salary): EmployeeProfile
    {
        return EmployeeProfile::withoutSyncingToSearch(function () use ($salary) {
            $employee = EmployeeProfile::factory()->create();

            JobInformation::factory()
                ->forEmployee($employee)
                ->active()
                ->state([
                    'monthly_salary' => $salary,
                    'status' => 'active',
                    'employment_type' => 'full_time',
                ])
                ->create();

            $startDate = Carbon::now()->startOfMonth();
            $endDate = Carbon::now()->endOfMonth();

            $cursor = $startDate->copy();
            while ($cursor->lte($endDate)) {
                if (!$cursor->isWeekend()) {
                    Attendance::create([
                        'employee_id' => $employee->id,
                        'date' => $cursor->toDateString(),
                        'check_in' => $cursor->format('Y-m-d') . ' 08:00:00',
                        'check_out' => $cursor->format('Y-m-d') . ' 17:00:00',
                        'status' => 'present',
                        'notes' => 'Tax BPJS integration test',
                    ]);
                }
                $cursor->addDay();
            }

            return $employee;
        });
    }
}
