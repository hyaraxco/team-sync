<?php

namespace App\Http\Requests\PerformanceReview;

use Illuminate\Foundation\Http\FormRequest;

class PerformanceReviewTemplateUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['name' => ['sometimes', 'required', 'string', 'max:255'], 'description' => ['nullable', 'string'], 'is_default' => ['nullable', 'boolean'], 'sections' => ['sometimes', 'array', 'min:1'], 'sections.*.name' => ['required', 'string'], 'sections.*.description' => ['nullable', 'string'], 'sections.*.weight' => ['nullable', 'numeric', 'min:0', 'max:100']];
    }
}
