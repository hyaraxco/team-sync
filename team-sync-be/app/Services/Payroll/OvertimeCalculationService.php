<?php

namespace App\Services\Payroll;

use Illuminate\Support\Collection;

class OvertimeCalculationService
{
    /**
     * Indonesian overtime divisor: monthly salary ÷ 173 = hourly rate.
     */
    protected const HOURLY_DIVISOR = 173;

    /**
     * Calculate total overtime pay for a collection of approved overtime records.
     *
     * @param  float  $monthlySalary  Employee's monthly salary
     * @param  Collection  $overtimeRecords  Collection of OvertimeRecord models (approved)
     * @return array{total_amount: float, breakdown: array, total_hours: float}
     */
    public function calculateOvertimePay(float $monthlySalary, Collection $overtimeRecords): array
    {
        $hourlyRate = $this->getHourlyRate($monthlySalary);
        $totalAmount = 0.0;
        $totalHours = 0.0;
        $breakdown = [];

        foreach ($overtimeRecords as $record) {
            $hours = (float) $record->hours;
            $type = $record->overtime_type;

            $amount = match ($type) {
                'workday' => $this->calculateWorkdayOvertime($hourlyRate, $hours),
                'weekend' => $this->calculateWeekendOvertime($hourlyRate, $hours),
                'holiday' => $this->calculateHolidayOvertime($hourlyRate, $hours),
                default => 0.0,
            };

            $totalAmount += $amount;
            $totalHours += $hours;

            $dateValue = $record->date;
            $dateString = $dateValue instanceof \DateTimeInterface
                ? $dateValue->format('Y-m-d')
                : (string) $dateValue;

            $breakdown[] = [
                'date' => $dateString,
                'hours' => $hours,
                'type' => $type,
                'multiplier_applied' => $this->getMultiplierDescription($type, $hours),
                'amount' => round($amount, 2),
            ];
        }

        return [
            'total_amount' => round($totalAmount, 2),
            'breakdown' => $breakdown,
            'total_hours' => round($totalHours, 2),
        ];
    }

    /**
     * Calculate hourly rate from monthly salary.
     * Formula: Monthly Salary ÷ 173
     */
    public function getHourlyRate(float $monthlySalary): float
    {
        return $monthlySalary / self::HOURLY_DIVISOR;
    }

    /**
     * Calculate workday overtime pay.
     * - First hour: 1.5× hourly rate
     * - Subsequent hours: 2× hourly rate
     */
    public function calculateWorkdayOvertime(float $hourlyRate, float $hours): float
    {
        if ($hours <= 0) {
            return 0.0;
        }

        $firstHour = min($hours, 1.0);
        $subsequentHours = max(0, $hours - 1.0);

        $amount = ($firstHour * 1.5 * $hourlyRate)
            + ($subsequentHours * 2.0 * $hourlyRate);

        return round($amount, 2);
    }

    /**
     * Calculate weekend overtime pay (5-day work week).
     * - First 7 hours: 2× hourly rate
     * - 8th hour: 3× hourly rate
     * - 9th hour onwards: 4× hourly rate
     */
    public function calculateWeekendOvertime(float $hourlyRate, float $hours): float
    {
        if ($hours <= 0) {
            return 0.0;
        }

        $amount = 0.0;

        // First 7 hours at 2×
        $firstBlock = min($hours, 7.0);
        $amount += $firstBlock * 2.0 * $hourlyRate;

        // 8th hour at 3×
        if ($hours > 7.0) {
            $eighthHour = min($hours - 7.0, 1.0);
            $amount += $eighthHour * 3.0 * $hourlyRate;
        }

        // 9th hour onwards at 4×
        if ($hours > 8.0) {
            $remainingHours = $hours - 8.0;
            $amount += $remainingHours * 4.0 * $hourlyRate;
        }

        return round($amount, 2);
    }

    /**
     * Calculate holiday overtime pay.
     * Same structure as weekend overtime:
     * - First 7 hours: 2× hourly rate
     * - 8th hour: 3× hourly rate
     * - 9th hour onwards: 4× hourly rate
     */
    public function calculateHolidayOvertime(float $hourlyRate, float $hours): float
    {
        // Holiday overtime uses the same multiplier structure as weekend
        return $this->calculateWeekendOvertime($hourlyRate, $hours);
    }

    /**
     * Get a human-readable description of multipliers applied.
     */
    private function getMultiplierDescription(string $type, float $hours): string
    {
        if ($type === 'workday') {
            if ($hours <= 1.0) {
                return '1.5x';
            }

            return '1.5x (1h) + 2x (' . round($hours - 1, 2) . 'h)';
        }

        // Weekend/Holiday
        if ($hours <= 7.0) {
            return '2x';
        }

        if ($hours <= 8.0) {
            return '2x (7h) + 3x (' . round($hours - 7, 2) . 'h)';
        }

        return '2x (7h) + 3x (1h) + 4x (' . round($hours - 8, 2) . 'h)';
    }
}
