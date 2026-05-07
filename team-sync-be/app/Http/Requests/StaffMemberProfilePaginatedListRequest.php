<?php

namespace App\Http\Requests;

use App\Enums\WorkLocation;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StaffMemberProfilePaginatedListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'search' => 'nullable|string',
            'status' => 'nullable|string',
            'type' => 'nullable|string',
            'work_location' => 'nullable|string|in:'.implode(',', array_column(WorkLocation::cases(), 'value')),
            'project_id' => 'nullable|integer',
            'row_per_page' => 'required|integer|min:1',
        ];
    }
}
