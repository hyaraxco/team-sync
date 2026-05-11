<?php

use App\Helpers\PerformanceRatingHelper;
use App\Models\PerformanceReview;
use App\Models\PerformanceReviewCycle;
use App\Models\PerformanceReviewResponse;
use App\Models\PerformanceReviewSection;
use App\Models\PerformanceReviewTemplate;
use App\Models\StaffMemberProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'sanctum']);
});

function createTestReview()
{
    $cycle = PerformanceReviewCycle::factory()->create();
    $employee = StaffMemberProfile::factory()->create();
    $reviewer = StaffMemberProfile::factory()->create();

    return PerformanceReview::create([
        'cycle_id' => $cycle->id,
        'staff_member_id' => $employee->id,
        'reviewer_id' => $reviewer->id,
        'status' => 'pending_self',
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
        'self_rating' => 5.0,
    ]);
    PerformanceReviewResponse::create([
        'review_id' => $review->id,
        'section_id' => $section2->id,
        'final_rating' => null,
        'manager_rating' => 4.0,
        'self_rating' => 2.0,
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
        'self_rating' => 2.0,
    ]);

    $result = PerformanceRatingHelper::calculateFinalRating($review->id);

    expect($result['final_rating'])->toBe(2.0)
        ->and($result['final_rating_label'])->toBe('Needs Improvement');
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

it('calculateManagerRating returns weighted average of manager ratings only', function () {
    $review = createTestReview();

    $section1 = PerformanceReviewSection::create(['name' => 'S1', 'weight' => 60, 'order' => 1, 'is_active' => true]);
    $section2 = PerformanceReviewSection::create(['name' => 'S2', 'weight' => 40, 'order' => 2, 'is_active' => true]);

    PerformanceReviewResponse::create([
        'review_id' => $review->id,
        'section_id' => $section1->id,
        'self_rating' => 5,
        'manager_rating' => 4,
        'final_rating' => 2,
    ]);
    PerformanceReviewResponse::create([
        'review_id' => $review->id,
        'section_id' => $section2->id,
        'self_rating' => 1,
        'manager_rating' => 3,
        'final_rating' => 5,
    ]);

    $result = PerformanceRatingHelper::calculateManagerRating($review->id);

    expect($result)->toBe(3.6);
});

it('calculateManagerRating returns null when no manager ratings exist', function () {
    $review = createTestReview();

    $section1 = PerformanceReviewSection::create(['name' => 'S1', 'weight' => 100, 'order' => 1, 'is_active' => true]);

    PerformanceReviewResponse::create([
        'review_id' => $review->id,
        'section_id' => $section1->id,
        'self_rating' => 4,
        'manager_rating' => null,
    ]);

    $result = PerformanceRatingHelper::calculateManagerRating($review->id);

    expect($result)->toBeNull();
});

it('uses template weights instead of global section weights when template is present', function () {
    // 1. Setup Template and Sections
    $template = PerformanceReviewTemplate::create([
        'name' => 'Engineering Template',
        'is_active' => true,
    ]);

    // Section 1: Global weight 50, Template weight 80
    $section1 = PerformanceReviewSection::create([
        'name' => 'Technical Skills',
        'weight' => 50,
        'order' => 1,
        'is_active' => true,
    ]);

    // Section 2: Global weight 50, Template weight 20
    $section2 = PerformanceReviewSection::create([
        'name' => 'Soft Skills',
        'weight' => 50,
        'order' => 2,
        'is_active' => true,
    ]);

    // Attach to template with custom weights
    $template->sections()->attach($section1->id, ['weight' => 80]);
    $template->sections()->attach($section2->id, ['weight' => 20]);

    // 2. Create Review linked to template
    $review = createTestReview();
    $review->update(['review_template_id' => $template->id]);

    // 3. Create Responses (Rating 5 for both)
    PerformanceReviewResponse::create([
        'review_id' => $review->id,
        'section_id' => $section1->id,
        'manager_rating' => 5,
    ]);

    PerformanceReviewResponse::create([
        'review_id' => $review->id,
        'section_id' => $section2->id,
        'manager_rating' => 2,
    ]);

    /**
     * Calculation with Template Weights (80 & 20):
     * (5 * 80 + 2 * 20) / (80 + 20) = (400 + 40) / 100 = 4.4
     *
     * Calculation with Global Weights (50 & 50):
     * (5 * 50 + 2 * 50) / (50 + 50) = (250 + 100) / 100 = 3.5
     */
    $result = PerformanceRatingHelper::calculateFinalRating($review->id);

    expect($result['final_rating'])->toBe(4.4);
});
