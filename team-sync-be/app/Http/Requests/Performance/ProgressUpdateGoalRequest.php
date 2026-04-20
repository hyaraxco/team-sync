<?php

namespace App\Http\Requests\Performance;

use Illuminate\Foundation\Http\FormRequest;

class ProgressUpdateGoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // handled by middleware
    }

    public function rules(): array
    {
        return [
            'update_type' => 'required|in:progress,status_change,completion',
            'new_value' => 'nullable|string|max:255',
            'new_status' => 'nullable|in:not_started,in_progress,at_risk,completed,cancelled',
            'completion_percentage' => 'nullable|integer|min:0|max:100',
            'notes' => 'nullable|string',
        ];
    }
}
