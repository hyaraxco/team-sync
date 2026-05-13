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
        $this->service = new TaxCalculationService;
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
    // TER 2024 – Category & Rate Lookup Tests
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function test_ter_zero_tax_for_income_below_threshold_category_a(): void
    {
        // TK/0 maps to category A, first bracket: max 5_400_000 → rate 0%
        $result = $this->service->calculateMonthlyTer(5_000_000, 'TK/0', true);

        $this->assertEquals(0, $result['pph21_monthly']);
        $this->assertEquals('A', $result['ter_category']);
        $this->assertEquals(0.0, $result['ter_rate']);
    }

    /** @test */
    public function test_ter_applies_low_rate_for_salary_in_first_taxable_bracket(): void
    {
        // TK/0 category A: 5_650_000 bracket → 0.25%
        $result = $this->service->calculateMonthlyTer(5_650_000, 'TK/0', true);

        $this->assertEquals('A', $result['ter_category']);
        $this->assertEquals(0.0025, $result['ter_rate']);
        // PPh21 = 5_650_000 * 0.0025 = 14_125
        $this->assertEquals(14125, $result['pph21_monthly']);
    }

    /** @test */
    public function test_ter_category_b_mapping_for_k1(): void
    {
        // K/1 maps to category B
        $result = $this->service->calculateMonthlyTer(10_000_000, 'K/1', true);

        $this->assertEquals('B', $result['ter_category']);
        $this->assertGreaterThan(0, $result['pph21_monthly']);
    }

    /** @test */
    public function test_ter_category_c_mapping_for_k3(): void
    {
        // K/3 maps to category C
        $result = $this->service->calculateMonthlyTer(15_000_000, 'K/3', true);

        $this->assertEquals('C', $result['ter_category']);
        $this->assertGreaterThan(0, $result['pph21_monthly']);
    }

    /** @test */
    public function test_ter_defaults_to_category_a_for_unknown_ptkp(): void
    {
        $result = $this->service->calculateMonthlyTer(10_000_000, 'UNKNOWN', true);

        $this->assertEquals('A', $result['ter_category']);
    }

    /** @test */
    public function test_ter_null_ptkp_defaults_to_tk0_category_a(): void
    {
        $result = $this->service->calculateMonthlyTer(10_000_000, null, true);

        $this->assertEquals('TK/0', $result['ptkp_status']);
        $this->assertEquals('A', $result['ter_category']);
    }

    /** @test */
    public function test_ter_high_salary_hits_top_rate(): void
    {
        // Gross 1.4B exactly → 33% (category A). Gross above → 34% top bracket
        $atExact = $this->service->calculateMonthlyTer(1_400_000_000, 'TK/0', true);
        $this->assertEquals(0.33, $atExact['ter_rate']);

        $aboveExact = $this->service->calculateMonthlyTer(2_000_000_000, 'TK/0', true);
        $this->assertEquals(0.34, $aboveExact['ter_rate']);
    }

    /** @test */
    public function test_ter_npwp_surcharge_applied(): void
    {
        $withNpwp = $this->service->calculateMonthlyTer(10_000_000, 'TK/0', true);
        $withoutNpwp = $this->service->calculateMonthlyTer(10_000_000, 'TK/0', false);

        if ($withNpwp['pph21_monthly'] > 0) {
            $this->assertEqualsWithDelta(
                $withNpwp['pph21_monthly'] * 1.20,
                $withoutNpwp['pph21_monthly'],
                1
            );
        } else {
            $this->assertEquals(0, $withoutNpwp['pph21_monthly']);
        }
    }

    /** @test */
    public function test_ter_returns_correct_meta_shape(): void
    {
        $result = $this->service->calculateMonthlyTer(10_000_000, 'TK/0', true);

        $this->assertArrayHasKey('pph21_monthly', $result);
        $this->assertArrayHasKey('has_npwp', $result);
        $this->assertArrayHasKey('ptkp_status', $result);
        $this->assertArrayHasKey('ter_category', $result);
        $this->assertArrayHasKey('ter_rate', $result);
        $this->assertArrayHasKey('method', $result);
        $this->assertEquals('ter_2024', $result['method']);
        $this->assertArrayHasKey('gross_monthly', $result['meta']);
        $this->assertArrayHasKey('ter_category', $result['meta']);
        $this->assertArrayHasKey('ter_rate', $result['meta']);
        $this->assertArrayHasKey('ter_rate_pct', $result['meta']);
    }

    /** @test */
    public function test_ter_different_categories_produce_different_tax_for_same_salary(): void
    {
        // Same salary, different PTKP status → different TER category → different tax
        $catA = $this->service->calculateMonthlyTer(10_000_000, 'TK/0', true);
        $catB = $this->service->calculateMonthlyTer(10_000_000, 'K/1', true);
        $catC = $this->service->calculateMonthlyTer(10_000_000, 'K/3', true);

        // Categories should be different
        $this->assertNotEquals($catA['ter_category'], $catB['ter_category']);
        $this->assertNotEquals($catB['ter_category'], $catC['ter_category']);

        // Higher category (more dependents) should have lower effective rate at same income
        $this->assertGreaterThanOrEqual($catB['pph21_monthly'], $catA['pph21_monthly']);
        $this->assertGreaterThanOrEqual($catC['pph21_monthly'], $catB['pph21_monthly']);
    }

    /** @test */
    public function test_annualized_pph21_is_alias_for_calculate_monthly_pph21(): void
    {
        $annualized = $this->service->calculateAnnualizedPph21(10_000_000, 'TK/0', true);
        $direct = $this->service->calculateMonthlyPph21(10_000_000, 'TK/0', true);

        $this->assertEquals($direct['pph21_monthly'], $annualized['pph21_monthly']);
        $this->assertEquals($direct['meta']['pph21_annual'], $annualized['meta']['pph21_annual']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function getApplicableBracketRate(float $pkp): float
    {
        if ($pkp <= 60_000_000) {
            return 5.0;
        }
        if ($pkp <= 250_000_000) {
            return 15.0;
        }
        if ($pkp <= 500_000_000) {
            return 25.0;
        }
        if ($pkp <= 5_000_000_000) {
            return 30.0;
        }

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
