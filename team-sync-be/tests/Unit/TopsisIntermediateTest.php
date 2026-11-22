<?php

use App\Services\TopsisService;

/**
 * Fixed-value intermediate assertions for TOPSIS (team-sync-lzo AC#2 and AC#3).
 *
 * Every expected number below was computed independently in JavaScript from
 * the R1 journal sample (Arundaa, Sopacua & Tamonob, IJIDS 3(2) 2024, pp.85-94,
 * Table 2, weights C1-C5 = 0.25/0.20/0.20/0.10/0.25) — not derived from the
 * PHP implementation. Rounding: service rounds distances and CC to 6
 * decimals, so tolerances are 1e-6 against full-precision references.
 *
 * Convention note (thesis-facing): with a single candidate, D+ = D- = 0 and
 * the ratio D-/(D+ + D-) is mathematically undefined (0/0). The application
 * convention assigns coefficient 1.0 and rank 1 deliberately. The zero-column
 * convention maps x/sqrt(0) to 0 via denominator fallback 1.0.
 */

function topsisR1Candidates(): array
{
    return [
        topsisMake('K2', 'Karyawan 2', 4.4, 4.7, 4.5, 5.0, 4.5),
        topsisMake('K3', 'Karyawan 3', 4.7, 4.7, 4.5, 5.0, 4.5),
        topsisMake('K6', 'Karyawan 6', 4.7, 4.7, 4.5, 5.0, 4.3),
        topsisMake('K11', 'Karyawan 11', 4.4, 4.0, 4.5, 5.0, 4.7),
        topsisMake('K17', 'Karyawan 17', 4.7, 4.7, 5.0, 4.0, 4.8),
    ];
}

function topsisMake(
    string $id,
    string $name,
    float $performanceScore,
    float $attendanceRate,
    float $goalCompletion,
    float $feedbackScore,
    float $tenureFactor
): array {
    return [
        'staff_member_id' => $id,
        'employee_name' => $name,
        'department' => 'Engineering',
        'performance_score' => $performanceScore,
        'attendance_rate' => $attendanceRate,
        'goal_completion' => $goalCompletion,
        'feedback_score' => $feedbackScore,
        'tenure_factor' => $tenureFactor,
    ];
}

function topsisR1Weights(): array
{
    return [
        'performance_score' => 0.25,
        'attendance_rate' => 0.20,
        'goal_completion' => 0.20,
        'feedback_score' => 0.10,
        'tenure_factor' => 0.25,
    ];
}

// --- AC#2: fixed-value normalized and weighted matrix ---

it('computes the R1 normalization denominators as fixed values', function () {
    // sqrt(sum of squares) per criterion, independent JS reference.
    $expectedDenominators = [
        'performance_score' => 10.246462804,
        'attendance_rate' => 10.215674231,
        'goal_completion' => 10.295630141,
        'feedback_score' => 10.770329614,
        'tenure_factor' => 10.203920815,
    ];

    $service = new TopsisService;
    $reflection = new ReflectionMethod(TopsisService::class, 'normalizeMatrix');
    $reflection->setAccessible(true);

    $matrix = array_map(function ($candidate) {
        return collect(['performance_score', 'attendance_rate', 'goal_completion', 'feedback_score', 'tenure_factor'])
            ->mapWithKeys(fn ($c) => [$c => (float) $candidate[$c]])
            ->all();
    }, topsisR1Candidates());

    $normalized = $reflection->invoke($service, $matrix);

    // Recover the denominator from normalized value: denom = raw / normalized.
    foreach ($matrix as $idx => $row) {
        foreach ($row as $criterion => $raw) {
            if ($raw > 0) {
                $recovered = $raw / $normalized[$idx][$criterion];
                expect($recovered)->toEqualWithDelta($expectedDenominators[$criterion], 1e-8);
            }
        }
    }
});

