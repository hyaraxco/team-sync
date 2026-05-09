<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class HybridScheduleOverrideStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'planned_work_mode' => ['required', 'string', 'in:office,remote'],
            'reason' => ['required', 'string'],
        ];
    }
}
