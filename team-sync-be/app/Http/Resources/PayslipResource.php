<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayslipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $employee = $this->employee;
        $user = $employee?->user;
        $jobInformation = $employee?->jobInformation;
        $payroll = $this->payroll;
        $basicSalary = (float) $this->original_salary;
        $netSalary = (float) $this->final_salary;
        $totalDeductions = (float) ($this->deduction_amount ?? max(0, $basicSalary - $netSalary));

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
            'allowances' => 0,
            'bonus' => 0,
            'gross_salary' => $basicSalary,
            'tax' => 0,
            'insurance' => 0,
            'other_deductions' => $totalDeductions,
            'total_deductions' => $totalDeductions,
            'adjustments' => $adjustments,
            'adjustment_total_amount' => (float) $adjustmentTotalAmount,
            'net_salary' => $netSalary,
            'notes' => $this->notes,
        ];
    }
}
