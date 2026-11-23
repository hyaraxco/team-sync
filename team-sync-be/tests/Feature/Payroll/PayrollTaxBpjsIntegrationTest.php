<?php

namespace Tests\Feature\Payroll;

use App\Interfaces\PayrollRepositoryInterface;
use App\Models\Attendance;
use App\Models\JobInformation;
use App\Models\OvertimeRecord;
use App\Models\PayrollSetting;
use App\Models\StaffMemberProfile;
use App\Services\Payroll\TaxCalculationService;
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
use Tests\Concerns\ActivatesLicense;
use Tests\TestCase;

/**
 * Integration tests: payroll generation correctly includes PPh 21 and BPJS
 * in payroll_details rows.
 */
class PayrollTaxBpjsIntegrationTest extends TestCase
{
    use ActivatesLicense, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->activateTestLicense();

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
        $detail = $payroll->payrollDetails->firstWhere('staff_member_id', $employee->id);

        $this->assertNotNull($detail, 'PayrollDetail should exist for employee');
        $this->assertGreaterThan(0, (float) $detail->pph21_amount, 'PPh 21 should be non-zero for 10jt salary');
    }

    /**
     * Paid approved overtime is taxable employment income for the same month, so it
     * must be in the TER gross (PMK 168/2023 Art. 5, 8). The previous implementation
     * taxed salary only and added overtime afterwards.
     */
    public function test_pph21_taxed_gross_includes_approved_overtime(): void
    {
        Carbon::setTestNow('2026-04-28 09:00:00');
        PayrollSetting::current()->update(['attendance_cutoff_day' => 25]);

        $salary = 8_000_000;
        $employee = $this->createEmployeeWithAttendance($salary);

        $overtime = OvertimeRecord::factory()->approved()->workday()->create([
            'staff_member_id' => $employee->id,
            'date' => Carbon::now()->startOfMonth()->addDays(3)->toDateString(),
            'hours' => 4,
        ]);

        $payroll = app(PayrollRepositoryInterface::class)->generatePayroll('2026-04', null);
        $detail = $payroll->payrollDetails->firstWhere('staff_member_id', $employee->id);

        $this->assertNotNull($detail);
        $this->assertGreaterThan(0, (float) $detail->overtime_amount, 'overtime must be paid');

        $meta = json_decode($detail->tax_calculation_meta, true);

        // The gross handed to the TER calculation must be salary + overtime, not salary.
        $expectedGross = $salary + (float) $detail->overtime_amount;
        $this->assertEqualsWithDelta(
            $expectedGross,
            (float) $meta['meta']['gross_monthly'],
            1.0,
            'taxed gross must include paid overtime'
        );

        // And the tax must match TER applied to that combined gross, not to salary alone.
        $taxService = app(TaxCalculationService::class);
        $expected = $taxService->calculateMonthlyTer(
            $expectedGross,
            $employee->ptkp_status ?? null,
            ! empty($employee->npwp),
            '2026-04'
        );
        $this->assertEqualsWithDelta(
            (float) $expected['pph21_monthly'],
            (float) $detail->pph21_amount,
            1.0,
            'pph21 must be withheld on the combined gross'
        );
    }

    /** @test */
    public function test_payroll_detail_includes_bpjs_tk_employee(): void
    {
        Carbon::setTestNow('2026-04-28 09:00:00');
        PayrollSetting::current()->update(['attendance_cutoff_day' => 25]);

        $employee = $this->createEmployeeWithAttendance(8_000_000);

        $payroll = app(PayrollRepositoryInterface::class)->generatePayroll('2026-04', null);
        $detail = $payroll->payrollDetails->firstWhere('staff_member_id', $employee->id);

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
        $detail = $payroll->payrollDetails->firstWhere('staff_member_id', $employee->id);

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
        $detail = $payroll->payrollDetails->firstWhere('staff_member_id', $employee->id);

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
        $detail = $payroll->payrollDetails->firstWhere('staff_member_id', $employee->id);

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
        $detail = $payroll->payrollDetails->firstWhere('staff_member_id', $employee->id);

        $this->assertNotNull($detail);
        $meta = is_string($detail->tax_calculation_meta)
            ? json_decode($detail->tax_calculation_meta, true)
            : $detail->tax_calculation_meta;

        // Meta snapshot should contain the key calculation figures
        $this->assertNotNull($meta, 'tax_calculation_meta should be stored');
        $this->assertArrayHasKey('pph21_monthly', $meta);
    }

    public function test_generation_persists_corrected_ter_b_and_c_amounts(): void
    {
        Carbon::setTestNow('2026-04-28 09:00:00');
        PayrollSetting::current()->update(['attendance_cutoff_day' => 25]);

        $b = $this->createEmployeeWithAttendance(21_000_000);
        $b->update(['ptkp_status' => 'K/1', 'npwp' => '123456789012345']);
        $c = $this->createEmployeeWithAttendance(43_050_000);
        $c->update(['ptkp_status' => 'K/3', 'npwp' => '123456789012346']);

        $payroll = app(PayrollRepositoryInterface::class)->generatePayroll('2026-04', null);

        foreach ([[$b, 'B', 0.08, 1_680_000], [$c, 'C', 0.16, 6_888_000]] as [$employee, $category, $rate, $tax]) {
            $detail = $payroll->payrollDetails->firstWhere('staff_member_id', $employee->id);
            $this->assertNotNull($detail);
            $detail->refresh();
            $meta = $detail->tax_calculation_meta;
            $meta = is_string($meta) ? json_decode($meta, true) : $meta;

            $this->assertEquals($tax, (float) $detail->pph21_amount);
            $this->assertSame('ter_2024', $meta['method']);
            $this->assertSame($category, $meta['ter_category']);
            $this->assertSame($rate, $meta['ter_rate']);
            $this->assertTrue($meta['has_npwp']);
            $this->assertEquals($tax, $meta['pph21_monthly']);
        }
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

        $highDetail = $payroll->payrollDetails->firstWhere('staff_member_id', $highEarner->id);
        $normalDetail = $payroll->payrollDetails->firstWhere('staff_member_id', $normalEarner->id);

        $this->assertNotNull($highDetail);
        $this->assertNotNull($normalDetail);

        // Due to JP cap at ~10jt, the BPJS TK should be very close (same JP portion)
        // The only uncapped difference would be JHT (2%) on full salary
        $jhtDiff = abs((float) $highDetail->bpjs_tk_employee - (float) $normalDetail->bpjs_tk_employee);

        // Due to JP cap at ~11.09jt (Mar 2026+), the BPJS TK should differ by:
        // JHT: (20jt - 10jt) * 2% = 200k (uncapped)
        // JP: (min(20jt, 11_086_300) - 10jt) * 1% = 10_863 (cap effect)
        // Total diff: ~210_863
        $this->assertEqualsWithDelta(210_863, $jhtDiff, 1000, 'Difference should reflect uncapped JHT + JP cap boundary');
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
        $detail = $payroll->payrollDetails->firstWhere('staff_member_id', $employee->id);

        // Core payroll fields should still be populated
        $this->assertNotNull($detail);
        $this->assertGreaterThan(0, (float) $detail->original_salary);
        $this->assertGreaterThan(0, (float) $detail->final_salary);
        $this->assertGreaterThan(0, $detail->attended_days);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helper
    // ─────────────────────────────────────────────────────────────────────────

    private function createEmployeeWithAttendance(int $salary): StaffMemberProfile
    {
        return StaffMemberProfile::withoutSyncingToSearch(function () use ($salary) {
            $employee = StaffMemberProfile::factory()->create();

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
                if (! $cursor->isWeekend()) {
                    Attendance::create([
                        'staff_member_id' => $employee->id,
                        'date' => $cursor->toDateString(),
                        'check_in' => $cursor->format('Y-m-d').' 08:00:00',
                        'check_out' => $cursor->format('Y-m-d').' 17:00:00',
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
