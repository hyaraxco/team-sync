<?php

namespace Tests\Unit\Services\Payroll;

use App\DTOs\BpjsPayrollInput;
use App\Exceptions\PayrollStateException;
use Database\Seeders\BpjsRateSeeder;
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
        $this->seed(BpjsRateSeeder::class);
    }

    /** @test */
    public function test_feb_2025_payroll_uses_old_jp_ceiling(): void
    {
        $service = app(TaxCalculationService::class);
        $result = $service->calculateBpjs(new BpjsPayrollInput(15_000_000, 0, 1, 3_000_000), '2025-02');

        $jpEmployee = $result['breakdown']['jp_employee'];
        $expected = round(10_042_300 * 0.01, 2);

        $this->assertEquals($expected, $jpEmployee);
    }

    /** @test */
    public function test_mar_2025_payroll_uses_new_jp_ceiling(): void
    {
        $service = app(TaxCalculationService::class);
        $result = $service->calculateBpjs(new BpjsPayrollInput(15_000_000, 0, 1, 3_000_000), '2025-03');

        $jpEmployee = $result['breakdown']['jp_employee'];
        $expected = round(10_547_400 * 0.01, 2);

        $this->assertEquals($expected, $jpEmployee);
    }

    /** @test */
    public function test_feb_2026_payroll_uses_2025_jp_ceiling(): void
    {
        $service = app(TaxCalculationService::class);
        $result = $service->calculateBpjs(new BpjsPayrollInput(15_000_000, 0, 1, 3_000_000), '2026-02');

        $jpEmployee = $result['breakdown']['jp_employee'];
        $expected = round(10_547_400 * 0.01, 2);

        $this->assertEquals($expected, $jpEmployee);
    }

    /** @test */
    public function test_mar_2026_payroll_uses_2026_jp_ceiling(): void
    {
        $service = app(TaxCalculationService::class);
        $result = $service->calculateBpjs(new BpjsPayrollInput(15_000_000, 0, 1, 3_000_000), '2026-03');

        $jpEmployee = $result['breakdown']['jp_employee'];
        $expected = round(11_086_300 * 0.01, 2);

        $this->assertEquals($expected, $jpEmployee);
    }

    /** @test */
    public function test_salary_below_all_jp_ceilings_uses_actual_salary(): void
    {
        $service = app(TaxCalculationService::class);

        foreach (['2025-02', '2025-03', '2026-02', '2026-03'] as $period) {
            $result = $service->calculateBpjs(new BpjsPayrollInput(5_000_000, 0, 1, 3_000_000), $period);
            $jpEmployee = $result['breakdown']['jp_employee'];
            $expected = round(5_000_000 * 0.01, 2);
            $this->assertEquals($expected, $jpEmployee, "Period {$period} should use actual salary below cap");
        }
    }

    /** @test */
    public function test_salary_exactly_at_jp_ceiling_uses_full_ceiling(): void
    {
        $service = app(TaxCalculationService::class);

        $result = $service->calculateBpjs(new BpjsPayrollInput(10_547_400, 0, 1, 3_000_000), '2025-03');
        $jpEmployee = $result['breakdown']['jp_employee'];
        $expected = round(10_547_400 * 0.01, 2);
        $this->assertEquals($expected, $jpEmployee);
    }

    /** @test */
    public function test_salary_one_rupiah_above_jp_ceiling_is_capped(): void
    {
        $service = app(TaxCalculationService::class);

        $result = $service->calculateBpjs(new BpjsPayrollInput(10_547_401, 0, 1, 3_000_000), '2025-03');
        $jpEmployee = $result['breakdown']['jp_employee'];
        $expected = round(10_547_400 * 0.01, 2);
        $this->assertEquals($expected, $jpEmployee);
    }

    /** @test */
    public function test_different_periods_return_different_jp_amounts_for_same_salary(): void
    {
        $service = app(TaxCalculationService::class);
        $input = new BpjsPayrollInput(15_000_000, 0, 1, 3_000_000);
        $feb2025 = $service->calculateBpjs($input, '2025-02');
        $mar2025 = $service->calculateBpjs($input, '2025-03');
        $mar2026 = $service->calculateBpjs($input, '2026-03');

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
        $input = new BpjsPayrollInput(8_000_000, 0, 1, 3_000_000);
        $feb2025 = $service->calculateBpjs($input, '2025-02');
        $mar2026 = $service->calculateBpjs($input, '2026-03');

        $this->assertEquals($feb2025['breakdown']['jht_employee'], $mar2026['breakdown']['jht_employee']);
        $this->assertEquals($feb2025['breakdown']['jkk_employer'], $mar2026['breakdown']['jkk_employer']);
        $this->assertEquals($feb2025['breakdown']['jkm_employer'], $mar2026['breakdown']['jkm_employer']);
        $this->assertEquals($feb2025['breakdown']['bpjs_kesehatan_employee'], $mar2026['breakdown']['bpjs_kesehatan_employee']);
    }

    /** @test */
    public function test_historical_recalculation_uses_correct_period_rate(): void
    {
        $service = app(TaxCalculationService::class);
        $input = new BpjsPayrollInput(12_000_000, 0, 1, 3_000_000);
        $jan2025 = $service->calculateBpjs($input, '2025-01');
        $jpJan = $jan2025['breakdown']['jp_employee'];
        $this->assertEquals(round(10_042_300 * 0.01, 2), $jpJan);

        $jul2025 = $service->calculateBpjs($input, '2025-07');
        $jpJul = $jul2025['breakdown']['jp_employee'];
        $this->assertEquals(round(10_547_400 * 0.01, 2), $jpJul);
    }

    /** @test */
    public function test_missing_effective_rate_blocks_calculation(): void
    {
        BpjsRate::query()->where('component', 'jp')->whereDate('effective_date', '2026-03-01')->delete();

        $this->expectException(PayrollStateException::class);
        $this->expectExceptionMessage('Required BPJS rate jp is missing for 2026-04.');
        app(TaxCalculationService::class)->calculateBpjs(new BpjsPayrollInput(15_000_000, 0, 1, 3_000_000), '2026-04');
    }

    public function test_class_five_cannot_use_a_class_one_rate(): void
    {
        BpjsRate::query()->where('component', 'jkk')->where('risk_class', 5)->update(['employer_rate' => 0.24]);

        $this->expectException(PayrollStateException::class);
        $this->expectExceptionMessage('BPJS rate jkk is not a supported ordinary rate for 2026-04.');
        app(TaxCalculationService::class)->calculateBpjs(new BpjsPayrollInput(5_000_000, 0, 5, 3_000_000), '2026-04');
    }

    /** @test */
    public function test_tax_helper_uses_supplied_employee_pension_instead_of_gross(): void
    {
        $this->seedPtkp();
        $result = app(TaxCalculationService::class)->calculateMonthlyPph21(15_000_000, 'TK/0', true, 225_000);

        $this->assertEquals(2_700_000, $result['meta']['bpjs_deduction_annual']);
    }

    /** @test */
    public function test_for_period_returns_correct_components(): void
    {
        $rates = BpjsRate::forPeriod('2026-04', 1);

        $this->assertCount(5, $rates);
        $this->assertArrayHasKey('jht', $rates->toArray());
        $this->assertArrayHasKey('jkk', $rates->toArray());
        $this->assertArrayHasKey('jkm', $rates->toArray());
        $this->assertArrayHasKey('jp', $rates->toArray());
        $this->assertArrayHasKey('bpjs_kesehatan', $rates->toArray());
    }

    public function test_ppu_uses_all_five_assigned_jkk_rates_and_kesehatan_floor_without_floors_for_other_programs(): void
    {
        $service = app(TaxCalculationService::class);
        foreach ([1 => 9_600, 2 => 21_600, 3 => 35_600, 4 => 50_800, 5 => 69_600] as $class => $expectedJkk) {
            $bpjs = $service->calculateBpjs(new BpjsPayrollInput(3_500_000, 500_000, $class, 5_000_000), '2026-04')['breakdown'];
            $this->assertEquals($expectedJkk, $bpjs['jkk_employer']);
            $this->assertEquals(0, $bpjs['jkk_employee']);
            $this->assertEquals(80_000, $bpjs['jht_employee']);
            $this->assertEquals(40_000, $bpjs['jp_employee']);
            $this->assertEquals(12_000, $bpjs['jkm_employer']);
            $this->assertEquals(50_000, $bpjs['bpjs_kesehatan_employee']);
            $this->assertEquals(200_000, $bpjs['bpjs_kesehatan_employer']);
            $this->assertArrayNotHasKey('jkp_employee', $bpjs);
        }
    }

    public function test_ppu_kesehatan_clamps_only_outside_floor_and_ceiling(): void
    {
        $service = app(TaxCalculationService::class);
        foreach ([4_999_999 => 50_000, 5_000_000 => 50_000, 5_000_001 => 50_000, 11_999_999 => 120_000, 12_000_000 => 120_000, 12_000_001 => 120_000] as $salary => $amount) {
            $bpjs = $service->calculateBpjs(new BpjsPayrollInput($salary, 0, 1, 5_000_000), '2026-04')['breakdown'];
            $this->assertEquals($amount, $bpjs['bpjs_kesehatan_employee']);
        }
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
            ['min_income' => 60_000_000, 'max_income' => 250_000_000, 'rate' => 15.00, 'order' => 2],
            ['min_income' => 250_000_000, 'max_income' => 500_000_000, 'rate' => 25.00, 'order' => 3],
            ['min_income' => 500_000_000, 'max_income' => 5_000_000_000, 'rate' => 30.00, 'order' => 4],
            ['min_income' => 5_000_000_000, 'max_income' => null, 'rate' => 35.00, 'order' => 5],
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
