<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class HybridScheduleListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => 'nullable|string',
            'status' => 'nullable|string|in:pending,approved,rejected',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }
}
