<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PerformanceOutcomeRuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'min_rating' => (float) $this->min_rating,
            'max_rating' => (float) $this->max_rating,
            'bonus_months' => (float) $this->bonus_months,
            'salary_increase_pct' => (float) $this->salary_increase_pct,
            'promotion_eligible' => $this->promotion_eligible,
            'pip_required' => $this->pip_required,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
