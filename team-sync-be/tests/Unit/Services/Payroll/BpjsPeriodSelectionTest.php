<?php

namespace Tests\Unit\Services\Payroll;

use App\Models\BpjsRate;
use App\Models\PtkpAmount;
use App\Models\TaxBracket;
use App\Services\Payroll\TaxCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for period-based BPJS rate selection (team-sync-mbr).
 *
 * JP ceiling history:
 * - Until Feb 2025: Rp 10.042.300
 * - Mar 2025 to Feb 2026: Rp 10.547.400
 * - Mar 2026 onward: Rp 11.086.300
 */
class BpjsPeriodSelectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRatesAndBrackets();
    }

    /** @test */
    public function test_feb_2025_payroll_uses_old_jp_ceiling(): void
    {
        $service = app(TaxCalculationService::class);
        $result = $service->calculateBpjs(15_000_000, '2025-02');

        $jpEmployee = $result['breakdown']['jp_employee'];
        $expected = round(10_042_300 * 0.01, 2);

        $this->assertEquals($expected, $jpEmployee);
    }

    /** @test */
    public function test_mar_2025_payroll_uses_new_jp_ceiling(): void
    {
        $service = app(TaxCalculationService::class);
        $result = $service->calculateBpjs(15_000_000, '2025-03');

        $jpEmployee = $result['breakdown']['jp_employee'];
        $expected = round(10_547_400 * 0.01, 2);

        $this->assertEquals($expected, $jpEmployee);
    }

    /** @test */
    public function test_feb_2026_payroll_uses_2025_jp_ceiling(): void
    {
        $service = app(TaxCalculationService::class);
        $result = $service->calculateBpjs(15_000_000, '2026-02');

        $jpEmployee = $result['breakdown']['jp_employee'];
        $expected = round(10_547_400 * 0.01, 2);

        $this->assertEquals($expected, $jpEmployee);
    }

    /** @test */
    public function test_mar_2026_payroll_uses_2026_jp_ceiling(): void
    {
        $service = app(TaxCalculationService::class);
        $result = $service->calculateBpjs(15_000_000, '2026-03');

        $jpEmployee = $result['breakdown']['jp_employee'];
        $expected = round(11_086_300 * 0.01, 2);

        $this->assertEquals($expected, $jpEmployee);
    }

    /** @test */
    public function test_salary_below_all_jp_ceilings_uses_actual_salary(): void
    {
        $service = app(TaxCalculationService::class);

        foreach (['2025-02', '2025-03', '2026-02', '2026-03'] as $period) {
            $result = $service->calculateBpjs(5_000_000, $period);
            $jpEmployee = $result['breakdown']['jp_employee'];
            $expected = round(5_000_000 * 0.01, 2);
            $this->assertEquals($expected, $jpEmployee, "Period {$period} should use actual salary below cap");
        }
    }

    /** @test */
    public function test_salary_exactly_at_jp_ceiling_uses_full_ceiling(): void
    {
        $service = app(TaxCalculationService::class);

        $result = $service->calculateBpjs(10_547_400, '2025-03');
        $jpEmployee = $result['breakdown']['jp_employee'];
        $expected = round(10_547_400 * 0.01, 2);
        $this->assertEquals($expected, $jpEmployee);
    }

    /** @test */
    public function test_salary_one_rupiah_above_jp_ceiling_is_capped(): void
    {
        $service = app(TaxCalculationService::class);

        $result = $service->calculateBpjs(10_547_401, '2025-03');
        $jpEmployee = $result['breakdown']['jp_employee'];
        $expected = round(10_547_400 * 0.01, 2);
        $this->assertEquals($expected, $jpEmployee);
    }

    /** @test */
    public function test_different_periods_return_different_jp_amounts_for_same_salary(): void
    {
        $service = app(TaxCalculationService::class);
        $salary = 15_000_000;

        $feb2025 = $service->calculateBpjs($salary, '2025-02');
        $mar2025 = $service->calculateBpjs($salary, '2025-03');
        $mar2026 = $service->calculateBpjs($salary, '2026-03');

        // Each period's JP ceiling is higher, so JP employee contribution increases
        $this->assertLessThan(
            $mar2025['breakdown']['jp_employee'],
            $feb2025['breakdown']['jp_employee']
        );
        $this->assertLessThan(
            $mar2026['breakdown']['jp_employee'],
            $mar2025['breakdown']['jp_employee']
        );
    }

    /** @test */
    public function test_jht_jkk_jkm_kesehatan_unchanged_across_periods(): void
    {
        $service = app(TaxCalculationService::class);
        $salary = 8_000_000;

        $feb2025 = $service->calculateBpjs($salary, '2025-02');
        $mar2026 = $service->calculateBpjs($salary, '2026-03');

        $this->assertEquals($feb2025['breakdown']['jht_employee'], $mar2026['breakdown']['jht_employee']);
        $this->assertEquals($feb2025['breakdown']['jkk_employer'], $mar2026['breakdown']['jkk_employer']);
        $this->assertEquals($feb2025['breakdown']['jkm_employer'], $mar2026['breakdown']['jkm_employer']);
        $this->assertEquals($feb2025['breakdown']['bpjs_kesehatan_employee'], $mar2026['breakdown']['bpjs_kesehatan_employee']);
    }

    /** @test */
    public function test_historical_recalculation_uses_correct_period_rate(): void
    {
        $service = app(TaxCalculationService::class);
        $salary = 12_000_000;

        $jan2025 = $service->calculateBpjs($salary, '2025-01');
        $jpJan = $jan2025['breakdown']['jp_employee'];
        $this->assertEquals(round(10_042_300 * 0.01, 2), $jpJan);

        $jul2025 = $service->calculateBpjs($salary, '2025-07');
        $jpJul = $jul2025['breakdown']['jp_employee'];
        $this->assertEquals(round(10_547_400 * 0.01, 2), $jpJul);
    }

    /** @test */
    public function test_missing_period_configuration_uses_no_rate(): void
    {
        BpjsRate::query()->delete();

        BpjsRate::create([
            'component' => 'jp',
            'employee_rate' => 1.00,
            'employer_rate' => 2.00,
            'max_salary_base' => 11_086_300,
            'effective_date' => '2026-03-01',
            'valid_until' => null,
        ]);

        $service = app(TaxCalculationService::class);
        $result = $service->calculateBpjs(15_000_000, '2025-01');

        $this->assertArrayNotHasKey('jp_employee', $result['breakdown']);
    }

    /** @test */
    public function test_pph21_annual_uses_correct_jp_ceiling_for_period(): void
    {
        $this->seedPtkp();

        $service = app(TaxCalculationService::class);

        $feb2025 = $service->calculateMonthlyPph21(15_000_000, 'TK/0', true, '2025-02');
        $mar2026 = $service->calculateMonthlyPph21(15_000_000, 'TK/0', true, '2026-03');

        // Lower JP ceiling (Feb 2025) means smaller BPJS deduction, so higher
        // taxable income and higher PPh 21 than Mar 2026's larger ceiling.
        $this->assertGreaterThan($mar2026['pph21_monthly'], $feb2025['pph21_monthly']);
    }

    /** @test */
    public function test_for_period_returns_correct_components(): void
    {
        $rates = BpjsRate::forPeriod('2026-04');

        $this->assertCount(5, $rates);
        $this->assertArrayHasKey('jht', $rates->toArray());
        $this->assertArrayHasKey('jkk', $rates->toArray());
        $this->assertArrayHasKey('jkm', $rates->toArray());
        $this->assertArrayHasKey('jp', $rates->toArray());
        $this->assertArrayHasKey('bpjs_kesehatan', $rates->toArray());
    }

    /** @test */
    public function test_for_period_selects_latest_matching_rate_per_component(): void
    {
        $rates = BpjsRate::forPeriod('2025-06');
        $jpRate = $rates->get('jp');

        $this->assertNotNull($jpRate);
        $this->assertEquals(10_547_400, (float) $jpRate->max_salary_base);
    }

    /** @test */
    public function test_validate_bpjs_rates_with_specific_period(): void
    {
        $service = app(TaxCalculationService::class);

        $result = $service->validateBpjsRates('2025-06');

        $this->assertArrayHasKey('jp', $result['rates']);
        $jpRate = $result['rates']['jp'];
        $this->assertEquals(10_547_400, $jpRate['max_salary_base']);
    }

    private function seedRatesAndBrackets(): void
    {
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

        $rates = [
            ['component' => 'jht', 'employee_rate' => 2.00, 'employer_rate' => 3.70, 'max_salary_base' => null, 'effective_date' => '2020-01-01', 'valid_until' => null],
            ['component' => 'jkk', 'employee_rate' => 0.00, 'employer_rate' => 0.24, 'max_salary_base' => null, 'effective_date' => '2020-01-01', 'valid_until' => null],
            ['component' => 'jkm', 'employee_rate' => 0.00, 'employer_rate' => 0.30, 'max_salary_base' => null, 'effective_date' => '2020-01-01', 'valid_until' => null],
            ['component' => 'jp', 'employee_rate' => 1.00, 'employer_rate' => 2.00, 'max_salary_base' => 10_042_300, 'effective_date' => '2020-01-01', 'valid_until' => '2025-02-28'],
            ['component' => 'jp', 'employee_rate' => 1.00, 'employer_rate' => 2.00, 'max_salary_base' => 10_547_400, 'effective_date' => '2025-03-01', 'valid_until' => '2026-02-28'],
            ['component' => 'jp', 'employee_rate' => 1.00, 'employer_rate' => 2.00, 'max_salary_base' => 11_086_300, 'effective_date' => '2026-03-01', 'valid_until' => null],
            ['component' => 'bpjs_kesehatan', 'employee_rate' => 1.00, 'employer_rate' => 4.00, 'max_salary_base' => 12_000_000, 'effective_date' => '2020-01-01', 'valid_until' => null],
        ];
        foreach ($rates as $r) {
            BpjsRate::create($r);
        }
    }

    private function seedPtkp(): void
    {
        $ptkp = [
            ['status' => 'TK/0', 'annual_amount' => 54_000_000, 'description' => 'Tidak Kawin, 0 tanggungan'],
            ['status' => 'K/0', 'annual_amount' => 58_500_000, 'description' => 'Kawin, 0 tanggungan'],
        ];
        foreach ($ptkp as $p) {
            PtkpAmount::updateOrCreate(['status' => $p['status']], $p);
        }
    }
}
