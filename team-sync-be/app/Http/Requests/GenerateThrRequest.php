<?php

namespace App\Http\Requests;

use App\Models\ThrPayroll;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateThrRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'religion_event' => [
                'required',
                'string',
                Rule::in(array_values(ThrPayroll::RELIGION_EVENT_MAP)),
            ],
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'religion_holiday_date' => ['required', 'date', 'after:today'],
            'income_due_date' => ['sometimes', 'date_format:Y-m-d', 'before_or_equal:religion_holiday_date'],
            'planned_credit_date' => ['sometimes', 'date_format:Y-m-d', 'before_or_equal:religion_holiday_date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages(): array
    {
        return [
            'religion_event.in' => 'Invalid religion event. Valid options: '.implode(', ', array_values(ThrPayroll::RELIGION_EVENT_MAP)),
            'religion_holiday_date.after' => 'Holiday date must be in the future.',
        ];
    }
}
