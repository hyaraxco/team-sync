<?php

namespace App\Http\Requests\Performance;

use Illuminate\Foundation\Http\FormRequest;

class CreateReviewCycleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // handled by middleware
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'cycle_type' => 'required|in:quarterly,semi_annual,annual,probation',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'review_period_start' => 'required|date',
            'review_period_end' => 'required|date|after:review_period_start|before:start_date',
            'self_assessment_deadline' => 'nullable|date|after_or_equal:start_date|before_or_equal:end_date',
            'manager_assessment_deadline' => 'nullable|date|after_or_equal:self_assessment_deadline|before_or_equal:end_date',
            'calibration_deadline' => 'nullable|date|after_or_equal:manager_assessment_deadline|before_or_equal:end_date',
        ];
    }
}
