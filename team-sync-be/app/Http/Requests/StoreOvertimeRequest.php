<?php

namespace App\Http\Requests;

use App\Models\Attendance;
use App\Models\OvertimeRecord;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class StoreOvertimeRequest extends FormRequest
{
    /**
     * Prevent an IDOR where a self-service (staff) caller could pass an
     * arbitrary `staff_member_id` and create overtime on behalf of another
     * employee.
     *
     * Privileged roles (manager, HR, superadmin) legitimately create overtime
     * for other employees, so they keep the ability to supply the id.
     */
    public function prepareForValidation(): void
    {
        $user = Auth::user();

        if ($user && $user->hasRole('staff')) {
            $this->merge([
                'staff_member_id' => $user->staffMemberProfile?->id,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'staff_member_id' => ['required', 'integer', 'exists:staff_member_profiles,id'],
            'date' => [
                'required',
                'date',
                'before_or_equal:today',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (! $value || ! $this->input('staff_member_id')) {
                        return;
                    }

                    $exists = OvertimeRecord::where('staff_member_id', $this->input('staff_member_id'))
                        ->whereRaw('DATE(date) = ?', [$value])
                        ->exists();

                    if ($exists) {
                        $fail('An overtime record already exists for this employee on the selected date.');
                    }
                },
            ],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'overtime_type' => ['required', 'string', 'in:workday,weekend,holiday'],
            'attendance_id' => [
                'nullable',
                'integer',
                'exists:attendances,id',
                function (string $attribute, mixed $value, \Closure $fail) {
                    if (! $value || ! $this->input('staff_member_id') || ! $this->input('date')) {
                        return;
                    }

                    $matches = Attendance::query()
                        ->whereKey($value)
                        ->where('staff_member_id', $this->input('staff_member_id'))
                        ->whereRaw('DATE(date) = ?', [$this->input('date')])
                        ->exists();

                    if (! $matches) {
                        $fail('The selected attendance record must belong to the same staff member and overtime date.');
                    }
                },
            ],
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
