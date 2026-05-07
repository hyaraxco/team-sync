<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class LeaveEntitlementUpdateRequest extends FormRequest
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
            'is_eligible' => 'sometimes|boolean',
            'is_paid' => 'sometimes|boolean',
            'quota_scope' => 'sometimes|nullable|string|in:annual,per_occurrence,unlimited,unpaid',
            'quota_days' => 'sometimes|nullable|numeric|min:0',
            'carry_over_max_days' => 'sometimes|nullable|integer|min:0',
            'requires_attachment' => 'sometimes|boolean',
            'requires_reason' => 'sometimes|boolean',
            'allowed_mime_types' => 'sometimes|nullable|array',
            'allowed_mime_types.*' => 'string',
            'max_attachment_size_kb' => 'sometimes|nullable|integer|min:0',
        ];
    }
}
