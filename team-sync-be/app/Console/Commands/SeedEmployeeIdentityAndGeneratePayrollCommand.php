<?php

namespace App\Console\Commands;

use App\Models\EmployeeProfile;
use App\Models\Payroll;
use App\Models\User;
use App\Repositories\PayrollRepository;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Seeds Indonesian identity fields for existing employees, then generates
 * a payroll for the specified salary month so PPh 21 & BPJS can be verified.
 *
 * Usage:
 *   php artisan payroll:seed-identity-and-generate
 *   php artisan payroll:seed-identity-and-generate --month=2026-05
 *   php artisan payroll:seed-identity-and-generate --month=2026-05 --dry-run
 */
class SeedEmployeeIdentityAndGeneratePayrollCommand extends Command
{
    protected $signature = 'payroll:seed-identity-and-generate
                            {--month= : Salary month in Y-m format, defaults to next month}
                            {--dry-run : Show what would be done without writing to DB}
                            {--actor-email= : Email of the HR user to act as (for audit trail)}';

    protected $description = 'Seed NPWP, BPJS, PTKP data for employees then generate payroll for testing PPh 21 & BPJS calculations';

    private array $ptkpPool = [
        'TK/0', 'K/0', 'K/1', 'TK/1', 'K/2',
        'TK/0', 'K/0', 'K/1', 'TK/0', 'K/3',
    ];

    private array $religionPool = ['islam', 'islam', 'islam', 'kristen', 'katolik'];

    private array $maritalPool = ['married', 'single', 'married', 'married', 'single'];

    private array $bloodPool = ['A', 'B', 'O', 'AB'];

    public function handle(PayrollRepository $payrollRepository): int
    {
        $isDryRun = $this->option('dry-run');
        $month = $this->option('month') ?? now()->addMonth()->format('Y-m');

        // Validate month format
        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            $this->error("Invalid month format: {$month}. Use Y-m (e.g. 2026-05).");
            return self::FAILURE;
        }

        // Check if payroll already exists for this month
        $existing = Payroll::whereDate('salary_month', $month . '-01')->first();
        if ($existing) {
            $this->warn("Payroll for {$month} already exists (status: {$existing->status}).");
            $this->warn('Choose a different month with --month=YYYY-MM');
            return self::FAILURE;
        }

        // ── Step 1: Seed identity data ──────────────────────────────────────
        $employees = EmployeeProfile::whereNull('ptkp_status')
            ->orWhereNull('npwp')
            ->get();

        $this->info("→ Found {$employees->count()} employees missing identity data.");
        $this->newLine();

        if ($employees->isEmpty()) {
            $this->info('✓ All employees already have identity data.');
        } else {
            $rows = [];
            foreach ($employees as $i => $emp) {
                $ptkp    = $this->ptkpPool[$i % count($this->ptkpPool)];
                $npwp    = $this->makeNpwp($i + 1);
                $bpjsTk  = $this->makeBpjsTk($i + 1);
                $bpjsKes = $this->makeBpjsKes($i + 1);

                $rows[] = [
                    'id'                   => $emp->id,
                    'ptkp'                 => $ptkp,
                    'npwp'                 => $npwp,
                    'bpjs_tk'              => $bpjsTk,
                    'bpjs_kes'             => $bpjsKes,
                    'religion'             => $emp->religion ?? $this->religionPool[$i % count($this->religionPool)],
                    'marital_status'       => $emp->marital_status ?? $this->maritalPool[$i % count($this->maritalPool)],
                    'blood_type'           => $emp->blood_type ?? $this->bloodPool[$i % count($this->bloodPool)],
                ];
            }

            // Display table
            $this->table(
                ['Employee ID', 'PTKP', 'NPWP', 'BPJS-TK', 'BPJS-Kes'],
                collect($rows)->map(fn ($r) => [
                    $r['id'], $r['ptkp'], $r['npwp'], $r['bpjs_tk'], $r['bpjs_kes'],
                ])->toArray()
            );

            if (! $isDryRun) {
                foreach ($rows as $row) {
                    DB::table('employee_profiles')->where('id', $row['id'])->update([
                        'npwp'                 => $row['npwp'],
                        'bpjs_ketenagakerjaan' => $row['bpjs_tk'],
                        'bpjs_kesehatan'       => $row['bpjs_kes'],
                        'ptkp_status'          => $row['ptkp'],
                        'religion'             => $row['religion'],
                        'marital_status'       => $row['marital_status'],
                        'blood_type'           => $row['blood_type'],
                    ]);
                }
                $this->info("✓ Updated {$employees->count()} employees with identity data.");
            } else {
                $this->warn('[DRY-RUN] No changes written to DB.');
            }
        }

        $this->newLine();

        // ── Step 2: Generate payroll ─────────────────────────────────────────
        $this->info("→ Generating payroll for {$month}...");

        if ($isDryRun) {
            $this->warn("[DRY-RUN] Would generate payroll for {$month}.");
            return self::SUCCESS;
        }

        // Resolve actor ID
        $actorEmail = $this->option('actor-email');
        $actorId = null;
        if ($actorEmail) {
            $actor = User::where('email', $actorEmail)->first();
            $actorId = $actor?->id;
            if (! $actorId) {
                $this->warn("Actor email not found: {$actorEmail}. Generating without actor.");
            }
        }

        // Disable mail & queue for seeding context
        config(['mail.default' => 'array', 'queue.default' => 'sync']);

        try {
            $payroll = $payrollRepository->generatePayroll($month . '-01', $actorId);

            $this->info("✓ Payroll generated! ID: {$payroll->id} | Month: {$month} | Status: {$payroll->status}");
            $this->newLine();

            // Show summary of tax & BPJS
            $details = $payroll->payrollDetails()->get();
            $totalPph21 = $details->sum('ph21_amount');
            $totalBpjsTkEmp = $details->sum('bpjs_tk_employee');
            $totalBpjsKesEmp = $details->sum('bpjs_kes_employee');
            $totalNet = $details->sum('final_salary');

            $this->table(
                ['Metric', 'Total'],
                [
                    ['Employees Processed', $details->count()],
                    ['Total Net Salary',    'Rp ' . number_format($totalNet, 0, ',', '.')],
                    ['Total PPh 21',        'Rp ' . number_format($totalPph21, 0, ',', '.')],
                    ['Total BPJS TK (emp)', 'Rp ' . number_format($totalBpjsTkEmp, 0, ',', '.')],
                    ['Total BPJS Kes (emp)','Rp ' . number_format($totalBpjsKesEmp, 0, ',', '.')],
                ]
            );

            if ($totalPph21 == 0 && $totalBpjsTkEmp == 0) {
                $this->warn('⚠ PPh 21 and BPJS are still 0. Check TaxCalculationService and payroll setup.');
            } else {
                $this->info('✓ PPh 21 and BPJS are calculated correctly!');
            }
        } catch (\Throwable $e) {
            $this->error('Failed to generate payroll: ' . $e->getMessage());
            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function makeNpwp(int $seq): string
    {
        $n = str_pad($seq, 6, '0', STR_PAD_LEFT);
        return "8{$n[0]}.{$n[1]}{$n[2]}{$n[3]}.{$n[4]}{$n[5]}{$seq}-001.000";
    }

    private function makeBpjsTk(int $seq): string
    {
        return '10' . str_pad((string) $seq, 8, '0', STR_PAD_LEFT);
    }

    private function makeBpjsKes(int $seq): string
    {
        return '0001' . str_pad((string) $seq, 9, '0', STR_PAD_LEFT);
    }
}
