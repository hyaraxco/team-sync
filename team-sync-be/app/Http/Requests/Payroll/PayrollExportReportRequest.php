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
        return ['month' => ['nullable', 'date_format:Y-m'], 'year' => ['nullable', 'integer'], 'type' => ['nullable', 'string', 'in:summary,detail'], 'status' => ['nullable', 'string']];
    }
}
