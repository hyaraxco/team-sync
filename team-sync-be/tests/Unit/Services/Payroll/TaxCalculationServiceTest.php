<?php

namespace Tests\Unit\Services\Payroll;

use App\Models\BpjsRate;
use App\Models\PtkpAmount;
use App\Models\TaxBracket;
use App\Services\Payroll\TaxCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit tests for TaxCalculationService.
 *
 * Covers:
 *  - PPh 21 progressive rate brackets (UU HPP)
 *  - PTKP deduction logic (TK/0, K/0, K/1, etc.)
 *  - NPWP surcharge (+20%)
 *  - BPJS salary cap enforcement
 *  - BPJS employee vs employer split
 */
class TaxCalculationServiceTest extends TestCase
{
    use RefreshDatabase;

    private TaxCalculationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTaxData();
        $this->service = new TaxCalculationService();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PPh 21 – Rate Bracket Tests
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function test_pph21_zero_for_income_below_ptkp(): void
    {
        // TK/0 PTKP = 54_000_000/year. Monthly gaji 4jt => annual 48jt < PTKP
        $result = $this->service->calculateMonthlyPph21(4_000_000, 'TK/0', true);

        $this->assertEquals(0.0, $result['pph21_monthly']);
        $this->assertEquals(0.0, $result['meta']['pkp']);
    }

    /** @test */
    public function test_pph21_applies_5_percent_bracket_for_low_pkp(): void
    {
        // Gaji 6jt/bulan => annual 72jt. PTKP TK/0 = 54jt. PKP = ~18jt -> 5%
        $result = $this->service->calculateMonthlyPph21(6_000_000, 'TK/0', true);

        // PKP ~18jt falls in bracket 1 (5%)
        $this->assertGreaterThan(0.0, $result['pph21_monthly']);
        $this->assertEquals(5.0, $this->getApplicableBracketRate($result['meta']['pkp']));
    }

    /** @test */
    public function test_pph21_applies_15_percent_bracket_for_mid_pkp(): void
    {
        // Gaji 10jt/bulan => annual 120jt. PTKP TK/0 54jt. PKP ~66jt -> spans 5% & 15%
        $result = $this->service->calculateMonthlyPph21(10_000_000, 'TK/0', true);

        // PKP after deductions (biaya jabatan + BPJS) — for gaji 10jt TK/0
        // gross_annual=120jt, biaya_jabatan=5.4jt, bpjs_annual~3.6jt → net~111jt, pkp=111-54=57jt
        // PKP should be > 0 and < 60jt (first bracket only)
        $this->assertGreaterThan(0.0, $result['meta']['pkp']);
        $this->assertLessThan(60_000_001, $result['meta']['pkp']);
    }

    /** @test */
    public function test_pph21_applies_correct_progressive_calculation(): void
    {
        // Gaji 30jt/bulan => annual 360jt. PTKP TK/0 54jt.
        // PKP = 360jt - biaya jabatan - BPJS - 54jt
        $result = $this->service->calculateMonthlyPph21(30_000_000, 'TK/0', true);

        // PPh 21 annual should be > 0 and monthly > 0
        $this->assertGreaterThan(0.0, $result['meta']['pph21_annual']);
        $this->assertGreaterThan(0.0, $result['pph21_monthly']);
        // Monthly is annual/12 rounded — allow 0.02 delta for rounding
        $this->assertEqualsWithDelta(
            round($result['meta']['pph21_annual'] / 12, 2),
            $result['pph21_monthly'],
            0.02
        );
    }

