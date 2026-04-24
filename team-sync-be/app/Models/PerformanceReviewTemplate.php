<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PerformanceReviewTemplate extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'description',
        'is_active',
        'is_default',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_default' => 'boolean',
    ];

    /**
     * Get the sections and their specific weights for this template.
     */
    public function sections(): BelongsToMany
    {
        return $this->belongsToMany(
            PerformanceReviewSection::class,
            'review_template_sections',
            'template_id',
            'section_id'
        )->withPivot('weight')->withTimestamps();
    }

    /**
     * Get the job informations that use this template as default.
     */
    public function jobInformations(): HasMany
    {
        return $this->hasMany(JobInformation::class, 'review_template_id');
    }

    /**
     * Get the performance reviews that used this template.
     */
    public function performanceReviews(): HasMany
    {
        return $this->hasMany(PerformanceReview::class, 'review_template_id');
    }
}
