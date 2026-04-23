<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PerformanceReviewSection extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'weight',
        'topsis_category',
        'order',
        'is_active',
    ];

    protected $casts = [
        'weight' => 'decimal:2',
        'is_active' => 'boolean',
    ];
}
