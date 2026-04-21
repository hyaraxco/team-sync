<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayrollDetailResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $adjustments = $this->whenLoaded('appliedAdjustments', function () {
            return $this->appliedAdjustments
                ->map(function ($adjustment) {
                    return [
                        'id' => $adjustment->id,
                        'adjustment_kind' => $adjustment->adjustment_kind,
                        'days_delta' => (float) $adjustment->days_delta,
                        'amount_delta' => (float) $adjustment->amount_delta,
                        'reason' => $adjustment->reason,
                        'source_period_id' => $adjustment->source_period_id,
                        'target_period_id' => $adjustment->target_period_id,
                        'status' => $adjustment->status,
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
            'id' => $this->id,
            'payroll_id' => $this->payroll_id,
            'employee_id' => $this->employee_id,
            'original_salary' => (float) $this->original_salary,
            'final_salary' => (float) $this->final_salary,
            'effective_working_days' => (int) ($this->effective_working_days ?? 0),
            'daily_rate' => (float) ($this->daily_rate ?? 0),
            'attended_days' => $this->attended_days,
            'present_days' => (int) ($this->present_days ?? 0),
            'late_days' => (int) ($this->late_days ?? 0),
            'half_day_count' => (int) ($this->half_day_count ?? 0),
            'paid_leave_days' => (int) ($this->paid_leave_days ?? 0),
            'unpaid_leave_days' => (int) ($this->unpaid_leave_days ?? 0),
            'holiday_days' => (int) ($this->holiday_days ?? 0),
            'sick_days' => $this->sick_days,
            'absent_days' => $this->absent_days,
            'deduction_days' => (float) ($this->deduction_days ?? 0),
            'deduction_amount' => (float) ($this->deduction_amount ?? 0),
            'policy_mismatch_days' => (int) ($this->policy_mismatch_days ?? 0),
            'warning_flags' => $this->warning_flags ?? [],
            'adjustments' => $adjustments,
            'adjustment_total_amount' => (float) $adjustmentTotalAmount,
            'notes' => $this->notes,
            'staff_member' => new StaffMemberProfileResource($this->whenLoaded('staffMember')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
