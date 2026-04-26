<?php

use App\Services\TopsisService;

/**
 * Helper: buat data kandidat dengan 5 kriteria TOPSIS.
 */
function makeCandidate(
    string $id,
    string $name,
    float $c1,
    float $c2,
    float $c3,
    float $c4,
    float $c5,
    ?string $department = 'Engineering'
): array {
    return [
        'staff_member_id' => $id,
        'employee_name' => $name,
        'department' => $department,
        'avg_manager_rating' => $c1,
        'final_rating' => $c2,
        'avg_goal_completion' => $c3,
        'goal_completion_ratio' => $c4,
        'positive_feedback_count' => $c5,
    ];
}

/**
 * Helper: bobot default TOPSIS (sama seperti controller).
 */
function defaultWeights(): array
{
    return [
        'avg_manager_rating' => 0.30,
        'final_rating' => 0.30,
        'avg_goal_completion' => 0.20,
        'goal_completion_ratio' => 0.10,
        'positive_feedback_count' => 0.10,
    ];
}

// --- Test 1: Empty candidates ---
it('returns empty ranking when no candidates provided', function () {
    $service = new TopsisService();
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
    $service = new TopsisService();
    $candidates = [makeCandidate('emp-1', 'Alice', 4.5, 4.0, 80.0, 0.9, 5)];
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
    $service = new TopsisService();
    $candidates = [
        makeCandidate('emp-low', 'Bob',   2.0, 2.0, 30.0, 0.3, 0),
        makeCandidate('emp-high', 'Alice', 5.0, 5.0, 100.0, 1.0, 10),
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
    $service = new TopsisService();
    $candidates = [
        makeCandidate('emp-3', 'Charlie', 3.0, 3.0, 50.0, 0.5, 3),
        makeCandidate('emp-5', 'Eve',     5.0, 5.0, 100.0, 1.0, 10),
        makeCandidate('emp-1', 'Alice',   1.0, 1.0, 10.0, 0.1, 0),
        makeCandidate('emp-4', 'Diana',   4.0, 4.0, 75.0, 0.8, 7),
        makeCandidate('emp-2', 'Bob',     2.0, 2.0, 30.0, 0.3, 1),
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
    $service = new TopsisService();
    $candidates = [
        makeCandidate('emp-1', 'Alice', 3.5, 3.5, 60.0, 0.7, 4),
        makeCandidate('emp-2', 'Bob',   3.5, 3.5, 60.0, 0.7, 4),
        makeCandidate('emp-3', 'Charlie', 3.5, 3.5, 60.0, 0.7, 4),
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
    $service = new TopsisService();
    // C5 (positive_feedback_count) = 0 for all candidates
    $candidates = [
        makeCandidate('emp-1', 'Alice', 4.0, 4.0, 80.0, 0.9, 0),
        makeCandidate('emp-2', 'Bob',   3.0, 3.0, 60.0, 0.5, 0),
        makeCandidate('emp-3', 'Charlie', 2.0, 2.0, 40.0, 0.3, 0),
    ];
    $result = $service->calculate($candidates, defaultWeights());

    expect($result['total_candidates'])->toBe(3);

    // Ranking should still work based on other criteria
    expect($result['ranking'][0]['staff_member_id'])->toBe('emp-1'); // Alice best
    expect($result['ranking'][2]['staff_member_id'])->toBe('emp-3'); // Charlie worst

    // All C5 normalized values should be 0 (not NaN)
    foreach ($result['ranking'] as $ranked) {
        expect(is_nan($ranked['normalized_scores']['positive_feedback_count']))->toBeFalse();
        expect($ranked['normalized_scores']['positive_feedback_count'])->toBe(0.0);
        expect(is_nan($ranked['closeness_coefficient']))->toBeFalse();
    }
});

// --- Test 7: Custom weights change ranking ---
it('produces different rankings when weights change', function () {
    $service = new TopsisService();

    // Alice: high competency (C1), low KPI (C2)
    // Bob: low competency (C1), high KPI (C2)
    $candidates = [
        makeCandidate('emp-1', 'Alice', 5.0, 2.0, 50.0, 0.5, 3),
        makeCandidate('emp-2', 'Bob',   2.0, 5.0, 50.0, 0.5, 3),
    ];

    // Weight heavily on C1 (competency) → Alice should win
    $weightsC1Heavy = [
        'avg_manager_rating' => 0.70,
        'final_rating' => 0.05,
        'avg_goal_completion' => 0.10,
        'goal_completion_ratio' => 0.10,
        'positive_feedback_count' => 0.05,
    ];
    $resultC1 = $service->calculate($candidates, $weightsC1Heavy);
    expect($resultC1['ranking'][0]['staff_member_id'])->toBe('emp-1'); // Alice wins

    // Weight heavily on C2 (KPI) → Bob should win
    $weightsC2Heavy = [
        'avg_manager_rating' => 0.05,
        'final_rating' => 0.70,
        'avg_goal_completion' => 0.10,
        'goal_completion_ratio' => 0.10,
        'positive_feedback_count' => 0.05,
    ];
    $resultC2 = $service->calculate($candidates, $weightsC2Heavy);
    expect($resultC2['ranking'][0]['staff_member_id'])->toBe('emp-2'); // Bob wins
});

// --- Test 8: Rating label boundaries ---
it('assigns correct labels based on closeness coefficient boundaries', function () {
    $service = new TopsisService();

    // Create 2 extreme candidates to produce known coefficients
    // Best candidate gets coefficient = 1.0 (Outstanding)
    // Worst candidate gets coefficient = 0.0 (Unsatisfactory)
    $candidates = [
        makeCandidate('emp-best', 'Best', 5.0, 5.0, 100.0, 1.0, 10),
        makeCandidate('emp-worst', 'Worst', 1.0, 1.0, 0.0, 0.0, 0),
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
    $service = new TopsisService();
    $candidates = [
        makeCandidate('emp-1', 'Alice', 4.0, 4.5, 75.0, 0.8, 5),
        makeCandidate('emp-2', 'Bob',   3.0, 3.5, 60.0, 0.6, 3),
        makeCandidate('emp-3', 'Charlie', 2.5, 2.0, 40.0, 0.4, 1),
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
        'avg_manager_rating',
        'final_rating',
        'avg_goal_completion',
        'goal_completion_ratio',
        'positive_feedback_count'
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
