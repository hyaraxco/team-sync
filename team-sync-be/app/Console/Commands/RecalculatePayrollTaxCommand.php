<?php

namespace App\Console\Commands;

use App\Models\StaffMemberProfile;
use App\Models\Payroll;
use App\Models\PayrollDetail;
use App\Services\Payroll\TaxCalculationService;
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

    public function handle(TaxCalculationService $taxService): int
    {
        $month = $this->option('month') ?? now()->format('Y-m');
        $isDryRun = $this->option('dry-run');

        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            $this->error("Invalid month format. Use Y-m (e.g. 2026-04).");
            return self::FAILURE;
        }

        // Find existing payroll
        $payroll = Payroll::whereDate('salary_month', $month . '-01')->first();

        if (! $payroll) {
            $this->error("No payroll found for {$month}.");
            return self::FAILURE;
        }

        if (! in_array($payroll->status, ['pending', 'processing', 'draft'])) {
            $this->error("Payroll {$month} is in status '{$payroll->status}'. Only pending/processing/draft can be recalculated.");
            return self::FAILURE;
        }

        $this->info("Found payroll ID={$payroll->id} | month={$month} | status={$payroll->status}");
        $this->newLine();

        // Load all payroll details with employee profile
        $details = PayrollDetail::with(['staffMember' => function ($q) {
            $q->select('id', 'npwp', 'ptkp_status');
        }])
        ->where('payroll_id', $payroll->id)
        ->get();

        if ($details->isEmpty()) {
            $this->warn('No payroll details found for this payroll.');
            return self::FAILURE;
        }

        $this->info("Recalculating tax & BPJS for {$details->count()} employees...");
        $this->newLine();

        $rows = [];
        $before = ['pph21' => 0, 'bpjs_tk' => 0, 'bpjs_kes' => 0];
        $after  = ['pph21' => 0, 'bpjs_tk' => 0, 'bpjs_kes' => 0];

        foreach ($details as $detail) {
            $employee = $detail->staffMember;
            if (! $employee) continue;

            $gross    = (float) $detail->original_salary;
            $ptkp     = $employee->ptkp_status ?? null;
            $hasNpwp  = ! empty($employee->npwp);

            $taxResult  = $taxService->calculateMonthlyPph21($gross, $ptkp, $hasNpwp);
            $bpjsResult = $taxService->calculateBpjs($gross);

            $newPph21      = round($taxResult['pph21_monthly'], 2);
            $newBpjsTkEmp  = round(
                ($bpjsResult['breakdown']['jht_employee'] ?? 0) + ($bpjsResult['breakdown']['jp_employee'] ?? 0),
                2
            );
            $newBpjsTkEmpr = round(
                ($bpjsResult['breakdown']['jht_employer']  ?? 0)
                + ($bpjsResult['breakdown']['jkk_employer'] ?? 0)
                + ($bpjsResult['breakdown']['jkm_employer'] ?? 0)
                + ($bpjsResult['breakdown']['jp_employer']  ?? 0),
                2
            );
            $newBpjsKesEmp  = round($bpjsResult['breakdown']['bpjs_kesehatan_employee'] ?? 0, 2);
            $newBpjsKesEmpr = round($bpjsResult['breakdown']['bpjs_kesehatan_employer'] ?? 0, 2);

            $before['pph21']   += $detail->pph21_amount;
            $before['bpjs_tk'] += $detail->bpjs_tk_employee;
            $before['bpjs_kes']+= $detail->bpjs_kes_employee;

            $after['pph21']   += $newPph21;
            $after['bpjs_tk'] += $newBpjsTkEmp;
            $after['bpjs_kes']+= $newBpjsKesEmp;

            $rows[] = [
                'id'              => $detail->id,
                'employee_id'     => $employee->id,
                'ptkp'            => $ptkp ?? '—',
                'npwp'            => $hasNpwp ? 'Yes' : 'No',
                'old_pph21'       => number_format($detail->pph21_amount, 0, ',', '.'),
                'new_pph21'       => number_format($newPph21, 0, ',', '.'),
                'old_bpjs_tk'     => number_format($detail->bpjs_tk_employee, 0, ',', '.'),
                'new_bpjs_tk'     => number_format($newBpjsTkEmp, 0, ',', '.'),
                // For DB update
                '_pph21'          => $newPph21,
                '_bpjs_tk_emp'    => $newBpjsTkEmp,
                '_bpjs_tk_empr'   => $newBpjsTkEmpr,
                '_bpjs_kes_emp'   => $newBpjsKesEmp,
                '_bpjs_kes_empr'  => $newBpjsKesEmpr,
                '_tax_meta'       => json_encode($taxResult, JSON_THROW_ON_ERROR),
            ];
        }

        // Display diff table
        $this->table(
            ['Detail ID', 'Emp', 'PTKP', 'NPWP', 'PPh21 Before', 'PPh21 After', 'BPJS-TK Before', 'BPJS-TK After'],
            collect($rows)->map(fn ($r) => [
                $r['id'], $r['employee_id'], $r['ptkp'], $r['npwp'],
                'Rp ' . $r['old_pph21'], 'Rp ' . $r['new_pph21'],
                'Rp ' . $r['old_bpjs_tk'], 'Rp ' . $r['new_bpjs_tk'],
            ])->toArray()
        );

        $this->newLine();
        $this->info('Summary of changes:');
        $this->table(
            ['Metric', 'Before', 'After', 'Delta'],
            [
                [
                    'Total PPh 21',
                    'Rp ' . number_format($before['pph21'], 0, ',', '.'),
                    'Rp ' . number_format($after['pph21'], 0, ',', '.'),
                    'Rp ' . number_format($after['pph21'] - $before['pph21'], 0, ',', '.'),
                ],
                [
                    'Total BPJS-TK (emp)',
                    'Rp ' . number_format($before['bpjs_tk'], 0, ',', '.'),
                    'Rp ' . number_format($after['bpjs_tk'], 0, ',', '.'),
                    'Rp ' . number_format($after['bpjs_tk'] - $before['bpjs_tk'], 0, ',', '.'),
                ],
                [
                    'Total BPJS-Kes (emp)',
                    'Rp ' . number_format($before['bpjs_kes'], 0, ',', '.'),
                    'Rp ' . number_format($after['bpjs_kes'], 0, ',', '.'),
                    'Rp ' . number_format($after['bpjs_kes'] - $before['bpjs_kes'], 0, ',', '.'),
                ],
            ]
        );

        if ($isDryRun) {
            $this->warn('[DRY-RUN] No changes written. Remove --dry-run to apply.');
            return self::SUCCESS;
        }

        // Apply updates
        DB::transaction(function () use ($rows) {
            foreach ($rows as $row) {
                DB::table('payroll_details')->where('id', $row['id'])->update([
                    'pph21_amount'         => $row['_pph21'],
                    'bpjs_tk_employee'     => $row['_bpjs_tk_emp'],
                    'bpjs_tk_employer'     => $row['_bpjs_tk_empr'],
                    'bpjs_kes_employee'    => $row['_bpjs_kes_emp'],
                    'bpjs_kes_employer'    => $row['_bpjs_kes_empr'],
                    'tax_calculation_meta' => $row['_tax_meta'],
                    'updated_at'           => now(),
                ]);
            }
        });

        $this->newLine();
        $this->info("✓ Recalculated PPh 21 & BPJS for {$details->count()} payroll details.");
        $this->info("  Payroll ID={$payroll->id} ({$month}) is now up-to-date.");

        return self::SUCCESS;
    }
}
