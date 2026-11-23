<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayslipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $employee = $this->staffMember;
        $user = $employee?->user;
        $jobInformation = $employee?->jobInformation;
        $payroll = $this->payroll;

        $basicSalary = (float) $this->original_salary;
        $overtimeAmount = (float) ($this->overtime_amount ?? 0);
        $allowances = 0.0;
        $bonus = 0.0;
        $grossSalary = $basicSalary + $overtimeAmount + $allowances + $bonus;

        $tax = (float) ($this->pph21_amount ?? 0);
        $insurance = (float) (($this->bpjs_tk_employee ?? 0) + ($this->bpjs_kes_employee ?? 0));
        $absenceDeductions = (float) ($this->deduction_amount ?? 0);
        $otherDeductions = $absenceDeductions;
        $totalDeductions = $tax + $insurance + $otherDeductions;
        $netSalary = (float) $this->final_salary;

        $adjustments = $this->whenLoaded('appliedAdjustments', function () {
            return $this->appliedAdjustments
                ->map(function ($adjustment) {
                    return [
                        'id' => $adjustment->id,
                        'adjustment_kind' => $adjustment->adjustment_kind,
                        'days_delta' => (float) $adjustment->days_delta,
                        'amount_delta' => (float) $adjustment->amount_delta,
                        'reason' => $adjustment->reason,
                        'status' => $adjustment->status,
                        'source_period_id' => $adjustment->source_period_id,
                        'target_period_id' => $adjustment->target_period_id,
                    ];
                })
                ->values()
                ->all();
        }, []);

        $adjustmentTotalAmount = $this->whenLoaded('appliedAdjustments', function () {
            return round((float) $this->appliedAdjustments->sum(function ($adjustment) {
                return (float) $adjustment->amount_delta;
            }), 2);
        }, 0.0);

        return [
            'id' => $this->getKey(),
            'payroll_id' => $this->payroll_id,
            'status' => $payroll?->status ?? 'paid',
            'period' => $payroll?->salary_month?->format('Y-m-d'),
            'payment_date' => $payroll?->payment_date?->format('Y-m-d'),
            'created_at' => $this->created_at,
            'employee_name' => $user?->name,
            'employee_email' => $user?->email,
            'employee_code' => $employee?->code,
            'department' => $jobInformation?->team?->name ?? $jobInformation?->job_title,
            'company_name' => config('app.name'),
            'company_address' => 'Indonesia',
            'basic_salary' => $basicSalary,
            'overtime_amount' => $overtimeAmount,
            'allowances' => $allowances,
            'bonus' => $bonus,
            'gross_salary' => $grossSalary,
            'tax' => $tax,
            'insurance' => $insurance,
            'other_deductions' => $otherDeductions,
            'total_deductions' => $totalDeductions,
            'adjustments' => $adjustments,
            'adjustment_total_amount' => (float) $adjustmentTotalAmount,
            'net_salary' => $netSalary,
            'notes' => $this->notes,
        ];
    }
}
