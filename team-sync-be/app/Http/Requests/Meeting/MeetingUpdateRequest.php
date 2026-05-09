<?php

namespace App\Http\Requests\Meeting;

use Illuminate\Foundation\Http\FormRequest;

class MeetingUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['title' => ['sometimes', 'required', 'string', 'max:255'], 'description' => ['nullable', 'string'], 'scheduled_at' => ['sometimes', 'required', 'date', 'after:now'], 'duration_minutes' => ['nullable', 'integer', 'min:15'], 'meeting_link' => ['nullable', 'url'], 'departments' => ['nullable', 'array'], 'departments.*' => ['string'], 'team_ids' => ['nullable', 'array'], 'team_ids.*' => ['integer']];
    }
}
