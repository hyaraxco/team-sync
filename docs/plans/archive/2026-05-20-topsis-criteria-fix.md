# TOPSIS Criteria Fix Implementation Plan

> **For agentic workers:** Use @fixer per task. Steps use checkbox (`- [ ]`) syntax for tracking.
> **Status:** v4 — incorporates 3 review rounds (Dede, Gani, Fitri ×2) + cross-check of seeder behavior (single-candidate shortcut gap).

**Goal:** Fix silent TOPSIS breakage where `closeness_coefficient` resolves to `0.0` due to weight key mismatch with criteria keys. Critical academic-risk bug per PRD §3.2. Plus fix the existing test suite which currently validates the broken behavior as correct (false-positive tests).

**Architecture:** Pure backend fix. `PerformanceTopsisController::DEFAULT_WEIGHTS` currently uses 7 wrong keys; `TopsisService::CRITERIA` expects 5 keys; repository already returns 5 correct keys. Fix DEFAULT_WEIGHTS + `resolveWeights()` query param mapping + 2 docblocks. Update existing `PerformanceTopsisControllerTest.php` (currently masking the bug) and add regression-guard tests. Augment test setUp with multi-candidate fixture so feature tests actually exercise the multi-candidate TOPSIS path (not the single-candidate `buildSingleResult` shortcut that bypasses weight math).

**Tech Stack:** Laravel 12, PHP 8.2+, Pest 4.

---

## Root Cause

- `TopsisService::CRITERIA` (line 64-70) = `['performance_score', 'attendance_rate', 'goal_completion', 'feedback_score', 'tenure_factor']`
- `PerformanceTopsisController::DEFAULT_WEIGHTS` (line 35-43) uses 7 different keys: `avg_manager_rating`, `final_rating`, `avg_goal_completion`, `goal_completion_ratio`, `positive_feedback_count`, `attendance_quality`, `task_completion_quality`
- `TopsisService::weightedNormalize()` looks up weights by CRITERIA keys → all weights resolve to `0.0` → output silently broken
- `PerformanceReviewRepository::getEmployeeScoresForCycle()` (line 539-565) already returns the 5 correct keys including computed `tenure_factor` (months since hire_date, capped 60, scaled 0-100)

## Why Existing Tests Didn't Catch This (Fitri's Critical Finding)

`tests/Feature/Performance/PerformanceTopsisControllerTest.php` has 5 tests, all PASSING with the bug present:

- **Line 68-92** `test_hr_can_fetch_topsis_ranking_for_completed_cycle`: asserts `assertNotEmpty(ranking)` but never checks `closeness_coefficient > 0`. Ranking array is populated even when all coefficients are 0.0.
- **Line 124-138** `test_topsis_endpoint_applies_custom_weights`: uses the OLD broken 7-key params (`w_avg_manager_rating`, etc.) and asserts they're echoed back. This test **validates the broken behavior as correct**.

The plan must fix BOTH the bug AND these tests. FE store (`performanceReview.js:363-367`) already sends the correct 5-key params — so production FE never used the broken 7-key path; only tests did.

## The Single-Candidate Shortcut Gap (Cross-Check Finding)

`PerformanceDataSeeder` (line 199 log: `"Cycle: Q4 2025 ... (completed) with 1 review"`) creates only ONE completed review (Agung) in the Q4 2025 cycle. `TopsisService::calculate()` routes `count($candidates) < 2` to `buildSingleResult()` which hardcodes `closeness_coefficient = 1.0` regardless of weights. **The bug doesn't manifest with 1 candidate.**

Without augmenting the test fixture, even after the fix, feature tests pass for trivially-correct reasons (single-candidate shortcut), not because the multi-candidate TOPSIS math actually exercises the corrected weight keys. Step 0.5 below adds 2 more differentiated completed reviews to Q4 2025 — local to setUp(), no seeder modification, no risk to other tests.

---

## Files Modified

- Modify: `team-sync-be/app/Http/Controllers/PerformanceTopsisController.php` (lines 25-43, 50-65, 117-153)
- Modify: `team-sync-be/app/Services/TopsisService.php` (lines 5-60 doc block)
- Modify: `team-sync-be/tests/Feature/Performance/PerformanceTopsisControllerTest.php` (augment setUp + 1 helper method, update 1 broken test, augment 1 weak test, add 4 new tests)
- Modify: `team-sync-be/tests/Unit/TopsisServiceTest.php` (lines 30-39 stale docblock only)
- Modify: `team-sync-be/tests/Unit/Services/TopsisServiceTest.php` (lines 50-59 stale docblock only)

No new test file created — existing file already has full role/permission/cycle setup. Step 0.5 augments setUp with a private helper that adds 2 completed reviews; localized change, no seeder modification.

---

## Steps

