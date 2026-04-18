<?php

namespace Database\Seeders;

use App\Models\EmployeeProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds Indonesian identity fields (NPWP, BPJS, PTKP) for existing employees.
 * Safe to run multiple times — only updates employees where ptkp_status is null.
 *
 * Run: php artisan db:seed --class=EmployeeIdentitySeeder
 */
class EmployeeIdentitySeeder extends Seeder
{
    // PTKP 2024 rates — used as rotation pool for demo data
    private array $ptkpPool = [
        'TK/0', 'TK/1', 'K/0', 'K/1', 'K/2', 'K/3',
        'TK/0', 'K/0', 'TK/0', 'K/1', // weighted toward common statuses
    ];

    private array $religionPool = [
        'islam', 'islam', 'islam', 'kristen', 'katolik', 'hindu', 'budha',
    ];

    private array $maritalPool = [
        'married', 'married', 'single', 'single', 'married',
    ];

    private array $bloodPool = ['A', 'B', 'AB', 'O'];

    public function run(): void
    {
        $employees = EmployeeProfile::whereNull('ptkp_status')->get();

        if ($employees->isEmpty()) {
            $this->command->info('All employees already have ptkp_status set. Skipping.');
            return;
        }

        $this->command->info("Seeding identity data for {$employees->count()} employees...");

        $updatedCount = 0;

        foreach ($employees as $i => $emp) {
            $npwp = $this->generateNpwp($i + 1);
            $bpjsTk = $this->generateBpjsTk($i + 1);
            $bpjsKes = $this->generateBpjsKes($i + 1);
            $ptkp = $this->ptkpPool[$i % count($this->ptkpPool)];

            DB::table('employee_profiles')
                ->where('id', $emp->id)
                ->update([
                    'npwp'                  => $npwp,
                    'bpjs_ketenagakerjaan'  => $bpjsTk,
                    'bpjs_kesehatan'        => $bpjsKes,
                    'ptkp_status'           => $ptkp,
                    'religion'              => $emp->religion ?? $this->religionPool[$i % count($this->religionPool)],
                    'marital_status'        => $emp->marital_status ?? $this->maritalPool[$i % count($this->maritalPool)],
                    'blood_type'            => $emp->blood_type ?? $this->bloodPool[$i % count($this->bloodPool)],
                ]);

            $this->command->line("  [{$emp->id}] PTKP={$ptkp} | NPWP={$npwp} | BPJS-TK={$bpjsTk}");
            $updatedCount++;
        }

        $this->command->info("✓ Updated {$updatedCount} employees with Indonesian identity data.");
        $this->command->newLine();
        $this->command->info('You can now generate payroll for May 2026 (2026-05) to test PPh 21 & BPJS auto-calculation.');
    }

    private function generateNpwp(int $seq): string
    {
        // Format: XX.XXX.XXX.X-XXX.XXX
        $base = str_pad($seq, 6, '0', STR_PAD_LEFT);
        $checkDigit = $seq % 10;
        return "8{$base[0]}.{$base[1]}{$base[2]}{$base[3]}.{$base[4]}{$base[5]}{$checkDigit}-{$base[0]}{$base[1]}{$base[2]}.000";
    }

    private function generateBpjsTk(int $seq): string
    {
        // Format: 10 digit
        return str_pad('10' . $seq, 10, '0', STR_PAD_RIGHT);
    }

    private function generateBpjsKes(int $seq): string
    {
        // Format: 13 digit
        return str_pad('0001' . $seq, 13, '0', STR_PAD_RIGHT);
    }
}
