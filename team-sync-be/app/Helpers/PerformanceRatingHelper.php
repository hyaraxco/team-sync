<?php

namespace App\Helpers;

use App\Models\PerformanceReview;
use App\Models\PerformanceReviewResponse;

class PerformanceRatingHelper
{
    private const RATING_LABELS = [
        ['min' => 4.50, 'label' => 'Outstanding'],
        ['min' => 3.50, 'label' => 'Exceeds Expectations'],
        ['min' => 2.50, 'label' => 'Meets Expectations'],
        ['min' => 1.50, 'label' => 'Needs Improvement'],
        ['min' => 0.00, 'label' => 'Unsatisfactory'],
    ];

    /**
     * Weighted average: SUM(effective_rating * section.weight) / SUM(section.weight)
     * effective_rating = final_rating ?? manager_rating ?? self_rating
     *
     * @return array{final_rating: float|null, final_rating_label: string|null}
     */
    public static function calculateFinalRating(int $reviewId): array
    {
        $review = PerformanceReview::find($reviewId);
        if (! $review) {
            return ['final_rating' => null, 'final_rating_label' => null];
        }

        $responses = PerformanceReviewResponse::with('section')
            ->where('review_id', $reviewId)
            ->get();

        if ($responses->isEmpty()) {
            return ['final_rating' => null, 'final_rating_label' => null];
        }

        $templateWeights = [];
        if ($review->review_template_id) {
            $templateWeights = \DB::table('review_template_sections')
                ->where('template_id', $review->review_template_id)
                ->pluck('weight', 'section_id')
                ->toArray();
        }

        $totalWeight = 0.0;
        $weightedSum = 0.0;

        foreach ($responses as $response) {
            $section = $response->section;
            if (! $section || ! $section->is_active) {
                continue;
            }

            $effectiveRating = $response->final_rating
                ?? $response->manager_rating
                ?? $response->self_rating;

            if ($effectiveRating === null) {
                continue;
            }

            $weight = (float) ($templateWeights[$section->id] ?? $section->weight);
            $weightedSum += $effectiveRating * $weight;
            $totalWeight += $weight;
        }

        if ($totalWeight <= 0) {
            return ['final_rating' => null, 'final_rating_label' => null];
        }

        $finalRating = round(max(1.00, min(5.00, $weightedSum / $totalWeight)), 2);

        return [
            'final_rating' => $finalRating,
            'final_rating_label' => self::getRatingLabel($finalRating),
        ];
    }

    /**
     * Weighted average using only manager_rating per section.
     */
    public static function calculateManagerRating(int $reviewId): ?float
    {
        $review = PerformanceReview::find($reviewId);
        if (! $review) {
            return null;
        }

        $responses = PerformanceReviewResponse::with('section')
            ->where('review_id', $reviewId)
            ->get();

        if ($responses->isEmpty()) {
            return null;
        }

        $templateWeights = [];
        if ($review->review_template_id) {
            $templateWeights = \DB::table('review_template_sections')
                ->where('template_id', $review->review_template_id)
                ->pluck('weight', 'section_id')
                ->toArray();
        }

        $totalWeight = 0.0;
        $weightedSum = 0.0;

        foreach ($responses as $response) {
            $section = $response->section;
            if (! $section || ! $section->is_active || $response->manager_rating === null) {
                continue;
            }

            $weight = (float) ($templateWeights[$section->id] ?? $section->weight);
            $weightedSum += $response->manager_rating * $weight;
            $totalWeight += $weight;
        }

        if ($totalWeight <= 0) {
            return null;
        }

        return round(max(1.00, min(5.00, $weightedSum / $totalWeight)), 2);
    }

    public static function getRatingLabel(float $rating): string
    {
        foreach (self::RATING_LABELS as $entry) {
            if ($rating >= $entry['min']) {
                return $entry['label'];
            }
        }

        return 'Unsatisfactory';
    }
}
