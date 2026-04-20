<?php

namespace App\Http\Requests\Performance;

use Illuminate\Foundation\Http\FormRequest;

class SubmitSelfAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // handled by middleware
    }

    public function rules(): array
    {
        return [
            'responses' => 'required|array',
            'responses.*.section_id' => 'required|exists:performance_review_sections,id',
            'responses.*.rating' => 'required|integer|min:1|max:5',
            'responses.*.comments' => 'nullable|string',
        ];
    }
}
