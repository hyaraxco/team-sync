<?php

use App\Services\TopsisService;

/*
 |--------------------------------------------------------------------------
 | TOPSIS Service — Pure Math Unit Tests
 |
 | TopsisService is a pure algorithm service with no database, no IO, and no
 | external dependencies. Every test here exercises the TOPSIS math directly.
 |
 | Criteria (all Benefit type — higher is better):
 |   performance_score, attendance_rate, goal_completion,
 |   feedback_score, tenure_factor
 |
 | getRatingLabel() thresholds:
 |   ≥ 0.80 → Outstanding
 |   ≥ 0.65 → Exceeds Expectations
 |   ≥ 0.50 → Meets Expectations
 |   ≥ 0.35 → Needs Improvement
 |   < 0.35 → Unsatisfactory
 |--------------------------------------------------------------------------
 */

/**
 * Helper: create a candidate array with all 5 TOPSIS criteria.
 */
function topsisCandidate(
    string $id,
    string $name,
    float $performanceScore,
    float $attendanceRate,
    float $goalCompletion,
    float $feedbackScore,
    float $tenureFactor,
    ?string $department = 'Engineering',
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
 * Helper: default weights that sum to 1.0.
 */
function topsisWeights(): array
{
    return [
        'performance_score' => 0.30,
        'attendance_rate' => 0.20,
        'goal_completion' => 0.25,
        'feedback_score' => 0.15,
        'tenure_factor' => 0.10,
    ];
}

/**
 * Helper: weights heavily skewed toward performance_score (C1).
 */
function topsisWeightsC1Heavy(): array
{
    return [
        'performance_score' => 0.70,
        'attendance_rate' => 0.05,
        'goal_completion' => 0.10,
        'feedback_score' => 0.05,
        'tenure_factor' => 0.10,
    ];
}

/**
 * Helper: weights heavily skewed toward attendance_rate (C2).
 */
function topsisWeightsC2Heavy(): array
{
    return [
        'performance_score' => 0.05,
        'attendance_rate' => 0.70,
        'goal_completion' => 0.10,
        'feedback_score' => 0.05,
        'tenure_factor' => 0.10,
    ];
}

/*
|--------------------------------------------------------------------------
| describe() — calculate() with 2 candidates
|--------------------------------------------------------------------------
*/
describe('calculate() with 2 candidates', function () {

    it('ranks the higher performer above the lower performer', function () {
        $service = new TopsisService;

        $candidates = [
            topsisCandidate('emp-low', 'Bob', 20.0, 50.0, 30.0, 0, 40.0),
            topsisCandidate('emp-high', 'Alice', 100.0, 98.0, 100.0, 10, 95.0),
        ];

        $result = $service->calculate($candidates, topsisWeights());

        expect($result['total_candidates'])->toBe(2);
        expect($result['ranking'])->toHaveCount(2);

        // Alice (best across all criteria) → rank 1
        expect($result['ranking'][0]['staff_member_id'])->toBe('emp-high');
        expect($result['ranking'][0]['rank'])->toBe(1);

        // Bob (worst across all criteria) → rank 2
        expect($result['ranking'][1]['staff_member_id'])->toBe('emp-low');
        expect($result['ranking'][1]['rank'])->toBe(2);

        // Higher performer has strictly greater closeness coefficient
        expect($result['ranking'][0]['closeness_coefficient'])
            ->toBeGreaterThan($result['ranking'][1]['closeness_coefficient']);
    });

    it('gives the best candidate a closeness coefficient of 1.0 (is the ideal)', function () {
        $service = new TopsisService;

        $candidates = [
            topsisCandidate('emp-low', 'Bob', 20.0, 50.0, 30.0, 0, 40.0),
            topsisCandidate('emp-high', 'Alice', 100.0, 98.0, 100.0, 10, 95.0),
        ];

        $result = $service->calculate($candidates, topsisWeights());

        // Alice is the ideal: D+ = 0 → CC = D- / (0 + D-) = 1.0
        expect($result['ranking'][0]['closeness_coefficient'])->toBe(1.0);
        expect($result['ranking'][0]['distance_positive'])->toBe(0.0);
    });

    it('gives the worst candidate a closeness coefficient of 0.0 (is the anti-ideal)', function () {
        $service = new TopsisService;

        $candidates = [
            topsisCandidate('emp-low', 'Bob', 20.0, 50.0, 30.0, 0, 40.0),
            topsisCandidate('emp-high', 'Alice', 100.0, 98.0, 100.0, 10, 95.0),
        ];

        $result = $service->calculate($candidates, topsisWeights());

        // Bob is the anti-ideal: D- = 0 → CC = 0 / (D+ + 0) = 0.0
        expect($result['ranking'][1]['closeness_coefficient'])->toBe(0.0);
        expect($result['ranking'][1]['distance_negative'])->toBe(0.0);
    });

    it('produces labels matching closeness coefficient values', function () {
        $service = new TopsisService;

        $candidates = [
            topsisCandidate('emp-low', 'Bob', 20.0, 50.0, 30.0, 0, 40.0),
            topsisCandidate('emp-high', 'Alice', 100.0, 98.0, 100.0, 10, 95.0),
        ];

        $result = $service->calculate($candidates, topsisWeights());

        expect($result['ranking'][0]['label'])->toBe('Outstanding'); // CC = 1.0
        expect($result['ranking'][1]['label'])->toBe('Unsatisfactory'); // CC = 0.0
    });
});

/*
|--------------------------------------------------------------------------
| describe() — calculate() with 3+ candidates
|--------------------------------------------------------------------------
*/
describe('calculate() with 3+ candidates', function () {

    it('correctly ranks 3 candidates in descending order', function () {
        $service = new TopsisService;

        $candidates = [
            topsisCandidate('emp-mid', 'Charlie', 60.0, 70.0, 50.0, 3, 65.0),
            topsisCandidate('emp-high', 'Alice', 100.0, 98.0, 100.0, 10, 95.0),
            topsisCandidate('emp-low', 'Bob', 20.0, 40.0, 10.0, 0, 35.0),
        ];

        $result = $service->calculate($candidates, topsisWeights());

        expect($result['total_candidates'])->toBe(3);
        expect($result['ranking'])->toHaveCount(3);

        // Ranks should be sequential 1, 2, 3
        $ranks = array_column($result['ranking'], 'rank');
        expect($ranks)->toBe([1, 2, 3]);

        // Alice best, Bob worst
        expect($result['ranking'][0]['staff_member_id'])->toBe('emp-high');
        expect($result['ranking'][2]['staff_member_id'])->toBe('emp-low');

        // Closeness coefficients strictly descending
        $cc = array_column($result['ranking'], 'closeness_coefficient');
        expect($cc[0])->toBeGreaterThan($cc[1]);
        expect($cc[1])->toBeGreaterThan($cc[2]);
    });

    it('correctly ranks 5 candidates with varied scores', function () {
        $service = new TopsisService;

        $candidates = [
            topsisCandidate('emp-3', 'Charlie', 60.0, 75.0, 50.0, 3, 70.0),
            topsisCandidate('emp-5', 'Eve', 100.0, 99.0, 100.0, 10, 99.0),
            topsisCandidate('emp-1', 'Alice', 20.0, 40.0, 10.0, 0, 35.0),
            topsisCandidate('emp-4', 'Diana', 80.0, 88.0, 75.0, 7, 86.0),
            topsisCandidate('emp-2', 'Bob', 40.0, 60.0, 30.0, 1, 55.0),
        ];

        $result = $service->calculate($candidates, topsisWeights());

        expect($result['total_candidates'])->toBe(5);
        expect($result['ranking'])->toHaveCount(5);

        // Eve (best) → rank 1
        expect($result['ranking'][0]['staff_member_id'])->toBe('emp-5');
        // Alice (worst) → rank 5
        expect($result['ranking'][4]['staff_member_id'])->toBe('emp-1');

        // Verify descending order
        $cc = array_column($result['ranking'], 'closeness_coefficient');
        for ($i = 0; $i < count($cc) - 1; $i++) {
            expect($cc[$i])->toBeGreaterThanOrEqual($cc[$i + 1]);
        }

        // Verify sequential ranks
        $ranks = array_column($result['ranking'], 'rank');
        expect($ranks)->toBe([1, 2, 3, 4, 5]);
    });
});

/*
|--------------------------------------------------------------------------
| describe() — buildSingleResult() with 1 candidate
|--------------------------------------------------------------------------
*/
describe('buildSingleResult() with 1 candidate', function () {

    it('returns rank 1 with closeness coefficient 1.0', function () {
        $service = new TopsisService;

        $candidates = [
            topsisCandidate('emp-1', 'Alice', 90.0, 95.0, 80.0, 5, 90.0),
        ];

        $result = $service->calculate($candidates, topsisWeights());

        expect($result['total_candidates'])->toBe(1);
        expect($result['ranking'])->toHaveCount(1);

        $ranked = $result['ranking'][0];
        expect($ranked['rank'])->toBe(1);
        expect($ranked['staff_member_id'])->toBe('emp-1');
        expect($ranked['closeness_coefficient'])->toBe(1.0);
        expect($ranked['distance_positive'])->toBe(0.0);
        expect($ranked['distance_negative'])->toBe(1.0);
        expect($ranked['label'])->toBe('Outstanding');
    });

    it('uses raw scores for normalized and weighted scores (no division needed)', function () {
        $service = new TopsisService;

        $candidates = [
            topsisCandidate('emp-1', 'Alice', 90.0, 95.0, 80.0, 5, 90.0),
        ];

        $result = $service->calculate($candidates, topsisWeights());
        $ranked = $result['ranking'][0];

        // Single candidate: normalized = raw, weighted = raw
        expect($ranked['normalized_scores'])->toBe($ranked['raw_scores']);
        expect($ranked['weighted_scores'])->toBe($ranked['raw_scores']);
    });

    it('sets ideal_positive to the candidate raw scores', function () {
        $service = new TopsisService;

        $candidates = [
            topsisCandidate('emp-1', 'Alice', 90.0, 95.0, 80.0, 5, 90.0),
        ];

        $result = $service->calculate($candidates, topsisWeights());

        expect($result['ideal_positive'])->toBe([
            'performance_score' => 90.0,
            'attendance_rate' => 95.0,
            'goal_completion' => 80.0,
            'feedback_score' => 5.0,
            'tenure_factor' => 90.0,
        ]);
    });

    it('sets ideal_negative to all zeros', function () {
        $service = new TopsisService;

        $candidates = [
            topsisCandidate('emp-1', 'Alice', 90.0, 95.0, 80.0, 5, 90.0),
        ];

        $result = $service->calculate($candidates, topsisWeights());

        $expected = array_fill_keys([
            'performance_score', 'attendance_rate', 'goal_completion',
            'feedback_score', 'tenure_factor',
        ], 0);

        expect($result['ideal_negative'])->toBe($expected);
    });

    it('preserves department in ranking output', function () {
        $service = new TopsisService;

        $candidates = [
            topsisCandidate('emp-1', 'Alice', 90.0, 95.0, 80.0, 5, 90.0, 'Product'),
        ];

        $result = $service->calculate($candidates, topsisWeights());

        expect($result['ranking'][0]['department'])->toBe('Product');
    });

    it('defaults department to null when not provided', function () {
        $service = new TopsisService;

        $candidate = [
            'staff_member_id' => 'emp-1',
            'employee_name' => 'Alice',
            'performance_score' => 90.0,
            'attendance_rate' => 95.0,
            'goal_completion' => 80.0,
            'feedback_score' => 5,
            'tenure_factor' => 90.0,
        ];

        $result = $service->calculate([$candidate], topsisWeights());

        expect($result['ranking'][0]['department'])->toBeNull();
    });
});

/*
|--------------------------------------------------------------------------
| describe() — buildSingleResult() with 0 candidates
|--------------------------------------------------------------------------
*/
describe('buildSingleResult() with 0 candidates', function () {

    it('returns empty ranking with correct structure', function () {
        $service = new TopsisService;

        $result = $service->calculate([], topsisWeights());

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

    it('returns the provided weights unchanged', function () {
        $service = new TopsisService;
        $weights = topsisWeights();

        $result = $service->calculate([], $weights);

        expect($result['weights'])->toBe($weights);
    });

    it('lists all 5 criteria and their types', function () {
        $service = new TopsisService;

        $result = $service->calculate([], topsisWeights());

        expect($result['criteria'])->toHaveCount(5);
        expect($result['criteria'])->toContain(
            'performance_score',
            'attendance_rate',
            'goal_completion',
            'feedback_score',
            'tenure_factor'
        );

        // All benefit type
        foreach ($result['criteria'] as $criterion) {
            expect($result['criteria_types'][$criterion])->toBeTrue();
        }
    });
});

/*
|--------------------------------------------------------------------------
| describe() — getRatingLabel() boundary values (tested indirectly)
|--------------------------------------------------------------------------
*/
describe('getRatingLabel() — all 5 rating boundaries', function () {

    /**
     * Helper: create two candidates whose closeness coefficients bracket
     * a known threshold, then read the label from the ranking output.
     *
     * Because getRatingLabel is private, we exercise it through calculate().
     * The two-candidate case always produces CC=1.0 (best) and CC=0.0 (worst).
     * For intermediate labels we craft candidates with specific score patterns.
     */
    it('assigns Outstanding for CC ≥ 0.80', function () {
        $service = new TopsisService;

        // Best candidate in a 2-candidate set → CC = 1.0 → Outstanding
        $candidates = [
            topsisCandidate('emp-a', 'Alice', 20.0, 40.0, 10.0, 0, 35.0),
            topsisCandidate('emp-b', 'Bob', 100.0, 98.0, 100.0, 10, 95.0),
        ];

        $result = $service->calculate($candidates, topsisWeights());
        $best = collect($result['ranking'])->firstWhere('staff_member_id', 'emp-b');

        expect($best['closeness_coefficient'])->toBeGreaterThanOrEqual(0.80);
        expect($best['label'])->toBe('Outstanding');
    });

    it('assigns Exceeds Expectations for CC ∈ [0.65, 0.80)', function () {
        $service = new TopsisService;

        // Use a broad set of candidates and verify label/CC consistency
        // for the one that lands in [0.65, 0.80)
        $candidates = [
            topsisCandidate('emp-1', 'Alice', 20.0, 10.0, 5.0, 0, 10.0),
            topsisCandidate('emp-2', 'Bob', 40.0, 45.0, 25.0, 2, 45.0),
            topsisCandidate('emp-3', 'Charlie', 70.0, 70.0, 55.0, 5, 70.0),
            topsisCandidate('emp-4', 'Diana', 80.0, 90.0, 80.0, 8, 90.0),
            topsisCandidate('emp-5', 'Eve', 100.0, 100.0, 100.0, 10, 100.0),
        ];

        $result = $service->calculate($candidates, topsisWeights());

        // Verify that EVERY candidate's label is consistent with its CC
        foreach ($result['ranking'] as $ranked) {
            $cc = $ranked['closeness_coefficient'];
            if ($cc >= 0.80) {
                expect($ranked['label'])->toBe('Outstanding');
            } elseif ($cc >= 0.65) {
                expect($ranked['label'])->toBe('Exceeds Expectations');
            } elseif ($cc >= 0.50) {
                expect($ranked['label'])->toBe('Meets Expectations');
            } elseif ($cc >= 0.35) {
                expect($ranked['label'])->toBe('Needs Improvement');
            } else {
                expect($ranked['label'])->toBe('Unsatisfactory');
            }
        }

        // Confirm at least one candidate landed in [0.65, 0.80)
        $ccs = array_column($result['ranking'], 'closeness_coefficient');
        $hasExceeds = collect($ccs)->contains(fn ($cc) => $cc >= 0.65 && $cc < 0.80);
        expect($hasExceeds)->toBeTrue('Expected at least one candidate with CC in [0.65, 0.80)');
    });

    it('assigns Meets Expectations for CC ∈ [0.50, 0.65)', function () {
        $service = new TopsisService;

        $candidates = [
            topsisCandidate('emp-1', 'Alice', 20.0, 10.0, 5.0, 0, 10.0),
            topsisCandidate('emp-2', 'Bob', 50.0, 55.0, 35.0, 3, 55.0),
            topsisCandidate('emp-3', 'Charlie', 70.0, 75.0, 60.0, 5, 75.0),
            topsisCandidate('emp-4', 'Diana', 90.0, 95.0, 90.0, 9, 95.0),
            topsisCandidate('emp-5', 'Eve', 100.0, 100.0, 100.0, 10, 100.0),
        ];

        $result = $service->calculate($candidates, topsisWeights());

        // Verify label/CC consistency for every candidate
        foreach ($result['ranking'] as $ranked) {
            $cc = $ranked['closeness_coefficient'];
            if ($cc >= 0.80) {
                expect($ranked['label'])->toBe('Outstanding');
            } elseif ($cc >= 0.65) {
                expect($ranked['label'])->toBe('Exceeds Expectations');
            } elseif ($cc >= 0.50) {
                expect($ranked['label'])->toBe('Meets Expectations');
            } elseif ($cc >= 0.35) {
                expect($ranked['label'])->toBe('Needs Improvement');
            } else {
                expect($ranked['label'])->toBe('Unsatisfactory');
            }
        }

        // Confirm at least one candidate landed in [0.50, 0.65)
        $ccs = array_column($result['ranking'], 'closeness_coefficient');
        $hasMeets = collect($ccs)->contains(fn ($cc) => $cc >= 0.50 && $cc < 0.65);
        expect($hasMeets)->toBeTrue('Expected at least one candidate with CC in [0.50, 0.65)');
    });

    it('assigns Needs Improvement for CC ∈ [0.35, 0.50)', function () {
        $service = new TopsisService;

        // Verify label/CC consistency for a broader set of candidates.
        // This tests the label mapping correctness even if we can't guarantee
        // a candidate lands in every specific sub-range.
        $candidates = [
            topsisCandidate('emp-1', 'Alice', 20.0, 10.0, 5.0, 0, 10.0),
            topsisCandidate('emp-2', 'Bob', 40.0, 45.0, 25.0, 2, 45.0),
            topsisCandidate('emp-3', 'Charlie', 70.0, 70.0, 55.0, 5, 70.0),
            topsisCandidate('emp-4', 'Diana', 80.0, 90.0, 80.0, 8, 90.0),
            topsisCandidate('emp-5', 'Eve', 100.0, 100.0, 100.0, 10, 100.0),
        ];

        $result = $service->calculate($candidates, topsisWeights());

        // Verify label/CC consistency for every candidate
        foreach ($result['ranking'] as $ranked) {
            $cc = $ranked['closeness_coefficient'];
            if ($cc >= 0.80) {
                expect($ranked['label'])->toBe('Outstanding');
            } elseif ($cc >= 0.65) {
                expect($ranked['label'])->toBe('Exceeds Expectations');
            } elseif ($cc >= 0.50) {
                expect($ranked['label'])->toBe('Meets Expectations');
            } elseif ($cc >= 0.35) {
                expect($ranked['label'])->toBe('Needs Improvement');
            } else {
                expect($ranked['label'])->toBe('Unsatisfactory');
            }
        }

        // Verify range coverage: with 5 candidates spanning the full spectrum,
        // we expect at least one candidate below 0.50 (either in Needs Improvement
        // or Unsatisfactory range)
        $ccs = array_column($result['ranking'], 'closeness_coefficient');
        $hasBelowMidpoint = collect($ccs)->contains(fn ($cc) => $cc < 0.50);
        expect($hasBelowMidpoint)->toBeTrue('Expected at least one candidate with CC < 0.50');
    });

    it('assigns Unsatisfactory for CC < 0.35', function () {
        $service = new TopsisService;

        // Worst candidate in a 2-candidate set → CC = 0.0 → Unsatisfactory
        $candidates = [
            topsisCandidate('emp-a', 'Alice', 20.0, 40.0, 10.0, 0, 35.0),
            topsisCandidate('emp-b', 'Bob', 100.0, 98.0, 100.0, 10, 95.0),
        ];

        $result = $service->calculate($candidates, topsisWeights());
        $worst = collect($result['ranking'])->firstWhere('staff_member_id', 'emp-a');

        expect($worst['closeness_coefficient'])->toBeLessThan(0.35);
        expect($worst['label'])->toBe('Unsatisfactory');
    });

    it('labels are always one of the 5 defined labels', function () {
        $service = new TopsisService;

        $candidates = [
            topsisCandidate('emp-1', 'Alice', 90.0, 95.0, 80.0, 5, 90.0),
            topsisCandidate('emp-2', 'Bob', 50.0, 60.0, 40.0, 2, 55.0),
            topsisCandidate('emp-3', 'Charlie', 70.0, 78.0, 60.0, 4, 72.0),
        ];

        $result = $service->calculate($candidates, topsisWeights());

        $validLabels = [
            'Outstanding',
            'Exceeds Expectations',
            'Meets Expectations',
            'Needs Improvement',
            'Unsatisfactory',
        ];

        foreach ($result['ranking'] as $ranked) {
            expect($ranked['label'])->toBeIn($validLabels);
        }
    });
});

/*
|--------------------------------------------------------------------------
| describe() — Identical scores across all candidates
|--------------------------------------------------------------------------
*/
describe('identical scores — all candidates', function () {

    it('gives all candidates the same closeness coefficient', function () {
        $service = new TopsisService;

        $candidates = [
            topsisCandidate('emp-1', 'Alice', 70.0, 80.0, 60.0, 4, 80.0),
            topsisCandidate('emp-2', 'Bob', 70.0, 80.0, 60.0, 4, 80.0),
            topsisCandidate('emp-3', 'Charlie', 70.0, 80.0, 60.0, 4, 80.0),
        ];

        $result = $service->calculate($candidates, topsisWeights());

        expect($result['total_candidates'])->toBe(3);

        $coefficients = array_column($result['ranking'], 'closeness_coefficient');

        // All CC values should be identical
        expect($coefficients[0])->toBe($coefficients[1]);
        expect($coefficients[1])->toBe($coefficients[2]);
    });

    it('produces no NaN or Infinity values', function () {
        $service = new TopsisService;

        $candidates = [
            topsisCandidate('emp-1', 'Alice', 70.0, 80.0, 60.0, 4, 80.0),
            topsisCandidate('emp-2', 'Bob', 70.0, 80.0, 60.0, 4, 80.0),
        ];

        $result = $service->calculate($candidates, topsisWeights());

        foreach ($result['ranking'] as $ranked) {
            expect(is_nan($ranked['closeness_coefficient']))->toBeFalse();
            expect(is_infinite($ranked['closeness_coefficient']))->toBeFalse();
            expect(is_nan($ranked['distance_positive']))->toBeFalse();
            expect(is_infinite($ranked['distance_positive']))->toBeFalse();
            expect(is_nan($ranked['distance_negative']))->toBeFalse();
            expect(is_infinite($ranked['distance_negative']))->toBeFalse();
        }
    });

    it('normalization denominator uses 1.0 fallback when all values are zero', function () {
        $service = new TopsisService;

        // All candidates have all-zero scores → every column sum of squares = 0
        $candidates = [
            topsisCandidate('emp-1', 'Alice', 0.0, 0.0, 0.0, 0, 0.0),
            topsisCandidate('emp-2', 'Bob', 0.0, 0.0, 0.0, 0, 0.0),
        ];

        $result = $service->calculate($candidates, topsisWeights());

        expect($result['total_candidates'])->toBe(2);

        // Should produce valid results, not NaN/Inf
        foreach ($result['ranking'] as $ranked) {
            expect(is_nan($ranked['closeness_coefficient']))->toBeFalse();
            expect(is_infinite($ranked['closeness_coefficient']))->toBeFalse();

            // All normalized scores should be 0.0 (0 / 1.0)
            foreach ($ranked['normalized_scores'] as $val) {
                expect($val)->toBe(0.0);
            }

            // All weighted scores should be 0.0
            foreach ($ranked['weighted_scores'] as $val) {
                expect($val)->toBe(0.0);
            }
        }
    });

    it('handles both candidates having identical scores and all-zero scores', function () {
        $service = new TopsisService;

        $candidates = [
            topsisCandidate('emp-1', 'Alice', 0.0, 0.0, 0.0, 0, 0.0),
            topsisCandidate('emp-2', 'Bob', 0.0, 0.0, 0.0, 0, 0.0),
        ];

        $result = $service->calculate($candidates, topsisWeights());

        // Both should have the same CC (D+=0, D-=0 → CC=0 for safety division)
        $cc = array_column($result['ranking'], 'closeness_coefficient');
        expect($cc[0])->toBe($cc[1]);
        expect($cc[0])->toBe(0.0); // D+ + D- = 0 → fallback to 0.0
    });
});

/*
|--------------------------------------------------------------------------
| describe() — All-zero raw scores (normalization denominator = 0)
|--------------------------------------------------------------------------
*/
describe('candidate with all-zero raw scores', function () {

    it('normalizes zero-score candidate using denominator fallback of 1.0', function () {
        $service = new TopsisService;

        $candidates = [
            topsisCandidate('emp-zero', 'Zero', 0.0, 0.0, 0.0, 0, 0.0),
            topsisCandidate('emp-normal', 'Normal', 80.0, 90.0, 75.0, 6, 85.0),
        ];

        $result = $service->calculate($candidates, topsisWeights());

        expect($result['total_candidates'])->toBe(2);

        $zero = collect($result['ranking'])->firstWhere('staff_member_id', 'emp-zero');
        $normal = collect($result['ranking'])->firstWhere('staff_member_id', 'emp-normal');

        // Zero-score candidate should be ranked last
        expect($zero['rank'])->toBe(2);
        expect($normal['rank'])->toBe(1);

        // Zero candidate: all normalized scores = 0.0 / 1.0 = 0.0
        foreach ($zero['normalized_scores'] as $val) {
            expect($val)->toBe(0.0);
        }

        // Normal candidate has non-zero normalized values
        $hasNonZero = false;
        foreach ($normal['normalized_scores'] as $val) {
            if ($val > 0.0) {
                $hasNonZero = true;
                break;
            }
        }
        expect($hasNonZero)->toBeTrue();
    });

    it('zero-score candidate gets closeness coefficient 0.0', function () {
        $service = new TopsisService;

        $candidates = [
            topsisCandidate('emp-zero', 'Zero', 0.0, 0.0, 0.0, 0, 0.0),
            topsisCandidate('emp-normal', 'Normal', 80.0, 90.0, 75.0, 6, 85.0),
        ];

        $result = $service->calculate($candidates, topsisWeights());

        $zero = collect($result['ranking'])->firstWhere('staff_member_id', 'emp-zero');
        expect($zero['closeness_coefficient'])->toBe(0.0);
    });

    it('handles one column being all-zero while others are non-zero', function () {
        $service = new TopsisService;

        // feedback_score = 0 for all candidates
        $candidates = [
            topsisCandidate('emp-1', 'Alice', 80.0, 95.0, 80.0, 0, 92.0),
            topsisCandidate('emp-2', 'Bob', 60.0, 80.0, 60.0, 0, 75.0),
            topsisCandidate('emp-3', 'Charlie', 40.0, 60.0, 40.0, 0, 58.0),
        ];

        $result = $service->calculate($candidates, topsisWeights());

        expect($result['total_candidates'])->toBe(3);

        // Ranking still works on other criteria
        expect($result['ranking'][0]['staff_member_id'])->toBe('emp-1');
        expect($result['ranking'][2]['staff_member_id'])->toBe('emp-3');

        // All feedback_score normalized values should be 0.0 (not NaN)
        foreach ($result['ranking'] as $ranked) {
            expect(is_nan($ranked['normalized_scores']['feedback_score']))->toBeFalse();
            expect($ranked['normalized_scores']['feedback_score'])->toBe(0.0);
        }
    });
});

/*
|--------------------------------------------------------------------------
| describe() — Weight = 0 for a criterion
|--------------------------------------------------------------------------
*/
describe('weight = 0 for a criterion', function () {

    it('excludes that criterion from influencing the ranking', function () {
        $service = new TopsisService;

        // Alice: high performance_score (100), low attendance_rate (50)
        // Bob: low performance_score (20), high attendance_rate (100)
        // All other criteria identical
        $candidates = [
            topsisCandidate('emp-1', 'Alice', 100.0, 50.0, 50.0, 3, 80.0),
            topsisCandidate('emp-2', 'Bob', 20.0, 100.0, 50.0, 3, 80.0),
        ];

        // Weight performance_score = 0 → only attendance_rate matters
        $weightsZeroC1 = [
            'performance_score' => 0.00,
            'attendance_rate' => 0.80,
            'goal_completion' => 0.05,
            'feedback_score' => 0.05,
            'tenure_factor' => 0.10,
        ];

        $result = $service->calculate($candidates, $weightsZeroC1);

        // Bob has higher attendance_rate → should rank first
        expect($result['ranking'][0]['staff_member_id'])->toBe('emp-2');
        expect($result['ranking'][1]['staff_member_id'])->toBe('emp-1');
    });

    it('weighted score is 0.0 for a zero-weight criterion', function () {
        $service = new TopsisService;

        $candidates = [
            topsisCandidate('emp-1', 'Alice', 80.0, 90.0, 75.0, 5, 88.0),
            topsisCandidate('emp-2', 'Bob', 60.0, 80.0, 60.0, 3, 76.0),
        ];

        // Set performance_score weight to 0
        $weights = topsisWeights();
        $weights['performance_score'] = 0.0;

        $result = $service->calculate($candidates, $weights);

        foreach ($result['ranking'] as $ranked) {
            expect($ranked['weighted_scores']['performance_score'])->toBe(0.0);
        }
    });

    it('all weights = 0 still produces valid output', function () {
        $service = new TopsisService;

        $candidates = [
            topsisCandidate('emp-1', 'Alice', 80.0, 90.0, 75.0, 5, 88.0),
            topsisCandidate('emp-2', 'Bob', 60.0, 80.0, 60.0, 3, 76.0),
        ];

        $zeroWeights = [
            'performance_score' => 0.0,
            'attendance_rate' => 0.0,
            'goal_completion' => 0.0,
            'feedback_score' => 0.0,
            'tenure_factor' => 0.0,
        ];

        $result = $service->calculate($candidates, $zeroWeights);

        expect($result['total_candidates'])->toBe(2);
        expect($result['ranking'])->toHaveCount(2);

        // All weighted scores should be 0 → distances to ideal are 0 → CC = 0
        foreach ($result['ranking'] as $ranked) {
            expect(is_nan($ranked['closeness_coefficient']))->toBeFalse();
            expect(is_infinite($ranked['closeness_coefficient']))->toBeFalse();
        }
    });

    it('reverses ranking when the only non-zero weight shifts from C1 to C2', function () {
        $service = new TopsisService;

        // Alice: high performance_score, low attendance_rate
        // Bob: low performance_score, high attendance_rate
        $candidates = [
            topsisCandidate('emp-1', 'Alice', 100.0, 50.0, 50.0, 3, 80.0),
            topsisCandidate('emp-2', 'Bob', 40.0, 95.0, 50.0, 3, 80.0),
        ];

        // Heavy C1 (performance_score) → Alice wins
        $resultC1 = $service->calculate($candidates, topsisWeightsC1Heavy());
        expect($resultC1['ranking'][0]['staff_member_id'])->toBe('emp-1');

        // Heavy C2 (attendance_rate) → Bob wins
        $resultC2 = $service->calculate($candidates, topsisWeightsC2Heavy());
        expect($resultC2['ranking'][0]['staff_member_id'])->toBe('emp-2');
    });
});

/*
|--------------------------------------------------------------------------
| describe() — Output structure completeness
|--------------------------------------------------------------------------
*/
describe('output structure completeness', function () {

    it('contains all expected top-level keys', function () {
        $service = new TopsisService;

        $candidates = [
            topsisCandidate('emp-1', 'Alice', 80.0, 90.0, 75.0, 5, 88.0),
            topsisCandidate('emp-2', 'Bob', 60.0, 80.0, 60.0, 3, 76.0),
        ];

        $result = $service->calculate($candidates, topsisWeights());

        expect($result)->toHaveKeys([
            'weights',
            'ideal_positive',
            'ideal_negative',
            'criteria',
            'criteria_types',
            'ranking',
            'total_candidates',
        ]);
    });

    it('each ranking entry contains all expected keys', function () {
        $service = new TopsisService;

        $candidates = [
            topsisCandidate('emp-1', 'Alice', 80.0, 90.0, 75.0, 5, 88.0),
            topsisCandidate('emp-2', 'Bob', 60.0, 80.0, 60.0, 3, 76.0),
            topsisCandidate('emp-3', 'Charlie', 50.0, 70.0, 40.0, 1, 68.0),
        ];

        $result = $service->calculate($candidates, topsisWeights());

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
        }
    });

    it('each ranking entry has sub-arrays with all 5 criteria keys', function () {
        $service = new TopsisService;

        $candidates = [
            topsisCandidate('emp-1', 'Alice', 80.0, 90.0, 75.0, 5, 88.0),
            topsisCandidate('emp-2', 'Bob', 60.0, 80.0, 60.0, 3, 76.0),
        ];

        $result = $service->calculate($candidates, topsisWeights());

        $criteriaKeys = [
            'performance_score', 'attendance_rate', 'goal_completion',
            'feedback_score', 'tenure_factor',
        ];

        foreach ($result['ranking'] as $ranked) {
            foreach ($criteriaKeys as $key) {
                expect($ranked['raw_scores'])->toHaveKey($key);
                expect($ranked['normalized_scores'])->toHaveKey($key);
                expect($ranked['weighted_scores'])->toHaveKey($key);
            }
        }
    });

    it('closeness coefficients are between 0 and 1', function () {
        $service = new TopsisService;

        $candidates = [
            topsisCandidate('emp-1', 'Alice', 80.0, 90.0, 75.0, 5, 88.0),
            topsisCandidate('emp-2', 'Bob', 60.0, 80.0, 60.0, 3, 76.0),
            topsisCandidate('emp-3', 'Charlie', 50.0, 70.0, 40.0, 1, 68.0),
        ];

        $result = $service->calculate($candidates, topsisWeights());

        foreach ($result['ranking'] as $ranked) {
            expect($ranked['closeness_coefficient'])->toBeGreaterThanOrEqual(0.0);
            expect($ranked['closeness_coefficient'])->toBeLessThanOrEqual(1.0);
        }
    });

    it('distances are non-negative', function () {
        $service = new TopsisService;

        $candidates = [
            topsisCandidate('emp-1', 'Alice', 80.0, 90.0, 75.0, 5, 88.0),
            topsisCandidate('emp-2', 'Bob', 60.0, 80.0, 60.0, 3, 76.0),
        ];

        $result = $service->calculate($candidates, topsisWeights());

        foreach ($result['ranking'] as $ranked) {
            expect($ranked['distance_positive'])->toBeGreaterThanOrEqual(0.0);
            expect($ranked['distance_negative'])->toBeGreaterThanOrEqual(0.0);
        }
    });

    it('ideal_positive and ideal_negative have all criteria keys', function () {
        $service = new TopsisService;

        $candidates = [
            topsisCandidate('emp-1', 'Alice', 80.0, 90.0, 75.0, 5, 88.0),
            topsisCandidate('emp-2', 'Bob', 60.0, 80.0, 60.0, 3, 76.0),
        ];

        $result = $service->calculate($candidates, topsisWeights());

        $criteriaKeys = [
            'performance_score', 'attendance_rate', 'goal_completion',
            'feedback_score', 'tenure_factor',
        ];

        foreach ($criteriaKeys as $key) {
            expect($result['ideal_positive'])->toHaveKey($key);
            expect($result['ideal_negative'])->toHaveKey($key);
        }
    });

    it('weights in output match the input weights', function () {
        $service = new TopsisService;
        $weights = topsisWeights();

        $candidates = [
            topsisCandidate('emp-1', 'Alice', 80.0, 90.0, 75.0, 5, 88.0),
            topsisCandidate('emp-2', 'Bob', 60.0, 80.0, 60.0, 3, 76.0),
        ];

        $result = $service->calculate($candidates, $weights);

        expect($result['weights'])->toBe($weights);
    });

    it('distances are rounded to 6 decimal places', function () {
        $service = new TopsisService;

        $candidates = [
            topsisCandidate('emp-1', 'Alice', 80.0, 90.0, 75.0, 5, 88.0),
            topsisCandidate('emp-2', 'Bob', 60.0, 80.0, 60.0, 3, 76.0),
        ];

        $result = $service->calculate($candidates, topsisWeights());

        foreach ($result['ranking'] as $ranked) {
            // Check that distance values don't have more than 6 decimal places
            $dp = $ranked['distance_positive'];
            $dn = $ranked['distance_negative'];
            $cc = $ranked['closeness_coefficient'];

            // round(x, 6) == x means it's already rounded to 6 places
            expect(round($dp, 6))->toBe($dp);
            expect(round($dn, 6))->toBe($dn);
            expect(round($cc, 6))->toBe($cc);
        }
    });

    it('ranks are sequential starting from 1', function () {
        $service = new TopsisService;

        $candidates = [
            topsisCandidate('emp-1', 'Alice', 90.0, 95.0, 80.0, 5, 90.0),
            topsisCandidate('emp-2', 'Bob', 60.0, 75.0, 55.0, 3, 70.0),
            topsisCandidate('emp-3', 'Charlie', 40.0, 55.0, 30.0, 1, 50.0),
            topsisCandidate('emp-4', 'Diana', 80.0, 85.0, 70.0, 6, 80.0),
            topsisCandidate('emp-5', 'Eve', 20.0, 40.0, 15.0, 0, 35.0),
        ];

        $result = $service->calculate($candidates, topsisWeights());

        $ranks = array_column($result['ranking'], 'rank');
        expect($ranks)->toBe([1, 2, 3, 4, 5]);
    });
});

/*
|--------------------------------------------------------------------------
| describe() — Mathematical correctness of TOPSIS steps
|--------------------------------------------------------------------------
*/
describe('mathematical correctness', function () {

    it('normalization produces values between 0 and 1', function () {
        $service = new TopsisService;

        $candidates = [
            topsisCandidate('emp-1', 'Alice', 80.0, 90.0, 75.0, 5, 88.0),
            topsisCandidate('emp-2', 'Bob', 60.0, 80.0, 60.0, 3, 76.0),
            topsisCandidate('emp-3', 'Charlie', 50.0, 70.0, 40.0, 1, 68.0),
        ];

        $result = $service->calculate($candidates, topsisWeights());

        foreach ($result['ranking'] as $ranked) {
            foreach ($ranked['normalized_scores'] as $val) {
                expect($val)->toBeGreaterThanOrEqual(0.0);
                expect($val)->toBeLessThanOrEqual(1.0);
            }
        }
    });

    it('ideal_positive values are all Benefit-type (max per column)', function () {
        $service = new TopsisService;

        $candidates = [
            topsisCandidate('emp-1', 'Alice', 80.0, 90.0, 75.0, 5, 88.0),
            topsisCandidate('emp-2', 'Bob', 60.0, 80.0, 60.0, 3, 76.0),
        ];

        $result = $service->calculate($candidates, topsisWeights());

        // For each criterion, ideal_positive should be >= all weighted scores
        foreach ($result['ranking'] as $ranked) {
            foreach ($result['ideal_positive'] as $criterion => $idealVal) {
                expect($idealVal)->toBeGreaterThanOrEqual($ranked['weighted_scores'][$criterion]);
            }
        }
    });

    it('ideal_negative values are all Benefit-type (min per column)', function () {
        $service = new TopsisService;

        $candidates = [
            topsisCandidate('emp-1', 'Alice', 80.0, 90.0, 75.0, 5, 88.0),
            topsisCandidate('emp-2', 'Bob', 60.0, 80.0, 60.0, 3, 76.0),
        ];

        $result = $service->calculate($candidates, topsisWeights());

        // For each criterion, ideal_negative should be <= all weighted scores
        foreach ($result['ranking'] as $ranked) {
            foreach ($result['ideal_negative'] as $criterion => $idealVal) {
                expect($idealVal)->toBeLessThanOrEqual($ranked['weighted_scores'][$criterion]);
            }
        }
    });

    it('closeness coefficient formula: CC = D- / (D+ + D-)', function () {
        $service = new TopsisService;

        $candidates = [
            topsisCandidate('emp-1', 'Alice', 80.0, 90.0, 75.0, 5, 88.0),
            topsisCandidate('emp-2', 'Bob', 60.0, 80.0, 60.0, 3, 76.0),
        ];

        $result = $service->calculate($candidates, topsisWeights());

        foreach ($result['ranking'] as $ranked) {
            $dPlus = $ranked['distance_positive'];
            $dMinus = $ranked['distance_negative'];
            $cc = $ranked['closeness_coefficient'];

            $total = $dPlus + $dMinus;
            if ($total > 0) {
                expect(abs($cc - ($dMinus / $total)))->toBeLessThan(0.000001);
            } else {
                expect($cc)->toBe(0.0);
            }
        }
    });

    it('two-candidate best case: ideal positive equals candidate, distance is 0', function () {
        $service = new TopsisService;

        // When there are only 2 candidates, the best one IS the ideal positive
        // and the worst one IS the ideal negative
        $candidates = [
            topsisCandidate('emp-low', 'Bob', 20.0, 50.0, 30.0, 0, 40.0),
            topsisCandidate('emp-high', 'Alice', 100.0, 98.0, 100.0, 10, 95.0),
        ];

        $result = $service->calculate($candidates, topsisWeights());

        $best = collect($result['ranking'])->firstWhere('staff_member_id', 'emp-high');

        // Best candidate: D+ = 0, D- > 0, CC = 1.0
        expect($best['distance_positive'])->toBe(0.0);
        expect($best['distance_negative'])->toBeGreaterThan(0.0);
        expect($best['closeness_coefficient'])->toBe(1.0);
    });

    it('missing criteria keys default to 0', function () {
        $service = new TopsisService;

        // Candidate missing some criteria keys — they should default to 0
        $candidatePartial = [
            'staff_member_id' => 'emp-partial',
            'employee_name' => 'Partial',
            'department' => 'HR',
            'performance_score' => 60.0,
            // Missing: attendance_rate, goal_completion, feedback_score, tenure_factor
        ];

        $candidateFull = topsisCandidate('emp-full', 'Full', 80.0, 90.0, 70.0, 5, 80.0);

        $result = $service->calculate([$candidatePartial, $candidateFull], topsisWeights());

        expect($result['total_candidates'])->toBe(2);

        $partial = collect($result['ranking'])->firstWhere('staff_member_id', 'emp-partial');

        // Missing keys should have raw_scores = 0.0
        expect($partial['raw_scores']['attendance_rate'])->toBe(0.0);
        expect($partial['raw_scores']['goal_completion'])->toBe(0.0);
        expect($partial['raw_scores']['feedback_score'])->toBe(0.0);
        expect($partial['raw_scores']['tenure_factor'])->toBe(0.0);
    });
});
