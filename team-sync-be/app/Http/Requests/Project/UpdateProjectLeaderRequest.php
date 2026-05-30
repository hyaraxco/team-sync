<?php

namespace App\Http\Requests\Project;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProjectLeaderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'project_leader_id' => ['required', 'integer', 'exists:staff_member_profiles,id'],
        ];
    }

    public function attributes(): array
    {
        return [
            'project_leader_id' => 'Project Leader',
        ];
    }
}
