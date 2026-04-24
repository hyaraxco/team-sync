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

    /**
     * Get the templates that include this section.
     */
    public function templates()
    {
        return $this->belongsToMany(
            PerformanceReviewTemplate::class,
            'review_template_sections',
            'section_id',
            'template_id'
        )->withPivot('weight')->withTimestamps();
    }
}