### Step 0.5: Augment setUp() with multi-candidate Q4 2025 fixture

**Why:** `PerformanceDataSeeder` only creates 1 completed review in Q4 2025 → `TopsisService::buildSingleResult()` shortcut hardcodes CC=1.0 regardless of weights → bug doesn't manifest. Feature tests need ≥2 differentiated candidates to actually exercise the multi-candidate `weightedNormalize` path where the bug lives.

**Approach:** Add a private helper method `seedDifferentiatedCompletedReviews()` to the test class. Call it from `setUp()` after the existing seeder calls. Yudhis (manager) and Tasyia (HR) already have `StaffMemberProfile` from their respective seeders — we just attach completed reviews + responses to Q4 2025 with deterministic differentiated ratings (Yudhis=5/5 high, Tasyia=2/2 low). Also override Agung's existing rand(3,5) ratings with deterministic 4/4 mid for stable ordering.

In `team-sync-be/tests/Feature/Performance/PerformanceTopsisControllerTest.php`:

**A.** Add imports near the top of the file (after existing `use` statements):

```php
use App\Helpers\PerformanceRatingHelper;
use App\Models\PerformanceReview;
use App\Models\PerformanceReviewResponse;
use App\Services\Performance\ReviewerResolverService;
use Carbon\Carbon;
```

**B.** In `setUp()`, after the existing `$this->completedCycle = PerformanceReviewCycle::where(...)->first();` line (line 64), append:

```php
        $this->seedDifferentiatedCompletedReviews();
```

**C.** Add this private helper method to the test class (after the `setUp()` method closes at line 66):

```php
    /**
     * Add differentiated completed reviews to Q4 2025 cycle so feature tests exercise
     * the multi-candidate TOPSIS path (weightedNormalize) instead of buildSingleResult.
     *
     * PerformanceDataSeeder only creates 1 completed review (Agung). With 1 candidate,
     * TopsisService::calculate() routes to buildSingleResult() which hardcodes CC=1.0
     * regardless of weights — meaning the criteria-key-mismatch bug doesn't manifest.
     *
     * This helper:
     *   - Overrides Agung's existing review responses to deterministic 4/4 (mid)
     *   - Adds Yudhis (manager) completed review with 5/5 ratings (high performer)
     *   - Adds Tasyia (HR) completed review with 2/2 ratings (low performer)
     *
     * Result: 3 differentiated candidates → multi-candidate TOPSIS path → bug observable.
     * Deterministic ordering: Yudhis > Agung > Tasyia.
     */
    private function seedDifferentiatedCompletedReviews(): void
    {
        $sections = PerformanceReviewSection::where('is_active', true)->orderBy('order')->get();
        $resolver = app(ReviewerResolverService::class);

        $manager = User::where('email', 'yudhis@teamsync.com')->first();
        $hr = User::where('email', 'tasyia@teamsync.com')->first();
        $employee = User::where('email', 'agung@teamsync.com')->first();

        $managerProfile = $manager->staffMemberProfile;
        $hrProfile = $hr->staffMemberProfile;
        $employeeProfile = $employee->staffMemberProfile;

        // 1. Override Agung's existing review responses to deterministic 4/4 (mid performer).
        $agungReview = PerformanceReview::where('cycle_id', $this->completedCycle->id)
            ->where('staff_member_id', $employeeProfile->id)
            ->first();

        if ($agungReview) {
            foreach ($sections as $section) {
                PerformanceReviewResponse::updateOrCreate(
                    ['review_id' => $agungReview->id, 'section_id' => $section->id],
                    [
                        'self_rating' => 4,
                        'self_comments' => 'Mid performer self-assessment.',
                        'manager_rating' => 4,
                        'manager_comments' => 'Solid mid-level performance.',
                        'final_rating' => 4,
                    ]
                );
            }

            $agungCalc = PerformanceRatingHelper::calculateFinalRating($agungReview->id);
            $agungMgr = PerformanceRatingHelper::calculateManagerRating($agungReview->id);
            $agungReview->update([
                'final_rating' => $agungCalc['final_rating'],
                'final_rating_label' => $agungCalc['final_rating_label'],
                'manager_recommended_rating' => $agungMgr,
            ]);
        }

        // 2. Yudhis (manager) — high performer (5/5 across all sections).
        $managerReview = PerformanceReview::create([
            'cycle_id' => $this->completedCycle->id,
            'staff_member_id' => $managerProfile->id,
            'reviewer_id' => $resolver->resolve($managerProfile)?->id,
            'status' => 'completed',
            'self_assessment_submitted_at' => Carbon::parse('2026-01-12'),
            'manager_assessment_submitted_at' => Carbon::parse('2026-01-26'),
            'calibrated_at' => Carbon::parse('2026-02-11'),
            'calibrated_by' => $hr->id,
            'completed_at' => Carbon::parse('2026-02-11'),
        ]);

        foreach ($sections as $section) {
            PerformanceReviewResponse::create([
                'review_id' => $managerReview->id,
                'section_id' => $section->id,
                'self_rating' => 5,
                'self_comments' => 'High performer self-assessment.',
                'manager_rating' => 5,
                'manager_comments' => 'Outstanding manager review.',
                'final_rating' => 5,
            ]);
        }

        $managerCalc = PerformanceRatingHelper::calculateFinalRating($managerReview->id);
        $managerMgrRating = PerformanceRatingHelper::calculateManagerRating($managerReview->id);
        $managerReview->update([
            'final_rating' => $managerCalc['final_rating'],
            'final_rating_label' => $managerCalc['final_rating_label'],
            'manager_recommended_rating' => $managerMgrRating,
        ]);

        // 3. Tasyia (HR) — low performer (2/2 across all sections).
        $hrReview = PerformanceReview::create([
            'cycle_id' => $this->completedCycle->id,
            'staff_member_id' => $hrProfile->id,
            'reviewer_id' => $resolver->resolve($hrProfile)?->id,
            'status' => 'completed',
            'self_assessment_submitted_at' => Carbon::parse('2026-01-13'),
            'manager_assessment_submitted_at' => Carbon::parse('2026-01-27'),
            'calibrated_at' => Carbon::parse('2026-02-12'),
            'calibrated_by' => $hr->id,
            'completed_at' => Carbon::parse('2026-02-12'),
        ]);

        foreach ($sections as $section) {
            PerformanceReviewResponse::create([
                'review_id' => $hrReview->id,
                'section_id' => $section->id,
                'self_rating' => 2,
                'self_comments' => 'Below expectations self-assessment.',
                'manager_rating' => 2,
                'manager_comments' => 'Below expectations.',
                'final_rating' => 2,
            ]);
        }

        $hrCalc = PerformanceRatingHelper::calculateFinalRating($hrReview->id);
        $hrMgrRating = PerformanceRatingHelper::calculateManagerRating($hrReview->id);
        $hrReview->update([
            'final_rating' => $hrCalc['final_rating'],
            'final_rating_label' => $hrCalc['final_rating_label'],
            'manager_recommended_rating' => $hrMgrRating,
        ]);
    }
```

