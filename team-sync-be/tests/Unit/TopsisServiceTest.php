<?php

use App\Services\TopsisService;

/**
 * Helper: buat data kandidat dengan 5 kriteria TOPSIS.
 */
function makeCandidate(
    string $id,
    string $name,
    float $performanceScore,
    float $attendanceRate,
    float $goalCompletion,
    float $feedbackScore,
    float $tenureFactor,
    ?string $department = 'Engineering'
): array {
    return [
        'staff_member_id' => $id,
        'employee_name' => $name,
        'department' => $department,
        'performance_score' => $performanceScore,
        'attendance_rate' => $attendanceRate,
        'goal_completion' => $goalCompletion,
        'feedback_score' => $feedbackScore,
        'tenure_factor' => $tenureFactor,
    ];
}

/**
 * Helper: bobot default TOPSIS sesuai PRD Section 3.2.
 *
 * 5 kriteria langsung (bukan 7 seperti implementasi lama):
 *   performance_score (30%), attendance_rate (20%), goal_completion (25%),
 *   feedback_score (15%), tenure_factor (10%)
 */
function defaultWeights(): array
{
    return [
        'performance_score' => 0.30,
        'attendance_rate' => 0.20,
        'goal_completion' => 0.25,
        'feedback_score' => 0.15,
        'tenure_factor' => 0.10,
    ];
}

// --- Test 1: Empty candidates ---
it('returns empty ranking when no candidates provided', function () {
    $service = new TopsisService;
    $result = $service->calculate([], defaultWeights());

    expect($result)
        ->toHaveKey('ranking')
        ->toHaveKey('weights')
        ->toHaveKey('ideal_positive')
        ->toHaveKey('ideal_negative')
        ->toHaveKey('criteria')
        ->toHaveKey('criteria_types')
        ->toHaveKey('total_candidates');

    expect($result['ranking'])->toBeEmpty();
    expect($result['total_candidates'])->toBe(0);
    expect($result['ideal_positive'])->toBeEmpty();
    expect($result['ideal_negative'])->toBeEmpty();
});

// --- Test 2: Single candidate ---
it('ranks single candidate as rank 1 with coefficient 1.0', function () {
    $service = new TopsisService;
    $candidates = [makeCandidate('emp-1', 'Alice', 90.0, 95.0, 80.0, 5.0, 85.0)];
    $result = $service->calculate($candidates, defaultWeights());

    expect($result['total_candidates'])->toBe(1);
    expect($result['ranking'])->toHaveCount(1);

    $ranked = $result['ranking'][0];
    expect($ranked['rank'])->toBe(1);
    expect($ranked['staff_member_id'])->toBe('emp-1');
    expect($ranked['closeness_coefficient'])->toBe(1.0);
    expect($ranked['distance_positive'])->toBe(0.0);
    expect($ranked['label'])->toBe('Outstanding');
});

// --- Test 3: Two candidates — high vs low performer ---
it('ranks high performer above low performer with two candidates', function () {
    $service = new TopsisService;
    $candidates = [
        makeCandidate('emp-low', 'Bob', 30.0, 40.0, 20.0, 1.0, 25.0),
        makeCandidate('emp-high', 'Alice', 95.0, 98.0, 100.0, 10.0, 95.0),
    ];
    $result = $service->calculate($candidates, defaultWeights());

    expect($result['total_candidates'])->toBe(2);
    expect($result['ranking'])->toHaveCount(2);

    // Alice should be rank 1
    expect($result['ranking'][0]['staff_member_id'])->toBe('emp-high');
    expect($result['ranking'][0]['rank'])->toBe(1);

    // Bob should be rank 2
    expect($result['ranking'][1]['staff_member_id'])->toBe('emp-low');
    expect($result['ranking'][1]['rank'])->toBe(2);

    // Alice's coefficient > Bob's coefficient
    expect($result['ranking'][0]['closeness_coefficient'])
        ->toBeGreaterThan($result['ranking'][1]['closeness_coefficient']);

    // Best candidate: D+ = 0 (is the ideal), so coefficient = 1.0
    expect($result['ranking'][0]['closeness_coefficient'])->toBe(1.0);

    // Worst candidate: D- = 0 (is the anti-ideal), so coefficient = 0.0
    expect($result['ranking'][1]['closeness_coefficient'])->toBe(0.0);
});

