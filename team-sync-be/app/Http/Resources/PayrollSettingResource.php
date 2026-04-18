<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayrollSettingResource extends JsonResource
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
            'payday_day' => $this->payday_day,
            'attendance_cutoff_day' => $this->attendance_cutoff_day,
            'working_days_mode' => $this->working_days_mode,
            'default_working_days' => $this->default_working_days,
            'absent_deduction_rate' => (float) $this->absent_deduction_rate,
            'rounding_mode' => $this->rounding_mode,
            'rounding_unit' => $this->rounding_unit,
            'note_template' => $this->note_template,
            'payroll_bank_name' => $this->payroll_bank_name,
            'payroll_bank_code' => $this->payroll_bank_code,
            'active_version_id' => $this->latestVersion?->id,
            'active_version' => $this->whenLoaded(
                'latestVersion',
                fn () => $this->latestVersion
                    ? new PayrollSettingVersionResource($this->latestVersion)
                    : null
            ),
            'updated_at' => $this->updated_at,
            'updated_by' => $this->updatedBy ? [
                'id' => $this->updatedBy->id,
                'name' => $this->updatedBy->name,
                'email' => $this->updatedBy->email,
            ] : null,
        ];
    }
}
