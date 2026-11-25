<?php

namespace App\Services\Payroll;

use App\Enums\PayrollStatus;
use App\Exceptions\PayrollStateException;
use App\Models\Payroll;
use App\Models\PayrollDetail;
use App\Models\PayrollSetting;
use App\Models\PayrollTaxYear;
use App\Models\ThrPayroll;
use App\Models\ThrPayrollDetail;
use App\Support\PayrollTaxMetadata;
use Illuminate\Support\Facades\DB;

class PayrollTaxIntegrationService
{
    public function __construct(
        private readonly TaxCalculationService $calculator,
        private readonly PayrollTaxPeriodService $periods,
        private readonly PayrollApprovalService $approvals,
    ) {}

    public function transaction(callable $operation): mixed
    {
        return DB::transaction(function () use ($operation) {
            // One company-wide payroll mutex also serializes cross-source reallocations.
            $settings = PayrollSetting::current();
            PayrollSetting::whereKey($settings->id)->lockForUpdate()->firstOrFail();
            return $operation();
        });
    }

    private function lockSources(array $employeeIds): void
    {
        $this->periods->lockEmployees($employeeIds);
        $payrollIds = PayrollDetail::whereIn('staff_member_id', $employeeIds)->pluck('payroll_id');
        Payroll::whereIn('id', $payrollIds)->orderBy('id')->lockForUpdate()->get();
        $thrIds = ThrPayrollDetail::whereIn('staff_member_id', $employeeIds)->pluck('thr_payroll_id');
        ThrPayroll::whereIn('id', $thrIds)->orderBy('id')->lockForUpdate()->get();
    }

    public function settlePayroll(Payroll $payroll, string $creditDate): bool
    {
        return $this->transaction(function () use ($payroll, $creditDate) {
            $payroll->refresh();
            $details = $payroll->payrollDetails()->orderBy('staff_member_id')->get();
            $this->lockSources($details->pluck('staff_member_id')->all());
            if ($payroll->status !== PayrollStatus::APPROVED) {
                throw new PayrollStateException('Only an approved payroll can be settled.');
            }
            $this->approvals->assertComplete($payroll);
            foreach ($details as $detail) {
                $meta = json_decode($detail->tax_calculation_meta ?? 'null', true, 512, JSON_THROW_ON_ERROR);
                if (data_get($meta, 'meta.components.bpjs.contribution_month') !== $payroll->salary_month->format('Y-m')) {
                    throw new PayrollStateException('Payroll has no verified BPJS calculation. Reopen it, import the contribution month inputs, and approve again.');
                }
            }
            $payroll->update(['planned_credit_date' => $creditDate]);
            $ready = true;
            foreach ($details as $detail) {
                $prepared = $this->periods->prepareSettlement('payroll', $detail->id, $creditDate);
                $ready = $prepared['ready'] && $ready;
                $this->writeResult('payroll', $detail->id, $prepared['calculation']);
                foreach ($prepared['affected_sources'] as $source) {
                    $this->writeResult($source['source_type'], (int) $source['source_id'], $source['calculation']);
                }
            }
            $payroll->refresh();
            if (! $ready || $payroll->status !== PayrollStatus::APPROVED) {
                return false;
            }
            $this->approvals->assertComplete($payroll);
            foreach ($details as $detail) {
                $changes = $this->periods->settle('payroll', $detail->id, $creditDate);
                foreach ($changes['affected_sources'] as $source) {
                    $this->writeResult($source['source_type'], (int) $source['source_id'], $source['calculation']);
                }
            }
            return true;
        });
    }

    public function settleThr(ThrPayroll $payroll, string $creditDate): bool
    {
        return $this->transaction(function () use ($payroll, $creditDate) {
            $payroll->refresh();
            $details = $payroll->details()->orderBy('staff_member_id')->get();
            $this->lockSources($details->pluck('staff_member_id')->all());
            if ($payroll->status !== ThrPayroll::STATUS_APPROVED) {
                throw new PayrollStateException('Only an approved THR payroll can be settled.');
            }
            $payroll->update(['planned_credit_date' => $creditDate]);
            $ready = true;
            foreach ($details as $detail) {
                $prepared = $this->periods->prepareSettlement('thr', $detail->id, $creditDate);
                $ready = $prepared['ready'] && $ready;
                $this->writeResult('thr', $detail->id, $prepared['calculation']);
                foreach ($prepared['affected_sources'] as $source) {
                    $this->writeResult($source['source_type'], (int) $source['source_id'], $source['calculation']);
                }
            }
            $payroll->refresh();
            if (! $ready || $payroll->status !== ThrPayroll::STATUS_APPROVED) {
                return false;
            }
            foreach ($details as $detail) {
                $changes = $this->periods->settle('thr', $detail->id, $creditDate);
                foreach ($changes['affected_sources'] as $source) {
                    $this->writeResult($source['source_type'], (int) $source['source_id'], $source['calculation']);
                }
            }
            return true;
        });
    }

    public function refreshPayroll(Payroll $payroll): void
    {
        $this->transaction(function () use ($payroll) {
            $payroll->refresh();
            $this->lockSources($payroll->payrollDetails()->pluck('staff_member_id')->all());
            $this->refreshPayrollLocked($payroll);
        });
    }