**Verification after Step 0.5:** Run `composer test --filter=PerformanceTopsisControllerTest::test_hr_can_fetch_topsis_ranking_for_completed_cycle` — pre-existing assertions (`total_candidates >= 1`, `assertNotEmpty(ranking)`) still pass; total_candidates is now 3 instead of 1. No assertion changes yet — Steps 1-5b update assertions.

### Step 1: Augment existing test with closeness coefficient + ordering assertions (write FAILING test first)

In `team-sync-be/tests/Feature/Performance/PerformanceTopsisControllerTest.php`, replace `test_hr_can_fetch_topsis_ranking_for_completed_cycle` (lines 68-92) with:

```php
    public function test_hr_can_fetch_topsis_ranking_for_completed_cycle()
    {
        Sanctum::actingAs($this->hrAdmin);

        $response = $this->getJson("/api/v1/performance/cycles/{$this->completedCycle->id}/topsis-ranking");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'cycle_id',
                'cycle_name',
                'cycle_status',
                'total_candidates',
                'ranking',
                'weights',
                'ideal_positive',
                'ideal_negative',
            ],
        ]);

        $this->assertGreaterThanOrEqual(1, $response->json('data.total_candidates'));
        $this->assertNotEmpty($response->json('data.ranking'));

        // Regression guard: closeness_coefficient must be > 0 when candidates differ.
        // The original bug caused all coefficients to resolve to 0.0 due to weight/criteria key mismatch.
        $ranking = $response->json('data.ranking');
        $top = $ranking[0];
        $this->assertGreaterThan(0.0, $top['closeness_coefficient'],
            'Top candidate closeness_coefficient must be > 0 (regression guard for criteria-weight key mismatch)');

        // If multiple candidates, top CC should exceed bottom CC (proves ordering works).
        if (count($ranking) > 1) {
            $bottom = $ranking[count($ranking) - 1];
            $this->assertGreaterThanOrEqual(
                $bottom['closeness_coefficient'],
                $top['closeness_coefficient'],
                'Top candidate must rank >= bottom candidate'
            );
        }
    }
```

### Step 2: Replace broken custom-weights test with new 5-key version

In the same file, replace `test_topsis_endpoint_applies_custom_weights` (lines 124-138) with:

