<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayrollResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'salary_month' => $this->salary_month,
            'period' => $this->salary_month, // Alias for compatibility
            'payroll_setting_version_id' => $this->payroll_setting_version_id,
            'is_legacy_settings_version' => $this->payroll_setting_version_id === null,
            'payroll_setting_version' => $this->whenLoaded(
                'payrollSettingVersion',
                fn () => $this->payrollSettingVersion
                    ? new PayrollSettingVersionResource($this->payrollSettingVersion)
                    : null
            ),
            'payment_date' => $this->payment_date,
            'status' => $this->status,

            // Only include full details if relationship is loaded
            'payroll_details' => PayrollDetailResource::collection($this->whenLoaded('payrollDetails')),

            // Use withCount if available, otherwise calculate from loaded relationship
            'employee_count' => $this->when(
                isset($this->payroll_details_count),
                $this->payroll_details_count,
                fn () => $this->whenLoaded('payrollDetails', fn () => $this->payrollDetails->count())
            ),

            // Calculate total amount from loaded details (if available)
            'total_amount' => $this->whenLoaded('payrollDetails', function () {
                return $this->payrollDetails->sum('final_salary');
            }),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
