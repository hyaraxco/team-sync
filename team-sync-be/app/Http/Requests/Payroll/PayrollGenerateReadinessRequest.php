<?php

namespace App\Http\Requests\Payroll;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

class PayrollGenerateReadinessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'salary_month' => [
                'required',
                'date_format:Y-m',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value > now()->format('Y-m')) {
                        $fail("The {$attribute} cannot be in the future.");
                    }
                },
            ],
        ];
    }
}
