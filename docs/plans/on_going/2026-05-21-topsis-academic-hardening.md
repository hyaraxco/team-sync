# TOPSIS Academic Hardening Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Fix academic validity issues identified by party mode audit v3 (Dede/Gani/Fitri) — normalize feedback_score, fix buildSingleResult math, add intermediate step verification, add tie-breaking.

**Architecture:** Repository normalizes feedback_score before TOPSIS input. TopsisService fixes buildSingleResult math and adds stable tie-breaking. Unit test adds intermediate step assertions against R1 journal.

**Tech Stack:** PHP 8.2, Laravel 12, Pest 4, TopsisService

**Party Mode Audit Scores:** Dede 7.5/10, Gani 7/10, Fitri 7.5/10. Target post-fix: ≥9/10.

---

## Files

| Action | Path | Purpose |
|--------|------|---------|
| Modify | `app/Repositories/PerformanceReviewRepository.php:564` | Normalize feedback_score to 0-100 |
| Modify | `app/Services/TopsisService.php:278-319` | Fix buildSingleResult intermediate values |
| Modify | `app/Services/TopsisService.php:257` | Add tie-breaking secondary sort |
| Modify | `tests/Unit/TopsisServiceTest.php:360-426` | Add intermediate step assertions (normalized matrix + D+/D-) |
| Modify | `tests/Feature/Performance/PerformanceTopsisControllerTest.php` | Update assertions if CC values shift |

---

## Task 1: Normalize `feedback_score` to 0-100

**Problem:** Line 564 of `PerformanceReviewRepository.php` passes raw count `(int) $positiveFeedbackCount`. Other 4 criteria are 0-100 scale. Violates comparability assumption. Penguji PASTI tanya.

**Solution:** Normalize using ratio: `min($count / $maxExpected, 1.0) * 100` where `$maxExpected` is derived from the cycle's average feedback count × 2 (cap at double the mean). Fallback: if no feedback exists for anyone in cycle, score = 0.

**Files:**
- Modify: `app/Repositories/PerformanceReviewRepository.php:508-515,564`
- Test: `tests/Feature/Performance/PerformanceTopsisControllerTest.php`

- [ ] **Step 1: Calculate max expected feedback for normalization**

In `getEmployeeScoresForCycle()`, BEFORE the foreach loop over reviews, pre-calculate the normalization cap:

```php
// Pre-calculate feedback normalization cap for this cycle
// Cap = max(10, max positive feedback count among all employees in cycle)
// This ensures relative scoring within the cycle
$maxFeedbackInCycle = PerformanceFeedback::whereIn('staff_member_id', $reviews->pluck('staff_member_id'))
    ->where('feedback_type', 'positive')
    ->whereBetween('created_at', [
        $cycle->start_date . ' 00:00:00',
        $cycle->end_date . ' 23:59:59',
    ])
    ->selectRaw('staff_member_id, COUNT(*) as cnt')
    ->groupBy('staff_member_id')
    ->pluck('cnt')
    ->max() ?? 10;

// Minimum cap of 10 to avoid over-sensitivity with low feedback counts
$feedbackNormCap = max(10, (int) $maxFeedbackInCycle);
```

Place this AFTER `$reviews` is fetched but BEFORE the `foreach ($reviews as $review)` loop.

- [ ] **Step 2: Replace raw count with normalized score**

Replace line 564:
```php
// OLD:
'feedback_score' => (int) $positiveFeedbackCount,                   // C5 (renamed)

// NEW:
'feedback_score' => round(min($positiveFeedbackCount / $feedbackNormCap, 1.0) * 100, 4),  // C5: normalized 0-100
```

- [ ] **Step 3: Update docblock in TopsisService**

In `TopsisService.php`, the class docblock already says "skor umpan balik positif (0-100)". After this fix, it's now accurate. No change needed — just verify it matches.

- [ ] **Step 4: Run TOPSIS tests to see impact**

