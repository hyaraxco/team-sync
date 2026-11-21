<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class BpjsRate extends Model
{
    protected $fillable = [
        'component',
        'employee_rate',
        'employer_rate',
        'max_salary_base',
        'description',
        'effective_date',
        'valid_until',
    ];

    protected function casts(): array
    {
        return [
            'employee_rate' => 'decimal:2',
            'employer_rate' => 'decimal:2',
            'max_salary_base' => 'decimal:2',
            'effective_date' => 'date',
            'valid_until' => 'date',
        ];
    }

    /**
     * Scope: rows whose effective period covers the given date.
     * effective_date <= $date AND (valid_until IS NULL OR valid_until >= $date)
     * whereDate() keeps the comparisons correct on SQLite, where date columns
     * are stored as full datetime strings.
     */
    public function scopeEffectiveOn(Builder $query, Carbon|string $date): Builder
    {
        $dateStr = $date instanceof Carbon ? $date->toDateString() : $date;

        return $query
            ->whereDate('effective_date', '<=', $dateStr)
            ->where(function (Builder $q) use ($dateStr) {
                $q->whereNull('valid_until')
                    ->orWhereDate('valid_until', '>=', $dateStr);
            });
    }

    /**
     * Load one rate per component for the given contribution period.
     * When multiple rows match, the latest effective_date wins.
     */
    public static function forPeriod(string $contributionMonth): Collection
    {
        $firstOfMonth = Carbon::parse($contributionMonth.'-01');

        $rates = static::effectiveOn($firstOfMonth)
            ->orderByDesc('effective_date')
            ->get();

        return $rates->groupBy('component')->map->first();
    }
}