it('produces fixed normalized matrix values for K17 against the R1 sample', function () {
    $expectedK17 = [
        'performance_score' => 0.458694877,
        'attendance_rate' => 0.460077318,
        'goal_completion' => 0.485642931,
        'feedback_score' => 0.371390676,
        'tenure_factor' => 0.470407414,
    ];

    $result = (new TopsisService)->calculate(topsisR1Candidates(), topsisR1Weights());

    $k17 = collect($result['ranking'])->firstWhere('staff_member_id', 'K17');
    foreach ($expectedK17 as $criterion => $expected) {
        expect($k17['normalized_scores'][$criterion])->toEqualWithDelta($expected, 1e-8);
    }
});

it('produces fixed weighted matrix values for K17 against the R1 sample', function () {
    $expectedK17 = [
        'performance_score' => 0.114673719,
        'attendance_rate' => 0.092015464,
        'goal_completion' => 0.097128586,
        'feedback_score' => 0.037139068,
        'tenure_factor' => 0.117601853,
    ];

    $result = (new TopsisService)->calculate(topsisR1Candidates(), topsisR1Weights());

    $k17 = collect($result['ranking'])->firstWhere('staff_member_id', 'K17');
    foreach ($expectedK17 as $criterion => $expected) {
        expect($k17['weighted_scores'][$criterion])->toEqualWithDelta($expected, 1e-8);
    }
});

it('produces fixed D+ and D- for every R1 candidate against independent computation', function () {
    // Independent JS reference, full precision. Service rounds to 6 decimals,
    // so a 1e-6 tolerance against these values is exact-rounding safe.
    $expected = [
        'K2' => ['dp' => 0.014210572, 'dn' => 0.017263519],
        'K3' => ['dp' => 0.012180469, 'dn' => 0.018751149],
        'K6' => ['dp' => 0.015633517, 'dn' => 0.018099581],
        'K11' => ['dp' => 0.018485948, 'dn' => 0.013499997],
        'K17' => ['dp' => 0.009284767, 'dn' => 0.022040753],
    ];

    $result = (new TopsisService)->calculate(topsisR1Candidates(), topsisR1Weights());

    foreach ($expected as $id => $dist) {
        $ranked = collect($result['ranking'])->firstWhere('staff_member_id', $id);
        expect($ranked['distance_positive'])->toEqualWithDelta($dist['dp'], 1e-6, "D+ for {$id}");
        expect($ranked['distance_negative'])->toEqualWithDelta($dist['dn'], 1e-6, "D- for {$id}");
    }
});

it('matches the journal-rounded K17 distances of 0.0093 and 0.0220', function () {
    // R1 rounds to 4 decimals on page 90-91. The archived plan's estimated
    // distances (0.0048/0.0115) were wrong and must never appear here.
    $result = (new TopsisService)->calculate(topsisR1Candidates(), topsisR1Weights());

    $k17 = collect($result['ranking'])->firstWhere('staff_member_id', 'K17');
    expect(round($k17['distance_positive'], 4))->toBe(0.0093);
    expect(round($k17['distance_negative'], 4))->toBe(0.022);
});

// --- AC#3: deterministic tie ordering across input permutations ---

it('breaks ties deterministically by staff_member_id regardless of input order', function () {
    // K2a/K2b share identical scores → identical CC → tie broken by id.
    $makePair = fn () => [
        topsisMake('K09', 'Tie A', 4.4, 4.7, 4.5, 5.0, 4.5),
        topsisMake('K31', 'Tie B', 4.4, 4.7, 4.5, 5.0, 4.5),
        topsisMake('K20', 'High', 5.0, 5.0, 5.0, 5.0, 5.0),
    ];

    $permutations = [
        [$makePair()[0], $makePair()[1], $makePair()[2]],
        [$makePair()[2], $makePair()[0], $makePair()[1]],
        [$makePair()[1], $makePair()[2], $makePair()[0]],
        [$makePair()[1], $makePair()[0], $makePair()[2]],
        [$makePair()[2], $makePair()[1], $makePair()[0]],
        [$makePair()[0], $makePair()[2], $makePair()[1]],
    ];

    foreach ($permutations as $i => $candidates) {
        $result = (new TopsisService)->calculate($candidates, topsisR1Weights());

        $order = array_column($result['ranking'], 'staff_member_id');

        // High performer first, then tie broken lexicographically: K09 before K31.
        expect($order)->toBe(['K20', 'K09', 'K31'], "permutation {$i} must produce stable ordering");
    }
});

