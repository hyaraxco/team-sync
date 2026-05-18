<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class PayrollUpdateDetailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string'],
            'final_salary' => ['nullable', 'integer', 'min:0'],
            'updated_at' => ['nullable', 'string'],
        ];
    }
}
