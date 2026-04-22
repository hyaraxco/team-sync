# Calibration System Overhaul — Implementation Plan

> **Execution:** Use the **executing-plans** skill to execute this plan in single-flow mode.

**Goal:** Fix the calibration system so that final_rating is auto-calculated from weighted section ratings, labels are auto-derived, HR can only calibrate other employees' reviews (not their own), and HR has a dedicated "Pending Calibration" view to access reviews needing calibration. Also add cross-manager normalization context to help HR make informed calibration decisions.

**Architecture:** Backend changes to auto-calculate ratings in the repository layer. New endpoint for pending calibration reviews. Frontend changes to remove manual final_rating input, auto-derive labels, add normalization context panel, and create new Pending Calibration view. Migration to add `manager_recommended_rating` column to preserve manager's overall recommendation separately from the auto-calculated `final_rating`.

**Tech Stack:** Laravel 12 (PHP 8.2), Vue 3 Composition API (`<script setup>`), Pinia, Tailwind CSS, Pest (testing)

**SPEC Reference:** `docs/performance-management/SPEC.md`
- Line 218: "Overall review rating is weighted average of section ratings."
- Line 129: "`final_rating` defaults to `manager_rating` unless calibrated"

---

## Context: Current Data Model

```
performance_review_sections: { id, name, weight (decimal 5,2), order, is_active }
  → weight is percentage (e.g., 25.00 = 25%)
  → total active section weights should = 100.00

performance_review_responses: { review_id, section_id, self_rating, manager_rating, final_rating }
  → ratings are 1-5 integer scale
  → final_rating = calibrated rating per section (optional override by HR)

performance_reviews: { final_rating (decimal 3,2), final_rating_label (string) }
  → final_rating = overall weighted average (SHOULD be auto-calculated)
  → final_rating_label = derived from final_rating range
```

## Rating Label Mapping (from SPEC)

| Rating Range | Label |
|---|---|
| >= 4.50 | Outstanding |
| >= 3.50 | Exceeds Expectations |
| >= 2.50 | Meets Expectations |
| >= 1.50 | Needs Improvement |
| < 1.50 | Unsatisfactory |

## Auto-Calculation Formula

```
For each section response:
  effective_rating = final_rating ?? manager_rating ?? self_rating

overall_final_rating = SUM(effective_rating * section.weight / 100) for all sections
final_rating_label = derived from overall_final_rating using label mapping above
```

---

## Task 1: Migration — Add `manager_recommended_rating` column

**Files:**
- Create: `team-sync-be/database/migrations/2026_04_21_000001_add_manager_recommended_rating_to_performance_reviews.php`
- Modify: `team-sync-be/app/Models/PerformanceReview.php`

**Step 1: Create migration**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('performance_reviews', function (Blueprint $table) {
            $table->decimal('manager_recommended_rating', 3, 2)
                ->nullable()
                ->after('manager_assessment_submitted_at')
                ->comment('Manager overall recommendation before HR calibration');
        });

        // Migrate existing data: copy current final_rating to manager_recommended_rating
        // for reviews that have manager assessment but are not yet calibrated or completed
        DB::table('performance_reviews')
            ->whereNotNull('manager_assessment_submitted_at')
            ->whereNotNull('final_rating')
            ->whereNull('manager_recommended_rating')
            ->update(['manager_recommended_rating' => DB::raw('final_rating')]);
    }

    public function down(): void
    {
        Schema::table('performance_reviews', function (Blueprint $table) {
            $table->dropColumn('manager_recommended_rating');
        });
    }
};
```

**Step 2: Update PerformanceReview model fillable and casts**

Add `'manager_recommended_rating'` to `$fillable` array.
Add `'manager_recommended_rating' => 'decimal:2'` to `$casts` array.

**Step 3: Run migration**

Run: `php artisan migrate` (in team-sync-be/)
Expected: Migration runs successfully

**Step 4: Commit**

```
feat(performance): add manager_recommended_rating column to performance_reviews
```

---

## Task 2: Backend — Auto-calculate final_rating helper

**Files:**
- Create: `team-sync-be/app/Helpers/PerformanceRatingHelper.php`

**Step 1: Create helper class**

```php
<?php

namespace App\Helpers;

