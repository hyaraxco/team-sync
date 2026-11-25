<?php

namespace App\Console\Commands;

use App\Exceptions\PayrollStateException;
use App\Models\Payroll;
use App\Models\PayrollBpjsPeriod;
use App\Models\PayrollSetting;
use App\Services\Payroll\BpjsPayrollFacts;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use JsonException;

class ImportPayrollBpjsCommand extends Command
{
    protected $signature = 'payroll:import-bpjs {month : Contribution month (YYYY-MM)} {path : Private verified inputs JSON file}';

    protected $description = 'Import verified BPJS inputs for ordinary private-sector salaried payroll';

    public function handle(): int
    {
        $path = $this->argument('path');
        if (! is_file($path) || ! is_readable($path) || ($json = file_get_contents($path)) === false) {
            $this->error('Cannot read the BPJS input file.');

            return self::FAILURE;
        }

        $month = $this->argument('month');
        try {
            $facts = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            if (! is_array($facts)) {
                throw new PayrollStateException('BPJS inputs must be a JSON object.');
            }
            $facts = BpjsPayrollFacts::validate($month, $facts);
            DB::transaction(function () use ($month, $facts) {
                PayrollSetting::query()->whereKey(PayrollSetting::current()->id)->lockForUpdate()->firstOrFail();
                if (Payroll::query()->whereDate('salary_month', $month.'-01')->whereIn('status', ['approved', 'paid'])->exists()) {
                    throw new PayrollStateException('BPJS inputs cannot change for approved or paid payroll. Reopen approved payroll first.');
                }
                PayrollBpjsPeriod::query()->updateOrCreate(['contribution_month' => $month], ['facts' => $facts]);
            });
        } catch (JsonException|PayrollStateException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info("Verified BPJS inputs imported for {$month}.");

        return self::SUCCESS;
    }
}