```php
    public function test_topsis_endpoint_applies_custom_weights()
    {
        Sanctum::actingAs($this->hrAdmin);

        // Custom PRD-aligned weights (must sum to 1.0)
        $response = $this->getJson(
            "/api/v1/performance/cycles/{$this->completedCycle->id}/topsis-ranking?".
            'w_performance_score=0.40&w_attendance_rate=0.20&'.
            'w_goal_completion=0.20&w_feedback_score=0.10&w_tenure_factor=0.10'
        );

        $response->assertStatus(200);

        $weights = $response->json('data.weights');

        // All 5 PRD criteria keys must be present
        $this->assertArrayHasKey('performance_score', $weights);
        $this->assertArrayHasKey('attendance_rate', $weights);
        $this->assertArrayHasKey('goal_completion', $weights);
        $this->assertArrayHasKey('feedback_score', $weights);
        $this->assertArrayHasKey('tenure_factor', $weights);

        // Custom weight values must be reflected
        $this->assertEquals(0.40, $weights['performance_score']);
        $this->assertEquals(0.20, $weights['attendance_rate']);
        $this->assertEquals(0.20, $weights['goal_completion']);
        $this->assertEquals(0.10, $weights['feedback_score']);
        $this->assertEquals(0.10, $weights['tenure_factor']);

        // Top candidate must still produce non-zero closeness with custom weights
        $this->assertGreaterThan(0.0, $response->json('data.ranking.0.closeness_coefficient'));
    }
```

### Step 3: Add critical regression-guard test for weight keys

Append to `PerformanceTopsisControllerTest.php`:

```php
    /**
     * Critical regression guard.
     *
     * The original bug: PerformanceTopsisController::DEFAULT_WEIGHTS used 7 keys
     * (avg_manager_rating, final_rating, etc.) but TopsisService::CRITERIA expects
     * 5 keys (performance_score, attendance_rate, goal_completion, feedback_score,
     * tenure_factor). Mismatch → all weights resolved to 0.0 → silent breakage.
     *
     * This test ensures any future change to DEFAULT_WEIGHTS or CRITERIA
     * triggers a failure if they drift apart.
     */
    public function test_response_weights_keys_exactly_match_topsis_criteria()
    {
        Sanctum::actingAs($this->hrAdmin);

        $response = $this->getJson("/api/v1/performance/cycles/{$this->completedCycle->id}/topsis-ranking");

        $response->assertStatus(200);

        $weightKeys = array_keys($response->json('data.weights'));
        sort($weightKeys);

        $expected = ['attendance_rate', 'feedback_score', 'goal_completion', 'performance_score', 'tenure_factor'];
        sort($expected);

        $this->assertEquals(
            $expected,
            $weightKeys,
            'Response weights keys must match TopsisService::CRITERIA exactly. '.
            'If this fails, DEFAULT_WEIGHTS in PerformanceTopsisController has drifted '.
            'from CRITERIA in TopsisService.'
        );
    }
```

### Step 4: Add partial-weights fallback test

Append:

```php
    public function test_partial_custom_weights_fall_back_to_defaults_for_unspecified_criteria()
    {
        Sanctum::actingAs($this->hrAdmin);

        // Only specify ONE weight — other 4 must fall back to defaults
        $response = $this->getJson(
            "/api/v1/performance/cycles/{$this->completedCycle->id}/topsis-ranking?w_performance_score=0.50"
        );

        $response->assertStatus(200);
        $weights = $response->json('data.weights');

        // All 5 keys present (not just the one provided)
        $this->assertCount(5, $weights);
        $this->assertArrayHasKey('performance_score', $weights);
        $this->assertArrayHasKey('attendance_rate', $weights);
        $this->assertArrayHasKey('goal_completion', $weights);
        $this->assertArrayHasKey('feedback_score', $weights);
        $this->assertArrayHasKey('tenure_factor', $weights);

        // Total weights must normalize to ~1.0
        $total = array_sum($weights);
        $this->assertEqualsWithDelta(1.0, $total, 0.01,
            'Weights must auto-normalize to 1.0 when partial custom weights provided');
    }
```

### Step 5: Add all-zero weights resilience test

Append:

```php
    public function test_all_zero_custom_weights_returns_gracefully_without_division_error()
    {
        Sanctum::actingAs($this->hrAdmin);

        $response = $this->getJson(
            "/api/v1/performance/cycles/{$this->completedCycle->id}/topsis-ranking?".
            'w_performance_score=0&w_attendance_rate=0&w_goal_completion=0&'.
            'w_feedback_score=0&w_tenure_factor=0'
        );

        // Must NOT 500 — endpoint must handle pathological input gracefully
        $this->assertNotEquals(500, $response->status(),
            'All-zero weights must not cause server error');
    }
```

### Step 5b: Add negative weight clamp test (regression guard for silent corruption)

Append:

```php
    /**
     * Negative weights would invert a criterion's TOPSIS contribution and produce
     * mathematically nonsensical rankings — same class of silent corruption as
     * the original key-mismatch bug (looks like it works, results are garbage).
     *
     * Implementation must clamp negative weights to 0.0 before normalization.
     */
    public function test_negative_weight_values_are_clamped_to_zero()
    {
        Sanctum::actingAs($this->hrAdmin);

        $response = $this->getJson(
            "/api/v1/performance/cycles/{$this->completedCycle->id}/topsis-ranking?".
            'w_performance_score=-0.5&w_attendance_rate=0.30&'.
            'w_goal_completion=0.30&w_feedback_score=0.20&w_tenure_factor=0.20'
        );

        $response->assertStatus(200);
        $weights = $response->json('data.weights');

        // Negative weight must be clamped to 0 (then normalization redistributes)
        $this->assertGreaterThanOrEqual(0.0, $weights['performance_score'],
            'Negative weight values must be clamped to 0.0 to prevent silent ranking corruption');
    }
```

### Step 6: Run tests to verify they FAIL with current broken code

```bash
cd team-sync-be && composer test -- --filter=PerformanceTopsisControllerTest
```

With Step 0.5's multi-candidate fixture in place, the bug now actually manifests in the feature tests (instead of being masked by `buildSingleResult`). Expected outcomes against the CURRENT (still-broken) code:

| Test | Expected | Reason |
|------|----------|--------|
| `test_hr_can_fetch_topsis_ranking_for_completed_cycle` | **FAIL** | 3 candidates → multi-candidate path → mismatched keys cause all weights to resolve to 0 → CC = 0 for top candidate (proves bug) |
| `test_topsis_endpoint_applies_custom_weights` | **FAIL** | New 5-key params (`w_performance_score`, etc.) not recognized by current 7-key resolveWeights → falls back to DEFAULT_WEIGHTS → response has 7 keys, not 5 → `assertArrayHasKey('performance_score', $weights)` fails |
| `test_response_weights_keys_exactly_match_topsis_criteria` | **FAIL** | DEFAULT_WEIGHTS still has 7 wrong keys → response.weights has 7 keys → exact match assertion against TopsisService::CRITERIA (5 keys) fails |
| `test_partial_custom_weights_fall_back_to_defaults_for_unspecified_criteria` | **FAIL** | Controller doesn't recognize `w_performance_score` → falls back to 7-key DEFAULT_WEIGHTS → `assertCount(5, $weights)` fails (count is 7) |
| `test_all_zero_custom_weights_returns_gracefully_without_division_error` | **PASS** | Current code: 5-key zero params unrecognized → falls back to DEFAULT_WEIGHTS → multi-candidate path runs, mismatched keys produce 0 distances → `calculateClosenessCoefficient` guards `D+ + D- = 0 → CC = 0` → returns 200, not 500. Test only asserts ≠ 500 |
| `test_negative_weight_values_are_clamped_to_zero` | **FAIL** | No clamp logic exists. `w_performance_score=-0.5` not recognized by 7-key resolveWeights → falls back to DEFAULT_WEIGHTS → response.weights has 7 keys → `$weights['performance_score']` is null → `assertGreaterThanOrEqual(0.0, null)` fails |
| 3 pre-existing tests (`...returns_422_empty_state`, `...returns_404_for_missing_cycle`, `..._employee_cannot_access`) | **PASS** | Unrelated to bug. Note: 422 test uses activeCycle (Q1 2026 with no completed reviews) — Step 0.5's fixture only adds reviews to completedCycle (Q4 2025), so this test is unaffected. |

**Expected total: 5 FAIL + 4 PASS = 9 tests.** If you see different numbers, investigate before proceeding to Step 7.

**Critical:** if `test_hr_can_fetch_topsis_ranking_for_completed_cycle` PASSES against broken code, Step 0.5's fixture didn't take effect — verify `seedDifferentiatedCompletedReviews()` is being called and check `total_candidates` in response (must be 3, not 1).

### Step 7: Fix DEFAULT_WEIGHTS in PerformanceTopsisController

Replace lines 25-43 of `team-sync-be/app/Http/Controllers/PerformanceTopsisController.php`:

```php
    /**
     * Bobot default sesuai PRD Section 3.2 (Kriteria Penilaian).
     *
     * 5 kriteria sesuai PRD — total = 1.0:
     *   - performance_score (30%) — gabungan kompetensi + KPI
     *   - attendance_rate   (20%) — % hari masuk / quality
     *   - goal_completion   (25%) — % selesai + tepat waktu
     *   - feedback_score    (15%) — count feedback positif
     *   - tenure_factor     (10%) — masa kerja, capped 60 bulan
     *
     * NOTE: Keys MUST match TopsisService::CRITERIA exactly.
     * Drift = silent breakage (all weights resolve to 0.0).
     */
    private const DEFAULT_WEIGHTS = [
        'performance_score' => 0.30,
        'attendance_rate' => 0.20,
        'goal_completion' => 0.25,
        'feedback_score' => 0.15,
        'tenure_factor' => 0.10,
    ];
```