use App\Models\PerformanceReviewResponse;
use App\Models\PerformanceReviewSection;

class PerformanceRatingHelper
{
    /**
     * Rating label mapping based on weighted average score.
     */
    private const RATING_LABELS = [
        ['min' => 4.50, 'label' => 'Outstanding'],
        ['min' => 3.50, 'label' => 'Exceeds Expectations'],
        ['min' => 2.50, 'label' => 'Meets Expectations'],
        ['min' => 1.50, 'label' => 'Needs Improvement'],
        ['min' => 0.00, 'label' => 'Unsatisfactory'],
    ];

    /**
     * Calculate weighted average final_rating from section responses.
     *
     * For each section: effective_rating = final_rating ?? manager_rating ?? self_rating
     * Overall = SUM(effective_rating * section.weight / 100)
     *
     * @param int $reviewId
     * @return array{final_rating: float|null, final_rating_label: string|null}
     */
    public static function calculateFinalRating(int $reviewId): array
    {
        $responses = PerformanceReviewResponse::with('section')
            ->where('review_id', $reviewId)
            ->get();

        if ($responses->isEmpty()) {
            return ['final_rating' => null, 'final_rating_label' => null];
        }

        $totalWeight = 0;
        $weightedSum = 0;

        foreach ($responses as $response) {
            $section = $response->section;
            if (!$section || !$section->is_active) {
                continue;
            }

            $effectiveRating = $response->final_rating
                ?? $response->manager_rating
                ?? $response->self_rating;

            if ($effectiveRating === null) {
                continue;
            }

            $weight = (float) $section->weight;
            $weightedSum += $effectiveRating * ($weight / 100);
            $totalWeight += $weight;
        }

        if ($totalWeight <= 0) {
            return ['final_rating' => null, 'final_rating_label' => null];
        }

        // Normalize if total weight != 100 (e.g., some sections inactive)
        $finalRating = $totalWeight != 100
            ? ($weightedSum / $totalWeight) * 100 / 100
            : $weightedSum;

        // Clamp to 1-5 range
        $finalRating = round(max(1, min(5, $finalRating)), 2);

        return [
            'final_rating' => $finalRating,
            'final_rating_label' => self::getRatingLabel($finalRating),
        ];
    }

