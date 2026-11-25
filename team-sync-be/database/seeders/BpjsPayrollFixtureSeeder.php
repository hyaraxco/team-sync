<?php

namespace Database\Seeders;

use App\Models\JobInformation;
use App\Models\PayrollBpjsPeriod;
use Illuminate\Database\Seeder;
use LogicException;

class BpjsPayrollFixtureSeeder extends Seeder
{
    public function run(string $period, ?array $salaries = null): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new LogicException('Synthetic BPJS payroll data can only be loaded in local or testing environments.');
        }

        app(BpjsRateSeeder::class)->run();
        $salaries ??= JobInformation::where('status', 'active')->pluck('monthly_salary', 'staff_member_id')->all();
        $employees = [];
        foreach ($salaries as $id => $salary) {
            if ($salary > 0) {
                $employees[$id] = [
                    'coverage' => 'private_salaried_ppu_all_five',
                    'basic_wage' => (int) $salary,
                    'fixed_allowances' => 0,
                    'health_floor' => 3_000_000,
                    'floor_type' => 'umk',
                    'region' => 'Synthetic test jurisdiction',
                    'floor_ref' => 'Synthetic test decree',
                ];
            }
        }
        if ($employees !== []) {
            $record = PayrollBpjsPeriod::query()->firstOrNew(['contribution_month' => $period]);
            $facts = $record->facts ?? [
                'employer_class' => 'ordinary',
                'jkk_class' => 1,
                'jkk_assignment_ref' => 'Synthetic test assignment',
                'employees' => [],
            ];
            $facts['employees'] += $employees;
            $record->fill(['facts' => $facts])->save();
        }
    }
}
