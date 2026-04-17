<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LeaveRequestProofUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'proof_file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ];
    }

    public function attributes(): array
    {
        return [
            'proof_file' => 'Proof file',
        ];
    }
}