### Step 8: Update ranking() method docblock

Replace lines 50-65 of `team-sync-be/app/Http/Controllers/PerformanceTopsisController.php`:

```php
    /**
     * Hitung dan kembalikan ranking TOPSIS karyawan dalam satu review cycle.
     *
     * GET /api/v1/performance/cycles/{id}/topsis-ranking
     *
     * Query params (optional) — Bobot PRD §3.2 (total = 1.0):
     *   - w_performance_score : float (0-1) — default 0.30
     *   - w_attendance_rate   : float (0-1) — default 0.20
     *   - w_goal_completion   : float (0-1) — default 0.25
     *   - w_feedback_score    : float (0-1) — default 0.15
     *   - w_tenure_factor     : float (0-1) — default 0.10
     *
     * Total bobot harus = 1.0 (jika tidak, akan dinormalisasi otomatis).
     */
```

### Step 9: Fix resolveWeights() — query param mapping + negative clamp + warning log

Replace lines 117-153 of `team-sync-be/app/Http/Controllers/PerformanceTopsisController.php`:

> **Note:** Add `use Illuminate\Support\Facades\Log;` to the controller's import block if not already present.

```php
    /**
     * Ambil bobot dari query params, fallback ke default.
     * Clamp nilai negatif ke 0 (mencegah silent corruption ranking).
     * Logs a warning when clamping occurs (audit trail for misuse detection).
     * Normalisasi otomatis jika total != 1.
     *
     * Query param mapping (criterion → param name):
     *   performance_score → w_performance_score
     *   attendance_rate   → w_attendance_rate
     *   goal_completion   → w_goal_completion
     *   feedback_score    → w_feedback_score
     *   tenure_factor     → w_tenure_factor
     */
    private function resolveWeights(Request $request): array
    {
        $keys = [
            'performance_score' => 'w_performance_score',
            'attendance_rate' => 'w_attendance_rate',
            'goal_completion' => 'w_goal_completion',
            'feedback_score' => 'w_feedback_score',
            'tenure_factor' => 'w_tenure_factor',
        ];

        $hasCustomWeights = collect($keys)->some(fn ($param) => $request->has($param));

        if (! $hasCustomWeights) {
            return self::DEFAULT_WEIGHTS;
        }

        $weights = [];
        foreach ($keys as $criterion => $param) {
            $raw = (float) $request->get($param, self::DEFAULT_WEIGHTS[$criterion]);
            // Clamp negative values to 0 — prevents silent ranking corruption
            // (negative weights invert criterion contribution, producing nonsense)
            $weights[$criterion] = max(0.0, $raw);

            if ($raw < 0) {
                Log::warning('TOPSIS weight clamped to zero', [
                    'criterion' => $criterion,
                    'raw_value' => $raw,
                    'user_id' => auth()->id(),
                    'cycle_id' => $request->route('id'),
                ]);
            }
        }

        // Normalisasi agar total = 1.0
        $total = array_sum($weights);
        if ($total > 0 && abs($total - 1.0) > 0.001) {
            foreach ($weights as &$w) {
                $w = round($w / $total, 6);
            }
        }

        return $weights;
    }
```

### Step 10: Update outdated comment in TopsisService

Replace lines 5-60 of `team-sync-be/app/Services/TopsisService.php`:

```php
/**
 * TOPSIS (Technique for Order of Preference by Similarity to Ideal Solution)
 *
 * Algoritma Multi-Criteria Decision Making untuk meranking karyawan berdasarkan
 * kinerja komprehensif dalam satu review cycle.
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * 5 Kriteria sesuai PRD Section 3.2 — semua Benefit (semakin besar semakin baik):
 * ─────────────────────────────────────────────────────────────────────────────
 *   C1 - performance_score (30%) : Skor kinerja gabungan kompetensi + KPI (0-100)
 *   C2 - attendance_rate   (20%) : Tingkat kehadiran/quality (0-100)
 *   C3 - goal_completion   (25%) : Penyelesaian tujuan (% + on-time bonus, 0-100)
 *   C4 - feedback_score    (15%) : Jumlah feedback positif yang diterima
 *   C5 - tenure_factor     (10%) : Masa kerja, scaled 0-100 (capped 60 bulan)
 *
 * Tenure factor dihitung dari job_information.start_date di
 * PerformanceReviewRepository::getEmployeeScoresForCycle().
 *
 * Originally implemented as 7 criteria; consolidated to 5 per PRD §3.2 alignment
 * (2026-05-20). Keys in this CRITERIA constant MUST match the keys in
 * PerformanceTopsisController::DEFAULT_WEIGHTS exactly — drift causes silent
 * breakage (weights resolve to 0.0).
 *
 * ─────────────────────────────────────────────────────────────────────────────
 * Langkah-langkah:
 *   1. Bangun matriks keputusan (decision matrix)
 *   2. Normalisasi dengan metode vector: r_ij = x_ij / sqrt(sum(x_ij^2))
 *   3. Bobot normalisasi: v_ij = w_j * r_ij
 *   4. Tentukan solusi ideal positif (A+) dan negatif (A-)
 *   5. Hitung jarak Euclidean ke A+ (D+) dan A- (D-)
 *   6. Hitung closeness coefficient: C_i = D- / (D+ + D-)
 *   7. Ranking berdasarkan C_i tertinggi
 */
```

