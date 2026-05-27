<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class HybridScheduleOverrideListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search'    => 'nullable|string',
            'status'    => 'nullable|string|in:pending,approved,rejected',
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to'   => 'nullable|date_format:Y-m-d|after_or_equal:date_from',
            'per_page'  => 'nullable|integer|min:1|max:100',
        ];
    }
}
