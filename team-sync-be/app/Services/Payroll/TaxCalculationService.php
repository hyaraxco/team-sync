<?php

namespace App\Services\Payroll;

use App\Models\BpjsRate;
use App\Models\PtkpAmount;
use App\Models\TaxBracket;
use Illuminate\Support\Collection;

class TaxCalculationService
{
    protected ?Collection $taxBrackets = null;
    protected ?Collection $bpjsRates = null;
    protected ?Collection $ptkpAmounts = null;

    protected const JABATAN_RATE = 0.05;
    protected const JABATAN_MAX_ANNUAL = 6_000_000;
    protected const JABATAN_MAX_MONTHLY = 500_000;

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

        $jpBase = min($grossMonthly, (float) $jpRate?->max_salary_base ?: $grossMonthly);
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
            ]
        ];
    }
}
