<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PerformanceGoal extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'title',
        'description',
        'goal_type',
        'category',
        'target_value',
        'current_value',
        'unit',
        'weight',
        'start_date',
        'due_date',
        'status',
        'completion_percentage',
        'created_by',
        'assigned_by',
        'linked_review_id',
    ];

    protected $casts = [
        'start_date' => 'date',
        'due_date' => 'date',
        'weight' => 'decimal:2',
        'completion_percentage' => 'integer',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(EmployeeProfile::class, 'employee_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(EmployeeProfile::class, 'assigned_by');
    }

    public function linkedReview(): BelongsTo
    {
        return $this->belongsTo(PerformanceReview::class, 'linked_review_id');
    }

    public function updates(): HasMany
    {
        return $this->hasMany(PerformanceGoalUpdate::class, 'goal_id');
    }
}
