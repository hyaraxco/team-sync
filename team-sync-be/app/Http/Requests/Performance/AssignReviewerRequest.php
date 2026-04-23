<?php

namespace App\Http\Requests\Performance;

use Illuminate\Foundation\Http\FormRequest;

class AssignReviewerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorized via middleware
    }

    public function rules(): array
    {
        return [
            'reviewer_id' => ['required', 'integer', 'exists:staff_member_profiles,id'],
        ];
    }
}