    private function refreshPayrollLocked(Payroll $payroll): void
    {
        if (! in_array($payroll->status, [PayrollStatus::PENDING, PayrollStatus::PROCESSING], true)) {
            throw new PayrollStateException('Only an unpaid payroll draft can be recalculated.');
        }

        $payroll->loadMissing('payrollSettingVersion');
        if (! $payroll->payrollSettingVersion) {
            throw new PayrollStateException('Payroll requires a verified settings version before recalculation. Run the settings version backfill first.');
        }
        $dueDate = $payroll->salary_month->copy()->day(min(
            (int) $payroll->payrollSettingVersion->payday_day,
            $payroll->salary_month->daysInMonth
        ))->toDateString();

        $period = $payroll->salary_month->format('Y-m');
        $facts = BpjsPayrollFacts::load($period);
        foreach ($payroll->payrollDetails()->with('staffMember.jobInformation')->orderBy('staff_member_id')->get() as $detail) {
            $input = BpjsPayrollFacts::forEmployee($facts, $detail->staff_member_id, (float) $detail->original_salary);
            $bpjs = $this->calculator->calculateBpjs($input, $period)['breakdown'];
            $pension = $bpjs['jht_employee'] + $bpjs['jp_employee'];
            $health = $bpjs['bpjs_kesehatan_employee'];
            // PMK 168 Art. 5: employer health/JKK/JKM are taxable; employer JHT/JP are not cash earnings.
            $benefits = $bpjs['bpjs_kesehatan_employer'] + $bpjs['jkk_employer'] + $bpjs['jkm_employer'];
            $cash = max(0, (float) $detail->original_salary - (float) $detail->deduction_amount)
                + (float) $detail->overtime_amount + (float) $detail->taxable_adjustment_amount;
            if ($cash < 0) {
                throw new PayrollStateException('Taxable wage corrections cannot exceed this payroll income. Use a sourced historical correction.');
            }
            $detail->update([
                'bpjs_tk_employee' => $pension,
                'bpjs_tk_employer' => $bpjs['jht_employer'] + $bpjs['jp_employer'] + $bpjs['jkk_employer'] + $bpjs['jkm_employer'],
                'bpjs_kes_employee' => $health,
                'bpjs_kes_employer' => $bpjs['bpjs_kesehatan_employer'],
            ]);
            $result = $this->periods->persistDraft(
                $detail->staffMember, 'payroll', $detail->id, $dueDate,
                $payroll->planned_credit_date?->toDateString(), $cash + $benefits, $pension, $cash,
                [
                    'salary' => (float) $detail->original_salary,
                    'absence_deduction' => (float) $detail->deduction_amount,
                    'overtime' => (float) $detail->overtime_amount,
                    'taxable_adjustments' => (float) $detail->taxable_adjustment_amount,
                    'taxable_employer_benefits' => $benefits,
                    'exempt_employer_pension' => $bpjs['jht_employer'] + $bpjs['jp_employer'],
                    'employee_bpjs' => $pension + $health,
                    'bpjs' => [
                        'contribution_month' => $period,
                        'wage_base' => $input->basicWage + $input->fixedAllowances,
                        'jkk_risk_class' => $input->jkkRiskClass,
                        'health_floor' => $input->healthFloor,
                        'contributions' => $bpjs,
                    ],
                ]
            );
            $this->apply('payroll', $detail->id, $result);
        }
    }

    public function refreshThr(ThrPayroll $payroll): void
    {
        $this->transaction(function () use ($payroll) {
            $payroll->refresh();
            $this->lockSources($payroll->details()->pluck('staff_member_id')->all());
            $this->refreshThrLocked($payroll);
        });
    }

    private function refreshThrLocked(ThrPayroll $payroll): void
    {
        if (! in_array($payroll->status, [ThrPayroll::STATUS_PENDING, ThrPayroll::STATUS_PROCESSING], true)) {
            throw new PayrollStateException('Only an unpaid THR draft can be recalculated.');
        }
        if (! $payroll->income_due_date) {
            throw new PayrollStateException('THR requires an explicit income due date, distinct from the legal deadline.');
        }
        foreach ($payroll->details()->with('staffMember.jobInformation')->orderBy('staff_member_id')->get() as $detail) {
            $result = $this->periods->persistDraft(
                $detail->staffMember, 'thr', $detail->id, $payroll->income_due_date->toDateString(),
                $payroll->planned_credit_date?->toDateString(), (float) $detail->gross_thr_amount, 0, (float) $detail->gross_thr_amount,
                ['thr' => (float) $detail->gross_thr_amount, 'employee_bpjs' => 0]
            );
            $this->apply('thr', $detail->id, $result);
        }
    }

