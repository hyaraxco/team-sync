<?php

namespace App\Http\Requests\Team;

use Illuminate\Foundation\Http\FormRequest;

class TeamListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string'],
            'leader_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['nullable', 'string'],
            'department' => ['nullable', 'string'],
            'row_per_page' => ['required', 'integer', 'min:1'],
        ];
    }
}
