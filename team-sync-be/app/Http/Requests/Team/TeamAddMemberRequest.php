<?php

namespace App\Http\Requests\Team;

use Illuminate\Foundation\Http\FormRequest;

class TeamAddMemberRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'staff_member_id' => ['required', 'integer', 'exists:staff_member_profiles,id'],
        ];
    }

    public function attributes()
    {
        return [
            'staff_member_id' => 'Employee ID',
        ];
    }
}
