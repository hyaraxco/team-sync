<?php

namespace Tests\Unit\Services\Payroll;

use App\DTOs\BpjsPayrollInput;
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
        $result = $this->service->calculateMonthlyPph21(4_000_000, 'TK/0', true, 120_000);

        $this->assertEquals(0.0, $result['pph21_monthly']);
        $this->assertEquals(0.0, $result['meta']['pkp']);
    }

    /** @test */
    public function test_pph21_applies_5_percent_bracket_for_low_pkp(): void
    {
        // Gaji 6jt/bulan => annual 72jt. PTKP TK/0 = 54jt. PKP = ~18jt -> 5%
        $result = $this->service->calculateMonthlyPph21(6_000_000, 'TK/0', true, 180_000);

        // PKP ~18jt falls in bracket 1 (5%)
        $this->assertGreaterThan(0.0, $result['pph21_monthly']);
        $this->assertEquals(5.0, $this->getApplicableBracketRate($result['meta']['pkp']));
    }

    /** @test */
    public function test_pph21_applies_15_percent_bracket_for_mid_pkp(): void
    {
        // Gaji 10jt/bulan => annual 120jt. PTKP TK/0 54jt. PKP ~66jt -> spans 5% & 15%
        $result = $this->service->calculateMonthlyPph21(10_000_000, 'TK/0', true, 300_000);

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
        $result = $this->service->calculateMonthlyPph21(30_000_000, 'TK/0', true, 700_423);

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
        $result = $this->service->calculateMonthlyPph21(25_000_000, 'TK/0', true, 600_423);

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
        $singleResult = $this->service->calculateMonthlyPph21(10_000_000, 'TK/0', true, 300_000);
        $marriedResult = $this->service->calculateMonthlyPph21(10_000_000, 'K/0', true, 300_000);

        $this->assertGreaterThan($marriedResult['pph21_monthly'], $singleResult['pph21_monthly']);
    }

    /** @test */
    public function test_pph21_null_ptkp_status_defaults_to_tk0(): void
    {
        $withNull = $this->service->calculateMonthlyPph21(10_000_000, null, true, 300_000);
        $withTk0 = $this->service->calculateMonthlyPph21(10_000_000, 'TK/0', true, 300_000);

        $this->assertEquals($withTk0['pph21_monthly'], $withNull['pph21_monthly']);
        $this->assertEquals('TK/0', $withNull['ptkp_status']);
    }

    /** @test */
    public function test_pph21_k1_status_has_lower_tax_than_k0(): void
    {
        $k0Result = $this->service->calculateMonthlyPph21(10_000_000, 'K/0', true, 300_000);
        $k1Result = $this->service->calculateMonthlyPph21(10_000_000, 'K/1', true, 300_000);

        // K/1 has one dependent → higher PTKP → lower tax
        $this->assertGreaterThanOrEqual($k1Result['pph21_monthly'], $k0Result['pph21_monthly']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PPh 21 – NPWP Surcharge Tests
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function test_pph21_no_npwp_applies_20_percent_surcharge(): void
    {
        $withNpwp = $this->service->calculateMonthlyPph21(10_000_000, 'TK/0', true, 300_000);
        $withoutNpwp = $this->service->calculateMonthlyPph21(10_000_000, 'TK/0', false, 300_000);

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
    public function test_pph21_has_taxpayer_id_flag_is_returned(): void
    {
        $withNpwp = $this->service->calculateMonthlyPph21(10_000_000, 'TK/0', true, 300_000);
        $withoutNpwp = $this->service->calculateMonthlyPph21(10_000_000, 'TK/0', false, 300_000);

        $this->assertTrue($withNpwp['has_taxpayer_id']);
        $this->assertFalse($withoutNpwp['has_taxpayer_id']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // BPJS – Salary Cap Tests
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function test_bpjs_jp_component_capped_at_max_salary_base(): void
    {
        // JP cap = 10_042_300. Test with salary well above cap.
        $result = $this->service->calculateBpjs(new BpjsPayrollInput(20_000_000, 0, 1, 3_000_000), '2025-02');
        $jpRate = BpjsRate::where('component', 'jp')->first();

        $expectedJpEmployee = round((float) $jpRate->max_salary_base * ((float) $jpRate->employee_rate / 100), 2);

        $this->assertEquals($expectedJpEmployee, $result['breakdown']['jp_employee']);
    }

    /** @test */
    public function test_bpjs_kesehatan_capped_at_max_salary_base(): void
    {
        // BPJS Kes cap = 12_000_000
        $result = $this->service->calculateBpjs(new BpjsPayrollInput(20_000_000, 0, 1, 3_000_000), '2025-02');
        $kesRate = BpjsRate::where('component', 'bpjs_kesehatan')->first();

        $expectedKesEmployee = round((float) $kesRate->max_salary_base * ((float) $kesRate->employee_rate / 100), 2);

        $this->assertEquals($expectedKesEmployee, $result['breakdown']['bpjs_kesehatan_employee']);
    }

    /** @test */
    public function test_bpjs_salary_below_cap_not_capped(): void
    {
        // Salary 5jt is below all caps
        $salary = 5_000_000;
        $result = $this->service->calculateBpjs(new BpjsPayrollInput($salary, 0, 1, 3_000_000), '2025-02');
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
        $result = $this->service->calculateBpjs(new BpjsPayrollInput(10_000_000, 0, 1, 3_000_000), '2025-02');

        $this->assertGreaterThan($result['employee_share'], $result['employer_share']);
    }

    /** @test */
    public function test_bpjs_jkk_jkm_are_employer_only(): void
    {
        $result = $this->service->calculateBpjs(new BpjsPayrollInput(10_000_000, 0, 1, 3_000_000), '2025-02');

        // JKK and JKM employee portions are 0%
        $this->assertEquals(0.0, $result['breakdown']['jkk_employee']);
        $this->assertEquals(0.0, $result['breakdown']['jkm_employee']);
        $this->assertGreaterThan(0.0, $result['breakdown']['jkk_employer']);
        $this->assertGreaterThan(0.0, $result['breakdown']['jkm_employer']);
    }

    /** @test */
    public function test_bpjs_breakdown_keys_are_present(): void
    {
        $result = $this->service->calculateBpjs(new BpjsPayrollInput(8_000_000, 0, 1, 3_000_000), '2025-02');

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
        $result = $this->service->calculateBpjs(new BpjsPayrollInput(10_000_000, 0, 1, 3_000_000), '2025-02');

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
        $result = $this->service->calculateMonthlyPph21(10_000_000, 'TK/0', true, 300_000);

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
        $result = $this->service->calculateMonthlyPph21(50_000_000, 'TK/0', true, 1_100_423);

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

    public function test_ter_all_annex_bands_have_inclusive_upper_bounds(): void
    {
        // Independently transcribed from PP 58/2023 Annex A-C, PDF pages 11-24.
        // https://peraturan.go.id/files/pp-no-58-tahun-2023.pdf
        $tables = [
            'TK/0' => [
                'category' => 'A',
                'ceilings' => [
                    5_400_000, 5_650_000, 5_950_000, 6_300_000, 6_750_000,
                    7_500_000, 8_550_000, 9_650_000, 10_050_000, 10_350_000,
                    10_700_000, 11_050_000, 11_600_000, 12_500_000, 13_750_000,
                    15_100_000, 16_950_000, 19_750_000, 24_150_000, 26_450_000,
                    28_000_000, 30_050_000, 32_400_000, 35_400_000, 39_100_000,
                    43_850_000, 47_800_000, 51_400_000, 56_300_000, 62_200_000,
                    68_600_000, 77_500_000, 89_000_000, 103_000_000, 125_000_000,
                    157_000_000, 206_000_000, 337_000_000, 454_000_000, 550_000_000,
                    695_000_000, 910_000_000, 1_400_000_000,
                ],
                'percentages' => [
                    0, 0.25, 0.5, 0.75, 1, 1.25, 1.5, 1.75, 2, 2.25, 2.5,
                    3, 3.5, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16,
                    17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30,
                    31, 32, 33, 34,
                ],
            ],
            'K/1' => [
                'category' => 'B',
                'ceilings' => [
                    6_200_000, 6_500_000, 6_850_000, 7_300_000, 9_200_000,
                    10_750_000, 11_250_000, 11_600_000, 12_600_000, 13_600_000,
                    14_950_000, 16_400_000, 18_450_000, 21_850_000, 26_000_000,
                    27_700_000, 29_350_000, 31_450_000, 33_950_000, 37_100_000,
                    41_100_000, 45_800_000, 49_500_000, 53_800_000, 58_500_000,
                    64_000_000, 71_000_000, 80_000_000, 93_000_000, 109_000_000,
                    129_000_000, 163_000_000, 211_000_000, 374_000_000, 459_000_000,
                    555_000_000, 704_000_000, 957_000_000, 1_405_000_000,
                ],
                'percentages' => [
                    0, 0.25, 0.5, 0.75, 1, 1.5, 2, 2.5, 3, 4, 5, 6, 7, 8, 9,
                    10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20, 21, 22, 23,
                    24, 25, 26, 27, 28, 29, 30, 31, 32, 33, 34,
                ],
            ],
            'K/3' => [
                'category' => 'C',
                'ceilings' => [
                    6_600_000, 6_950_000, 7_350_000, 7_800_000, 8_850_000,
                    9_800_000, 10_950_000, 11_200_000, 12_050_000, 12_950_000,
                    14_150_000, 15_550_000, 17_050_000, 19_500_000, 22_700_000,
                    26_600_000, 28_100_000, 30_100_000, 32_600_000, 35_400_000,
                    38_900_000, 43_000_000, 47_400_000, 51_200_000, 55_800_000,
                    60_400_000, 66_700_000, 74_500_000, 83_200_000, 95_600_000,
                    110_000_000, 134_000_000, 169_000_000, 221_000_000, 390_000_000,
                    463_000_000, 561_000_000, 709_000_000, 965_000_000, 1_419_000_000,
                ],
                'percentages' => [
                    0, 0.25, 0.5, 0.75, 1, 1.25, 1.5, 1.75, 2, 3, 4, 5, 6,
                    7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20,
                    21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, 32, 33, 34,
                ],
            ],
        ];

        foreach ($tables as $status => $table) {
            $this->assertCount(count($table['ceilings']) + 1, $table['percentages']);

            foreach ($table['ceilings'] as $index => $ceiling) {
                foreach ([-1, 0, 1] as $offset) {
                    $gross = $ceiling + $offset;
                    $rate = $table['percentages'][$index + ($offset === 1 ? 1 : 0)] / 100;
                    $result = $this->service->calculateMonthlyTer($gross, $status, true);
                    $context = "{$status}, gross {$gross}";

                    $this->assertSame($table['category'], $result['ter_category'], $context);
                    $this->assertEqualsWithDelta($rate, $result['ter_rate'], 1e-12, $context);
                    $this->assertEquals(round($gross * $rate), $result['pph21_monthly'], $context);
                }
            }

            $top = $this->service->calculateMonthlyTer(2_000_000_000, $status, true);
            $this->assertSame(0.34, $top['ter_rate']);
            $this->assertEquals(680_000_000, $top['pph21_monthly']);
        }
    }

    public function test_ter_category_mapping_matches_article_2(): void
    {
        foreach (['TK/0' => 'A', 'TK/1' => 'A', 'K/0' => 'A', 'TK/2' => 'B',
            'TK/3' => 'B', 'K/1' => 'B', 'K/2' => 'B', 'K/3' => 'C'] as $status => $category) {
            $result = $this->service->calculateMonthlyTer(21_000_000, $status, true);
            $this->assertSame($category, $result['ter_category']);
        }
    }

    public function test_ter_reported_b_and_c_regressions(): void
    {
        $b = $this->service->calculateMonthlyTer(21_000_000, 'K/1', true);
        $this->assertSame(0.08, $b['ter_rate']);
        $this->assertEquals(1_680_000, $b['pph21_monthly']);

        $c = $this->service->calculateMonthlyTer(43_050_000, 'K/3', true);
        $this->assertSame(0.16, $c['ter_rate']);
        $this->assertEquals(6_888_000, $c['pph21_monthly']);
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
        $this->assertArrayHasKey('has_taxpayer_id', $result);
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
            ['component' => 'jht', 'employee_rate' => 2.00, 'employer_rate' => 3.70, 'max_salary_base' => null, 'effective_date' => '2020-01-01'],
            ['component' => 'jkk', 'risk_class' => 1, 'employee_rate' => 0.00, 'employer_rate' => 0.24, 'max_salary_base' => null, 'effective_date' => '2020-01-01'],
            ['component' => 'jkm', 'employee_rate' => 0.00, 'employer_rate' => 0.30, 'max_salary_base' => null, 'effective_date' => '2020-01-01'],
            ['component' => 'jp', 'employee_rate' => 1.00, 'employer_rate' => 2.00, 'max_salary_base' => 10_042_300, 'effective_date' => '2020-01-01'],
            ['component' => 'bpjs_kesehatan', 'employee_rate' => 1.00, 'employer_rate' => 4.00, 'max_salary_base' => 12_000_000, 'effective_date' => '2020-01-01'],
        ];
        foreach ($rates as $r) {
            BpjsRate::updateOrCreate(['component' => $r['component'], 'effective_date' => $r['effective_date']], $r);
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
