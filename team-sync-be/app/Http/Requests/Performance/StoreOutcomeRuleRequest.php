<?php

namespace App\Http\Requests\Performance;

use App\Models\PerformanceOutcomeRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreOutcomeRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'label' => ['required', 'string', 'max:255'],
            'min_rating' => ['required', 'numeric', 'min:1.00', 'max:5.00'],
            'max_rating' => ['required', 'numeric', 'min:1.00', 'max:5.00', 'gte:min_rating'],
            'bonus_months' => ['required', 'numeric', 'min:0'],
            'salary_increase_pct' => ['required', 'numeric', 'min:0', 'max:100'],
            'promotion_eligible' => ['required', 'boolean'],
            'pip_required' => ['required', 'boolean'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $min = $this->input('min_rating');
                $max = $this->input('max_rating');

                if ($min === null || $max === null) {
                    return;
                }

                $overlap = PerformanceOutcomeRule::where('is_active', true)
                    ->where('min_rating', '<=', $max)
                    ->where('max_rating', '>=', $min)
                    ->exists();

                if ($overlap) {
                    $validator->errors()->add(
                        'min_rating',
                        "Rating range {$min}–{$max} overlaps with an existing active rule."
                    );
                }
            },
        ];
    }
}
