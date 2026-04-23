<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReviewerRule extends Model
{
    protected $fillable = [
        'reviewee_role',
        'reviewer_role',
        'priority',
        'is_active',
    ];

    protected $casts = [
        'priority' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Scope: only active rules.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
