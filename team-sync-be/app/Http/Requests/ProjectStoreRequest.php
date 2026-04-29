<?php

namespace App\Http\Requests;

use App\Enums\ProjectPriority;
use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProjectStoreRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:'.implode(',', array_column(ProjectType::cases(), 'value'))],
            'priority' => ['required', 'string', 'in:'.implode(',', array_column(ProjectPriority::cases(), 'value'))],
            'status' => ['required', 'string', 'in:'.implode(',', array_column(ProjectStatus::cases(), 'value'))],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after:start_date'],
            'description' => ['nullable', 'string'],
            'photo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'project_leader_id' => ['nullable', 'exists:staff_member_profiles,id'],
            'task_template' => ['nullable', 'string', 'in:product_mvp,website_delivery,campaign_launch'],
            'teams' => ['nullable', 'array'],
            'teams.*' => ['integer', 'exists:teams,id'],
        ];
    }

    public function attributes()
    {
        return [
            'name' => 'Project Name',
            'type' => 'Project Type',
            'priority' => 'Priority',
            'status' => 'Status',
            'start_date' => 'Start Date',
            'end_date' => 'End Date',
            'description' => 'Description',
            'photo' => 'Project Photo',
            'budget' => 'Budget',
            'project_leader_id' => 'Project Leader',
            'task_template' => 'Task Template',
            'teams' => 'Teams',
            'teams.*' => 'Team',
        ];
    }
}
