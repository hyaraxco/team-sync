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
        return ['notes' => ['nullable', 'string'], 'final_salary' => ['nullable', 'numeric', 'min:0']];
    }
}
