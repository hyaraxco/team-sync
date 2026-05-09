<?php

namespace App\Http\Requests\PayrollSetting;

use Illuminate\Foundation\Http\FormRequest;

class PayrollSettingUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['working_days_per_month' => ['nullable', 'integer', 'min:1', 'max:31'], 'rounding_mode' => ['nullable', 'string', 'in:floor,ceil,none'], 'deduction_formula' => ['nullable', 'string'], 'cutoff_date' => ['nullable', 'integer', 'min:1', 'max:31']];
    }
}