### Step 10b: Update stale docblocks in unit test files

Two unit test files have docblock comments still describing the old 7-key mapping. Actual `defaultWeights()` / `topsisWeights()` arrays in these files already use correct 5-key format — only the comments are stale. After the fix lands, these comments become actively misleading.

**File 1:** `team-sync-be/tests/Unit/TopsisServiceTest.php` lines 30-39

Replace:

```php
/**
 * Helper: bobot default TOPSIS sesuai PRD Section 3.2.
 *
 * PRD Mapping:
 *   Performance Score (30%) → avg_manager_rating (15%) + final_rating (15%)
 *   Attendance Rate (20%)   → attendance_quality (20%)
 *   Goal Completion (25%)   → avg_goal_completion (15%) + goal_completion_ratio (10%)
 *   Feedback Score (15%)    → positive_feedback_count (15%)
 *   Tenure Factor (10%)     → task_completion_quality (10%) [proxy]
 */
```

With:

```php
/**
 * Helper: bobot default TOPSIS sesuai PRD Section 3.2 (5 kriteria, total = 1.0).
 *
 *   performance_score (30%) — gabungan kompetensi + KPI
 *   attendance_rate   (20%) — % hari masuk / quality
 *   goal_completion   (25%) — % selesai + tepat waktu
 *   feedback_score    (15%) — count feedback positif
 *   tenure_factor     (10%) — masa kerja, capped 60 bulan
 */
```

**File 2:** `team-sync-be/tests/Unit/Services/TopsisServiceTest.php` lines 50-59

Replace:

```php
/**
 * Helper: default weights matching PRD Section 3.2 (total = 1.0).
 *
 * PRD Mapping:
 *   Performance Score (30%) → avg_manager_rating (15%) + final_rating (15%)
 *   Attendance Rate (20%)   → attendance_quality (20%)
 *   Goal Completion (25%)   → avg_goal_completion (15%) + goal_completion_ratio (10%)
 *   Feedback Score (15%)    → positive_feedback_count (15%)
 *   Tenure Factor (10%)     → task_completion_quality (10%) [proxy]
 */
```

With:

```php
/**
 * Helper: default weights matching PRD Section 3.2 (5 criteria, total = 1.0).
 *
 *   performance_score (30%) — combined competency + KPI
 *   attendance_rate   (20%) — attendance percentage / quality
 *   goal_completion   (25%) — completion % + on-time bonus
 *   feedback_score    (15%) — count of positive feedback
 *   tenure_factor     (10%) — months tenured, capped at 60
 */
```

Comment-only change — no test logic touched. Fast.

### Step 11: Run tests to verify all PASS

```bash
cd team-sync-be && composer test -- --filter=PerformanceTopsisControllerTest
```

Expected: 9 tests passing (3 pre-existing untouched + 1 augmented + 1 replaced + 4 added).

### Step 12: Run full BE suite for regressions

```bash
cd team-sync-be && composer test
```

Expected: 1500+ tests passing, no regressions.

### Step 13: Format and commit

```bash
cd team-sync-be && ./vendor/bin/pint
git checkout -b fix/topsis-criteria-weights
git add team-sync-be/app/Http/Controllers/PerformanceTopsisController.php \
        team-sync-be/app/Services/TopsisService.php \
        team-sync-be/tests/Feature/Performance/PerformanceTopsisControllerTest.php \
        team-sync-be/tests/Unit/TopsisServiceTest.php \
        team-sync-be/tests/Unit/Services/TopsisServiceTest.php
git commit -m "fix(topsis): align DEFAULT_WEIGHTS keys with TopsisService::CRITERIA per PRD §3.2"
```

### Step 14: Push and create PR

