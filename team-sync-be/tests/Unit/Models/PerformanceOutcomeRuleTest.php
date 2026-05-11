<?php

use App\Models\PerformanceOutcomeRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('findForRating returns rule where min_rating <= rating <= max_rating', function () {
    PerformanceOutcomeRule::create([
        'label' => 'Outstanding',
        'min_rating' => 4.0,
        'max_rating' => 5.0,
        'bonus_months' => 3,
        'salary_increase_pct' => 15,
        'promotion_eligible' => true,
        'pip_required' => false,
        'is_active' => true,
    ]);

    $result = PerformanceOutcomeRule::findForRating(4.5);

    expect($result)->not->toBeNull()
        ->and($result->label)->toBe('Outstanding');
});

it('findForRating returns highest min_rating when multiple rules match', function () {
    PerformanceOutcomeRule::create([
        'label' => 'Meets Expectations',
        'min_rating' => 2.0,
        'max_rating' => 3.5,
        'bonus_months' => 0,
        'salary_increase_pct' => 5,
        'promotion_eligible' => false,
        'pip_required' => false,
        'is_active' => true,
    ]);

    PerformanceOutcomeRule::create([
        'label' => 'Exceeds Expectations',
        'min_rating' => 3.0,
        'max_rating' => 4.5,
        'bonus_months' => 2,
        'salary_increase_pct' => 10,
        'promotion_eligible' => true,
        'pip_required' => false,
        'is_active' => true,
    ]);

    // Rating 3.5 matches both rules (Meets: 2.0-3.5, Exceeds: 3.0-4.5)
    // Should return Exceeds Expectations because it has higher min_rating (3.0 > 2.0)
    $result = PerformanceOutcomeRule::findForRating(3.5);

    expect($result)->not->toBeNull()
        ->and($result->label)->toBe('Exceeds Expectations')
        ->and((float) $result->min_rating)->toBe(3.0);
});

it('findForRating returns null when no rule matches', function () {
    PerformanceOutcomeRule::create([
        'label' => 'High Only',
        'min_rating' => 4.0,
        'max_rating' => 5.0,
        'bonus_months' => 3,
        'salary_increase_pct' => 15,
        'promotion_eligible' => true,
        'pip_required' => false,
        'is_active' => true,
    ]);

    // Rating 1.5 is below min_rating of 4.0
    $result = PerformanceOutcomeRule::findForRating(1.5);

    expect($result)->toBeNull();
});

it('findForRating handles exact boundary values', function () {
    PerformanceOutcomeRule::create([
        'label' => 'Boundary Rule',
        'min_rating' => 2.0,
        'max_rating' => 4.0,
        'bonus_months' => 1,
        'salary_increase_pct' => 5,
        'promotion_eligible' => false,
        'pip_required' => false,
        'is_active' => true,
    ]);

    // Exact min boundary
    expect(PerformanceOutcomeRule::findForRating(2.0)?->label)->toBe('Boundary Rule');

    // Exact max boundary
    expect(PerformanceOutcomeRule::findForRating(4.0)?->label)->toBe('Boundary Rule');

    // Just below min boundary
    expect(PerformanceOutcomeRule::findForRating(1.99))->toBeNull();

    // Just above max boundary
    expect(PerformanceOutcomeRule::findForRating(4.01))->toBeNull();
});

it('findForRating excludes inactive rules', function () {
    PerformanceOutcomeRule::create([
        'label' => 'Inactive Rule',
        'min_rating' => 1.0,
        'max_rating' => 5.0,
        'bonus_months' => 0,
        'salary_increase_pct' => 0,
        'promotion_eligible' => false,
        'pip_required' => false,
        'is_active' => false,
    ]);

    $result = PerformanceOutcomeRule::findForRating(3.0);

    expect($result)->toBeNull();
});

it('findForRating returns empty when no rules exist at all', function () {
    $result = PerformanceOutcomeRule::findForRating(3.0);

    expect($result)->toBeNull();
});
