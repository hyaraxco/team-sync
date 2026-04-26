<?php

namespace App\Http\Requests\AttendancePeriod;

use Illuminate\Foundation\Http\FormRequest;

class CreateAttendancePeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'cutoff_date' => ['required', 'date', 'after_or_equal:start_date'],
        ];
    }
}
