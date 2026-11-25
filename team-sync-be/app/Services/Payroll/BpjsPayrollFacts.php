<?php

namespace App\Services\Payroll;

use App\DTOs\BpjsPayrollInput;
use App\Exceptions\PayrollStateException;
use App\Models\PayrollBpjsPeriod;
use Illuminate\Support\Facades\Validator;

final class BpjsPayrollFacts
{
    public static function load(string $period): array
    {
        $record = PayrollBpjsPeriod::query()->where('contribution_month', $period)->first();
        if (! $record) {
            throw new PayrollStateException("Verified BPJS payroll inputs are missing for {$period}.");
        }

        return self::validate($period, $record->facts);
    }

    public static function validate(string $period, array $facts): array
    {
        $validator = Validator::make(['period' => $period, 'facts' => $facts], [
            'period' => ['required', 'date_format:Y-m', 'after_or_equal:2020-01'],
            'facts' => ['required', 'array:employer_class,jkk_class,jkk_assignment_ref,employees'],
            'facts.jkk_class' => ['required', 'integer:strict', 'between:1,5'],
            'facts.employer_class' => ['required', 'in:ordinary'],
            'facts.jkk_assignment_ref' => ['required', 'string', 'max:255'],
            'facts.employees' => ['required', 'array'],
            'facts.employees.*' => ['required', 'array:coverage,basic_wage,fixed_allowances,health_floor,floor_type,region,floor_ref'],
            'facts.employees.*.coverage' => ['required', 'in:private_salaried_ppu_all_five'],
            'facts.employees.*.basic_wage' => ['required', 'integer:strict', 'min:1', 'max:9999999999'],
            'facts.employees.*.fixed_allowances' => ['required', 'integer:strict', 'min:0', 'max:9999999999'],
            'facts.employees.*.health_floor' => ['required', 'integer:strict', 'min:1'],
            'facts.employees.*.floor_type' => ['required', 'in:umk,ump'],
            'facts.employees.*.region' => ['required', 'string', 'max:120'],
            'facts.employees.*.floor_ref' => ['required', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            throw new PayrollStateException('Invalid BPJS period inputs: '.$validator->errors()->first());
        }

        foreach (array_keys($facts['employees']) as $id) {
            if (! is_int($id) || $id < 1) {
                throw new PayrollStateException('BPJS employees must be keyed by staff member ID.');
            }
        }

        return $facts;
    }

    public static function forEmployee(array $facts, int $employeeId, float $contractualPay): BpjsPayrollInput
    {
        $employee = $facts['employees'][$employeeId] ?? null;
        if ($employee === null) {
            throw new PayrollStateException("Verified BPJS coverage, wage and floor inputs are missing for staff member {$employeeId}.");
        }
        if ($employee['basic_wage'] + $employee['fixed_allowances'] > $contractualPay) {
            throw new PayrollStateException("BPJS basic wage and fixed allowances exceed recorded monthly pay for staff member {$employeeId}.");
        }

        return new BpjsPayrollInput($employee['basic_wage'], $employee['fixed_allowances'], $facts['jkk_class'], $employee['health_floor']);
    }
}
