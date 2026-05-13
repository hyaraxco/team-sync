<?php

namespace App\Http\Requests\Performance;

use Illuminate\Foundation\Http\FormRequest;

class CreateGoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // handled by middleware
    }

    public function rules(): array
    {
        return [
            'staff_member_id' => 'required|exists:staff_member_profiles,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'goal_type' => 'required|in:okr,kpi,development,project',
            'category' => 'nullable|string|max:255',
            'target_value' => 'nullable|string|max:255',
            'current_value' => 'nullable|string|max:255',
            'unit' => 'nullable|string|max:255',
            'weight' => 'nullable|numeric|min:0',
            'start_date' => 'required|date',
            'due_date' => 'required|date|after:start_date',
            'status' => 'nullable|in:not_started,in_progress,at_risk,completed,cancelled',
            'completion_percentage' => 'nullable|integer|min:0|max:100',
            'assigned_by' => 'nullable|exists:staff_member_profiles,id',
            'linked_review_id' => 'nullable|exists:performance_reviews,id',
        ];
    }
}