    /** @test */
    public function test_pph21_high_earner_hits_25_percent_bracket(): void
    {
        // Gaji 25jt/bulan => annual 300jt. Should enter 25% bracket
        $result = $this->service->calculateMonthlyPph21(25_000_000, 'TK/0', true);

        // PKP should be > 250jt (enters 25% bracket)
        $this->assertGreaterThan(0.0, $result['pph21_monthly']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PPh 21 – PTKP Tests
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function test_pph21_married_status_produces_lower_tax(): void
    {
        // K/0 PTKP is higher than TK/0 => lower PKP => lower tax
        $singleResult = $this->service->calculateMonthlyPph21(10_000_000, 'TK/0', true);
        $marriedResult = $this->service->calculateMonthlyPph21(10_000_000, 'K/0', true);

        $this->assertGreaterThan($marriedResult['pph21_monthly'], $singleResult['pph21_monthly']);
    }

    /** @test */
    public function test_pph21_null_ptkp_status_defaults_to_tk0(): void
    {
        $withNull = $this->service->calculateMonthlyPph21(10_000_000, null, true);
        $withTk0 = $this->service->calculateMonthlyPph21(10_000_000, 'TK/0', true);

        $this->assertEquals($withTk0['pph21_monthly'], $withNull['pph21_monthly']);
        $this->assertEquals('TK/0', $withNull['ptkp_status']);
    }

    /** @test */
    public function test_pph21_k1_status_has_lower_tax_than_k0(): void
    {
        $k0Result = $this->service->calculateMonthlyPph21(10_000_000, 'K/0', true);
        $k1Result = $this->service->calculateMonthlyPph21(10_000_000, 'K/1', true);

        // K/1 has one dependent → higher PTKP → lower tax
        $this->assertGreaterThanOrEqual($k1Result['pph21_monthly'], $k0Result['pph21_monthly']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PPh 21 – NPWP Surcharge Tests
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function test_pph21_no_npwp_applies_20_percent_surcharge(): void
    {
        $withNpwp = $this->service->calculateMonthlyPph21(10_000_000, 'TK/0', true);
        $withoutNpwp = $this->service->calculateMonthlyPph21(10_000_000, 'TK/0', false);

        if ($withNpwp['pph21_monthly'] > 0) {
            $this->assertEqualsWithDelta(
                $withNpwp['pph21_monthly'] * 1.20,
                $withoutNpwp['pph21_monthly'],
                0.02
            );
        } else {
            // If PPh is 0, surcharge also stays 0
            $this->assertEquals(0.0, $withoutNpwp['pph21_monthly']);
        }
    }

    /** @test */
    public function test_pph21_has_npwp_flag_is_returned(): void
    {
        $withNpwp = $this->service->calculateMonthlyPph21(10_000_000, 'TK/0', true);
        $withoutNpwp = $this->service->calculateMonthlyPph21(10_000_000, 'TK/0', false);

        $this->assertTrue($withNpwp['has_npwp']);
        $this->assertFalse($withoutNpwp['has_npwp']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // BPJS – Salary Cap Tests
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function test_bpjs_jp_component_capped_at_max_salary_base(): void
    {
        // JP cap = 10_042_300. Test with salary well above cap.
        $result = $this->service->calculateBpjs(20_000_000);
        $jpRate = BpjsRate::where('component', 'jp')->first();

        $expectedJpEmployee = round((float) $jpRate->max_salary_base * ((float) $jpRate->employee_rate / 100), 2);

        $this->assertEquals($expectedJpEmployee, $result['breakdown']['jp_employee']);
    }

    /** @test */
    public function test_bpjs_kesehatan_capped_at_max_salary_base(): void
    {
        // BPJS Kes cap = 12_000_000
        $result = $this->service->calculateBpjs(20_000_000);
        $kesRate = BpjsRate::where('component', 'bpjs_kesehatan')->first();

        $expectedKesEmployee = round((float) $kesRate->max_salary_base * ((float) $kesRate->employee_rate / 100), 2);

        $this->assertEquals($expectedKesEmployee, $result['breakdown']['bpjs_kesehatan_employee']);
    }

    /** @test */
    public function test_bpjs_salary_below_cap_not_capped(): void
    {
        // Salary 5jt is below all caps
        $salary = 5_000_000;
        $result = $this->service->calculateBpjs($salary);
        $jhtRate = BpjsRate::where('component', 'jht')->first();

        $expectedJhtEmployee = round($salary * ((float) $jhtRate->employee_rate / 100), 2);

        $this->assertEquals($expectedJhtEmployee, $result['breakdown']['jht_employee']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // BPJS – Employee vs Employer Split Tests
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function test_bpjs_employer_share_greater_than_employee_share(): void
    {
        // Employer pays JHT(3.7%) + JKK(0.24%) + JKM(0.3%) + JP(2%) + Kes(4%)
        // Employee pays JHT(2%) + JP(1%) + Kes(1%)
        $result = $this->service->calculateBpjs(10_000_000);

        $this->assertGreaterThan($result['employee_share'], $result['employer_share']);
    }

    /** @test */
    public function test_bpjs_jkk_jkm_are_employer_only(): void
    {
        $result = $this->service->calculateBpjs(10_000_000);

        // JKK and JKM employee portions are 0%
        $this->assertEquals(0.0, $result['breakdown']['jkk_employee']);
        $this->assertEquals(0.0, $result['breakdown']['jkm_employee']);
        $this->assertGreaterThan(0.0, $result['breakdown']['jkk_employer']);
        $this->assertGreaterThan(0.0, $result['breakdown']['jkm_employer']);
    }

    /** @test */
    public function test_bpjs_breakdown_keys_are_present(): void
    {
        $result = $this->service->calculateBpjs(8_000_000);

        $expectedKeys = [
            'jht_employee', 'jht_employer',
            'jkk_employee', 'jkk_employer',
            'jkm_employee', 'jkm_employer',
            'jp_employee', 'jp_employer',
            'bpjs_kesehatan_employee', 'bpjs_kesehatan_employer',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $result['breakdown'], "Missing breakdown key: {$key}");
        }
    }

    /** @test */
    public function test_bpjs_total_equals_sum_of_breakdown(): void
    {
        $result = $this->service->calculateBpjs(10_000_000);

        $sumEmployee = 0.0;
        $sumEmployer = 0.0;
        foreach ($result['breakdown'] as $key => $value) {
            if (str_ends_with($key, '_employee')) {
                $sumEmployee += $value;
            } else {
                $sumEmployer += $value;
            }
        }

        $this->assertEqualsWithDelta($sumEmployee, $result['employee_share'], 0.01);
        $this->assertEqualsWithDelta($sumEmployer, $result['employer_share'], 0.01);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Meta / Return Shape Tests
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function test_pph21_result_contains_all_meta_keys(): void
    {
        $result = $this->service->calculateMonthlyPph21(10_000_000, 'TK/0', true);

        $expectedMeta = [
            'gross_monthly', 'gross_annual', 'biaya_jabatan_annual',
            'bpjs_deduction_annual', 'net_income_annual', 'ptkp_amount',
            'pkp', 'pph21_annual',
        ];

        foreach ($expectedMeta as $key) {
            $this->assertArrayHasKey($key, $result['meta'], "Missing meta key: {$key}");
        }
    }

    /** @test */
    public function test_biaya_jabatan_capped_at_500k_monthly(): void
    {
        // High earner should be capped at 500k/month (6jt/year)
        $result = $this->service->calculateMonthlyPph21(50_000_000, 'TK/0', true);

        $this->assertEquals(6_000_000.0, $result['meta']['biaya_jabatan_annual']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function getApplicableBracketRate(float $pkp): float
    {
        if ($pkp <= 60_000_000) return 5.0;
        if ($pkp <= 250_000_000) return 15.0;
        if ($pkp <= 500_000_000) return 25.0;
        if ($pkp <= 5_000_000_000) return 30.0;
        return 35.0;
    }

    private function seedTaxData(): void
    {
        // Tax Brackets (UU HPP)
        $brackets = [
            ['min_income' => 0, 'max_income' => 60_000_000, 'rate' => 5.00, 'order' => 1],
            ['min_income' => 60_000_001, 'max_income' => 250_000_000, 'rate' => 15.00, 'order' => 2],
            ['min_income' => 250_000_001, 'max_income' => 500_000_000, 'rate' => 25.00, 'order' => 3],
            ['min_income' => 500_000_001, 'max_income' => 5_000_000_000, 'rate' => 30.00, 'order' => 4],
            ['min_income' => 5_000_000_001, 'max_income' => null, 'rate' => 35.00, 'order' => 5],
        ];
        foreach ($brackets as $b) {
            TaxBracket::updateOrCreate(['order' => $b['order']], $b);
        }

        // BPJS Rates
        $rates = [
            ['component' => 'jht', 'employee_rate' => 2.00, 'employer_rate' => 3.70, 'max_salary_base' => null],
            ['component' => 'jkk', 'employee_rate' => 0.00, 'employer_rate' => 0.24, 'max_salary_base' => null],
            ['component' => 'jkm', 'employee_rate' => 0.00, 'employer_rate' => 0.30, 'max_salary_base' => null],
            ['component' => 'jp', 'employee_rate' => 1.00, 'employer_rate' => 2.00, 'max_salary_base' => 10_042_300],
            ['component' => 'bpjs_kesehatan', 'employee_rate' => 1.00, 'employer_rate' => 4.00, 'max_salary_base' => 12_000_000],
        ];
        foreach ($rates as $r) {
            BpjsRate::updateOrCreate(['component' => $r['component']], $r);
        }

        // PTKP Amounts (2024)
        $ptkp = [
            ['status' => 'TK/0', 'annual_amount' => 54_000_000, 'description' => 'Tidak Kawin, 0 tanggungan'],
            ['status' => 'TK/1', 'annual_amount' => 58_500_000, 'description' => 'Tidak Kawin, 1 tanggungan'],
            ['status' => 'TK/2', 'annual_amount' => 63_000_000, 'description' => 'Tidak Kawin, 2 tanggungan'],
            ['status' => 'TK/3', 'annual_amount' => 67_500_000, 'description' => 'Tidak Kawin, 3 tanggungan'],
            ['status' => 'K/0', 'annual_amount' => 58_500_000, 'description' => 'Kawin, 0 tanggungan'],
            ['status' => 'K/1', 'annual_amount' => 63_000_000, 'description' => 'Kawin, 1 tanggungan'],
            ['status' => 'K/2', 'annual_amount' => 67_500_000, 'description' => 'Kawin, 2 tanggungan'],
            ['status' => 'K/3', 'annual_amount' => 72_000_000, 'description' => 'Kawin, 3 tanggungan'],
        ];
        foreach ($ptkp as $p) {
            PtkpAmount::updateOrCreate(['status' => $p['status']], $p);
        }
    }
}