    /**
     * Derive label from rating value.
     */
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
```

**Step 2: Commit**

```
feat(performance): add PerformanceRatingHelper for auto-calculating weighted final_rating
```

---

## Task 3: Backend — Refactor submitManagerAssessment

**Files:**
- Modify: `team-sync-be/app/Repositories/PerformanceReviewRepository.php` (method `submitManagerAssessment`)

**Step 1: Update submitManagerAssessment**

Change the method to:
1. Save manager ratings per section (existing)
2. Auto-calculate `final_rating` from weighted section ratings using helper
3. Store manager's overall recommendation in `manager_recommended_rating` (instead of `final_rating`)

```php
public function submitManagerAssessment(int $reviewId, array $responses, array $data)
{
    $review = $this->getReviewById($reviewId);

    foreach ($responses as $response) {
        PerformanceReviewResponse::updateOrCreate(
            ['review_id' => $reviewId, 'section_id' => $response['section_id']],
            [
                'manager_rating' => $response['rating'],
                'manager_comments' => $response['comments'] ?? null,
            ]
        );
    }

    // Auto-calculate final_rating from weighted section ratings
    $calculated = \App\Helpers\PerformanceRatingHelper::calculateFinalRating($reviewId);

    $review->update([
        'status' => 'pending_calibration',
        'manager_assessment_submitted_at' => now(),
        'manager_recommended_rating' => $data['final_rating'] ?? null,
        'final_rating' => $calculated['final_rating'],
        'final_rating_label' => $calculated['final_rating_label'],
    ]);

    return $review->fresh()->load(['cycle', 'employee.user', 'reviewer.user', 'responses.section', 'calibrator']);
}
```

**Step 2: Update SubmitManagerAssessmentRequest — make final_rating optional**

File: `team-sync-be/app/Http/Requests/Performance/SubmitManagerAssessmentRequest.php`

Change `final_rating` rule from `required` to `nullable`.

**Step 3: Commit**

```
refactor(performance): auto-calculate final_rating on manager assessment, store recommendation separately
```

---

## Task 4: Backend — Refactor calibrateReview

**Files:**
- Modify: `team-sync-be/app/Repositories/PerformanceReviewRepository.php` (method `calibrateReview`)
- Modify: `team-sync-be/app/Http/Requests/Performance/CalibrateReviewRequest.php`

**Step 1: Update calibrateReview**

Change to:
1. Save HR's override ratings per section (existing)
2. Auto-calculate `final_rating` from weighted section ratings (using final_rating override where provided, falling back to manager_rating)
3. Auto-derive `final_rating_label` — no manual input
4. Block HR from calibrating their own review

```php
public function calibrateReview(int $reviewId, array $responses, array $data)
{
    $review = $this->getReviewById($reviewId);

    // Block self-calibration
    $currentEmployeeId = auth()->user()->employeeProfile?->id;
    if ($currentEmployeeId && $review->employee_id == $currentEmployeeId) {
        throw new \Exception('Cannot calibrate your own review', 403);
    }

    if (!empty($responses)) {
        foreach ($responses as $response) {
            PerformanceReviewResponse::updateOrCreate(
                ['review_id' => $reviewId, 'section_id' => $response['section_id']],
                [
                    'final_rating' => $response['rating'],
                ]
            );
        }
    }

    // Auto-calculate final_rating from weighted section ratings
    $calculated = \App\Helpers\PerformanceRatingHelper::calculateFinalRating($reviewId);

    $review->update([
        'status' => 'completed',
        'calibrated_at' => now(),
        'calibrated_by' => auth()->id(),
        'final_rating' => $calculated['final_rating'],
        'final_rating_label' => $calculated['final_rating_label'],
        'completed_at' => now(),
    ]);

    return $review->fresh()->load(['cycle', 'employee.user', 'reviewer.user', 'responses.section', 'calibrator']);
}
```

**Step 2: Update CalibrateReviewRequest**

Remove `final_rating` and `final_rating_label` from required rules (they are now auto-calculated):

```php
public function rules(): array
{
    return [
        'responses' => 'nullable|array',
        'responses.*.section_id' => 'required|exists:performance_review_sections,id',
        'responses.*.rating' => 'required|integer|min:1|max:5',
    ];
}
```

**Step 3: Commit**

```
refactor(performance): auto-calculate calibrated final_rating, block self-calibration
```

---

## Task 5: Backend — New endpoint for pending calibration reviews

**Files:**
- Modify: `team-sync-be/app/Interfaces/PerformanceReviewRepositoryInterface.php`
- Modify: `team-sync-be/app/Repositories/PerformanceReviewRepository.php`
- Modify: `team-sync-be/app/Http/Controllers/PerformanceReviewController.php`
- Modify: `team-sync-be/routes/api.php`

**Step 1: Add interface method**

```php
public function getReviewsPendingCalibration(array $filters = []): \Illuminate\Pagination\LengthAwarePaginator;
```

**Step 2: Implement in repository**

```php
public function getReviewsPendingCalibration(array $filters = []): LengthAwarePaginator
{
    $query = PerformanceReview::with(['cycle', 'employee.user', 'reviewer.user', 'responses.section'])
        ->where('status', 'pending_calibration');

    if (isset($filters['cycle_id'])) {
        $query->where('cycle_id', $filters['cycle_id']);
    }

    // Exclude current HR user's own review
    $currentEmployeeId = auth()->user()->employeeProfile?->id;
    if ($currentEmployeeId) {
        $query->where('employee_id', '!=', $currentEmployeeId);
    }

    return $query->orderBy('created_at', 'desc')
        ->paginate($filters['per_page'] ?? 15);
}
```

**Step 3: Add controller method**

```php
public function getPendingCalibration(Request $request)
{
    $reviews = $this->repository->getReviewsPendingCalibration($request->all());
    return ResponseHelper::jsonResponse(true, 'Pending calibration reviews retrieved successfully', $reviews);
}
```

**Step 4: Add route**

In `routes/api.php`, inside the performance prefix group, add:

```php
Route::get('reviews/pending-calibration', [PerformanceReviewController::class, 'getPendingCalibration'])
    ->middleware(PermissionMiddleware::using(['review-calibrate']));
```

**IMPORTANT:** This route MUST be placed BEFORE `Route::get('reviews/{id}', ...)` to avoid route conflict.

**Step 5: Commit**

```
feat(performance): add pending-calibration endpoint for HR calibration queue
```

---

## Task 6: Backend — Add normalization context endpoint

**Files:**
- Modify: `team-sync-be/app/Repositories/PerformanceReviewRepository.php`
- Modify: `team-sync-be/app/Http/Controllers/PerformanceReviewController.php`
- Modify: `team-sync-be/routes/api.php`

**Step 1: Add repository method for cross-manager stats**

```php
public function getCalibrationContext(int $reviewId): array
{
    $review = $this->getReviewById($reviewId);
    $cycleId = $review->cycle_id;

    // Get all reviews in same cycle that have manager assessment
    $cycleReviews = PerformanceReview::with(['responses.section', 'reviewer.user'])
        ->where('cycle_id', $cycleId)
        ->whereNotNull('manager_assessment_submitted_at')
        ->get();

    // Group by reviewer (manager) to show normalization context
    $managerStats = [];
    foreach ($cycleReviews as $r) {
        $managerId = $r->reviewer_id;
        $managerName = $r->reviewer?->user?->name ?? 'Unknown';

        if (!isset($managerStats[$managerId])) {
            $managerStats[$managerId] = [
                'manager_name' => $managerName,
                'review_count' => 0,
                'ratings' => [],
            ];
        }

        $managerStats[$managerId]['review_count']++;

        // Calculate average manager rating for this review
        $avgRating = $r->responses->whereNotNull('manager_rating')->avg('manager_rating');
        if ($avgRating !== null) {
            $managerStats[$managerId]['ratings'][] = round($avgRating, 2);
        }
    }

    // Calculate stats per manager
    $result = [];
    foreach ($managerStats as $managerId => $stats) {
        $ratings = $stats['ratings'];
        $result[] = [
            'manager_id' => $managerId,
            'manager_name' => $stats['manager_name'],
            'review_count' => $stats['review_count'],
            'avg_rating' => count($ratings) > 0 ? round(array_sum($ratings) / count($ratings), 2) : null,
            'min_rating' => count($ratings) > 0 ? min($ratings) : null,
            'max_rating' => count($ratings) > 0 ? max($ratings) : null,
            'is_current_reviewer' => $managerId == $review->reviewer_id,
        ];
    }

    // Cycle-wide stats
    $allRatings = collect($result)->pluck('avg_rating')->filter()->values();

    return [
        'cycle_name' => $review->cycle->name,
        'total_reviews_in_cycle' => $cycleReviews->count(),
        'cycle_avg_rating' => $allRatings->isNotEmpty() ? round($allRatings->avg(), 2) : null,
        'manager_breakdown' => $result,
    ];
}
```

**Step 2: Add controller method**

```php
public function getCalibrationContext(int $id)
{
    $context = $this->repository->getCalibrationContext($id);
    return ResponseHelper::jsonResponse(true, 'Calibration context retrieved successfully', $context);
}
```

**Step 3: Add route** (inside performance prefix, before `reviews/{id}`)

```php
Route::get('reviews/{id}/calibration-context', [PerformanceReviewController::class, 'getCalibrationContext'])
    ->middleware(PermissionMiddleware::using(['review-calibrate']));
```

**Step 4: Commit**

```
feat(performance): add calibration-context endpoint for cross-manager normalization
```

---

## Task 7: Frontend — Update store with new actions

**Files:**
- Modify: `team-sync-fe/src/stores/performanceReview.js`

**Step 1: Add new state and actions**

Add to state:
```js
// Pending Calibration
pendingCalibrationReviews: [],
pendingCalibrationLoading: false,

// Calibration Context
calibrationContext: null,
calibrationContextLoading: false,
```

Add new actions:
```js
async fetchPendingCalibration(filters = {}) {
  this.pendingCalibrationLoading = true;
  this.error = null;
  try {
    const response = await axiosInstance.get(
      "/performance/reviews/pending-calibration",
      { params: filters },
    );
    this.pendingCalibrationReviews = response.data.data.data || [];
    this.pagination = {
      current_page: response.data.data.current_page,
      per_page: response.data.data.per_page,
      total: response.data.data.total,
      last_page: response.data.data.last_page,
    };
    return response.data.data;
  } catch (error) {
    this.error = handleError(error);
    throw error;
  } finally {
    this.pendingCalibrationLoading = false;
  }
},

async fetchCalibrationContext(reviewId) {
  this.calibrationContextLoading = true;
  this.error = null;
  try {
    const response = await axiosInstance.get(
      `/performance/reviews/${reviewId}/calibration-context`,
    );
    this.calibrationContext = response.data.data;
    return response.data.data;
  } catch (error) {
    this.error = handleError(error);
    throw error;
  } finally {
    this.calibrationContextLoading = false;
  }
},
```

Update existing `calibrateReview` action — remove `finalRating` and `finalRatingLabel` params (now auto-calculated):
```js
async calibrateReview(reviewId, responses) {
  this.reviewsLoading = true;
  this.error = null;
  this.success = false;
  try {
    const response = await axiosInstance.post(
      `/performance/reviews/${reviewId}/calibrate`,
      { responses },
    );
    this.currentReview = response.data.data;
    this.success = true;
    return response.data.data;
  } catch (error) {
    this.error = handleError(error);
    throw error;
  } finally {
    this.reviewsLoading = false;
  }
},
```

**Step 2: Commit**

```
feat(performance): add pending-calibration and calibration-context store actions
```

---

## Task 8: Frontend — Create PendingCalibration.vue view

**Files:**
- Create: `team-sync-fe/src/views/admin/performance/PendingCalibration.vue`
- Modify: `team-sync-fe/src/router/performance.js`

This is a list view showing all reviews with status `pending_calibration` (excluding HR's own). Each row links to ReviewDetail where HR can calibrate.

**Step 1: Create the view component**

Create `PendingCalibration.vue` following the same patterns as `TeamReviews.vue` — a paginated list with cycle filter, showing employee name, reviewer name, current calculated rating, and status badge. Each row is clickable and navigates to `admin.performance.review.detail`.

**Step 2: Add route**

In `team-sync-fe/src/router/performance.js`, add inside the children array:

```js
{
  path: "reviews/pending-calibration",
  name: "admin.performance.pending-calibration",
  component: () => import("@/views/admin/performance/PendingCalibration.vue"),
  meta: {
    requiredPermission: "review-calibrate",
  },
},
```

**IMPORTANT:** Place this route BEFORE the `reviews/:id` route to avoid route conflict.

**Step 3: Add sidebar menu item**

In the Sidebar component, add "Pending Calibration" menu item under PERFORMANCE section, visible only to users with `review-calibrate` permission.

**Step 4: Commit**

```
feat(performance): add Pending Calibration view for HR calibration queue
```

---

## Task 9: Frontend — Refactor ReviewDetail.vue calibration tab

**Files:**
- Modify: `team-sync-fe/src/views/admin/performance/ReviewDetail.vue`

**Changes:**

1. **Remove manual Final Rating input** — `final_rating` is now auto-calculated and displayed read-only
2. **Remove Performance Label dropdown** — label is auto-derived from rating
3. **Show live-calculated rating** — as HR changes section override ratings, recalculate and display the projected final_rating in real-time
4. **Block self-calibration** — add `canCalibrate` check: `employee_id !== currentEmployeeId`
5. **Show normalization context panel** — fetch and display `calibrationContext` data showing cross-manager rating distribution
6. **Show manager's recommended rating** — display `manager_recommended_rating` as reference
7. **Fix isCalibrationValid** — remove truthy check bug, just check that review is in correct status and user has permission
8. **Update submitCalibration** — only send section override responses, no final_rating/label

**Key changes to `canCalibrate`:**
```js
const canCalibrate = computed(() => {
  return (
    reviewStatus.value === "pending_calibration" &&
    hasRole("hr") &&
    currentEmployeeId.value !== review.value?.employee_id
  );
});
```

**Key changes to `isCalibrationValid`:**
```js
const isCalibrationValid = computed(() => {
  // Always valid for calibration — HR can submit with or without section overrides
  // final_rating is auto-calculated from existing manager ratings + any overrides
  return canCalibrate.value;
});
```

**Key changes to `submitCalibration`:**
```js
const submitCalibration = async () => {
  submitting.value = true;
  try {
    const responses = displaySections.value
      .map((section) => ({
        section_id: section.id,
        rating: calibrationForm.value[section.id]?.rating || null,
      }))
      .filter((r) => r.rating);
    await reviewStore.calibrateReview(reviewId.value, responses);
    await reviewStore.fetchReviewById(reviewId.value);
  } finally {
    submitting.value = false;
  }
};
```

**Replace "Final Rating & Label" card with auto-calculated display:**
- Show projected final_rating (calculated client-side from section ratings + overrides)
- Show auto-derived label
- Show manager's recommended rating as reference
- Show normalization context (cycle average, manager's average vs other managers)

**Step 5: Commit**

```
refactor(performance): auto-calculate final_rating in calibration tab, add normalization context
```

---

## Task 10: Frontend — Update manager assessment to remove manual final_rating

**Files:**
- Modify: `team-sync-fe/src/views/admin/performance/ReviewDetail.vue`

**Changes:**

1. Remove the "Overall Final Rating" input card from manager assessment tab
2. Instead, show the auto-calculated rating (read-only) based on manager's section ratings
3. Update `submitManagerAssessment` to not send `final_rating` (or send as optional recommendation)

**Step 1: Remove manual input, show calculated preview**

Replace the `<MainCard v-if="canSubmitManagerAssessment">` (Overall Final Rating) with a read-only display showing the weighted average of manager's section ratings.

**Step 2: Update store action call**

```js
const submitManagerAssessment = async () => {
  submitting.value = true;
  try {
    const responses = displaySections.value.map((section) => ({
      section_id: section.id,
      rating: managerAssessmentForm.value[section.id]?.rating,
      comments: managerAssessmentForm.value[section.id]?.comments || null,
    }));
    await reviewStore.submitManagerAssessment(reviewId.value, responses, null);
    await reviewStore.fetchReviewById(reviewId.value);
  } finally {
    submitting.value = false;
  }
};
```

**Step 3: Commit**

```
refactor(performance): remove manual final_rating from manager assessment, show auto-calculated preview
```

---

## Task 11: Backend Tests

**Files:**
- Modify/Create: `team-sync-be/tests/Unit/Helpers/PerformanceRatingHelperTest.php`
- Modify/Create: `team-sync-be/tests/Feature/Performance/CalibrationTest.php`

**Test cases for PerformanceRatingHelper:**
1. Calculates weighted average correctly with all sections rated
2. Falls back to manager_rating when final_rating is null
3. Falls back to self_rating when both final_rating and manager_rating are null
4. Returns null when no responses exist
5. Handles sections with different weights correctly
6. Derives correct label for each rating range boundary

**Test cases for Calibration:**
1. HR can calibrate another employee's review
2. HR cannot calibrate their own review (403)
3. Calibration auto-calculates final_rating
4. Calibration auto-derives final_rating_label
5. Pending calibration endpoint returns only pending_calibration reviews
6. Pending calibration endpoint excludes HR's own review
7. Calibration context returns cross-manager stats

**Step: Commit**

```
test(performance): add tests for auto-calculated ratings and calibration flow
```

---

## Task 12: Frontend Tests

**Files:**
- Create: `team-sync-fe/src/tests/admin/performance/PendingCalibration.smoke.test.js`

**Test cases:**
1. PendingCalibration view renders with loading state
2. PendingCalibration view renders review list
3. Clicking review navigates to detail page

**Step: Commit**

```
test(performance): add smoke tests for PendingCalibration view
```

---

## Execution Order & Dependencies

```
Task 1 (migration) → Task 2 (helper) → Task 3 (manager assessment) → Task 4 (calibrate)
                                      → Task 5 (pending endpoint) → Task 6 (context endpoint)
                                      → Task 7 (store) → Task 8 (pending view)
                                                       → Task 9 (calibration tab refactor)
                                                       → Task 10 (manager tab refactor)
Task 11 (backend tests) — after Tasks 1-6
Task 12 (frontend tests) — after Tasks 7-10
```

Tasks 5-6 can run in parallel with Tasks 3-4.
Tasks 8-10 can run in parallel after Task 7.
