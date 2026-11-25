<?php

namespace Database\Seeders;

use App\Models\BpjsRate;
use Illuminate\Database\Seeder;

class BpjsRateSeeder extends Seeder
{
    /**
     * BPJS Ketenagakerjaan & Kesehatan rates.
     * JKK rates cover all five classes; payroll requires the assigned class separately.
     *
     * JP ceiling sources:
     * - Pre-Mar 2025: Rp 10.042.300 (PP 45/2015 prior adjustments)
     * - Mar 2025: Rp 10.547.400 (BPJS TK circular B/0456/02/2025)
     * - Mar 2026: Rp 11.086.300 (BPJS TK circular B/1226/02/2026)
     */
    public function run(): void
    {
        $rates = [
            [
                'component' => 'jht',
                'employee_rate' => 2.00,
                'employer_rate' => 3.70,
                'max_salary_base' => null,
                'effective_date' => '2020-01-01',
                'valid_until' => null,
                'description' => 'Jaminan Hari Tua',
            ],
            [
                'component' => 'jkm',
                'employee_rate' => 0.00,
                'employer_rate' => 0.30,
                'max_salary_base' => null,
                'effective_date' => '2020-01-01',
                'valid_until' => null,
                'description' => 'Jaminan Kematian',
            ],
            [
                'component' => 'jp',
                'employee_rate' => 1.00,
                'employer_rate' => 2.00,
                'max_salary_base' => 10_042_300,
                'effective_date' => '2020-01-01',
                'valid_until' => '2025-02-28',
                'description' => 'Jaminan Pensiun (s/d Feb 2025)',
            ],
            [
                'component' => 'jp',
                'employee_rate' => 1.00,
                'employer_rate' => 2.00,
                'max_salary_base' => 10_547_400,
                'effective_date' => '2025-03-01',
                'valid_until' => '2026-02-28',
                'description' => 'Jaminan Pensiun (Mar 2025 s/d Feb 2026)',
            ],
            [
                'component' => 'jp',
                'employee_rate' => 1.00,
                'employer_rate' => 2.00,
                'max_salary_base' => 11_086_300,
                'effective_date' => '2026-03-01',
                'valid_until' => null,
                'description' => 'Jaminan Pensiun (Mar 2026+)',
            ],
            [
                'component' => 'bpjs_kesehatan',
                'employee_rate' => 1.00,
                'employer_rate' => 4.00,
                'max_salary_base' => 12_000_000,
                'effective_date' => '2020-01-01',
                'valid_until' => null,
                'description' => 'BPJS Kesehatan',
            ],
        ];
        foreach ([1 => 0.24, 2 => 0.54, 3 => 0.89, 4 => 1.27, 5 => 1.74] as $class => $employerRate) {
            $rates[] = [
                'component' => 'jkk',
                'risk_class' => $class,
                'employee_rate' => 0,
                'employer_rate' => $employerRate,
                'max_salary_base' => null,
                'effective_date' => '2020-01-01',
                'valid_until' => null,
                'description' => "Jaminan Kecelakaan Kerja (Risk Class {$class})",
            ];
        }

        foreach ($rates as $rate) {
            $riskClass = $rate['risk_class'] ?? 0;
            $exists = BpjsRate::query()->where('component', $rate['component'])->where('risk_class', $riskClass)
                ->whereDate('effective_date', $rate['effective_date'])->exists();
            if (! $exists) {
                BpjsRate::create($rate);
            }
        }
    }
}
