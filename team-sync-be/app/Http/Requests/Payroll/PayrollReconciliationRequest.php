<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class PayrollReconciliationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['severity' => ['nullable', 'string', 'in:critical,warning'], 'type' => ['nullable', 'string', 'max:100']];
    }
}
