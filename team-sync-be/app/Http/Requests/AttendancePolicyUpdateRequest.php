<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class AttendancePolicyUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'work_start_time' => 'sometimes|date_format:H:i:s',
            'work_end_time' => 'sometimes|date_format:H:i:s',
            'work_days_per_week' => 'sometimes|integer|min:1|max:7',
            'default_working_weekdays' => 'sometimes|array',
            'default_working_weekdays.*' => 'string|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'late_grace_minutes' => 'sometimes|integer|min:0|max:120',
            'half_day_min_hours' => 'sometimes|numeric|min:0|max:12',
            'warning_absent_pct' => 'sometimes|numeric|min:0|max:100',
        ];
    }
}
