<?php

namespace Database\Seeders;

use App\Models\BpjsRate;
use Illuminate\Database\Seeder;

class BpjsRateSeeder extends Seeder
{
    /**
     * BPJS Ketenagakerjaan & Kesehatan rates.
     * JKK uses risk level I (0.24%) — typical for IT/office-based companies.
     */
    public function run(): void
    {
        $rates = [
            [
                'component' => 'jht',
                'employee_rate' => 2.00,
                'employer_rate' => 3.70,
                'max_salary_base' => null,
                'description' => 'Jaminan Hari Tua',
            ],
            [
                'component' => 'jkk',
                'employee_rate' => 0.00,
                'employer_rate' => 0.24,
                'max_salary_base' => null,
                'description' => 'Jaminan Kecelakaan Kerja (Risk Level I)',
            ],
            [
                'component' => 'jkm',
                'employee_rate' => 0.00,
                'employer_rate' => 0.30,
                'max_salary_base' => null,
                'description' => 'Jaminan Kematian',
            ],
            [
                'component' => 'jp',
                'employee_rate' => 1.00,
                'employer_rate' => 2.00,
                'max_salary_base' => 10_042_300,
                'description' => 'Jaminan Pensiun',
            ],
            [
                'component' => 'bpjs_kesehatan',
                'employee_rate' => 1.00,
                'employer_rate' => 4.00,
                'max_salary_base' => 12_000_000,
                'description' => 'BPJS Kesehatan',
            ],
        ];

        foreach ($rates as $rate) {
            BpjsRate::updateOrCreate(
                ['component' => $rate['component']],
                $rate
            );
        }
    }
}
