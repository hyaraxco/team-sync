<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreOvertimeRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'staff_member_id' => ['required', 'integer', 'exists:staff_member_profiles,id'],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'overtime_type' => ['required', 'string', 'in:workday,weekend,holiday'],
            'attendance_id' => ['nullable', 'integer', 'exists:attendances,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'staff_member_id' => 'Staff member',
            'date' => 'Overtime date',
            'start_time' => 'Start time',
            'end_time' => 'End time',
            'overtime_type' => 'Overtime type',
            'attendance_id' => 'Attendance record',
            'notes' => 'Notes',
        ];
    }
}
