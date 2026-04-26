<?php

namespace App\Http\Requests\HolidayCalendar;

use Illuminate\Foundation\Http\FormRequest;

class CreateHolidayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:national_holiday,collective_leave'],
            'applies_to' => ['nullable', 'array'],
            'applies_to.*' => ['string'],
        ];
    }
}
