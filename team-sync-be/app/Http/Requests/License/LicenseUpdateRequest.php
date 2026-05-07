<?php

namespace App\Http\Requests\License;

use Illuminate\Foundation\Http\FormRequest;

class LicenseUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'is_active' => ['sometimes', 'boolean'],
            'features' => ['sometimes', 'array'],
            'features.*' => ['string'],
            'max_users' => ['sometimes', 'integer', 'min:1'],
            'current_users' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
