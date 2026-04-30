<?php

namespace App\Http\Resources;

use App\Models\PayrollReconciliationResolution;
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
            'correction_count' => (int) ($this->correction_count ?? 0),

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

            // Lightweight reconciliation summary for dashboard badges
            'reconciliation_summary' => $this->whenLoaded('payrollDetails', function () {
                if (! in_array($this->status, ['pending', 'approved'], true)) {
                    return null;
                }

                return $this->computeLightweightReconciliationSummary();
            }),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function computeLightweightReconciliationSummary(): array
    {
        $criticalCount = 0;
        $warningCount = 0;

        foreach ($this->payrollDetails as $detail) {
            $bankInfo = $detail->staffMember?->bankInformation;
            $hasMissingBank = ! $bankInfo
                || blank($bankInfo->bank_name)
                || blank($bankInfo->account_number)
                || blank($bankInfo->account_holder_name);

            if ($hasMissingBank) {
                $criticalCount++;
            }

            if ((float) $detail->final_salary <= 0) {
                $criticalCount++;
            }

            $originalSalary = (float) $detail->original_salary;
            $finalSalary = (float) $detail->final_salary;
            if ($originalSalary > 0 && $finalSalary > 0 && $finalSalary < ($originalSalary * 0.5)) {
                $warningCount++;
            }
        }

        // Subtract resolved critical exceptions
        $resolvedCriticalCount = PayrollReconciliationResolution::query()
            ->where('payroll_id', $this->id)
            ->whereIn('exception_type', ['missing_bank_account', 'zero_salary'])
            ->count();

        $unresolvedCriticalCount = max(0, $criticalCount - $resolvedCriticalCount);

        return [
            'critical_count' => $criticalCount,
            'unresolved_critical_count' => $unresolvedCriticalCount,
            'warning_count' => $warningCount,
        ];
    }
}
