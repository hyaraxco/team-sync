<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MarkThrPaidRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'payment_date' => ['required', 'date'],
        ];
    }
}
