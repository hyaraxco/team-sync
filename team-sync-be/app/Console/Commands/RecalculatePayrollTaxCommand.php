<?php

namespace App\Console\Commands;

use App\Enums\PayrollStatus;
use App\Models\Payroll;
use App\Services\Payroll\PayrollTaxIntegrationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Recalculates PPh 21 and BPJS for an existing pending payroll.
 * Useful after employee identity data (PTKP, NPWP) has been populated.
 *
 * Usage:
 *   php artisan payroll:recalculate-tax --month=2026-04
 *   php artisan payroll:recalculate-tax --month=2026-04 --dry-run
 */
class RecalculatePayrollTaxCommand extends Command
{
    protected $signature = 'payroll:recalculate-tax
                            {--month= : Salary month in Y-m format (e.g. 2026-04)}
                            {--dry-run : Show what would change without writing to DB}';

    protected $description = 'Recalculate PPh 21 & BPJS for an existing pending/processing payroll after employee PTKP/NPWP data has been updated';

    public function handle(PayrollTaxIntegrationService $taxIntegration): int
    {
        $month = $this->option('month') ?? now()->format('Y-m');
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            $this->error('Invalid month format. Use Y-m (e.g. 2026-04).');
            return self::FAILURE;
        }
        try {
            $taxIntegration->transaction(function () use ($month, $taxIntegration) {
                $payroll = Payroll::whereDate('salary_month', $month.'-01')->lockForUpdate()->first();
                if (! $payroll) {
                    throw new \RuntimeException("No payroll found for {$month}.");
                }
                if (! in_array($payroll->status, [PayrollStatus::PENDING, PayrollStatus::PROCESSING], true)) {
                    throw new \RuntimeException('Only pending/processing payroll can be recalculated.');
                }
                $before = $payroll->payrollDetails()->get()->keyBy('id');
                if ($before->isEmpty()) {
                    throw new \RuntimeException('No payroll details found for this payroll.');
                }
                DB::beginTransaction();
                try {
                    $taxIntegration->refreshPayroll($payroll);
                    $rows = $payroll->payrollDetails()->get()->map(fn ($detail) => [
                        $detail->id, $before[$detail->id]->pph21_amount, $detail->pph21_amount,
                        $before[$detail->id]->final_salary, $detail->final_salary,
                    ])->all();
                    $this->table(['Detail ID', 'PPh21 Before', 'PPh21 After', 'Net Before', 'Net After'], $rows);
                    if ($this->option('dry-run')) {
                        DB::rollBack();
                        $this->warn('[DRY-RUN] No changes written. Remove --dry-run to apply.');
                    } else {
                        DB::commit();
                    }
                } catch (\Throwable $error) {
                    DB::rollBack();
                    throw $error;
                }
            });
        } catch (\Throwable $error) {
            $this->error($error->getMessage());
            return self::FAILURE;
        }
        $this->info("Recalculated PPh 21, BPJS and net pay for {$month}.");
        return self::SUCCESS;
    }
}