// --- Test 4: Five candidates — full ranking sort ---
it('sorts five candidates by closeness coefficient descending', function () {
    $service = new TopsisService;
    $candidates = [
        makeCandidate('emp-3', 'Charlie', 50.0, 75.0, 55.0, 3.0, 60.0),
        makeCandidate('emp-5', 'Eve', 100.0, 99.0, 100.0, 10.0, 99.0),
        makeCandidate('emp-1', 'Alice', 10.0, 40.0, 15.0, 0.0, 30.0),
        makeCandidate('emp-4', 'Diana', 75.0, 88.0, 80.0, 7.0, 85.0),
        makeCandidate('emp-2', 'Bob', 30.0, 60.0, 35.0, 1.0, 50.0),
    ];
    $result = $service->calculate($candidates, defaultWeights());

    expect($result['total_candidates'])->toBe(5);
    expect($result['ranking'])->toHaveCount(5);

    // Verify sorted descending by closeness_coefficient
    $coefficients = array_column($result['ranking'], 'closeness_coefficient');
    for ($i = 0; $i < count($coefficients) - 1; $i++) {
        expect($coefficients[$i])->toBeGreaterThanOrEqual($coefficients[$i + 1]);
    }

    // Verify ranks are sequential 1-5
    $ranks = array_column($result['ranking'], 'rank');
    expect($ranks)->toBe([1, 2, 3, 4, 5]);

    // Eve (best across all criteria) should be rank 1
    expect($result['ranking'][0]['staff_member_id'])->toBe('emp-5');

    // Alice (worst across all criteria) should be rank 5
    expect($result['ranking'][4]['staff_member_id'])->toBe('emp-1');

    // Ideal solutions should exist
    expect($result['ideal_positive'])->not->toBeEmpty();
    expect($result['ideal_negative'])->not->toBeEmpty();
});

// --- Test 5: Identical scores — division-by-zero safety ---
it('handles identical scores without NaN or Infinity', function () {
    $service = new TopsisService;
    $candidates = [
        makeCandidate('emp-1', 'Alice', 60.0, 80.0, 70.0, 5.0, 75.0),
        makeCandidate('emp-2', 'Bob', 60.0, 80.0, 70.0, 5.0, 75.0),
        makeCandidate('emp-3', 'Charlie', 60.0, 80.0, 70.0, 5.0, 75.0),
    ];
    $result = $service->calculate($candidates, defaultWeights());

    expect($result['total_candidates'])->toBe(3);

    foreach ($result['ranking'] as $ranked) {
        // No NaN or Infinity in closeness_coefficient
        expect(is_nan($ranked['closeness_coefficient']))->toBeFalse();
        expect(is_infinite($ranked['closeness_coefficient']))->toBeFalse();

        // No NaN or Infinity in distances
        expect(is_nan($ranked['distance_positive']))->toBeFalse();
        expect(is_infinite($ranked['distance_positive']))->toBeFalse();
        expect(is_nan($ranked['distance_negative']))->toBeFalse();
        expect(is_infinite($ranked['distance_negative']))->toBeFalse();

        // All candidates should have equal coefficients
        // When D+ = 0 and D- = 0, coefficient should be 0 (safe division)
        expect($ranked['closeness_coefficient'])->toBe($result['ranking'][0]['closeness_coefficient']);
    }
});

// --- Test 6: All-zero values in one criterion column ---
it('handles all-zero values in one criterion column', function () {
    $service = new TopsisService;
    // feedback_score = 0 for all candidates
    $candidates = [
        makeCandidate('emp-1', 'Alice', 90.0, 95.0, 85.0, 0.0, 88.0),
        makeCandidate('emp-2', 'Bob', 60.0, 80.0, 65.0, 0.0, 75.0),
        makeCandidate('emp-3', 'Charlie', 40.0, 60.0, 45.0, 0.0, 55.0),
    ];
    $result = $service->calculate($candidates, defaultWeights());

    expect($result['total_candidates'])->toBe(3);

    // Ranking should still work based on other criteria
    expect($result['ranking'][0]['staff_member_id'])->toBe('emp-1'); // Alice best
    expect($result['ranking'][2]['staff_member_id'])->toBe('emp-3'); // Charlie worst

    // All feedback_score normalized values should be 0 (not NaN)
    foreach ($result['ranking'] as $ranked) {
        expect(is_nan($ranked['normalized_scores']['feedback_score']))->toBeFalse();
        expect($ranked['normalized_scores']['feedback_score'])->toBe(0.0);
        expect(is_nan($ranked['closeness_coefficient']))->toBeFalse();
    }
});

