<?php

namespace App\Http\Requests\ProjectTask;

use Illuminate\Foundation\Http\FormRequest;

class ProjectTaskListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['search' => ['nullable', 'string'], 'project_id' => ['nullable', 'integer'], 'row_per_page' => ['nullable', 'integer']];
    }
}
