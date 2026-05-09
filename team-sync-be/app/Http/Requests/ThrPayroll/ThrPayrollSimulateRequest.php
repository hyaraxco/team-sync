<?php

namespace App\Http\Requests\ThrPayroll;

use Illuminate\Foundation\Http\FormRequest;

class ThrPayrollSimulateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['event_name' => ['required', 'string'], 'event_date' => ['required', 'date'], 'religion' => ['nullable', 'string']];
    }
}
