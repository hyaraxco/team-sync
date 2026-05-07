<?php

namespace App\Http\Requests;

use App\Models\AttendancePolicyMismatch;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttendancePolicyMismatchListRequest extends FormRequest
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
            'status' => ['nullable', 'string', Rule::in([
                AttendancePolicyMismatch::STATUS_PENDING_REVIEW,
                AttendancePolicyMismatch::STATUS_ACKNOWLEDGED,
                AttendancePolicyMismatch::STATUS_ESCALATED_HR,
                AttendancePolicyMismatch::STATUS_RESOLVED,
            ])],
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }
}
