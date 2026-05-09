<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class PayrollComparisonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['months' => ['required', 'array', 'min:2'], 'months.*' => ['required', 'date_format:Y-m']];
    }
}