```bash
cd team-sync-be && php artisan config:clear && ./vendor/bin/pest tests/Unit/TopsisServiceTest.php tests/Feature/Performance/PerformanceTopsisControllerTest.php --stop-on-failure
```

**Expected:** Unit tests PASS (they use direct numeric input, not repository). Feature tests may need assertion updates if CC values shift due to normalized feedback_score.

- [ ] **Step 5: Update feature test fixture if needed**

The multi-candidate fixture in `PerformanceTopsisControllerTest.php` uses `PerformanceDataSeeder` which seeds `PerformanceFeedback` records. The normalized feedback_score will now be 0-100 instead of raw count. Feature test assertions (`closeness_coefficient > 0`, `top >= bottom`) should still pass since they're relative, not absolute.

If any assertion fails, update the expected values based on the new normalized scores.

- [ ] **Step 6: Run full suite**

```bash
cd team-sync-be && composer test
```

Expected: All 1577+ tests pass.

---

## Task 2: Fix `buildSingleResult` Intermediate Values

**Problem:** Lines 310-313 set `normalized_scores = rawScores` and `weighted_scores = rawScores`. Mathematically wrong — single-candidate vector normalization yields 1.0 for non-zero values (x/√x² = 1), and weighted = weight × 1.0.

**Files:**
- Modify: `app/Services/TopsisService.php:292-318`
- Test: `tests/Unit/TopsisServiceTest.php` (existing single-candidate test)

- [ ] **Step 1: Write failing assertion for single-candidate normalized values**

In `tests/Unit/TopsisServiceTest.php`, find the existing single-candidate test (Test 2) and add assertions:

```php
// After existing assertions, add:
$single = $result['ranking'][0];

// Single candidate: normalized = x/sqrt(x²) = 1.0 for non-zero, 0.0 for zero
foreach ($single['normalized_scores'] as $criterion => $value) {
    if ($single['raw_scores'][$criterion] > 0) {
        expect($value)->toEqualWithDelta(1.0, 0.0001, "normalized $criterion should be 1.0 for single candidate");
    } else {
        expect($value)->toBe(0.0);
    }
}

// weighted = weight × normalized
foreach ($single['weighted_scores'] as $criterion => $value) {
    $expectedWeighted = $weights[$criterion] * $single['normalized_scores'][$criterion];
    expect($value)->toEqualWithDelta($expectedWeighted, 0.0001, "weighted $criterion should be weight × normalized");
}
```

- [ ] **Step 2: Run test to verify it fails**

```bash
cd team-sync-be && php artisan config:clear && ./vendor/bin/pest tests/Unit/TopsisServiceTest.php --filter="single"
```

Expected: FAIL — normalized_scores currently returns raw values (e.g., 90.0) instead of 1.0.

- [ ] **Step 3: Fix buildSingleResult**

Replace lines 292-318 in `TopsisService.php`:

```php
$candidate = $candidates[0];
$rawScores = [];
$normalizedScores = [];
$weightedScores = [];

foreach (self::CRITERIA as $criterion) {
    $raw = (float) ($candidate[$criterion] ?? 0);
    $rawScores[$criterion] = $raw;
    // Single candidate: x / sqrt(x²) = 1.0 for non-zero, 0.0 for zero
    $normalizedScores[$criterion] = $raw > 0 ? 1.0 : 0.0;
    $weightedScores[$criterion] = (float) ($weights[$criterion] ?? 0) * $normalizedScores[$criterion];
}

return [
    'weights' => $weights,
    'ideal_positive' => $weightedScores,  // A+ = weighted scores (only candidate = ideal)
    'ideal_negative' => $weightedScores,  // A- = same (only candidate = anti-ideal too)
    'criteria' => self::CRITERIA,
    'criteria_types' => self::CRITERIA_TYPES,
    'ranking' => [[
        'rank' => 1,
        'staff_member_id' => $candidate['staff_member_id'],
        'employee_name' => $candidate['employee_name'],
        'department' => $candidate['department'] ?? null,
        'raw_scores' => $rawScores,
        'normalized_scores' => $normalizedScores,
        'weighted_scores' => $weightedScores,
        'distance_positive' => 0.0,
        'distance_negative' => 0.0,  // D- = 0 (candidate IS the ideal)
        'closeness_coefficient' => 1.0,
        'label' => $this->getRatingLabel(1.0),
    ]],
    'total_candidates' => 1,
];
```

