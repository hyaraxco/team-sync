<?php

namespace App\Http\Requests\HolidayCalendar;

use Illuminate\Foundation\Http\FormRequest;

class UpdateHolidayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['sometimes', 'date'],
            'name' => ['sometimes', 'string', 'max:255'],
            'type' => ['sometimes', 'string', 'in:national_holiday,collective_leave'],
            'applies_to' => ['nullable', 'array'],
            'applies_to.*' => ['string'],
        ];
    }
}
