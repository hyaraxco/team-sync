<?php

namespace App\Services\Payroll;

use App\Models\StaffMemberProfile;
use App\Models\ThrPayroll;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ThrCalculationService
{
    public function __construct(
        private readonly TaxCalculationService $taxService
    ) {}

    /**
     * Calculate THR for a single employee.
     *
     * @return array{
     *     eligible: bool,
     *     tenure_months: int,
     *     proration_factor: float,
     *     gross_thr_amount: float,
     *     pph21_amount: float,
     *     net_thr_amount: float,
     *     tax_calculation_meta: array|null,
     *     ineligibility_reason: string|null
     * }
     */
    public function calculateForEmployee(
        StaffMemberProfile $employee,
        Carbon $paymentDate
    ): array {
        $jobInfo = $employee->jobInformation;

        if (! $jobInfo || ! $jobInfo->start_date) {
            return $this->ineligibleResult('No job information or start date found');
        }

        if ($jobInfo->status !== 'active') {
            return $this->ineligibleResult('Employee is not active');
        }

        $monthlySalary = (float) $jobInfo->monthly_salary;
        if ($monthlySalary <= 0) {
            return $this->ineligibleResult('Monthly salary is zero or not set');
        }

        // Calculate tenure in whole calendar months from start_date to payment_date.
        //
        // Indonesian THR (Permenaker 6/2016, masa kerja Pasal 3): tenure is counted
        // in completed calendar months from the date of hire. We must NOT use
        // Carbon::diffInMonths because it strictly compares day-of-month, which
        // misclassifies end-of-short-month hires (e.g. hired Feb 28, evaluated
        // May 30 → diffInMonths returns 2 instead of 3 even though three full
        // calendar months have elapsed).
        //
        // Approach: count year/month difference, then decrement only when the
        // payment day-of-month has not yet reached the start day-of-month, AND
        // the start date was not the last day of its own month. The
        // isLastOfMonth() guard handles end-of-Feb hires fairly when later
        // months have more days.
        $startDate = Carbon::parse($jobInfo->start_date);
        $tenureMonths = $this->calculateTenureMonths($startDate, $paymentDate);

        // Minimum 1 month tenure required
        if ($tenureMonths < ThrPayroll::MIN_TENURE_MONTHS) {
            return $this->ineligibleResult("Tenure less than {$tenureMonths} month(s). Minimum required: ".ThrPayroll::MIN_TENURE_MONTHS);
        }

        // Calculate proration factor
        $prorationFactor = $tenureMonths >= ThrPayroll::FULL_THR_TENURE_MONTHS
            ? 1.0
            : round($tenureMonths / ThrPayroll::FULL_THR_TENURE_MONTHS, 4);

        // Calculate gross THR
        $grossThr = round($monthlySalary * $prorationFactor, 0);

        // Calculate PPh 21 on THR (treated as irregular/bonus income)
        $taxResult = $this->calculateThrTax(
            $monthlySalary,
            $grossThr,
            $employee->ptkp_status,
            ! empty($employee->npwp)
        );

        $pph21Amount = $taxResult['pph21_on_thr'];
        $netThr = round($grossThr - $pph21Amount, 0);

        return [
            'eligible' => true,
            'tenure_months' => $tenureMonths,
            'proration_factor' => $prorationFactor,
            'gross_thr_amount' => $grossThr,
            'pph21_amount' => $pph21Amount,
            'net_thr_amount' => $netThr,
            'tax_calculation_meta' => $taxResult['meta'],
            'ineligibility_reason' => null,
        ];
    }

    /**
     * Calculate PPh 21 on THR using the annualization method for irregular income.
     *
     * Method: PPh21 on (regular + THR) annualized - PPh21 on regular annualized = PPh21 on THR
     *
     * @return array{pph21_on_thr: float, meta: array}
     */
    public function calculateThrTax(
        float $monthlySalary,
        float $thrAmount,
        ?string $ptkpStatus,
        bool $hasNpwp
    ): array {
        // Step 1: Calculate annual PPh21 on regular salary only
        $regularTax = $this->taxService->calculateMonthlyPph21($monthlySalary, $ptkpStatus, $hasNpwp);
        $regularAnnualPph21 = $regularTax['meta']['pph21_annual'];

        // Step 2: Calculate annual PPh21 on (regular salary + THR spread over 12 months)
        $salaryWithThr = $monthlySalary + ($thrAmount / 12);
        $withThrTax = $this->taxService->calculateMonthlyPph21($salaryWithThr, $ptkpStatus, $hasNpwp);
        $withThrAnnualPph21 = $withThrTax['meta']['pph21_annual'];

        // Step 3: Difference = tax attributable to THR
        $pph21OnThr = max(0, round($withThrAnnualPph21 - $regularAnnualPph21, 0));

        return [
            'pph21_on_thr' => $pph21OnThr,
            'meta' => [
                'method' => 'annualization_difference',
                'regular_annual_pph21' => round($regularAnnualPph21, 0),
                'with_thr_annual_pph21' => round($withThrAnnualPph21, 0),
                'thr_amount' => $thrAmount,
                'monthly_salary' => $monthlySalary,
                'ptkp_status' => $ptkpStatus,
                'has_npwp' => $hasNpwp,
            ],
        ];
    }

    /**
     * Calculate payment deadline (7 days before holiday).
     */
    public function calculatePaymentDeadline(Carbon $holidayDate): Carbon
    {
        return $holidayDate->copy()->subDays(ThrPayroll::MIN_DAYS_BEFORE_HOLIDAY);
    }

    /**
     * Get eligible employees for a specific religion event.
     *
     * IMPORTANT: Religion string matching is case-insensitive and trimmed.
     * Database values like 'Kristen', 'KRISTEN', ' kristen ' will all match
     * the 'kristen' key in RELIGION_EVENT_MAP. Employees with null religion
     * or a religion not in the map (e.g., 'protestan') are excluded — ensure
     * religion values in the database match the exact keys defined in
     * ThrPayroll::RELIGION_EVENT_MAP.
     */
    public function getEligibleEmployees(string $religionEvent): Collection
    {
        // Find religions that map to this event
        $religions = array_keys(
            array_filter(ThrPayroll::RELIGION_EVENT_MAP, fn ($event) => $event === $religionEvent)
        );

        return StaffMemberProfile::with(['user', 'jobInformation'])
            ->where(function (Builder $query) use ($religions) {
                foreach ($religions as $religion) {
                    $query->orWhereRaw('LOWER(TRIM(religion)) = ?', [$religion]);
                }
            })
            ->whereHas('jobInformation', function ($query) {
                $query->where('status', 'active');
            })
            ->get();
    }

    private function ineligibleResult(string $reason): array
    {
        return [
            'eligible' => false,
            'tenure_months' => 0,
            'proration_factor' => 0,
            'gross_thr_amount' => 0,
            'pph21_amount' => 0,
            'net_thr_amount' => 0,
            'tax_calculation_meta' => null,
            'ineligibility_reason' => $reason,
        ];
    }

    /**
     * Calculate tenure in completed calendar months between two dates.
     *
     * Algorithm:
     * 1. Compute raw year/month difference.
     * 2. Resolve the effective anniversary day in the target month. If the
     *    start date is the last day of its own month, clamp the anniversary
     *    day to the last day of the target month — this prevents end-of-Feb
     *    hires from being penalized when later months have more days, while
     *    still requiring the employee to reach the equivalent end-of-month
     *    boundary in months with fewer days (e.g. Feb 29 → Feb 28 next year).
     * 3. If the payment day-of-month is below the effective anniversary day,
     *    the in-progress month is not yet complete — decrement.
     *
     * Examples:
     * - Feb 28 2026 → May 30 2026 = 3 (Feb is last-of-month; May has 31 days, day 28 reached)
     * - Jan 31 2026 → Apr 30 2026 = 3 (Jan is last-of-month; Apr has 30 days, anniversary day clamps to 30)
     * - Jan 20 2026 → Apr 15 2026 = 2 (mid-month start, day 15 < 20)
     * - Feb 29 2024 → Feb 27 2025 = 11 (last-of-month leap-day start; Feb 2025 has 28 days, day 27 < 28)
     * - Feb 29 2024 → Feb 28 2025 = 12 (last-of-month leap-day start; reaches Feb 28 anniversary)
     */
    private function calculateTenureMonths(Carbon $startDate, Carbon $paymentDate): int
    {
        if ($paymentDate->lessThan($startDate)) {
            return 0;
        }

        $months = ($paymentDate->year - $startDate->year) * 12
            + ($paymentDate->month - $startDate->month);

        if ($startDate->isLastOfMonth()) {
            // Anniversary day in target month is the last day of target month
            // (or the original start day, whichever is smaller). Handles
            // 31st-of-month hires landing in 30-day months and end-of-Feb hires.
            $targetLastDay = $paymentDate->copy()->endOfMonth()->day;
            $effectiveAnniversaryDay = min($startDate->day, $targetLastDay);
        } else {
            $effectiveAnniversaryDay = $startDate->day;
        }

        if ($paymentDate->day < $effectiveAnniversaryDay) {
            $months--;
        }

        return max(0, $months);
    }
}
