<?php

namespace App\Http\Requests\Performance;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // handled by middleware
    }

    public function rules(): array
    {
        return [
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'goal_type' => 'sometimes|in:okr,kpi,development,project',
            'category' => 'nullable|string|max:255',
            'target_value' => 'nullable|string|max:255',
            'current_value' => 'nullable|string|max:255',
            'unit' => 'nullable|string|max:255',
            'weight' => 'nullable|numeric|min:0',
            'start_date' => 'sometimes|date',
            'due_date' => 'sometimes|date|after:start_date',
            'status' => 'sometimes|in:not_started,in_progress,at_risk,completed,cancelled',
            'completion_percentage' => 'nullable|integer|min:0|max:100',
            'linked_review_id' => 'nullable|exists:performance_reviews,id',
        ];
    }
}
