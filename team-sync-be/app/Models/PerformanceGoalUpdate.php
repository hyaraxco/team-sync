<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PerformanceGoalUpdate extends Model
{
    use HasFactory;

    protected $fillable = [
        'goal_id',
        'updated_by',
        'update_type',
        'previous_value',
        'new_value',
        'previous_status',
        'new_status',
        'completion_percentage',
        'notes',
    ];

    protected $casts = [
        'completion_percentage' => 'integer',
    ];

    public function goal(): BelongsTo
    {
        return $this->belongsTo(PerformanceGoal::class, 'goal_id');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
