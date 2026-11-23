<?php

namespace Tests\Unit\Services\Payroll;

use App\Services\Payroll\TaxCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression tests for TER B and C rate-table boundaries (team-sync-2fo).
 *
 * Expected rates come from the PP 58/2023 annex / DJP published tables
 * (PMK 168/2023), not from the implementation. The two headline cases
 * from the ticket: category B at Rp 21.000.000 must select 8%, category
 * C at Rp 43.050.000 must select 16%.
 */
class TerBoundaryTest extends TestCase
{
    use RefreshDatabase;

    private TaxCalculationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(TaxCalculationService::class);
    }

    /** @test */
    public function test_category_b_gross_21jt_selects_eight_percent(): void
    {
        $result = $this->service->calculateMonthlyTer(21_000_000, 'TK/2', true);

        $this->assertSame(0.08, $result['ter_rate']);
        $this->assertSame(1_680_000.0, $result['pph21_monthly']);
    }

    /** @test */
    public function test_category_c_gross_43_05jt_selects_sixteen_percent(): void
    {
        $result = $this->service->calculateMonthlyTer(43_050_000, 'K/3', true);

        $this->assertSame(0.16, $result['ter_rate']);
        $this->assertSame(6_888_000.0, $result['pph21_monthly']);
    }

    /** @test */
    public function test_category_b_boundaries_around_the_eight_percent_band(): void
    {
        // Band: 18.450.001 s.d. 21.850.000 at 8%
        $this->assertSame(0.07, $this->service->calculateMonthlyTer(18_450_000, 'TK/2', true)['ter_rate']);
        $this->assertSame(0.08, $this->service->calculateMonthlyTer(18_450_001, 'TK/2', true)['ter_rate']);
        $this->assertSame(0.08, $this->service->calculateMonthlyTer(21_850_000, 'TK/2', true)['ter_rate']);
        $this->assertSame(0.09, $this->service->calculateMonthlyTer(21_850_001, 'TK/2', true)['ter_rate']);
    }

    /** @test */
    public function test_category_c_boundaries_around_the_fifteen_percent_band(): void
    {
        // Band: 38.900.001 s.d. 43.000.000 at 15%
        $this->assertSame(0.14, $this->service->calculateMonthlyTer(38_900_000, 'K/3', true)['ter_rate']);
        $this->assertSame(0.15, $this->service->calculateMonthlyTer(38_900_001, 'K/3', true)['ter_rate']);
        $this->assertSame(0.15, $this->service->calculateMonthlyTer(43_000_000, 'K/3', true)['ter_rate']);
        $this->assertSame(0.16, $this->service->calculateMonthlyTer(43_000_001, 'K/3', true)['ter_rate']);
    }

    /** @test */
    public function test_category_b_full_table_walks_all_upper_bounds_inclusively(): void
    {
        // Every row's upper bound picks that row's rate; one rupiah above
        // picks the next band's rate. Expected values are the published
        // annex boundaries.
        $boundaries = [
            [6_200_000, 0.00], [6_500_000, 0.0025], [6_850_000, 0.005],
            [7_300_000, 0.0075], [9_200_000, 0.01], [10_750_000, 0.015],
            [11_250_000, 0.02], [11_600_000, 0.025], [12_600_000, 0.03],
            [13_600_000, 0.04], [14_950_000, 0.05], [16_400_000, 0.06],
            [18_450_000, 0.07], [21_850_000, 0.08], [26_000_000, 0.09],
            [27_700_000, 0.10], [29_350_000, 0.11], [31_450_000, 0.12],
            [33_950_000, 0.13], [37_100_000, 0.14], [41_100_000, 0.15],
            [45_800_000, 0.16], [49_500_000, 0.17], [53_800_000, 0.18],
            [58_500_000, 0.19], [64_000_000, 0.20], [71_000_000, 0.21],
            [80_000_000, 0.22], [93_000_000, 0.23], [109_000_000, 0.24],
            [129_000_000, 0.25], [163_000_000, 0.26], [211_000_000, 0.27],
            [374_000_000, 0.28], [459_000_000, 0.29], [555_000_000, 0.30],
            [704_000_000, 0.31], [957_000_000, 0.32], [1_405_000_000, 0.33],
        ];

        foreach ($boundaries as [$max, $rate]) {
            $this->assertSame(
                $rate,
                $this->service->calculateMonthlyTer($max, 'TK/2', true)['ter_rate'],
                "Category B at exactly {$max} must select {$rate}"
            );
        }
    }

    /** @test */
    public function test_category_c_full_table_walks_all_upper_bounds_inclusively(): void
    {
        $boundaries = [
            [6_600_000, 0.00], [6_950_000, 0.0025], [7_350_000, 0.005],
            [7_800_000, 0.0075], [8_850_000, 0.01], [9_800_000, 0.0125],
            [10_950_000, 0.015], [11_200_000, 0.0175], [12_050_000, 0.02],
            [12_950_000, 0.03], [14_150_000, 0.04], [15_550_000, 0.05],
            [17_050_000, 0.06], [19_500_000, 0.07], [22_700_000, 0.08],
            [26_600_000, 0.09], [28_100_000, 0.10], [30_100_000, 0.11],
            [32_600_000, 0.12], [35_400_000, 0.13], [38_900_000, 0.14],
            [43_000_000, 0.15], [47_400_000, 0.16], [51_200_000, 0.17],
            [55_800_000, 0.18], [60_400_000, 0.19], [66_700_000, 0.20],
            [74_500_000, 0.21], [83_200_000, 0.22], [95_600_000, 0.23],
            [110_000_000, 0.24], [134_000_000, 0.25], [169_000_000, 0.26],
            [221_000_000, 0.27], [390_000_000, 0.28], [463_000_000, 0.29],
            [561_000_000, 0.30], [709_000_000, 0.31], [965_000_000, 0.32],
            [1_419_000_000, 0.33],
        ];

        foreach ($boundaries as [$max, $rate]) {
            $this->assertSame(
                $rate,
                $this->service->calculateMonthlyTer($max, 'K/3', true)['ter_rate'],
                "Category C at exactly {$max} must select {$rate}"
            );
        }
    }

    /** @test */
    public function test_category_c_top_band_recovers_the_missing_thirty_three_percent_row(): void
    {
        // Band 33%: 965.000.001 s.d. 1.419.000.000. Previously the source
        // jumped from 32% straight to the 34% sentinel.
        $this->assertSame(0.32, $this->service->calculateMonthlyTer(965_000_000, 'K/3', true)['ter_rate']);
        $this->assertSame(0.33, $this->service->calculateMonthlyTer(965_000_001, 'K/3', true)['ter_rate']);
        $this->assertSame(0.33, $this->service->calculateMonthlyTer(1_419_000_000, 'K/3', true)['ter_rate']);
        $this->assertSame(0.34, $this->service->calculateMonthlyTer(1_419_000_001, 'K/3', true)['ter_rate']);
    }

    /** @test */
    public function test_category_b_top_boundaries_match_published_table(): void
    {
        $this->assertSame(0.32, $this->service->calculateMonthlyTer(957_000_000, 'TK/2', true)['ter_rate']);
        $this->assertSame(0.33, $this->service->calculateMonthlyTer(957_000_001, 'TK/2', true)['ter_rate']);
        $this->assertSame(0.33, $this->service->calculateMonthlyTer(1_405_000_000, 'TK/2', true)['ter_rate']);
        $this->assertSame(0.34, $this->service->calculateMonthlyTer(1_405_000_001, 'TK/2', true)['ter_rate']);
    }

    /** @test */
    public function test_no_npwp_applies_twenty_percent_higher_amount_not_rate(): void
    {
        // Higher-rate treatment is a 1.20 multiplier on the amount.
        $withNpwp = $this->service->calculateMonthlyTer(21_000_000, 'TK/2', true);
        $withoutNpwp = $this->service->calculateMonthlyTer(21_000_000, 'TK/2', false);

        $this->assertEqualsWithDelta(
            $withNpwp['pph21_monthly'] * 1.2,
            $withoutNpwp['pph21_monthly'],
            0.01
        );
    }

    /** @test */
    public function category_a_table_is_unchanged_against_published_rows(): void
    {
        // Spot checks on category A to prove this fix did not disturb it.
        $this->assertSame(0.02, $this->service->calculateMonthlyTer(10_050_000, 'TK/0', true)['ter_rate']);
        $this->assertSame(0.08, $this->service->calculateMonthlyTer(19_750_000, 'TK/0', true)['ter_rate']);
        $this->assertSame(0.33, $this->service->calculateMonthlyTer(1_400_000_000, 'TK/0', true)['ter_rate']);
        $this->assertSame(0.34, $this->service->calculateMonthlyTer(1_400_000_001, 'TK/0', true)['ter_rate']);
    }

    /** @test */
    public function test_category_a_variable_pay_jumps_bracket_boundary(): void
    {
        // PP 58/2023 Category A (TK/0, TK/1, K/0):
        // Bracket <= 8.550.000: 1.5% (rate 0.015) -> 8.550.000 * 0.015 = 128.250
        // Bracket 8.550.001 - 9.650.000: 1.75% (rate 0.0175) -> 8.600.000 * 0.0175 = 150.500
        $baseSalary = 8_550_000;
        $overtimePay = 50_000;
        $combinedGross = $baseSalary + $overtimePay; // 8.600.000

        // Base salary alone sits exactly at the 1.5% ceiling:
        $baseOnly = $this->service->calculateMonthlyTer($baseSalary, 'TK/0', true);
        $this->assertSame(0.015, $baseOnly['ter_rate']);
        $this->assertSame(128_250.0, $baseOnly['pph21_monthly']);

        // Adding overtime pushes gross across the 8.550.000 threshold to 8.600.000,
        // which must jump to the 1.75% rate for the whole gross:
        $withOvertime = $this->service->calculateMonthlyTer($combinedGross, 'TK/0', true);
        $this->assertSame(0.0175, $withOvertime['ter_rate']);
        $this->assertSame(150_500.0, $withOvertime['pph21_monthly']);

        // Proves that combined withholding differs from taxing overtime at the base rate:
        $naiveTax = round($combinedGross * 0.015); // 129.000
        $this->assertNotEquals($naiveTax, $withOvertime['pph21_monthly']);
    }
}
