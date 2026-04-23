<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PerformanceOutcomeRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'label',
        'min_rating',
        'max_rating',
        'bonus_months',
        'salary_increase_pct',
        'promotion_eligible',
        'pip_required',
        'description',
        'is_active',
    ];

    protected $casts = [
        'min_rating' => 'decimal:2',
        'max_rating' => 'decimal:2',
        'bonus_months' => 'decimal:2',
        'salary_increase_pct' => 'decimal:2',
        'promotion_eligible' => 'boolean',
        'pip_required' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Find the matching active rule for a given final_rating.
     */
    public static function findForRating(float $rating): ?self
    {
        return static::where('is_active', true)
            ->where('min_rating', '<=', $rating)
            ->where('max_rating', '>=', $rating)
            ->orderByDesc('min_rating')
            ->first();
    }
}
