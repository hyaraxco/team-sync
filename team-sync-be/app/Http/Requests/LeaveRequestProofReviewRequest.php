<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class LeaveRequestProofReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'proof_review_status' => 'required|string|in:approved,rejected',
            'proof_review_notes' => 'nullable|string|max:1000',
        ];
    }
}
