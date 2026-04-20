<?php

namespace App\Http\Requests\Performance;

use Illuminate\Foundation\Http\FormRequest;

class UpdateReviewCycleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // handled by middleware
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'cycle_type' => 'sometimes|in:quarterly,semi_annual,annual,probation',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date|after:start_date',
            'review_period_start' => 'sometimes|date',
            'review_period_end' => 'sometimes|date|after:review_period_start|before:start_date',
            'status' => 'sometimes|in:draft,active,completed,cancelled',
            'self_assessment_deadline' => 'nullable|date|after_or_equal:start_date|before_or_equal:end_date',
            'manager_assessment_deadline' => 'nullable|date|after_or_equal:self_assessment_deadline|before_or_equal:end_date',
            'calibration_deadline' => 'nullable|date|after_or_equal:manager_assessment_deadline|before_or_equal:end_date',
        ];
    }
}
