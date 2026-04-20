<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AnalyticsSnapshot extends Model
{
    use HasFactory;

    protected $fillable = [
        'metric_type',
        'metric_name',
        'period_type',
        'period_start',
        'period_end',
        'value',
        'metadata',
        'calculated_at',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'value' => 'decimal:2',
        'metadata' => 'array',
        'calculated_at' => 'datetime',
    ];
}
