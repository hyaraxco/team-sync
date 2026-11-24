<?php

namespace App\Services\Payroll;

use App\Exceptions\PayrollStateException;
use App\Models\PayrollDetail;
use App\Models\PayrollSetting;
use App\Models\PayrollTaxEntry;
use App\Models\PayrollTaxYear;
use App\Models\PtkpAmount;
use App\Models\StaffMemberProfile;
use App\Models\TaxBracket;
use App\Models\ThrPayrollDetail;
use App\Support\PayrollTaxMetadata;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PayrollTaxPeriodService
{
    public function lockEmployees(array $employeeIds): void
    {
        StaffMemberProfile::withTrashed()->whereIn('id', $employeeIds)->orderBy('id')->lockForUpdate()->get();
    }

    public function transaction(array $employeeIds, callable $callback): mixed
    {
        return DB::transaction(function () use ($employeeIds, $callback) {
            if (! PayrollSetting::query()->orderBy('id')->lockForUpdate()->first()) {
                throw new PayrollStateException('Payroll settings must exist before tax operations.');
            }
            $this->lockEmployees($employeeIds);

            return $callback();
        });
    }

    public function calculate(StaffMemberProfile $employee, string $sourceType, int $sourceId, string $dueDate, ?string $creditDate, float $gross, float $pension, float $cashBase, array $components = []): array
    {
        $entry = $this->candidate($employee, $sourceType, $sourceId, $dueDate, $creditDate, $gross, $pension, $cashBase, $components);

        return $this->allocation($entry);
    }

    public function persistDraft(StaffMemberProfile $employee, string $sourceType, int $sourceId, string $dueDate, ?string $creditDate, float $gross, float $pension, float $cashBase, array $components = []): array
    {
        return $this->transaction([$employee->id], function () use ($employee, $sourceType, $sourceId, $dueDate, $creditDate, $gross, $pension, $cashBase, $components) {
            $entry = PayrollTaxEntry::query()->where('source_type', $sourceType)->where('source_id', $sourceId)->first();
            if ($entry?->settled_at) {
                throw new PayrollStateException('Settled tax entries cannot be recalculated.');
            }
            if ($entry && $entry->employee_id !== $employee->id) {
                throw new PayrollStateException('Tax source belongs to another employee.');
            }
            $candidate = $this->candidate($employee, $sourceType, $sourceId, $dueDate, $creditDate, $gross, $pension, $cashBase, $components);
            $entry ??= new PayrollTaxEntry;
            foreach (array_keys($candidate->getAttributes()) as $attribute) {
                $entry->setAttribute($attribute, $candidate->getAttribute($attribute));
            }
            $entry->save();
            $affected = $this->reallocateEmployee($employee->id);
            $entry->refresh();
            $result = $entry->calculation;
            $result['meta']['affected_sources'] = array_values(array_filter($affected, fn ($item) => $item['source_type'] !== $sourceType || $item['source_id'] !== $sourceId));

            return $result;
        });
    }

    public function assertFresh(string $sourceType, int $sourceId, ?string $creditDate = null): void
    {
        $entry = $this->source($sourceType, $sourceId);
        if (! $entry->active || $entry->settled_at || ! $entry->calculation) {
            throw new PayrollStateException('An active unsettled tax calculation is required.');
        }
        if ($creditDate !== null) {
            $recognized = $this->recognition($entry->input['due_date'], $creditDate);
            if ($recognized->format('Y-m-d H:i:s.u') !== $entry->recognized_at->format('Y-m-d H:i:s.u')) {
                throw new PayrollStateException('Credit changes tax recognition; recalculate and reapprove before payment.');
            }
        }
        $current = $this->allocation($entry);
        if ($current['meta']['context_hash'] !== $entry->calculation['meta']['context_hash'] || (float) $current['pph21_monthly'] !== (float) $entry->calculation['pph21_monthly']) {
            throw new PayrollStateException('Tax facts changed; refresh all affected sources and reapprove.');
        }
    }

    public function settle(string $sourceType, int $sourceId, string $creditDate): array
    {
        $source = $this->source($sourceType, $sourceId);

        return $this->transaction([$source->employee_id], function () use ($sourceType, $sourceId, $creditDate) {
            $entry = $this->source($sourceType, $sourceId);
            $this->assertFresh($sourceType, $sourceId, $creditDate);
            if (PayrollTaxEntry::query()->where('employee_id', $entry->employee_id)->where('period', $entry->period)
                ->where('active', true)->whereNull('settled_at')->where('allocation_key', '<', $entry->allocation_key)->exists()) {
                throw new PayrollStateException('Settle earlier-recognized active tax sources first.');
            }
            $input = $entry->input;
            $input['credit_date'] = $creditDate;
            $entry->update(['input' => $input, 'settled_at' => $this->instant($creditDate), 'actual_withholding' => $entry->calculation['pph21_monthly']]);

            return ['affected_sources' => $this->reallocateEmployee($entry->employee_id)];
        });
    }

    public function prepareSettlement(string $sourceType, int $sourceId, string $creditDate): array
    {
        $source = $this->source($sourceType, $sourceId);

        return $this->transaction([$source->employee_id], function () use ($sourceType, $sourceId, $creditDate) {
            $entry = $this->source($sourceType, $sourceId);
            if ($entry->settled_at || ! $entry->active) {
                throw new PayrollStateException('Only active unsettled tax sources can prepare payment.');
            }
            $before = $entry->calculation;
            $input = $entry->input;
            $result = $this->persistDraft(StaffMemberProfile::withTrashed()->findOrFail($entry->employee_id), $sourceType, $sourceId,
                $input['due_date'], $creditDate, $input['gross'], $input['pension'], $input['cash_base'], $input['components']);
            $affected = $result['meta']['affected_sources'];
            unset($result['meta']['affected_sources']);

            return ['ready' => $before !== null && $before['meta']['context_hash'] === $result['meta']['context_hash']
                && (float) $before['pph21_monthly'] === (float) $result['pph21_monthly'],
                'calculation' => $result, 'affected_sources' => $affected];
        });
    }

    public function deactivate(string $sourceType, int $sourceId): array
    {
        $source = $this->source($sourceType, $sourceId);

        return $this->transaction([$source->employee_id], function () use ($sourceType, $sourceId) {
            $entry = $this->source($sourceType, $sourceId);
            if ($entry->settled_at) {
                throw new PayrollStateException('Cannot deactivate settled tax history.');
            }
            $entry->update(['active' => false]);

            return ['affected_sources' => $this->reallocateEmployee($entry->employee_id)];
        });
    }

    private function source(string $type, int $id): PayrollTaxEntry
    {
        return PayrollTaxEntry::query()->where('source_type', $type)->where('source_id', $id)->first()
            ?? throw new PayrollStateException('Tax source snapshot is missing; refresh before payment.');
    }

    private function instant(string $value): CarbonImmutable
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value)) {
            Validator::make(['date' => $value], ['date' => 'required|date_format:Y-m-d'])->validate();

            return CarbonImmutable::createFromFormat('!Y-m-d', $value, 'Asia/Jakarta')->utc();
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})$/D', $value)) {
            throw new PayrollStateException('Tax dates require YYYY-MM-DD or an ISO timestamp with timezone.');
        }
        $date = substr($value, 0, 10);
        Validator::make(['date' => $date], ['date' => 'date_format:Y-m-d'])->validate();
        if ((int) substr($value, 11, 2) > 23 || (int) substr($value, 14, 2) > 59 || (int) substr($value, 17, 2) > 59) {
            throw new PayrollStateException('Invalid tax timestamp time.');
        }

        return CarbonImmutable::parse($value)->utc();
    }

    private function recognition(string $due, ?string $credit): CarbonImmutable
    {
        $date = $this->instant($due);

        return $credit === null ? $date : $date->min($this->instant($credit));
    }

    private function candidate(StaffMemberProfile $employee, string $type, int $id, string $due, ?string $credit, float $gross, float $pension, float $cash, array $components): PayrollTaxEntry
    {
        Validator::make(compact('type', 'id'), ['type' => 'required|in:payroll,thr,correction', 'id' => 'required|integer|min:1'])->validate();
        foreach ([$gross, $pension, $cash, $components['deductible_donations'] ?? 0, $components['government_borne_tax'] ?? 0] as $amount) {
            if (! is_numeric($amount) || ! is_finite((float) $amount) || $amount < 0) {
                throw new PayrollStateException('Tax amounts must be finite and nonnegative.');
            }
        }
        $recognized = $this->recognition($due, $credit);

        return new PayrollTaxEntry([
            'employee_id' => $employee->id, 'source_type' => $type, 'source_id' => $id,
            'period' => $recognized->setTimezone('Asia/Jakarta')->format('Y-m'),
            'recognized_at' => $recognized,
            'allocation_key' => $recognized->format('Y-m-d H:i:s.u').'|'.$type.'|'.str_pad((string) $id, 20, '0', STR_PAD_LEFT),
            'input' => ['due_date' => $due, 'credit_date' => $credit, 'gross' => $gross, 'pension' => $pension, 'cash_base' => $cash, 'components' => $components],
            'active' => true,
        ]);
    }

    private function reallocateEmployee(int $employeeId): array
    {
        $affected = [];
        foreach (PayrollTaxEntry::query()->where('employee_id', $employeeId)->where('active', true)->whereNull('settled_at')->orderBy('allocation_key')->get() as $entry) {
            $result = $this->allocation($entry);
            if (PayrollTaxMetadata::hash($entry->calculation) !== PayrollTaxMetadata::hash($result)) {
                $entry->update(['calculation' => $result]);
                $affected[] = ['source_type' => $entry->source_type, 'source_id' => $entry->source_id, 'calculation' => $result];
            }
        }

        return $affected;
    }

    private function allocation(PayrollTaxEntry $current): array
    {
        $employee = StaffMemberProfile::withTrashed()->findOrFail($current->employee_id);
        $year = (int) substr($current->period, 0, 4);
        $profile = PayrollTaxYear::query()->where('employee_id', $employee->id)->where('year', $year)->first();
        $history = $profile?->history ?? [];
        $entries = PayrollTaxEntry::query()->where('employee_id', $employee->id)->where('active', true)
            ->where('period', 'like', $year.'-%')->orderBy('allocation_key')->get()
            ->reject(fn ($entry) => $entry->source_type === $current->source_type && $entry->source_id === $current->source_id);
        foreach ($entries as $entry) {
            if (array_key_exists($entry->period, $history)) {
                throw new PayrollStateException('Imported history overlaps internal tax sources: '.$entry->period);
            }
        }
        if (array_key_exists($current->period, $history)) {
            throw new PayrollStateException('Imported history overlaps the current tax source.');
        }
        $facts = $profile?->profile ?? [];
        $endPeriods = array_filter([isset($facts['employment_end_date']) ? substr($facts['employment_end_date'], 0, 7) : null, isset($facts['subjective_end_date']) ? substr($facts['subjective_end_date'], 0, 7) : null]);
        $final = substr($current->period, 5) === '12' || in_array($current->period, $endPeriods, true);
        $this->assertNoLegacy($employee->id, $current->period, $history, $final);
        $same = $entries->filter(fn ($entry) => $entry->period === $current->period && ($entry->settled_at || strcmp($entry->allocation_key, $current->allocation_key) < 0));
        $prefix = $same->values()->all();
        $gross = (float) $current->input['gross'];
        $pension = (float) $current->input['pension'];
        $donations = (float) ($current->input['components']['deductible_donations'] ?? 0);
        $government = (float) ($current->input['components']['government_borne_tax'] ?? 0);
        $actual = 0.0;
        $reserved = 0.0;
        foreach ($same as $entry) {
            $gross += $entry->input['gross'];
            $pension += $entry->input['pension'];
            $donations += $entry->input['components']['deductible_donations'] ?? 0;
            $government += $entry->input['components']['government_borne_tax'] ?? 0;
            if ($entry->settled_at) {
                $actual += (float) $entry->actual_withholding;
            } else {
                if (! $entry->calculation) {
                    throw new PayrollStateException('Earlier draft allocation is missing.');
                }
                $reserved += $entry->calculation['pph21_monthly'];
            }
        }
        $calculator = new TaxCalculationService;
        $finalFacts = null;
        if ($final) {
            if (substr($current->period, 5) === '01' && ! $profile) {
                throw new PayrollStateException('January cessation requires explicit imported employment/subjective facts.');
            }
            $start = $facts['employment_start_date'] ?? $employee->jobInformation?->start_date?->format('Y-m-d');
            if (! $start) {
                throw new PayrollStateException('Employment start date is required for final tax.');
            }
            $subjectiveStart = $facts['subjective_start_date'] ?? $year.'-01-01';
            $subjectiveEnd = $facts['subjective_end_date'] ?? $year.'-12-31';
            if ($subjectiveStart > $year.'-12-31' || $subjectiveEnd < $year.'-01-01') {
                throw new PayrollStateException('Subjective liability does not cover this tax year.');
            }
            $first = max(substr($start, 0, 7), substr($subjectiveStart, 0, 7), $year.'-01');
            if ($first > $current->period) {
                throw new PayrollStateException('Final period precedes employment or subjective liability.');
            }
            for ($month = CarbonImmutable::parse($first.'-01'); $month->format('Y-m') < $current->period; $month = $month->addMonth()) {
                $period = $month->format('Y-m');
                $prior = $entries->where('period', $period);
                if (isset($history[$period])) {
                    $row = $history[$period];
                    $gross += $row['gross_income'];
                    $pension += $row['employee_pension_contributions'];
                    $donations += $row['deductible_donations'];
                    $actual += $row['withheld'];
                    $government += $row['government_borne_tax'];
                } elseif ($prior->isNotEmpty() && $prior->every(fn ($entry) => $entry->settled_at !== null)) {
                    foreach ($prior as $entry) {
                        $gross += $entry->input['gross'];
                        $pension += $entry->input['pension'];
                        $donations += $entry->input['components']['deductible_donations'] ?? 0;
                        $government += $entry->input['components']['government_borne_tax'] ?? 0;
                        $actual += (float) $entry->actual_withholding;
                    }
                } else {
                    throw new PayrollStateException('Tax history missing/unsettled for '.$period.'; explicitly import complete history.');
                }
            }
            $lastMonth = (int) substr($current->period, 5);
            $employmentMonths = $lastMonth - (int) substr($first, 5) + 1;
            $liabilityFirst = max($subjectiveStart, $year.'-01-01');
            $liabilityLast = min($subjectiveEnd, $year.'-12-31');
            $liabilityMonths = (int) substr($liabilityLast, 5, 2) - (int) substr($liabilityFirst, 5, 2) + 1;
            $finalFacts = [
                'gross_income' => $gross, 'employee_pension_contributions' => $pension,
                'deductible_donations' => $donations, 'employment_months' => $employmentMonths,
                'tax_liability_months' => $liabilityMonths,
                'annualize' => $subjectiveStart > $year.'-01-01' || $subjectiveEnd < $year.'-12-31',
                'previously_withheld' => $actual, 'government_borne_tax' => $government, 'final_period' => $current->period,
            ];
            $result = $calculator->calculateFinalPeriodPph21($finalFacts, $employee->ptkp_status, filled($employee->npwp));
            $result['pph21_monthly'] -= $reserved;
        } else {
            $result = $calculator->calculateMonthlyTer($gross, $employee->ptkp_status, filled($employee->npwp), $current->period);
            $result['pph21_monthly'] -= $actual + $reserved;
        }
        $context = [
            'period' => $current->period, 'allocation_key' => $current->allocation_key,
            'input' => array_diff_key($current->input, ['credit_date' => true, 'due_date' => true]),
            'profile' => $facts, 'history' => $history, 'ptkp_status' => $employee->ptkp_status,
            'has_npwp' => filled($employee->npwp), 'identity' => hash('sha256', (string) $employee->npwp),
            'job_start' => $employee->jobInformation?->start_date?->format('Y-m-d'),
            'entries' => array_map(fn ($entry) => [$entry->source_type, $entry->source_id, $entry->allocation_key, $entry->input, $entry->settled_at?->format('c'), $entry->actual_withholding, $entry->settled_at ? null : $entry->calculation], $prefix),
            'prior_entries' => $final ? $entries->filter(fn ($entry) => $entry->period < $current->period)->map(fn ($entry) => [$entry->source_type, $entry->source_id, $entry->input, $entry->actual_withholding, $entry->settled_at?->format('c')])->values()->all() : [],
            'ptkp_rates' => PtkpAmount::query()->orderBy('id')->get()->toArray(),
            'tax_rates' => TaxBracket::query()->orderBy('id')->get()->toArray(), 'facts' => $finalFacts,
        ];
        $result['meta'] = array_merge($result['meta'] ?? [], [
            'period' => $current->period, 'source_type' => $current->source_type, 'source_id' => $current->source_id,
            'recognized_at' => $current->recognized_at->utc()->format('Y-m-d\TH:i:s.u\Z'),
            'allocation_key' => $current->allocation_key, 'components' => $current->input['components'],
            'cash_base' => $current->input['cash_base'], 'previously_withheld' => $actual, 'earlier_reserved' => $reserved,
            'context_hash' => PayrollTaxMetadata::hash($context),
            'refund_deadline' => $final && $result['pph21_monthly'] < 0 ? CarbonImmutable::parse($current->period.'-01')->addMonth()->endOfMonth()->toDateString() : null,
        ]);

        return $result;
    }

    private function assertNoLegacy(int $employeeId, string $period, array $history, bool $final): void
    {
        foreach (['payroll' => PayrollDetail::class, 'thr' => ThrPayrollDetail::class] as $type => $model) {
            $relation = $type === 'payroll' ? 'payroll' : 'thrPayroll';
            foreach ($model::query()->where('staff_member_id', $employeeId)->whereHas($relation, fn ($query) => $query->withTrashed()->where('status', 'paid'))->with([$relation => fn ($query) => $query->withTrashed()])->get() as $detail) {
                if (PayrollTaxEntry::query()->where('source_type', $type)->where('source_id', $detail->id)->whereNotNull('settled_at')->exists()) {
                    continue;
                }
                $parent = $detail->$relation;
                $due = $type === 'payroll' ? $parent->salary_month?->format('Y-m-d') : $parent->payment_deadline?->format('Y-m-d');
                $credit = $parent->payment_date?->format('Y-m-d');
                if (! $due) {
                    throw new PayrollStateException('Paid legacy source has unknown recognition; explicit history import required.');
                }
                $legacyPeriod = $this->recognition($due, $credit)->setTimezone('Asia/Jakarta')->format('Y-m');
                if (($legacyPeriod === $period || ($final && substr($legacyPeriod, 0, 4) === substr($period, 0, 4) && $legacyPeriod < $period)) && ! array_key_exists($legacyPeriod, $history)) {
                    throw new PayrollStateException('Paid legacy '.$type.' requires explicit payroll:import-tax-history; paid status is not actual withholding evidence.');
                }
            }
        }
    }
}
