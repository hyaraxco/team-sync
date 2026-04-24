<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class AttendanceCheckInRequest extends FormRequest
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
            'check_in_lat' => ['nullable', 'numeric'],
            'check_in_long' => ['nullable', 'numeric'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function attributes()
    {
        return [
            'staff_member_id' => 'Employee',
            'check_in_lat' => 'Latitude',
            'check_in_long' => 'Longitude',
            'notes' => 'Catatan',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'staff_member_id' => Auth::user()->staffMemberProfile->id,
        ]);
    }
}
