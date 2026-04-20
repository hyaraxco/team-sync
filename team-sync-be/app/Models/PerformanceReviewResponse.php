<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerformanceReviewResponse extends Model
{
    use HasFactory;

    protected $fillable = [
        'review_id',
        'section_id',
        'self_rating',
        'self_comments',
        'manager_rating',
        'manager_comments',
        'final_rating',
    ];

    public function review(): BelongsTo
    {
        return $this->belongsTo(PerformanceReview::class, 'review_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(PerformanceReviewSection::class, 'section_id');
    }
}
