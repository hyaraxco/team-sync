<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AcceptNikTaxpayerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('payroll-process', 'sanctum') ?? false;
    }

    public function rules(): array
    {
        return [];
    }
}
