<?php

namespace App\Http\Requests\Performance;

use Illuminate\Foundation\Http\FormRequest;

class CalibrateReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // handled by middleware
    }

    public function rules(): array
    {
        return [
            'responses' => 'nullable|array',
            'responses.*.section_id' => 'required|exists:performance_review_sections,id',
            'responses.*.rating' => 'required|integer|min:1|max:5',
        ];
    }
}
