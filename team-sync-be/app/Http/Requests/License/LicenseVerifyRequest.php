<?php

namespace App\Http\Requests\License;

use Illuminate\Foundation\Http\FormRequest;

class LicenseVerifyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'license_key' => ['required', 'string'],
        ];
    }
}