// --- Test 7: Custom weights change ranking ---
it('produces different rankings when weights change', function () {
    $service = new TopsisService;

    // Alice: high performance_score, low attendance_rate
    // Bob: low performance_score, high attendance_rate
    $candidates = [
        makeCandidate('emp-1', 'Alice', 90.0, 50.0, 60.0, 5.0, 70.0),
        makeCandidate('emp-2', 'Bob', 40.0, 95.0, 60.0, 5.0, 70.0),
    ];

    // Weight heavily on performance_score → Alice should win
    $weightsPerfHeavy = [
        'performance_score' => 0.70,
        'attendance_rate' => 0.05,
        'goal_completion' => 0.10,
        'feedback_score' => 0.10,
        'tenure_factor' => 0.05,
    ];
    $resultPerf = $service->calculate($candidates, $weightsPerfHeavy);
    expect($resultPerf['ranking'][0]['staff_member_id'])->toBe('emp-1'); // Alice wins

    // Weight heavily on attendance_rate → Bob should win
    $weightsAttendHeavy = [
        'performance_score' => 0.05,
        'attendance_rate' => 0.70,
        'goal_completion' => 0.10,
        'feedback_score' => 0.10,
        'tenure_factor' => 0.05,
    ];
    $resultAttend = $service->calculate($candidates, $weightsAttendHeavy);
    expect($resultAttend['ranking'][0]['staff_member_id'])->toBe('emp-2'); // Bob wins
});

// --- Test 8: Rating label boundaries ---
it('assigns correct labels based on closeness coefficient boundaries', function () {
    $service = new TopsisService;

    // Create 2 extreme candidates to produce known coefficients
    // Best candidate gets coefficient = 1.0 (Outstanding)
    // Worst candidate gets coefficient = 0.0 (Unsatisfactory)
    $candidates = [
        makeCandidate('emp-best', 'Best', 100.0, 100.0, 100.0, 10.0, 100.0),
        makeCandidate('emp-worst', 'Worst', 0.0, 0.0, 0.0, 0.0, 0.0),
    ];
    $result = $service->calculate($candidates, defaultWeights());

    // Best (coefficient = 1.0) → Outstanding (≥ 0.80)
    $best = collect($result['ranking'])->firstWhere('staff_member_id', 'emp-best');
    expect($best['label'])->toBe('Outstanding');
    expect($best['closeness_coefficient'])->toBeGreaterThanOrEqual(0.80);

    // Worst (coefficient = 0.0) → Unsatisfactory (< 0.35)
    $worst = collect($result['ranking'])->firstWhere('staff_member_id', 'emp-worst');
    expect($worst['label'])->toBe('Unsatisfactory');
    expect($worst['closeness_coefficient'])->toBeLessThan(0.35);
});

// --- Test 9: Output structure completeness ---
it('returns complete output structure with all required keys', function () {
    $service = new TopsisService;
    $candidates = [
        makeCandidate('emp-1', 'Alice', 85.0, 92.0, 78.0, 6.0, 80.0),
        makeCandidate('emp-2', 'Bob', 65.0, 80.0, 62.0, 3.0, 70.0),
        makeCandidate('emp-3', 'Charlie', 45.0, 70.0, 48.0, 1.0, 55.0),
    ];
    $weights = defaultWeights();
    $result = $service->calculate($candidates, $weights);

    // Top-level keys
    expect($result)->toHaveKeys([
        'weights',
        'ideal_positive',
        'ideal_negative',
        'criteria',
        'criteria_types',
        'ranking',
        'total_candidates',
    ]);

    // Weights should match input
    expect($result['weights'])->toBe($weights);

    // Criteria should list all 5
    expect($result['criteria'])->toHaveCount(5);
    expect($result['criteria'])->toContain(
        'performance_score',
        'attendance_rate',
        'goal_completion',
        'feedback_score',
        'tenure_factor'
    );

    // Ideal solutions should have all criteria keys
    foreach ($result['criteria'] as $criterion) {
        expect($result['ideal_positive'])->toHaveKey($criterion);
        expect($result['ideal_negative'])->toHaveKey($criterion);
    }

    // Each ranking entry should have all required keys
    foreach ($result['ranking'] as $ranked) {
        expect($ranked)->toHaveKeys([
            'staff_member_id',
            'employee_name',
            'department',
            'raw_scores',
            'normalized_scores',
            'weighted_scores',
            'distance_positive',
            'distance_negative',
            'closeness_coefficient',
            'label',
            'rank',
        ]);

        // Sub-arrays should have all criteria
        foreach ($result['criteria'] as $criterion) {
            expect($ranked['raw_scores'])->toHaveKey($criterion);
            expect($ranked['normalized_scores'])->toHaveKey($criterion);
            expect($ranked['weighted_scores'])->toHaveKey($criterion);
        }

        // Closeness coefficient should be between 0 and 1
        expect($ranked['closeness_coefficient'])->toBeGreaterThanOrEqual(0.0);
        expect($ranked['closeness_coefficient'])->toBeLessThanOrEqual(1.0);

        // Distances should be non-negative
        expect($ranked['distance_positive'])->toBeGreaterThanOrEqual(0.0);
        expect($ranked['distance_negative'])->toBeGreaterThanOrEqual(0.0);

        // Label must be one of the defined labels
        expect($ranked['label'])->toBeIn([
            'Outstanding',
            'Exceeds Expectations',
            'Meets Expectations',
            'Needs Improvement',
            'Unsatisfactory',
        ]);
    }
});
