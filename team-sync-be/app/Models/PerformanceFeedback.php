<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerformanceFeedback extends Model
{
    use HasFactory;

    protected $fillable = [
        'staff_member_id',
        'given_by',
        'feedback_type',
        'category',
        'content',
        'is_private',
        'acknowledged_at',
        'linked_goal_id',
    ];

    protected $casts = [
        'is_private' => 'boolean',
        'acknowledged_at' => 'datetime',
    ];

    public function staffMember(): BelongsTo
    {
        return $this->belongsTo(StaffMemberProfile::class, 'staff_member_id');
    }

    public function giver(): BelongsTo
    {
        return $this->belongsTo(StaffMemberProfile::class, 'given_by');
    }

    public function linkedGoal(): BelongsTo
    {
        return $this->belongsTo(PerformanceGoal::class, 'linked_goal_id');
    }
}
