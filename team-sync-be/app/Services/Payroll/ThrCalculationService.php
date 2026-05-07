<?php

namespace App\Services\Payroll;

use App\Models\StaffMemberProfile;
use App\Models\ThrPayroll;
use Carbon\Carbon;
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

        // Calculate tenure in months from start_date to payment_date
        $startDate = Carbon::parse($jobInfo->start_date);
        $tenureMonths = (int) $startDate->diffInMonths($paymentDate);

        // Minimum 1 month tenure required
        if ($tenureMonths < ThrPayroll::MIN_TENURE_MONTHS) {
            return $this->ineligibleResult("Tenure less than {$tenureMonths} month(s). Minimum required: ".ThrPayroll::MIN_TENURE_MONTHS);
        }

        // Calculate proration factor
        $prorationFactor = $tenureMonths >= ThrPayroll::FULL_THR_TENURE_MONTHS
            ? 1.0
            : round($tenureMonths / ThrPayroll::FULL_THR_TENURE_MONTHS, 4);

        // Calculate gross THR
        $grossThr = round($monthlySalary * $prorationFactor, 2);

        // Calculate PPh 21 on THR (treated as irregular/bonus income)
        $taxResult = $this->calculateThrTax(
            $monthlySalary,
            $grossThr,
            $employee->ptkp_status,
            ! empty($employee->npwp)
        );

        $pph21Amount = $taxResult['pph21_on_thr'];
        $netThr = round($grossThr - $pph21Amount, 2);

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
        $pph21OnThr = max(0, round($withThrAnnualPph21 - $regularAnnualPph21, 2));

        return [
            'pph21_on_thr' => $pph21OnThr,
            'meta' => [
                'method' => 'annualization_difference',
                'regular_annual_pph21' => round($regularAnnualPph21, 2),
                'with_thr_annual_pph21' => round($withThrAnnualPph21, 2),
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
     */
    public function getEligibleEmployees(string $religionEvent): Collection
    {
        // Find religions that map to this event
        $religions = array_keys(
            array_filter(ThrPayroll::RELIGION_EVENT_MAP, fn ($event) => $event === $religionEvent)
        );

        return StaffMemberProfile::with(['user', 'jobInformation'])
            ->whereIn('religion', $religions)
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
}
