<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ResolveReconciliationExceptionRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'staff_member_id' => ['required', 'integer', 'exists:staff_member_profiles,id'],
            'exception_type' => ['required', 'string', 'max:100'],
            'resolution_action' => ['required', 'string', 'in:acknowledged,resolved,waived'],
            'reason' => ['required', 'string', 'min:10', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return [
            'staff_member_id' => 'Staff member',
            'exception_type' => 'Exception type',
            'resolution_action' => 'Resolution action',
            'reason' => 'Reason',
        ];
    }
}
