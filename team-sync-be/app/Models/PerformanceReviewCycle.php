<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PerformanceReviewCycle extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'cycle_type',
        'start_date',
        'end_date',
        'review_period_start',
        'review_period_end',
        'status',
        'self_assessment_deadline',
        'manager_assessment_deadline',
        'calibration_deadline',
        'template_id',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'review_period_start' => 'date',
        'review_period_end' => 'date',
        'self_assessment_deadline' => 'date',
        'manager_assessment_deadline' => 'date',
        'calibration_deadline' => 'date',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(PerformanceReviewTemplate::class, 'template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(PerformanceReview::class, 'cycle_id');
    }
}