// --- AC#3: single-candidate and zero-column conventions ---

it('documents the single-candidate convention with explicit intermediate values', function () {
    $result = (new TopsisService)->calculate(
        [topsisMake('SOLO', 'Only', 4.0, 3.5, 4.2, 5.0, 2.0)],
        topsisR1Weights()
    );

    $ranked = $result['ranking'][0];

    // Convention: positive raw normalizes to 1.0, zero raw to 0.0.
    expect($ranked['normalized_scores'])->toBe([
        'performance_score' => 1.0,
        'attendance_rate' => 1.0,
        'goal_completion' => 1.0,
        'feedback_score' => 1.0,
        'tenure_factor' => 1.0,
    ]);

    // Weighted equals the weight itself; ideals collapse onto the row.
    expect($ranked['weighted_scores']['performance_score'])->toBe(0.25);
    expect($result['ideal_positive']['performance_score'])->toBe(0.25);
    expect($result['ideal_negative']['performance_score'])->toBe(0.25);

    // D+ = D- = 0; the undefined 0/0 ratio is conventionally 1.0 (rank 1).
    expect($ranked['distance_positive'])->toBe(0.0);
    expect($ranked['distance_negative'])->toBe(0.0);
    expect($ranked['closeness_coefficient'])->toBe(1.0);
    expect($ranked['rank'])->toBe(1);
});

it('handles a single zero-scored candidate under the same convention', function () {
    $result = (new TopsisService)->calculate(
        [topsisMake('ZERO', 'Zero', 0.0, 0.0, 0.0, 0.0, 0.0)],
        topsisR1Weights()
    );

    $ranked = $result['ranking'][0];

    foreach ($ranked['normalized_scores'] as $criterion => $value) {
        expect($value)->toBe(0.0, "normalized {$criterion} must be 0 for zero-scored candidate");
    }
    foreach ($ranked['weighted_scores'] as $criterion => $value) {
        expect($value)->toBe(0.0, "weighted {$criterion} must be 0 for zero-scored candidate");
    }
    expect($ranked['closeness_coefficient'])->toBe(1.0);
    expect($ranked['rank'])->toBe(1);
});

it('maps an all-zero criterion column to zero via the denominator fallback', function () {
    // attendance_rate is 0 for every candidate: x/sqrt(0) is undefined, the
    // application convention uses denominator 1.0 so the column normalizes to 0.
    $candidates = [
        ['staff_member_id' => 'A1', 'employee_name' => 'A', 'department' => 'Engineering', 'performance_score' => 4.0, 'attendance_rate' => 0.0, 'goal_completion' => 4.5, 'feedback_score' => 5.0, 'tenure_factor' => 4.5],
        ['staff_member_id' => 'A2', 'employee_name' => 'B', 'department' => 'Engineering', 'performance_score' => 4.7, 'attendance_rate' => 0.0, 'goal_completion' => 4.5, 'feedback_score' => 5.0, 'tenure_factor' => 4.3],
        ['staff_member_id' => 'A3', 'employee_name' => 'C', 'department' => 'Engineering', 'performance_score' => 4.4, 'attendance_rate' => 0.0, 'goal_completion' => 5.0, 'feedback_score' => 4.0, 'tenure_factor' => 4.7],
    ];

    $result = (new TopsisService)->calculate($candidates, topsisR1Weights());

    foreach ($result['ranking'] as $ranked) {
        expect($ranked['normalized_scores']['attendance_rate'])->toBe(0.0);
        expect($ranked['weighted_scores']['attendance_rate'])->toBe(0.0);
    }

    // The zero column contributes nothing to distances; ranking still works.
    expect($result['ranking'])->toHaveCount(3);
    expect($result['ranking'][0]['closeness_coefficient'])->toBeGreaterThan(0.0);
});
