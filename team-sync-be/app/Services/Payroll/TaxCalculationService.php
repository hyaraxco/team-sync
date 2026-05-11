<?php

namespace App\Services\Payroll;

use App\Models\BpjsRate;
use App\Models\PtkpAmount;
use App\Models\TaxBracket;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class TaxCalculationService
{
    protected ?Collection $taxBrackets = null;

    protected ?Collection $bpjsRates = null;

    protected ?Collection $ptkpAmounts = null;

    protected const JABATAN_RATE = 0.05;

    protected const JABATAN_MAX_ANNUAL = 6_000_000;

    protected const JABATAN_MAX_MONTHLY = 500_000;

    /**
     * Number of months after which BPJS rates are considered potentially outdated.
     */
    protected const BPJS_RATE_STALENESS_MONTHS = 12;

    public function __construct()
    {
        $this->loadConfig();
    }

    protected function loadConfig(): void
    {
        $this->taxBrackets = TaxBracket::orderBy('order')->get();
        $this->bpjsRates = BpjsRate::all()->keyBy('component');
        $this->ptkpAmounts = PtkpAmount::all()->keyBy('status');
    }

    /**
     * @return array{
     *     employee_share: float,
     *     employer_share: float,
     *     breakdown: array{
     *         jht_employee: float, jht_employer: float,
     *         jkk_employer: float, jkm_employer: float,
     *         jp_employee: float, jp_employer: float,
     *         bpjs_kes_employee: float, bpjs_kes_employer: float
     *     }
     * }
     */
    public function calculateBpjs(float $grossMonthly): array
    {
        $breakdown = [];
        $employeeShareTotal = 0.0;
        $employerShareTotal = 0.0;

        foreach ($this->bpjsRates as $component => $rate) {
            // Apply salary cap if configured
            $baseSalary = $grossMonthly;
            if ($rate->max_salary_base && $grossMonthly > $rate->max_salary_base) {
                $baseSalary = (float) $rate->max_salary_base;
            }

            $employeeAmount = round($baseSalary * ((float) $rate->employee_rate / 100), 2);
            $employerAmount = round($baseSalary * ((float) $rate->employer_rate / 100), 2);

            $breakdown["{$component}_employee"] = $employeeAmount;
            $breakdown["{$component}_employer"] = $employerAmount;

            $employeeShareTotal += $employeeAmount;
            $employerShareTotal += $employerAmount;
        }

        return [
            'employee_share' => $employeeShareTotal,
            'employer_share' => $employerShareTotal,
            'breakdown' => $breakdown,
        ];
    }

    /**
     * @return array{
     *    pph21_monthly: float,
     *    has_npwp: bool,
     *    ptkp_status: string,
     *    meta: array{
     *        gross_monthly: float,
     *        gross_annual: float,
     *        biaya_jabatan_annual: float,
     *        bpjs_deduction_annual: float,
     *        net_income_annual: float,
     *        ptkp_amount: float,
     *        pkp: float,
     *        pph21_annual: float
     *    }
     * }
     */
    public function calculateMonthlyPph21(float $grossMonthly, ?string $ptkpStatus, bool $hasNpwp): array
    {
        // 1. Annualize gross
        $grossAnnual = $grossMonthly * 12;

        // 2. Biaya Jabatan (5% max 6jt/year)
        $biayaJabatanAnnual = min($grossAnnual * static::JABATAN_RATE, static::JABATAN_MAX_ANNUAL);

        // 3. Deductible BPJS (JHT & JP employee portions)
        $jhtRate = $this->bpjsRates->get('jht');
        $jpRate = $this->bpjsRates->get('jp');

        $jhtBase = $grossMonthly;
        $jhtAmountMonthly = $jhtBase * ((float) $jhtRate?->employee_rate / 100);

        $jpBase = min($grossMonthly, $jpRate?->max_salary_base !== null ? (float) $jpRate->max_salary_base : $grossMonthly);
        $jpAmountMonthly = $jpBase * ((float) $jpRate?->employee_rate / 100);

        $bpjsDeductionAnnual = ($jhtAmountMonthly + $jpAmountMonthly) * 12;

        // 4. Net Income Annual
        $netIncomeAnnual = $grossAnnual - $biayaJabatanAnnual - $bpjsDeductionAnnual;

        // 5. PTKP
        $defaultPtkpStatus = 'TK/0';
        $ptkpStatusToUse = $ptkpStatus ?: $defaultPtkpStatus;
        $ptkpRecord = $this->ptkpAmounts->get($ptkpStatusToUse) ?? $this->ptkpAmounts->get($defaultPtkpStatus);
        $ptkpAmount = $ptkpRecord ? (float) $ptkpRecord->annual_amount : 54_000_000;

        // 6. PKP (Penghasilan Kena Pajak) - rounded down to nearest thousand
        $pkp = max(0, $netIncomeAnnual - $ptkpAmount);
        $pkpRoundedDown = floor($pkp / 1000) * 1000;

        // 7. Calculate PPh 21 Annual progressively
        $pph21Annual = 0.0;
        $remainingPkp = $pkpRoundedDown;

        foreach ($this->taxBrackets as $bracket) {
            if ($remainingPkp <= 0) {
                break;
            }

            $bracketMax = $bracket->max_income;
            $bracketRange = $bracketMax ? ((float) $bracketMax - (float) $bracket->min_income) : $remainingPkp;
            $taxableInBracket = min($remainingPkp, $bracketRange);

            $pph21Annual += $taxableInBracket * ((float) $bracket->rate / 100);
            $remainingPkp -= $taxableInBracket;
        }

        // 8. NPWP Surcharge (+20% if no NPWP)
        if (! $hasNpwp) {
            $pph21Annual *= 1.20;
        }

        $pph21Monthly = $pph21Annual / 12;

        return [
            'pph21_monthly' => round($pph21Monthly, 2),
            'has_npwp' => $hasNpwp,
            'ptkp_status' => $ptkpStatusToUse,
            'meta' => [
                'gross_monthly' => round($grossMonthly, 2),
                'gross_annual' => round($grossAnnual, 2),
                'biaya_jabatan_annual' => round($biayaJabatanAnnual, 2),
                'bpjs_deduction_annual' => round($bpjsDeductionAnnual, 2),
                'net_income_annual' => round($netIncomeAnnual, 2),
                'ptkp_amount' => round($ptkpAmount, 2),
                'pkp' => round($pkpRoundedDown, 2),
                'pph21_annual' => round($pph21Annual, 2),
            ],
        ];
    }

    /**
     * Validate BPJS rates for staleness and completeness.
     *
     * @return array{
     *     is_valid: bool,
     *     warnings: array<int, array{component: string, type: string, message: string}>,
     *     rates: array<string, array{
     *         component: string,
     *         effective_date: ?string,
     *         valid_until: ?string,
     *         is_expired: bool,
     *         is_potentially_outdated: bool
     *     }>
     * }
     */
    public function validateBpjsRates(): array
    {
        $rates = BpjsRate::all()->keyBy('component');
        $warnings = [];
        $rateDetails = [];
        $now = Carbon::now();

        $expectedComponents = ['jht', 'jkk', 'jkm', 'jp', 'bpjs_kesehatan'];
        $missingComponents = array_diff($expectedComponents, $rates->keys()->all());

        foreach ($missingComponents as $component) {
            $warnings[] = [
                'component' => $component,
                'type' => 'missing_component',
                'message' => sprintf('BPJS component "%s" is not configured in the database.', $component),
            ];
        }

        foreach ($rates as $component => $rate) {
            $effectiveDate = $rate->effective_date;
            $validUntil = $rate->valid_until;
            $isExpired = false;
            $isPotentiallyOutdated = false;

            // Check if rate has expired based on valid_until
            if ($validUntil && Carbon::parse($validUntil)->lt($now)) {
                $isExpired = true;
                $warnings[] = [
                    'component' => $component,
                    'type' => 'expired',
                    'message' => sprintf(
                        'BPJS rate for "%s" expired on %s. Please update with the latest government regulation.',
                        $rate->description ?? $component,
                        Carbon::parse($validUntil)->format('d M Y')
                    ),
                ];
            }

            // Check staleness: effective_date > BPJS_RATE_STALENESS_MONTHS ago
            if ($effectiveDate) {
                $monthsSinceEffective = Carbon::parse($effectiveDate)->diffInMonths($now);
                if ($monthsSinceEffective >= static::BPJS_RATE_STALENESS_MONTHS) {
                    $isPotentiallyOutdated = true;
                    $warnings[] = [
                        'component' => $component,
                        'type' => 'potentially_outdated',
                        'message' => sprintf(
                            'BPJS rate for "%s" has been effective since %s (%d months ago). Consider verifying against the latest regulation.',
                            $rate->description ?? $component,
                            Carbon::parse($effectiveDate)->format('d M Y'),
                            $monthsSinceEffective
                        ),
                    ];
                }
            } elseif (! $effectiveDate && ! $validUntil) {
                // No date fields set — fall back to updated_at staleness check
                $updatedAt = $rate->updated_at;
                if ($updatedAt) {
                    $monthsSinceUpdate = Carbon::parse($updatedAt)->diffInMonths($now);
                    if ($monthsSinceUpdate >= static::BPJS_RATE_STALENESS_MONTHS) {
                        $isPotentiallyOutdated = true;
                        $warnings[] = [
                            'component' => $component,
                            'type' => 'potentially_outdated',
                            'message' => sprintf(
                                'BPJS rate for "%s" was last updated %d months ago. Consider verifying against the latest regulation.',
                                $rate->description ?? $component,
                                $monthsSinceUpdate
                            ),
                        ];
                    }
                }
            }

            $rateDetails[$component] = [
                'component' => $component,
                'description' => $rate->description,
                'employee_rate' => (float) $rate->employee_rate,
                'employer_rate' => (float) $rate->employer_rate,
                'max_salary_base' => $rate->max_salary_base !== null ? (float) $rate->max_salary_base : null,
                'effective_date' => $effectiveDate ? Carbon::parse($effectiveDate)->toDateString() : null,
                'valid_until' => $validUntil ? Carbon::parse($validUntil)->toDateString() : null,
                'is_expired' => $isExpired,
                'is_potentially_outdated' => $isPotentiallyOutdated,
            ];
        }

        return [
            'is_valid' => empty($warnings),
            'warnings' => $warnings,
            'rates' => $rateDetails,
        ];
    }

    /**
     * Get BPJS cap warnings for a given gross salary.
     * Returns which salary caps are being applied and their impact.
     *
     * @return array{
     *     gross_salary: float,
     *     caps_applied: array<int, array{
     *         component: string,
     *         description: ?string,
     *         cap_amount: float,
     *         salary_exceeds_cap: bool,
     *         excess_amount: float,
     *         capped_base: float
     *     }>
     * }
     */
    public function getBpjsCapWarnings(float $grossSalary): array
    {
        $capsApplied = [];

        foreach ($this->bpjsRates as $component => $rate) {
            if ($rate->max_salary_base === null) {
                continue;
            }

            $capAmount = (float) $rate->max_salary_base;
            $exceedsCap = $grossSalary > $capAmount;

            $capsApplied[] = [
                'component' => $component,
                'description' => $rate->description,
                'cap_amount' => $capAmount,
                'salary_exceeds_cap' => $exceedsCap,
                'excess_amount' => $exceedsCap ? round($grossSalary - $capAmount, 2) : 0,
                'capped_base' => $exceedsCap ? $capAmount : $grossSalary,
            ];
        }

        return [
            'gross_salary' => round($grossSalary, 2),
            'caps_applied' => $capsApplied,
        ];
    }
}
