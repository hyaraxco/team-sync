<?php

namespace App\Http\Requests\Performance;

use Illuminate\Foundation\Http\FormRequest;

class CreateFeedbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // handled by middleware
    }

    public function rules(): array
    {
        return [
            'staff_member_id' => 'required|exists:staff_member_profiles,id',
            'feedback_type' => 'required|in:positive,constructive,general',
            'category' => 'nullable|string|max:255',
            'content' => 'required|string',
            'is_private' => 'boolean',
            'linked_goal_id' => 'nullable|exists:performance_goals,id',
        ];
    }
}
