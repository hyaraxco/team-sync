<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AttendancePolicyMismatchResolveRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'resolution_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'resolution_notes' => 'Resolution notes',
        ];
    }
}
