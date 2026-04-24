<?php

namespace App\Http\Requests;

use App\Enums\BankName;
use App\Enums\BloodType;
use App\Enums\EmploymentType;
use App\Enums\Gender;
use App\Enums\JobStatus;
use App\Enums\MaritalStatus;
use App\Enums\PtkpStatus;
use App\Enums\Religion;
use App\Enums\WorkLocation;
use App\Models\BankInformation;
use App\Models\StaffMemberProfile;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StaffMemberProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $employeeId = $this->route('staff_member') ?? $this->route('id');
        $employee = StaffMemberProfile::find($employeeId);
        $userId = $employee?->user_id;
        $bankInfoId = BankInformation::where('staff_member_id', $employeeId)->value('id');

        return [
            // User fields
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', Rule::unique('users', 'email')->ignore($userId)],
            'password' => ['sometimes', 'required', 'string', 'min:8'],
            'profile_photo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
            'roles' => ['sometimes', 'required', 'array'],
            'roles.*' => [
                'required',
                'string',
                'in:manager,hr,finance,employee',
                Rule::exists('roles', 'name'),
            ],

            // Employee Profile fields
            'code' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('staff_member_profiles', 'code')->ignore($employeeId)],
            'identity_number' => ['sometimes', 'required', 'string', 'max:20', Rule::unique('staff_member_profiles', 'identity_number')->ignore($employeeId)],
            'npwp' => ['nullable', 'string', 'max:30'],
            'bpjs_ketenagakerjaan' => ['nullable', 'string', 'max:30'],
            'bpjs_kesehatan' => ['nullable', 'string', 'max:30'],
            'ptkp_status' => ['nullable', 'string', 'in:'.implode(',', array_column(PtkpStatus::cases(), 'value'))],
            'phone' => ['sometimes', 'required', 'string', 'max:20'],
            'date_of_birth' => ['sometimes', 'required', 'date', 'before:today'],
            'gender' => ['sometimes', 'required', 'string', 'in:'.implode(',', array_column(Gender::cases(), 'value'))],
            'religion' => ['nullable', 'string', 'in:'.implode(',', array_column(Religion::cases(), 'value'))],
            'marital_status' => ['nullable', 'string', 'in:'.implode(',', array_column(MaritalStatus::cases(), 'value'))],
            'blood_type' => ['nullable', 'string', 'in:'.implode(',', array_column(BloodType::cases(), 'value'))],
            'place_of_birth' => ['sometimes', 'required', 'string', 'max:100'],
            'address' => ['sometimes', 'required', 'string'],
            'city' => ['sometimes', 'required', 'string', 'max:100'],
            'postal_code' => ['sometimes', 'required', 'string', 'max:10'],

            // Job Information fields
            'job_title' => ['sometimes', 'required', 'string', 'max:255'],
            'team_id' => ['nullable', 'exists:teams,id'],
            'status' => ['sometimes', 'required', 'string', 'in:'.implode(',', array_column(JobStatus::cases(), 'value'))],
            'employment_type' => ['sometimes', 'required', 'string', 'in:'.implode(',', array_column(EmploymentType::cases(), 'value'))],
            'work_location' => ['sometimes', 'required', 'string', 'in:'.implode(',', array_column(WorkLocation::cases(), 'value'))],
            'start_date' => ['sometimes', 'required', 'date'],
            'monthly_salary' => ['sometimes', 'required', 'numeric', 'min:0', 'max:9999999999.99'],

            // Bank Information fields
            'bank_name' => ['sometimes', 'required', 'string', 'in:'.implode(',', array_column(BankName::cases(), 'value'))],
            'account_number' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('bank_information', 'account_number')->ignore($bankInfoId)],
            'account_holder_name' => ['sometimes', 'required', 'string', 'max:255'],

            // Emergency Contacts fields (array)
            'emergency_contacts' => ['sometimes', 'required', 'array', 'min:1'],
            'emergency_contacts.*.id' => ['nullable', 'integer', 'exists:emergency_contacts,id'],
            'emergency_contacts.*.full_name' => ['sometimes', 'string', 'max:255'],
            'emergency_contacts.*.relationship' => ['sometimes', 'string', 'max:100'],
            'emergency_contacts.*.phone' => ['sometimes', 'string', 'max:20'],
            'emergency_contacts.*.email' => ['nullable', 'email', 'max:255'],
        ];
    }

    public function attributes()
    {
        return [
            // User attributes
            'name' => 'Name',
            'email' => 'Email',
            'password' => 'Password',
            'user_profile_photo' => 'User Profile Photo',

            // Employee Profile attributes
            'code' => 'Employee Code',
            'identity_number' => 'Identity Number',
            'npwp' => 'NPWP',
            'bpjs_ketenagakerjaan' => 'BPJS Ketenagakerjaan',
            'bpjs_kesehatan' => 'BPJS Kesehatan',
            'ptkp_status' => 'PTKP Status',
            'phone' => 'Phone Number',
            'date_of_birth' => 'Date of Birth',
            'gender' => 'Gender',
            'religion' => 'Religion',
            'marital_status' => 'Marital Status',
            'blood_type' => 'Blood Type',
            'place_of_birth' => 'Place of Birth',
            'address' => 'Address',
            'city' => 'City',
            'postal_code' => 'Postal Code',
            'profile_photo' => 'Profile Photo',

            // Job Information attributes
            'job_title' => 'Job Title',
            'team' => 'Team',
            'status' => 'Job Status',
            'employment_type' => 'Employment Type',
            'work_location' => 'Work Location',
            'start_date' => 'Start Date',
            'monthly_salary' => 'Monthly Salary',

            // Bank Information attributes
            'bank_name' => 'Bank Name',
            'account_number' => 'Account Number',
            'account_holder_name' => 'Account Holder Name',

            // Emergency Contacts attributes
            'emergency_contacts' => 'Emergency Contacts',
            'emergency_contacts.*.full_name' => 'Full Name',
            'emergency_contacts.*.relationship' => 'Relationship',
            'emergency_contacts.*.phone' => 'Phone Number',
            'emergency_contacts.*.email' => 'Email',
        ];
    }
}
