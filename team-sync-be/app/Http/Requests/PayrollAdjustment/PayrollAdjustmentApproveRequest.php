<?php

namespace App\Http\Requests\PayrollAdjustment;

use Illuminate\Foundation\Http\FormRequest;

class PayrollAdjustmentApproveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['notes' => ['nullable', 'string']];
    }
}
