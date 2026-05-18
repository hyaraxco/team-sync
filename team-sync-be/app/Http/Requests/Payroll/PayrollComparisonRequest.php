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
        return [
            'month1' => ['required', 'date_format:Y-m'],
            'month2' => ['required', 'date_format:Y-m'],
        ];
    }
}
