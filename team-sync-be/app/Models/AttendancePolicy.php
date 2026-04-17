<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendancePolicy extends Model
{
    use HasFactory;

    protected $fillable = [
        'employment_type',
        'work_start_time',
        'work_end_time',
        'work_days_per_week',
        'default_working_weekdays',
        'late_grace_minutes',
        'half_day_min_hours',
        'warning_absent_pct',
    ];

    protected function casts(): array
    {
        return [
            'default_working_weekdays' => 'array',
            'half_day_min_hours' => 'decimal:2',
            'warning_absent_pct' => 'decimal:2',
        ];
    }
}