```bash
git push -u origin fix/topsis-criteria-weights
gh pr create --title "fix(topsis): align DEFAULT_WEIGHTS keys with TopsisService::CRITERIA per PRD §3.2" \
    --body "$(cat <<'EOF'
## Summary

Fixes silent TOPSIS breakage where `closeness_coefficient` resolved to `0.0` for all candidates due to weight key mismatch with `TopsisService::CRITERIA`.

## Root Cause

Controller's `DEFAULT_WEIGHTS` used 7 keys (avg_manager_rating, etc.) but service expected 5 (performance_score, etc.). Repository already returned correct 5 keys. Service's weight lookup fell through to 0.0 for every criterion.

## Why Existing Tests Didn't Catch It

`PerformanceTopsisControllerTest` had 5 tests, all passing — but none asserted `closeness_coefficient > 0`, and the custom-weights test validated the broken 7-key API as correct. This PR fixes both bug AND tests.

## Changes

- `PerformanceTopsisController::DEFAULT_WEIGHTS` — 7 keys → 5 keys, weights per PRD §3.2 (30/20/25/15/10)
- `PerformanceTopsisController::resolveWeights()` — query params remapped to 5-key form + `max(0.0, ...)` negative clamp before normalization
- 2 docblocks updated (`ranking()` method + `resolveWeights()`)
- `TopsisService` class docblock updated with PRD alignment note + drift warning
- `PerformanceTopsisControllerTest`:
  - **setUp augmented** with `seedDifferentiatedCompletedReviews()` private helper (3 differentiated candidates in Q4 2025) so feature tests exercise the multi-candidate TOPSIS path instead of the single-candidate `buildSingleResult` shortcut that masks the bug
  - Augmented `test_hr_can_fetch_topsis_ranking_for_completed_cycle` with closeness > 0 + ordering assertions
  - Replaced `test_topsis_endpoint_applies_custom_weights` with 5-key version + meaningful weight assertions
  - Added `test_response_weights_keys_exactly_match_topsis_criteria` (regression guard)
  - Added `test_partial_custom_weights_fall_back_to_defaults_for_unspecified_criteria`
  - Added `test_all_zero_custom_weights_returns_gracefully_without_division_error`
  - Added `test_negative_weight_values_are_clamped_to_zero` (silent-corruption regression guard)
- `tests/Unit/TopsisServiceTest.php` + `tests/Unit/Services/TopsisServiceTest.php` — stale 7-key docblocks updated to 5-key

## FE Impact

None. Frontend store (`performanceReview.js:363-367`) already sends correct 5-key params. Backend was the only broken side.

## Test Plan

- [ ] `composer test --filter=PerformanceTopsisControllerTest` — 9 passing
- [ ] `composer test` — full suite passes
- [ ] Manual: HR runs ranking on completed cycle → verify non-zero CC values

## Tracked Follow-ups (Not in This PR)

- Audit logging for ranking executions (PHI dispute defense)
- Weight bounds validation (prevent gaming via extreme weights)
- Feedback score normalization (small-team fairness)

See `docs/plans/future/topsis-governance.md` (to be created).
EOF
)"
```

---

## Tracked for Future (Out of Scope for This PR)

Per Gani's HR domain review, these are real concerns but separate from the bug fix:

1. **Audit trail for ranking executions** — Required for PHI (Pengadilan Hubungan Industrial) defense per UU Ketenagakerjaan pasal 151-153. Need `activity_log` entry per ranking call with user_id, cycle_id, weights_used, timestamp.
2. **Weight bounds validation** — Prevent HR gaming results via extreme weights. Suggested: min 0.05, max 0.50 per criterion.
3. **Feedback score normalization** — Raw count penalizes employees in small teams. Consider normalize-by-team-size or average sentiment score.

To be captured in `docs/plans/future/topsis-governance.md` as a follow-up plan.

---

## Notes

- **Severity: HIGH** — production TOPSIS rankings have been silently broken. closeness_coefficient = 0.0 for all employees. UI likely doesn't display this raw value, masking the visible symptom.
- **Backward compatibility:** No external API consumers known. FE already uses correct 5-key params. Internal-only API.
- **Test data:** `PerformanceDataSeeder` creates only 1 completed review in Q4 2025, which routes TopsisService to `buildSingleResult` (hardcoded CC=1.0) — masking the bug. Step 0.5 augments setUp with `seedDifferentiatedCompletedReviews()` to add Yudhis (5/5 high) and Tasyia (2/2 low) reviews + override Agung's rand(3,5) ratings to deterministic 4/4 mid. Result: 3 differentiated candidates → multi-candidate path → bug observable. Localized to test setUp; no seeder modification, no risk to other tests.
- **TDD discipline:** Step 0.5 sets up data; Steps 1-5b write/update test assertions FIRST (must fail); Steps 7-10b implement the fix; Step 11 verifies green.
- **Confidence target:** v4 closes the single-candidate-shortcut gap identified during cross-check verification. After Step 0.5, the bug-symptom test (`CC > 0` + ordering) actually exercises the multi-candidate `weightedNormalize` path where the bug lives, instead of being masked by `buildSingleResult`.
