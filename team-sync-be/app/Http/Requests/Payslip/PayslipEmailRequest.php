<?php

namespace App\Http\Requests\Payslip;

use Illuminate\Foundation\Http\FormRequest;

class PayslipEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['email' => ['nullable', 'email']];
    }
}
