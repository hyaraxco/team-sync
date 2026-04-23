<?php

namespace App\Http\Requests\Performance;

use App\Models\PerformanceOutcomeRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateOutcomeRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'label' => ['sometimes', 'string', 'max:255'],
            'min_rating' => ['sometimes', 'numeric', 'min:1.00', 'max:5.00'],
            'max_rating' => ['sometimes', 'numeric', 'min:1.00', 'max:5.00'],
            'bonus_months' => ['sometimes', 'numeric', 'min:0'],
            'salary_increase_pct' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'promotion_eligible' => ['sometimes', 'boolean'],
            'pip_required' => ['sometimes', 'boolean'],
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

                if ($min === null && $max === null) {
                    return;
                }

                $ruleId = $this->route('outcome_rule');
                $current = PerformanceOutcomeRule::find($ruleId);

                $checkMin = $min ?? $current?->min_rating;
                $checkMax = $max ?? $current?->max_rating;

                if ($checkMin === null || $checkMax === null) {
                    return;
                }

                $overlap = PerformanceOutcomeRule::where('is_active', true)
                    ->where('id', '!=', $ruleId)
                    ->where('min_rating', '<=', $checkMax)
                    ->where('max_rating', '>=', $checkMin)
                    ->exists();

                if ($overlap) {
                    $validator->errors()->add(
                        'min_rating',
                        "Rating range {$checkMin}–{$checkMax} overlaps with an existing active rule."
                    );
                }
            },
        ];
    }
}