    public function updateTaxYearProfile(array $row): void
    {
        $this->transaction(function () use ($row) {
            $this->lockSources([$row['employee_id']]);
            $year = PayrollTaxYear::query()->firstOrNew([
                'employee_id' => $row['employee_id'], 'year' => $row['year'],
            ]);
            if (($year->history ?? []) != $row['history']) {
                throw new PayrollStateException('Profile updates must preserve imported history.');
            }
            $profile = array_intersect_key($row, array_flip(['employment_start_date', 'employment_end_date', 'subjective_start_date', 'subjective_end_date']));
            if ($year->exists && $year->profile == $profile && ($year->provenance === $row['provenance']
                || str_ends_with($year->provenance, "\n".$row['provenance']))) {
                return;
            }
            $year->fill([
                'profile' => $profile,
                'history' => $row['history'], 'provenance' => $row['provenance'],
            ])->save();
            foreach ($this->periods->reallocateEmployee($row['employee_id'], $row['year']) as $source) {
                $this->writeResult($source['source_type'], (int) $source['source_id'], $source['calculation']);
            }
        });
    }

    private function apply(string $sourceType, int $sourceId, array $result): void
    {
        $affected = $result['meta']['affected_sources'] ?? [];
        unset($result['meta']['affected_sources']);
        $this->writeResult($sourceType, $sourceId, $result);
        foreach ($affected as $source) {
            if ($source['source_type'] === $sourceType && (int) $source['source_id'] === $sourceId) {
                continue;
            }
            $this->writeResult($source['source_type'], (int) $source['source_id'], $source['calculation']);
        }
    }

    private function writeResult(string $type, int $id, array $result): void
    {
        $cash = (float) $result['meta']['cash_base'];
        $tax = (float) $result['pph21_monthly'];
        if ($type === 'payroll') {
            $detail = PayrollDetail::findOrFail($id);
            $payroll = Payroll::with('payrollSettingVersion')->whereKey($detail->payroll_id)->lockForUpdate()->firstOrFail();
            if ($payroll->status === PayrollStatus::PAID) {
                throw new PayrollStateException('Paid payroll history is immutable.');
            }
            $employeeBpjs = (float) $detail->bpjs_tk_employee + (float) $detail->bpjs_kes_employee;
            $settings = $payroll->payrollSettingVersion;
            if (! $settings) {
                throw new PayrollStateException('Payroll requires a verified settings version before recalculation.');
            }
            $unroundedNet = $cash - $employeeBpjs - $tax;
            if ($unroundedNet < 0) {
                throw new PayrollStateException('Withholding exceeds available cash. Record a separate funded tax settlement.');
            }
            $net = $this->applyRounding($unroundedNet, $settings->rounding_mode, (int) $settings->rounding_unit);
            $encoded = json_encode($result, JSON_THROW_ON_ERROR);
            $stored = $detail->tax_calculation_meta === null ? null : json_decode($detail->tax_calculation_meta, true, 512, JSON_THROW_ON_ERROR);
            if ((float) $detail->pph21_amount !== $tax || (float) $detail->final_salary !== $net || PayrollTaxMetadata::hash($stored) !== PayrollTaxMetadata::hash($result)) {
                $this->approvals->invalidate($payroll);
                $detail->update(['pph21_amount' => $tax, 'final_salary' => $net, 'tax_calculation_meta' => $encoded]);
                if ($payroll->status === PayrollStatus::APPROVED) {
                    $payroll->update(['status' => PayrollStatus::PENDING]);
                }
            }
            return;
        }
        if ($type !== 'thr') {
            throw new PayrollStateException('Unsupported payment source.');
        }
        $detail = ThrPayrollDetail::findOrFail($id);
        $payroll = ThrPayroll::whereKey($detail->thr_payroll_id)->lockForUpdate()->firstOrFail();
        if ($payroll->status === ThrPayroll::STATUS_PAID) {
            throw new PayrollStateException('Paid THR history is immutable.');
        }
        $net = round($cash - $tax, 0);
        if ($net < 0) {
            throw new PayrollStateException('THR withholding exceeds available cash.');
        }
        if ((float) $detail->pph21_amount !== $tax || (float) $detail->net_thr_amount !== $net || PayrollTaxMetadata::hash($detail->tax_calculation_meta) !== PayrollTaxMetadata::hash($result)) {
            $detail->update(['pph21_amount' => $tax, 'net_thr_amount' => $net, 'tax_calculation_meta' => $result]);
            $payroll->update([
                'approved_by' => null,
                'approved_at' => null,
                'status' => $payroll->status === ThrPayroll::STATUS_PROCESSING ? ThrPayroll::STATUS_PROCESSING : ThrPayroll::STATUS_PENDING,
            ]);
        }
        $totals = $payroll->details()->selectRaw('SUM(gross_thr_amount) gross, SUM(pph21_amount) tax, SUM(net_thr_amount) net')->first();
        $payroll->update(['total_thr_amount' => $totals->gross, 'total_tax_amount' => $totals->tax, 'total_net_amount' => $totals->net]);
    }
    private function applyRounding(float $amount, string $mode, int $unit): float
    {
        if ($mode === 'none' || $unit <= 1) {
            return round($amount, 2);
        }

        $scaled = $amount / $unit;

        $rounded = match ($mode) {
            'floor' => floor($scaled),
            'ceil' => ceil($scaled),
            default => round($scaled),
        };

        return (float) ($rounded * $unit);
    }
}
