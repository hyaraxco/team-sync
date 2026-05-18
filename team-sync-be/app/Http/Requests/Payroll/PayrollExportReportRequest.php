<?php

namespace App\Http\Requests\Payroll;

use Illuminate\Foundation\Http\FormRequest;

class PayrollExportReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'in:pending,paid,all'],
            'period_type' => ['required', 'in:monthly,yearly'],
            'report_type' => ['nullable', 'in:summary,detail'],
            'month' => ['required_if:period_type,monthly', 'nullable', 'date_format:Y-m'],
            'year' => ['required_if:period_type,yearly', 'nullable', 'digits:4'],
        ];
    }
}
