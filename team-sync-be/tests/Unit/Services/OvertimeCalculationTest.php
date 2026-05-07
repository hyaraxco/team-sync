<?php

namespace Tests\Unit\Services;

use App\Services\Payroll\OvertimeCalculationService;
use Illuminate\Support\Collection;
use Tests\TestCase;

class OvertimeCalculationTest extends TestCase
{
    private OvertimeCalculationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new OvertimeCalculationService;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Hourly Rate Tests
    // ─────────────────────────────────────────────────────────────────────────

    public function test_hourly_rate_equals_salary_divided_by_173(): void
    {
        $salary = 10_000_000;
        $hourlyRate = $this->service->getHourlyRate($salary);

        $this->assertEqualsWithDelta(57803.47, $hourlyRate, 0.01);
        $this->assertEqualsWithDelta($salary / 173, $hourlyRate, 0.001);
    }

    public function test_hourly_rate_with_different_salaries(): void
    {
        // UMR Jakarta ~5jt
        $this->assertEqualsWithDelta(5_000_000 / 173, $this->service->getHourlyRate(5_000_000), 0.001);

        // Higher salary
        $this->assertEqualsWithDelta(20_000_000 / 173, $this->service->getHourlyRate(20_000_000), 0.001);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Workday Overtime Tests
    // ─────────────────────────────────────────────────────────────────────────

    public function test_workday_overtime_1_hour_uses_1_5x_multiplier(): void
    {
        $hourlyRate = 10_000_000 / 173; // ~57,803.47

        $result = $this->service->calculateWorkdayOvertime($hourlyRate, 1.0);

        // 1h × 1.5 × hourlyRate
        $expected = 1.5 * $hourlyRate;
        $this->assertEqualsWithDelta($expected, $result, 0.01);
    }

    public function test_workday_overtime_2_hours_uses_1_5x_plus_2x(): void
    {
        $hourlyRate = 10_000_000 / 173;

        $result = $this->service->calculateWorkdayOvertime($hourlyRate, 2.0);

        // 1h × 1.5 × rate + 1h × 2 × rate
        $expected = (1.5 * $hourlyRate) + (2.0 * $hourlyRate);
        $this->assertEqualsWithDelta($expected, $result, 0.01);
    }

    public function test_workday_overtime_3_hours_uses_1_5x_plus_2x_plus_2x(): void
    {
        $hourlyRate = 10_000_000 / 173;

        $result = $this->service->calculateWorkdayOvertime($hourlyRate, 3.0);

        // 1h × 1.5 × rate + 2h × 2 × rate
        $expected = (1.5 * $hourlyRate) + (2.0 * 2.0 * $hourlyRate);
        $this->assertEqualsWithDelta($expected, $result, 0.01);
    }

    public function test_workday_overtime_4_hours_max(): void
    {
        $hourlyRate = 10_000_000 / 173;

        $result = $this->service->calculateWorkdayOvertime($hourlyRate, 4.0);

        // 1h × 1.5 × rate + 3h × 2 × rate
        $expected = (1.5 * $hourlyRate) + (3.0 * 2.0 * $hourlyRate);
        $this->assertEqualsWithDelta($expected, $result, 0.01);
    }

    public function test_workday_overtime_zero_hours_returns_zero(): void
    {
        $hourlyRate = 10_000_000 / 173;

        $result = $this->service->calculateWorkdayOvertime($hourlyRate, 0);

        $this->assertEquals(0.0, $result);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Weekend Overtime Tests
    // ─────────────────────────────────────────────────────────────────────────

    public function test_weekend_overtime_first_7_hours_at_2x(): void
    {
        $hourlyRate = 10_000_000 / 173;

        $result = $this->service->calculateWeekendOvertime($hourlyRate, 5.0);

        // 5h × 2 × rate
        $expected = 5.0 * 2.0 * $hourlyRate;
        $this->assertEqualsWithDelta($expected, $result, 0.01);
    }

    public function test_weekend_overtime_7_hours_at_2x(): void
    {
        $hourlyRate = 10_000_000 / 173;

        $result = $this->service->calculateWeekendOvertime($hourlyRate, 7.0);

        // 7h × 2 × rate
        $expected = 7.0 * 2.0 * $hourlyRate;
        $this->assertEqualsWithDelta($expected, $result, 0.01);
    }

    public function test_weekend_overtime_8th_hour_at_3x(): void
    {
        $hourlyRate = 10_000_000 / 173;

        $result = $this->service->calculateWeekendOvertime($hourlyRate, 8.0);

        // 7h × 2 × rate + 1h × 3 × rate
        $expected = (7.0 * 2.0 * $hourlyRate) + (1.0 * 3.0 * $hourlyRate);
        $this->assertEqualsWithDelta($expected, $result, 0.01);
    }

    public function test_weekend_overtime_9th_hour_onwards_at_4x(): void
    {
        $hourlyRate = 10_000_000 / 173;

        $result = $this->service->calculateWeekendOvertime($hourlyRate, 10.0);

        // 7h × 2 × rate + 1h × 3 × rate + 2h × 4 × rate
        $expected = (7.0 * 2.0 * $hourlyRate) + (1.0 * 3.0 * $hourlyRate) + (2.0 * 4.0 * $hourlyRate);
        $this->assertEqualsWithDelta($expected, $result, 0.01);
    }

    public function test_weekend_overtime_zero_hours_returns_zero(): void
    {
        $hourlyRate = 10_000_000 / 173;

        $result = $this->service->calculateWeekendOvertime($hourlyRate, 0);

        $this->assertEquals(0.0, $result);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Holiday Overtime Tests
    // ─────────────────────────────────────────────────────────────────────────

    public function test_holiday_overtime_uses_same_structure_as_weekend(): void
    {
        $hourlyRate = 10_000_000 / 173;

        $weekendResult = $this->service->calculateWeekendOvertime($hourlyRate, 8.0);
        $holidayResult = $this->service->calculateHolidayOvertime($hourlyRate, 8.0);

        $this->assertEquals($weekendResult, $holidayResult);
    }

    public function test_holiday_overtime_9_hours(): void
    {
        $hourlyRate = 10_000_000 / 173;

        $result = $this->service->calculateHolidayOvertime($hourlyRate, 9.0);

        // 7h × 2 × rate + 1h × 3 × rate + 1h × 4 × rate
        $expected = (7.0 * 2.0 * $hourlyRate) + (1.0 * 3.0 * $hourlyRate) + (1.0 * 4.0 * $hourlyRate);
        $this->assertEqualsWithDelta($expected, $result, 0.01);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Real Salary Integration Tests
    // ─────────────────────────────────────────────────────────────────────────

    public function test_real_salary_10_million_workday_2_hours(): void
    {
        $salary = 10_000_000;
        $hourlyRate = $salary / 173; // 57,803.47

        $result = $this->service->calculateWorkdayOvertime($hourlyRate, 2.0);

        // 1h × 1.5 × 57803.47 + 1h × 2 × 57803.47
        // = 86,705.20 + 115,606.94 = 202,312.14
        $expected = (1.5 * $hourlyRate) + (2.0 * $hourlyRate);
        $this->assertEqualsWithDelta($expected, $result, 0.01);
        $this->assertGreaterThan(200_000, $result);
        $this->assertLessThan(210_000, $result);
    }

    public function test_real_salary_10_million_weekend_8_hours(): void
    {
        $salary = 10_000_000;
        $hourlyRate = $salary / 173;

        $result = $this->service->calculateWeekendOvertime($hourlyRate, 8.0);

        // 7h × 2 × 57803.47 + 1h × 3 × 57803.47
        // = 809,248.55 + 173,410.40 = 982,658.96
        $expected = (7.0 * 2.0 * $hourlyRate) + (1.0 * 3.0 * $hourlyRate);
        $this->assertEqualsWithDelta($expected, $result, 0.01);
        $this->assertGreaterThan(980_000, $result);
        $this->assertLessThan(990_000, $result);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // calculateOvertimePay Integration Tests
    // ─────────────────────────────────────────────────────────────────────────

    public function test_calculate_overtime_pay_with_multiple_records(): void
    {
        $salary = 10_000_000;
        $hourlyRate = $salary / 173;

        // Create mock records as objects
        $records = new Collection([
            (object) ['hours' => 2.0, 'overtime_type' => 'workday', 'date' => '2026-05-01'],
            (object) ['hours' => 8.0, 'overtime_type' => 'weekend', 'date' => '2026-05-03'],
        ]);

        $result = $this->service->calculateOvertimePay($salary, $records);

        $this->assertArrayHasKey('total_amount', $result);
        $this->assertArrayHasKey('breakdown', $result);
        $this->assertArrayHasKey('total_hours', $result);

        // Hours are capped at 4.0 per record (Indonesian labor law max)
        $this->assertEquals(6.0, $result['total_hours']);
        $this->assertCount(2, $result['breakdown']);

        // Verify individual amounts (weekend record capped from 8.0 to 4.0)
        $workdayAmount = $this->service->calculateWorkdayOvertime($hourlyRate, 2.0);
        $weekendAmount = $this->service->calculateWeekendOvertime($hourlyRate, 4.0);
        $expectedTotal = round($workdayAmount + $weekendAmount, 2);

        $this->assertEqualsWithDelta($expectedTotal, $result['total_amount'], 0.01);
    }

    public function test_calculate_overtime_pay_with_empty_records(): void
    {
        $result = $this->service->calculateOvertimePay(10_000_000, new Collection);

        $this->assertEquals(0.0, $result['total_amount']);
        $this->assertEquals(0.0, $result['total_hours']);
        $this->assertEmpty($result['breakdown']);
    }

    public function test_breakdown_contains_correct_fields(): void
    {
        $records = new Collection([
            (object) ['hours' => 1.5, 'overtime_type' => 'workday', 'date' => '2026-05-01'],
        ]);

        $result = $this->service->calculateOvertimePay(10_000_000, $records);

        $breakdown = $result['breakdown'][0];
        $this->assertArrayHasKey('date', $breakdown);
        $this->assertArrayHasKey('hours', $breakdown);
        $this->assertArrayHasKey('type', $breakdown);
        $this->assertArrayHasKey('multiplier_applied', $breakdown);
        $this->assertArrayHasKey('amount', $breakdown);

        $this->assertEquals('2026-05-01', $breakdown['date']);
        $this->assertEquals(1.5, $breakdown['hours']);
        $this->assertEquals('workday', $breakdown['type']);
    }

    public function test_fractional_hours_calculated_correctly(): void
    {
        $hourlyRate = 10_000_000 / 173;

        // 0.5 hours workday = 0.5 × 1.5 × rate (still in first hour)
        $result = $this->service->calculateWorkdayOvertime($hourlyRate, 0.5);
        $expected = 0.5 * 1.5 * $hourlyRate;
        $this->assertEqualsWithDelta($expected, $result, 0.01);

        // 1.5 hours workday = 1h × 1.5 × rate + 0.5h × 2 × rate
        $result = $this->service->calculateWorkdayOvertime($hourlyRate, 1.5);
        $expected = (1.0 * 1.5 * $hourlyRate) + (0.5 * 2.0 * $hourlyRate);
        $this->assertEqualsWithDelta($expected, $result, 0.01);
    }
}
