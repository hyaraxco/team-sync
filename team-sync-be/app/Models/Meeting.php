<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Meeting extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'scheduled_at',
        'duration_minutes',
        'location',
        'departments',
        'created_by',
        'reminder_sent_at',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
            'departments' => 'array',
            'duration_minutes' => 'integer',
        ];
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function teams()
    {
        return $this->belongsToMany(Team::class, 'meeting_team');
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('scheduled_at', '>', now())
            ->orderBy('scheduled_at');
    }

    public function scopeNeedsReminder(Builder $query): Builder
    {
        return $query->where('scheduled_at', '<=', now()->addMinutes(15))
            ->where('scheduled_at', '>', now())
            ->whereNull('reminder_sent_at');
    }
}