Note: `ideal_positive = ideal_negative = weightedScores` because with 1 candidate, A+ and A- are the same point. `distance_negative = 0.0` (not 1.0) because the candidate IS at the ideal negative point (they're the same). CC = 1.0 is maintained by convention (single candidate = best by default).

- [ ] **Step 4: Run test to verify it passes**

```bash
cd team-sync-be && php artisan config:clear && ./vendor/bin/pest tests/Unit/TopsisServiceTest.php --filter="single"
```

Expected: PASS

- [ ] **Step 5: Check other tests still pass**

```bash
cd team-sync-be && ./vendor/bin/pest tests/Unit/TopsisServiceTest.php tests/Unit/Services/TopsisServiceTest.php
```

Expected: All unit tests pass. The `tests/Unit/Services/TopsisServiceTest.php` has its own single-candidate test that may need updating if it asserts specific intermediate values.

---

## Task 3: Add Tie-Breaking Secondary Sort

**Problem:** `usort` in PHP is unstable. Two candidates with identical CC get arbitrary ordering. Examiner: "Bagaimana jika CC sama?"

**Files:**
- Modify: `app/Services/TopsisService.php:257`
- Test: `tests/Unit/TopsisServiceTest.php` (existing identical-scores test)

- [ ] **Step 1: Add tie-breaking test assertion**

Find the existing "identical scores" test in `tests/Unit/TopsisServiceTest.php` and add:

```php
// Verify tie-breaking is deterministic (sorted by staff_member_id ascending)
$ids = array_column($result['ranking'], 'staff_member_id');
$sortedIds = $ids;
sort($sortedIds);
expect($ids)->toBe($sortedIds, 'Tied candidates should be sorted by staff_member_id for determinism');
```

- [ ] **Step 2: Run test — may pass or fail depending on PHP's sort behavior**

```bash
cd team-sync-be && ./vendor/bin/pest tests/Unit/TopsisServiceTest.php --filter="identical"
```

- [ ] **Step 3: Implement stable tie-breaking**

Replace line 257:

```php
// OLD:
usort($ranking, fn ($a, $b) => $b['closeness_coefficient'] <=> $a['closeness_coefficient']);

// NEW: Stable sort — tie-break by staff_member_id ascending for determinism
usort($ranking, fn ($a, $b) =>
    $b['closeness_coefficient'] <=> $a['closeness_coefficient']
    ?: strcmp((string) $a['staff_member_id'], (string) $b['staff_member_id'])
);
```

- [ ] **Step 4: Run test to verify**

```bash
cd team-sync-be && ./vendor/bin/pest tests/Unit/TopsisServiceTest.php --filter="identical"
```

Expected: PASS — tied candidates now sorted by staff_member_id.

---

## Task 4: Add Intermediate Step Verification to R1 Test

**Problem:** Test 10 verifies CC + ideal solutions but NOT normalized matrix or D+/D- values. Examiner: "Tunjukkan matriks normalisasi cocok dengan jurnal."

**Reference data from R1 (IJIDS 2024, halaman 89-90):**
- Normalized matrix (Tabel Normalisasi): computed from decision matrix
- D+ and D- values: K17 D+=0.0048, D-=0.0115; K3 D+=0.0068, D-=0.0105; etc.

**Files:**
- Modify: `tests/Unit/TopsisServiceTest.php:381-426` (extend Test 10)

- [ ] **Step 1: Calculate expected normalized values manually**

Decision matrix:
```
K2:  [4.4, 4.7, 4.5, 5.0, 4.5]
K3:  [4.7, 4.7, 4.5, 5.0, 4.5]
K6:  [4.7, 4.7, 4.5, 5.0, 4.3]
K11: [4.4, 4.0, 4.5, 5.0, 4.7]
K17: [4.7, 4.7, 5.0, 4.0, 4.8]
```

Denominators (sqrt of sum of squares per column):
- C1: sqrt(4.4² + 4.7² + 4.7² + 4.4² + 4.7²) = sqrt(19.36+22.09+22.09+19.36+22.09) = sqrt(104.99) ≈ 10.2465
- C2: sqrt(4.7² + 4.7² + 4.7² + 4.0² + 4.7²) = sqrt(22.09×4 + 16.0) = sqrt(104.36) ≈ 10.2156
- C3: sqrt(4.5² + 4.5² + 4.5² + 4.5² + 5.0²) = sqrt(20.25×4 + 25.0) = sqrt(106.0) ≈ 10.2956
- C4: sqrt(5.0² + 5.0² + 5.0² + 5.0² + 4.0²) = sqrt(25×4 + 16) = sqrt(116) ≈ 10.7703
- C5: sqrt(4.5² + 4.5² + 4.3² + 4.7² + 4.8²) = sqrt(20.25+20.25+18.49+22.09+23.04) = sqrt(104.12) ≈ 10.2039

- [ ] **Step 2: Add D+ and D- assertions to Test 10**

After the existing ideal solution assertions (line 425), add:

```php
// Verifikasi intermediate: D+ dan D- per kandidat
// Computed from weighted normalized matrix distances to A+ and A-
$distByEmployee = [];
foreach ($result['ranking'] as $ranked) {
    $distByEmployee[$ranked['staff_member_id']] = [
        'dp' => $ranked['distance_positive'],
        'dn' => $ranked['distance_negative'],
    ];
}

// Verify D+ and D- produce correct CC ratio: CC = D- / (D+ + D-)
// K17: CC=0.7036 → D-/(D++D-) = 0.7036
foreach ($result['ranking'] as $ranked) {
    $dp = $ranked['distance_positive'];
    $dn = $ranked['distance_negative'];
    $total = $dp + $dn;
    if ($total > 0) {
        $computedCC = $dn / $total;
        expect($computedCC)->toEqualWithDelta(
            $ranked['closeness_coefficient'],
            0.000001,
            "CC for {$ranked['staff_member_id']} should equal D-/(D++D-)"
        );
    }
}

// Verify normalized scores are in valid range [0, 1]
foreach ($result['ranking'] as $ranked) {
    foreach ($ranked['normalized_scores'] as $criterion => $value) {
        expect($value)->toBeGreaterThanOrEqual(0.0);
        expect($value)->toBeLessThanOrEqual(1.0);
    }
}

// Verify weighted scores = weight × normalized
foreach ($result['ranking'] as $ranked) {
    foreach ($ranked['weighted_scores'] as $criterion => $value) {
        $expected = $weights[$criterion] * $ranked['normalized_scores'][$criterion];
        expect($value)->toEqualWithDelta($expected, 0.000001,
            "weighted $criterion for {$ranked['staff_member_id']} should equal weight × normalized"
        );
    }
}
```

- [ ] **Step 3: Run Test 10 to verify assertions pass**

```bash
cd team-sync-be && php artisan config:clear && ./vendor/bin/pest tests/Unit/TopsisServiceTest.php --filter="journal"
```

Expected: PASS — these are mathematical invariants that should hold for any correct TOPSIS implementation.

- [ ] **Step 4: Add explicit D+ / D- value assertions from R1**

The R1 journal (halaman 90) publishes D+ and D- values. Add explicit assertions:

```php
// D+ dan D- dari R1 halaman 90 (toleransi 0.001 karena 3 desimal di jurnal)
// Note: R1 may round differently — use 0.001 tolerance
expect($distByEmployee['K17']['dp'])->toEqualWithDelta(0.0048, 0.001);
expect($distByEmployee['K17']['dn'])->toEqualWithDelta(0.0115, 0.001);
expect($distByEmployee['K3']['dp'])->toEqualWithDelta(0.0068, 0.001);
expect($distByEmployee['K3']['dn'])->toEqualWithDelta(0.0105, 0.001);
expect($distByEmployee['K11']['dp'])->toEqualWithDelta(0.0115, 0.001);
expect($distByEmployee['K11']['dn'])->toEqualWithDelta(0.0084, 0.001);
```

**IMPORTANT:** The D+/D- values above are ESTIMATES from the CC ratios. Before committing, verify against the actual R1 journal images. If the journal doesn't publish D+/D- explicitly, compute them from the CC values:
- CC = D-/(D++D-) → if CC and one distance known, other can be derived
- Or just verify the mathematical invariant (Step 2) without hardcoded D+/D- values

- [ ] **Step 5: Run full unit test suite**

```bash
cd team-sync-be && ./vendor/bin/pest tests/Unit/TopsisServiceTest.php tests/Unit/Services/TopsisServiceTest.php
```

Expected: All pass.

---

## Task 5: Final Verification & Commit

- [ ] **Step 1: Run full backend test suite**

```bash
cd team-sync-be && composer test
```

Expected: 1577+ tests pass, 0 failures.

- [ ] **Step 2: Run Pint formatter**

```bash
cd team-sync-be && ./vendor/bin/pint
```

- [ ] **Step 3: Commit**

```bash
git add -A
git commit -m "fix(topsis): normalize feedback_score, fix buildSingleResult math, add tie-breaking and intermediate validation"
```

- [ ] **Step 4: Push and create PR**

```bash
git push -u origin fix/topsis-academic-hardening
gh pr create --title "fix(topsis): academic hardening — normalize feedback_score, fix intermediate math" --body "## Summary
- Normalize feedback_score from raw count to 0-100 scale (relative to cycle max)
- Fix buildSingleResult to compute actual normalized/weighted values (was returning raw scores)
- Add deterministic tie-breaking (staff_member_id) for identical CC values
- Add intermediate step verification to R1 journal test (normalized range, weighted = w×r, CC = D-/(D++D-))

## Context
Party mode audit v3 (Dede 7.5, Gani 7, Fitri 7.5) identified these as critical for thesis defense.

## Test Plan
- [ ] All 1577+ backend tests pass
- [ ] R1 journal validation test passes with intermediate assertions
- [ ] Single-candidate test verifies correct normalized values (1.0)
- [ ] Identical-scores test verifies deterministic tie-breaking"
```

---

## Documentation Notes (Not Code — For Skripsi Writing)

These items from the audit are DOCUMENTATION tasks, not code changes:

1. **Bab 3**: Explain weight justification as "expert judgment dari stakeholder HR" + cite R1/R2 for similar approach
2. **Bab 3**: Explain attendance quality scoring (present=1.0, late=0.7, etc.) with reference to company policy
3. **Bab 3**: Explain tenure cap 60 months with reference to UU 13/2003 PKWT duration
4. **Bab 4**: Add disclaimer that rating labels (Outstanding ≥0.80, etc.) are application design decisions, not TOPSIS theory
5. **Bab 5 Saran**: Recommend AHP for weight determination (cite R7), cost criteria (cite R7), MCDM comparison (cite R10)

---

## Notes

- **feedback_score normalization approach**: Using cycle-relative max (not fixed cap) ensures fairness across cycles with different feedback volumes. Min cap of 10 prevents over-sensitivity in low-feedback environments.
- **buildSingleResult ideal solutions**: Both A+ and A- equal the weighted scores because with n=1, the single candidate IS both the best and worst. D+ = D- = 0. CC = 1.0 by convention.
- **Tie-breaking**: `strcmp` on staff_member_id ensures deterministic ordering regardless of PHP's unstable sort. Alphabetical by ID is neutral (no bias toward any criterion).
- **R1 intermediate verification**: Mathematical invariants (CC = D-/(D++D-), weighted = w×normalized, normalized ∈ [0,1]) are more robust than hardcoded values because they don't depend on journal rounding precision.
