<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PayrollApprovalPolicyUpdateRequest extends FormRequest
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
            'name' => 'sometimes|required|string|max:255',
            'min_amount' => 'sometimes|required|numeric|min:0',
            'max_amount' => 'nullable|numeric|min:0',
            'required_role' => 'sometimes|required|string|max:100',
            'approval_order' => 'sometimes|required|integer|min:1',
            'is_active' => 'nullable|boolean',
        ];
    }
}
