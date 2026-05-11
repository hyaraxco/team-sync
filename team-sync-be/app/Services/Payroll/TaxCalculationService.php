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

    /**
     * TER 2024 — PTKP status to TER category mapping (PP 58/2023).
     */
    protected const TER_CATEGORY_MAP = [
        'TK/0' => 'A',
        'TK/1' => 'A',
        'K/0'  => 'A',
        'TK/2' => 'B',
        'TK/3' => 'B',
        'K/1'  => 'B',
        'K/2'  => 'B',
        'K/3'  => 'C',
    ];

    /**
     * TER 2024 Category A rates — [max_bruto, rate%].
     * Used for TK/0, TK/1, K/0.
     */
    protected const TER_RATES_A = [
        [5_400_000, 0.00],
        [5_650_000, 0.0025],
        [5_950_000, 0.005],
        [6_300_000, 0.0075],
        [6_750_000, 0.01],
        [7_500_000, 0.0125],
        [8_550_000, 0.015],
        [9_650_000, 0.0175],
        [10_050_000, 0.02],
        [10_350_000, 0.0225],
        [10_700_000, 0.025],
        [11_050_000, 0.03],
        [11_600_000, 0.035],
        [12_500_000, 0.04],
        [13_750_000, 0.05],
        [15_100_000, 0.06],
        [16_950_000, 0.07],
        [19_750_000, 0.08],
        [24_150_000, 0.09],
        [26_450_000, 0.10],
        [28_000_000, 0.11],
        [30_050_000, 0.12],
        [32_400_000, 0.13],
        [35_400_000, 0.14],
        [39_100_000, 0.15],
        [43_850_000, 0.16],
        [47_800_000, 0.17],
        [51_400_000, 0.18],
        [56_300_000, 0.19],
        [62_200_000, 0.20],
        [68_600_000, 0.21],
        [77_500_000, 0.22],
        [89_000_000, 0.23],
        [103_000_000, 0.24],
        [125_000_000, 0.25],
        [157_000_000, 0.26],
        [206_000_000, 0.27],
        [337_000_000, 0.28],
        [454_000_000, 0.29],
        [550_000_000, 0.30],
        [695_000_000, 0.31],
        [910_000_000, 0.32],
        [1_400_000_000, 0.33],
        [PHP_INT_MAX, 0.34],
    ];

    /**
     * TER 2024 Category B rates — [max_bruto, rate%].
     * Used for TK/2, TK/3, K/1, K/2.
     */
    protected const TER_RATES_B = [
        [6_200_000, 0.00],
        [6_500_000, 0.0025],
        [6_850_000, 0.005],
        [7_300_000, 0.0075],
        [9_200_000, 0.01],
        [10_750_000, 0.015],
        [11_250_000, 0.02],
        [11_600_000, 0.025],
        [12_600_000, 0.03],
        [13_600_000, 0.04],
        [14_950_000, 0.05],
        [16_400_000, 0.06],
        [18_450_000, 0.07],
        [20_600_000, 0.08],
        [24_750_000, 0.09],
        [27_700_000, 0.10],
        [29_350_000, 0.11],
        [31_450_000, 0.12],
        [33_950_000, 0.13],
        [37_400_000, 0.14],
        [41_200_000, 0.15],
        [45_800_000, 0.16],
        [50_600_000, 0.17],
        [55_400_000, 0.18],
        [60_400_000, 0.19],
        [66_900_000, 0.20],
        [74_500_000, 0.21],
        [83_200_000, 0.22],
        [95_600_000, 0.23],
        [110_000_000, 0.24],
        [134_000_000, 0.25],
        [169_000_000, 0.26],
        [221_000_000, 0.27],
        [374_000_000, 0.28],
        [463_000_000, 0.29],
        [561_000_000, 0.30],
        [697_000_000, 0.31],
        [910_000_000, 0.32],
        [1_400_000_000, 0.33],
        [PHP_INT_MAX, 0.34],
    ];

    /**
     * TER 2024 Category C rates — [max_bruto, rate%].
     * Used for K/3.
     */
    protected const TER_RATES_C = [
        [6_600_000, 0.00],
        [6_950_000, 0.0025],
        [7_350_000, 0.005],
        [7_800_000, 0.0075],
        [8_850_000, 0.01],
        [9_800_000, 0.0125],
        [10_950_000, 0.015],
        [11_200_000, 0.0175],
        [12_050_000, 0.02],
        [12_950_000, 0.03],
        [14_150_000, 0.04],
        [15_550_000, 0.05],
        [17_050_000, 0.06],
        [19_500_000, 0.07],
        [22_700_000, 0.08],
        [26_600_000, 0.09],
        [28_100_000, 0.10],
        [30_100_000, 0.11],
        [32_600_000, 0.12],
        [35_400_000, 0.13],
        [38_900_000, 0.14],
        [43_100_000, 0.15],
        [48_100_000, 0.16],
        [53_700_000, 0.17],
        [59_800_000, 0.18],
        [67_300_000, 0.19],
        [74_500_000, 0.20],
        [83_200_000, 0.21],
        [95_600_000, 0.22],
        [110_000_000, 0.23],
        [134_000_000, 0.24],
        [169_000_000, 0.25],
        [221_000_000, 0.26],
        [337_000_000, 0.27],
        [463_000_000, 0.28],
        [561_000_000, 0.29],
        [697_000_000, 0.30],
        [910_000_000, 0.31],
        [1_400_000_000, 0.32],
        [PHP_INT_MAX, 0.34],
    ];

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
     * Calculate monthly PPh 21 using TER (Tarif Efektif Rata-rata) 2024 method (PP 58/2023).
     *
     * TER is applied directly on gross monthly income — PTKP and typical deductions
     * (biaya jabatan, pension contributions) are already factored into the rate table.
     * Use this for Jan–Nov payroll. For December, use calculateAnnualizedPph21() for true-up.
     *
     * @return array{
     *     pph21_monthly: float,
     *     has_npwp: bool,
     *     ptkp_status: string,
     *     ter_category: string,
     *     ter_rate: float,
     *     meta: array{
     *         gross_monthly: float,
     *         ter_category: string,
     *         ter_rate: float,
     *         ter_rate_pct: string
     *     }
     * }
     */
    public function calculateMonthlyTer(float $grossMonthly, ?string $ptkpStatus, bool $hasNpwp): array
    {
        $defaultPtkpStatus = 'TK/0';
        $ptkpStatusToUse = $ptkpStatus ?: $defaultPtkpStatus;

        $category = static::TER_CATEGORY_MAP[$ptkpStatusToUse] ?? 'A';
        $terRate = $this->lookupTerRate($category, $grossMonthly);

        $pph21Monthly = $grossMonthly * $terRate;

        // NPWP surcharge: +20% if no NPWP
        if (! $hasNpwp) {
            $pph21Monthly *= 1.20;
        }

        return [
            'pph21_monthly' => round($pph21Monthly, 0),
            'has_npwp' => $hasNpwp,
            'ptkp_status' => $ptkpStatusToUse,
            'ter_category' => $category,
            'ter_rate' => $terRate,
            'meta' => [
                'gross_monthly' => round($grossMonthly, 0),
                'ter_category' => $category,
                'ter_rate' => $terRate,
                'ter_rate_pct' => round($terRate * 100, 2).'%',
            ],
        ];
    }

    /**
     * Annualized PPh 21 using Pasal 17 progressive brackets.
     * Use for December year-end true-up or when annual method is explicitly required.
     *
     * @return array Same structure as calculateMonthlyPph21
     */
    public function calculateAnnualizedPph21(float $grossMonthly, ?string $ptkpStatus, bool $hasNpwp): array
    {
        return $this->calculateMonthlyPph21($grossMonthly, $ptkpStatus, $hasNpwp);
    }

    /**
     * Look up the TER rate for a given category and gross monthly income.
     */
    protected function lookupTerRate(string $category, float $grossMonthly): float
    {
        $table = match ($category) {
            'B' => static::TER_RATES_B,
            'C' => static::TER_RATES_C,
            default => static::TER_RATES_A,
        };

        foreach ($table as [$maxBruto, $rate]) {
            if ($grossMonthly <= $maxBruto) {
                return $rate;
            }
        }

        // Fallback — should never reach due to PHP_INT_MAX sentinel
        return end($table)[1];
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
