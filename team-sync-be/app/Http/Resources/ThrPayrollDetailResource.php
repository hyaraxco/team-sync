<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ThrPayrollDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'thr_payroll_id' => $this->thr_payroll_id,
            'staff_member_id' => $this->staff_member_id,
            'religion' => $this->religion,
            'monthly_salary' => (float) $this->monthly_salary,
            'join_date' => $this->join_date?->format('Y-m-d'),
            'tenure_months' => $this->tenure_months,
            'proration_factor' => (float) $this->proration_factor,
            'gross_thr_amount' => (float) $this->gross_thr_amount,
            'pph21_amount' => (float) $this->pph21_amount,
            'net_thr_amount' => (float) $this->net_thr_amount,
            'ptkp_status' => $this->ptkp_status,
            'has_npwp' => $this->has_npwp,
            'tax_calculation_meta' => $this->tax_calculation_meta,
            'notes' => $this->notes,
            'staff_member' => $this->whenLoaded('staffMember', function () {
                return [
                    'id' => $this->staffMember->id,
                    'full_name' => $this->staffMember->full_name,
                    'employee_id' => $this->staffMember->employee_id,
                    'user' => $this->staffMember->relationLoaded('user') ? [
                        'id' => $this->staffMember->user?->id,
                        'name' => $this->staffMember->user?->name,
                        'profile_photo' => $this->staffMember->user?->profile_photo,
                    ] : null,
                ];
            }),
            'created_at' => $this->created_at,
        ];
    }
}
