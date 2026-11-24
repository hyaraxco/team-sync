<?php

namespace App\Console\Commands;

use App\Exceptions\PayrollStateException;
use App\Models\PayrollTaxEntry;
use App\Models\PayrollTaxYear;
use App\Services\Payroll\PayrollTaxPeriodService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Throwable;

class ImportPayrollTaxHistoryCommand extends Command
{
    protected $signature = 'payroll:import-tax-history {path : JSON array of employee/year facts and monthly actual history}';

    protected $description = 'Import explicit, provenanced tax history without inferring withholding from paid status';

    public function handle(PayrollTaxPeriodService $service): int
    {
        try {
            $path = $this->argument('path');
            if (! is_file($path) || ! is_readable($path)) {
                throw new PayrollStateException('History JSON file is not readable.');
            }
            $rows = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
            Validator::make(['rows' => $rows], [
                'rows' => 'required|array|list|min:1',
                'rows.*' => 'required|array',
                'rows.*.employee_id' => 'required|integer|exists:staff_member_profiles,id',
                'rows.*.year' => 'required|integer|between:1900,9999',
                'rows.*.provenance' => 'required|string',
                'rows.*.employment_start_date' => 'required|date_format:Y-m-d',
                'rows.*.employment_end_date' => 'present|nullable|date_format:Y-m-d|after_or_equal:rows.*.employment_start_date',
                'rows.*.subjective_start_date' => 'required|date_format:Y-m-d',
                'rows.*.subjective_end_date' => 'present|nullable|date_format:Y-m-d|after_or_equal:rows.*.subjective_start_date',
                'rows.*.history' => 'present|array',
                'rows.*.history.*' => 'required|array',
                'rows.*.history.*.provenance' => 'required|string',
            ])->validate();
            $seen = [];
            foreach ($rows as $row) {
                $key = $row['employee_id'].'/'.$row['year'];
                if (isset($seen[$key])) {
                    throw new PayrollStateException('Duplicate employee/year in import.');
                }
                $seen[$key] = true;
                foreach ($row['history'] as $period => $month) {
                    if (! preg_match('/^'.preg_quote((string) $row['year'], '/').'-(0[1-9]|1[0-2])$/D', (string) $period)) {
                        throw new PayrollStateException('History keys must be strict YYYY-MM within the supplied year.');
                    }
                    foreach (['gross_income', 'employee_pension_contributions', 'deductible_donations', 'withheld', 'government_borne_tax'] as $field) {
                        Validator::make($month, [$field => 'required|integer'])->validate();
                        if (! is_finite((float) $month[$field]) || ($field !== 'withheld' && $month[$field] < 0)) {
                            throw new PayrollStateException('History amounts must be finite; only withheld may be negative.');
                        }
                    }
                }
            }
            $service->transaction(array_column($rows, 'employee_id'), function () use ($rows) {
                foreach ($rows as $row) {
                    $entries = PayrollTaxEntry::query()->where('employee_id', $row['employee_id'])->where('period', 'like', $row['year'].'-%');
                    if ((clone $entries)->where('active', true)->whereNull('settled_at')->exists()) {
                        throw new PayrollStateException('Import history before creating active drafts; deactivate drafts and refresh/reapprove after import.');
                    }
                    if ((clone $entries)->whereNotNull('settled_at')->exists()) {
                        throw new PayrollStateException('Cannot overwrite factual/history content of a settled tax year.');
                    }
                    if ((clone $entries)->where('active', true)->whereIn('period', array_keys($row['history']))->exists()) {
                        throw new PayrollStateException('Imported months overlap internal tax entries.');
                    }
                    $history = $row['history'];
                    ksort($history);
                    PayrollTaxYear::query()->updateOrCreate(['employee_id' => $row['employee_id'], 'year' => $row['year']], [
                        'profile' => array_intersect_key($row, array_flip(['employment_start_date', 'employment_end_date', 'subjective_start_date', 'subjective_end_date'])),
                        'history' => $history, 'provenance' => $row['provenance'],
                    ]);
                }
            });
            $this->info('Tax history imported; no application amounts or paid records changed.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
