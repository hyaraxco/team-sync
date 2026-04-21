<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PerformanceReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'cycle_id',
        'staff_member_id',
        'reviewer_id',
        'status',
        'self_assessment_submitted_at',
        'manager_assessment_submitted_at',
        'final_rating',
        'final_rating_label',
        'calibrated_at',
        'calibrated_by',
        'completed_at',
        'acknowledged_by_employee_at',
    ];

    protected $casts = [
        'self_assessment_submitted_at' => 'datetime',
        'manager_assessment_submitted_at' => 'datetime',
        'calibrated_at' => 'datetime',
        'completed_at' => 'datetime',
        'acknowledged_by_employee_at' => 'datetime',
        'final_rating' => 'decimal:2',
    ];

    public function cycle(): BelongsTo
    {
        return $this->belongsTo(PerformanceReviewCycle::class, 'cycle_id');
    }

    public function staffMember(): BelongsTo
    {
        return $this->belongsTo(StaffMemberProfile::class, 'staff_member_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(StaffMemberProfile::class, 'reviewer_id');
    }

    public function calibrator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'calibrated_by');
    }

    public function responses(): HasMany
    {
        return $this->hasMany(PerformanceReviewResponse::class, 'review_id');
    }
}
