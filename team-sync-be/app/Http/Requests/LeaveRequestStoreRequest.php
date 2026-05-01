<?php

namespace App\Http\Requests;

use App\Enums\LeaveType;
use App\Models\HolidayCalendar;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class LeaveRequestStoreRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'staff_member_id' => 'required|exists:staff_member_profiles,id',
            'leave_type' => 'required|string|in:'.implode(',', array_column(LeaveType::cases(), 'value')),
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'total_days' => 'nullable|integer|min:1',
            'reason' => 'required|string|max:1000',
            'emergency_contact' => 'nullable|string|max:255',
            'status' => 'nullable|string|in:pending,approved,rejected',
        ];
    }

    public function attributes()
    {
        return [
            'staff_member_id' => 'Employee',
            'leave_type' => 'Leave Type',
            'start_date' => 'Start Date',
            'end_date' => 'End Date',
        ];
    }

    public function prepareForValidation()
    {
        if (!$this->has('staff_member_id')) {
            $this->merge([
                'staff_member_id' => Auth::user()->staffMemberProfile?->id,
            ]);
        }
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$this->has('start_date') || !$this->has('end_date')) {
                return;
            }

            $startDate = Carbon::parse($this->start_date);
            $endDate = Carbon::parse($this->end_date);

            // Check for collective leave days (cuti bersama) in the requested period
            $collectiveLeaves = HolidayCalendar::query()
                ->where('type', 'collective_leave')
                ->whereBetween('date', [$startDate, $endDate])
                ->get();

            if ($collectiveLeaves->isNotEmpty()) {
                $cutiBersamaDates = $collectiveLeaves->pluck('name')->join(', ');
                
                $validator->errors()->add(
                    'start_date',
                    "Your leave request includes collective leave days (Cuti Bersama): {$cutiBersamaDates}. These are company-wide holidays and do not require a leave request. Please adjust your dates to exclude these days."
                );
            }
        });
    }
}

