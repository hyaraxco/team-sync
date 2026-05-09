<?php

namespace App\Http\Requests\AttendancePeriod;

use Illuminate\Foundation\Http\FormRequest;

class AttendancePeriodUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return ['status' => ['sometimes', 'required', 'string', 'in:open,review,locked']];
    }
}
