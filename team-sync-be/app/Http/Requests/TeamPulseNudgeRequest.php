<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TeamPulseNudgeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'message' => ['nullable', 'string', 'max:500'],
        ];
    }
}
