<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SendEmailVerificationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['nullable', 'string', 'email'],
        ];
    }
}
