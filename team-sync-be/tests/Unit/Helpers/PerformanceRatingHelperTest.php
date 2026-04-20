<?php

use App\Helpers\PerformanceRatingHelper;
use App\Models\PerformanceReview;
use App\Models\PerformanceReviewCycle;
use App\Models\PerformanceReviewResponse;
use App\Models\PerformanceReviewSection;
use App\Models\EmployeeProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function createTestReview() {
    $cycle = PerformanceReviewCycle::factory()->create();
    $employee = EmployeeProfile::factory()->create();
    $reviewer = EmployeeProfile::factory()->create();
    
    return PerformanceReview::create([
        'cycle_id' => $cycle->id,
        'employee_id' => $employee->id,
        'reviewer_id' => $reviewer->id,
        'status' => 'draft',
    ]);
}

it('calculates weighted average correctly with all sections rated', function () {
    $review = createTestReview();
    
    $section1 = PerformanceReviewSection::create(['name' => 'S1', 'weight' => 40, 'order' => 1, 'is_active' => true]);
    $section2 = PerformanceReviewSection::create(['name' => 'S2', 'weight' => 30, 'order' => 2, 'is_active' => true]);
    $section3 = PerformanceReviewSection::create(['name' => 'S3', 'weight' => 30, 'order' => 3, 'is_active' => true]);

    PerformanceReviewResponse::create(['review_id' => $review->id, 'section_id' => $section1->id, 'final_rating' => 4.0]);
    PerformanceReviewResponse::create(['review_id' => $review->id, 'section_id' => $section2->id, 'final_rating' => 3.0]);
    PerformanceReviewResponse::create(['review_id' => $review->id, 'section_id' => $section3->id, 'final_rating' => 5.0]);

    // (4.0 * 40) + (3.0 * 30) + (5.0 * 30) = 160 + 90 + 150 = 400
    // 400 / 100 = 4.0
    $result = PerformanceRatingHelper::calculateFinalRating($review->id);

    expect($result['final_rating'])->toBe(4.0)
        ->and($result['final_rating_label'])->toBe('Exceeds Expectations');
});

it('falls back to manager_rating when final_rating is null', function () {
    $review = createTestReview();
    
    $section1 = PerformanceReviewSection::create(['name' => 'S1', 'weight' => 50, 'order' => 1, 'is_active' => true]);
    $section2 = PerformanceReviewSection::create(['name' => 'S2', 'weight' => 50, 'order' => 2, 'is_active' => true]);

    PerformanceReviewResponse::create([
        'review_id' => $review->id, 
        'section_id' => $section1->id, 
        'final_rating' => null,
        'manager_rating' => 3.0,
        'self_rating' => 5.0
    ]);
    PerformanceReviewResponse::create([
        'review_id' => $review->id, 
        'section_id' => $section2->id, 
        'final_rating' => null,
        'manager_rating' => 4.0,
        'self_rating' => 2.0
    ]);

    // (3.0 * 50) + (4.0 * 50) = 150 + 200 = 350
    // 350 / 100 = 3.5
    $result = PerformanceRatingHelper::calculateFinalRating($review->id);

    expect($result['final_rating'])->toBe(3.5)
        ->and($result['final_rating_label'])->toBe('Exceeds Expectations');
});

it('falls back to self_rating when both final_rating and manager_rating are null', function () {
    $review = createTestReview();
    
    $section1 = PerformanceReviewSection::create(['name' => 'S1', 'weight' => 100, 'order' => 1, 'is_active' => true]);

    PerformanceReviewResponse::create([
        'review_id' => $review->id, 
        'section_id' => $section1->id, 
        'final_rating' => null,
        'manager_rating' => null,
        'self_rating' => 2.0
    ]);

    $result = PerformanceRatingHelper::calculateFinalRating($review->id);

    expect($result['final_rating'])->toBe(2.0)
        ->and($result['final_rating_label'])->toBe('Meets Expectations');
});

it('returns null when no responses exist', function () {
    $review = createTestReview();

    $result = PerformanceRatingHelper::calculateFinalRating($review->id);

    expect($result['final_rating'])->toBeNull()
        ->and($result['final_rating_label'])->toBeNull();
});

it('handles sections with different weights correctly', function () {
    $review = createTestReview();
    
    $section1 = PerformanceReviewSection::create(['name' => 'S1', 'weight' => 50, 'order' => 1, 'is_active' => true]);
    $section2 = PerformanceReviewSection::create(['name' => 'S2', 'weight' => 30, 'order' => 2, 'is_active' => true]);
    $section3 = PerformanceReviewSection::create(['name' => 'S3', 'weight' => 20, 'order' => 3, 'is_active' => true]);

    PerformanceReviewResponse::create(['review_id' => $review->id, 'section_id' => $section1->id, 'final_rating' => 4.0]);
    PerformanceReviewResponse::create(['review_id' => $review->id, 'section_id' => $section2->id, 'final_rating' => 3.0]);
    PerformanceReviewResponse::create(['review_id' => $review->id, 'section_id' => $section3->id, 'final_rating' => 2.0]);

    // (4.0 * 50) + (3.0 * 30) + (2.0 * 20) = 200 + 90 + 40 = 330
    // 330 / 100 = 3.3
    $result = PerformanceRatingHelper::calculateFinalRating($review->id);

    expect($result['final_rating'])->toBe(3.3)
        ->and($result['final_rating_label'])->toBe('Meets Expectations');
});

it('derives correct label for each rating range boundary', function () {
    expect(PerformanceRatingHelper::getRatingLabel(4.50))->toBe('Outstanding')
        ->and(PerformanceRatingHelper::getRatingLabel(3.50))->toBe('Exceeds Expectations')
        ->and(PerformanceRatingHelper::getRatingLabel(2.50))->toBe('Meets Expectations')
        ->and(PerformanceRatingHelper::getRatingLabel(1.50))->toBe('Needs Improvement')
        ->and(PerformanceRatingHelper::getRatingLabel(1.00))->toBe('Unsatisfactory')
        ->and(PerformanceRatingHelper::getRatingLabel(0.00))->toBe('Unsatisfactory');
});
