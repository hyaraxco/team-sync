# Performance Notification Wiring & Deep Links — Implementation Plan

> **Execution:** Use the **executing-plans** skill to execute this plan in single-flow mode.

**Goal:** Fix all 7 broken performance notification deep links, wire 4 remaining notifications to controller trigger points, and create the missing `GoalProgressUpdated` notification class.

**Architecture:** All notifications use the `database` channel only (no email/SMS). Deep links use relative paths matching Vue Router routes under `/admin/performance/...`. Wiring dispatches notifications directly in controller methods (not via EmailService, since performance notifications are DB-only).

**Tech Stack:** Laravel 10, Sanctum, Spatie Permissions, PHPUnit/Pest, Vue 3 Router

---

### Task 1: Fix Deep Links — All 7 Performance Notification `action_url` Values

**Files:**
- Modify: `team-sync-be/app/Notifications/Performance/FeedbackReceived.php:39`
- Modify: `team-sync-be/app/Notifications/Performance/GoalAssigned.php:36`
- Modify: `team-sync-be/app/Notifications/Performance/GoalDeadlineApproaching.php:37`
- Modify: `team-sync-be/app/Notifications/Performance/ReviewCycleStarted.php:37`
- Modify: `team-sync-be/app/Notifications/Performance/ReviewSubmittedForManager.php:36`
- Modify: `team-sync-be/app/Notifications/Performance/ReviewSubmittedForCalibration.php:37`
- Modify: `team-sync-be/app/Notifications/Performance/ReviewCalibrated.php:39`
- Test: `team-sync-be/tests/Feature/Performance/PerformanceNotificationTest.php`

**Step 1: Write failing tests for correct deep link URLs**

Add/update tests in `PerformanceNotificationTest.php` that assert the exact `action_url` value matches FE routes:

```php
// Add these test methods to PerformanceNotificationTest

public function test_feedback_received_action_url_matches_fe_route(): void
{
    $notification = new FeedbackReceived(
        feedbackId: 1,
        giverName: 'John',
        feedbackType: 'positive',
        contentPreview: 'Great work',
    );
    $data = $notification->toArray(new \stdClass);
    $this->assertEquals('/admin/performance/feedback/received', $data['action_url']);
}

public function test_goal_assigned_action_url_deep_links_to_goal_detail(): void
{
    $notification = new GoalAssigned(
        goalId: 42,
        goalTitle: 'Complete OKRs',
        assignedByName: 'Manager',
    );
    $data = $notification->toArray(new \stdClass);
    $this->assertEquals('/admin/performance/goals/42', $data['action_url']);
}

public function test_goal_deadline_approaching_action_url_deep_links_to_goal_detail(): void
{
    $notification = new GoalDeadlineApproaching(
        goalId: 42,
        goalTitle: 'Complete OKRs',
        dueDate: '2026-05-01',
        daysRemaining: 3,
    );
    $data = $notification->toArray(new \stdClass);
    $this->assertEquals('/admin/performance/goals/42', $data['action_url']);
}

public function test_review_cycle_started_action_url_matches_fe_route(): void
{
    $notification = new ReviewCycleStarted(
        cycleId: 1,
        cycleName: '2026 Annual',
        startDate: '2026-01-01',
        endDate: '2026-12-31',
    );
    $data = $notification->toArray(new \stdClass);
    $this->assertEquals('/admin/performance/reviews/my-reviews', $data['action_url']);
}

public function test_review_submitted_for_manager_action_url_deep_links_to_review(): void
{
    $notification = new ReviewSubmittedForManager(
        reviewId: 99,
        employeeName: 'Jane',
        cycleName: '2026 Annual',
    );
    $data = $notification->toArray(new \stdClass);
    $this->assertEquals('/admin/performance/reviews/99', $data['action_url']);
}

public function test_review_submitted_for_calibration_action_url_targets_pending_calibration(): void
{
    $notification = new ReviewSubmittedForCalibration(
        reviewId: 99,
        employeeName: 'Jane',
        managerName: 'John',
        cycleName: '2026 Annual',
    );
    $data = $notification->toArray(new \stdClass);
    $this->assertEquals('/admin/performance/reviews/pending-calibration', $data['action_url']);
}

public function test_review_calibrated_action_url_deep_links_to_review(): void
{
    $notification = new ReviewCalibrated(
        reviewId: 99,
        cycleName: '2026 Annual',
        finalRating: 4.5,
        outcome: 'Exceeds',
    );
    $data = $notification->toArray(new \stdClass);
    $this->assertEquals('/admin/performance/reviews/99', $data['action_url']);
}
```

**Step 2: Run tests to verify they fail**

Run: `cd team-sync-be && ./vendor/bin/pest tests/Feature/Performance/PerformanceNotificationTest.php --filter="action_url"`
Expected: 7 FAIL — current URLs don't have `/admin/` prefix

**Step 3: Fix all 7 notification `action_url` values**

| File | Old `action_url` | New `action_url` |
|---|---|---|
| `FeedbackReceived.php:39` | `'/performance/feedback'` | `'/admin/performance/feedback/received'` |
| `GoalAssigned.php:36` | `'/performance/goals'` | `"/admin/performance/goals/{$this->goalId}"` |
| `GoalDeadlineApproaching.php:37` | `'/performance/goals'` | `"/admin/performance/goals/{$this->goalId}"` |
| `ReviewCycleStarted.php:37` | `'/performance/reviews'` | `'/admin/performance/reviews/my-reviews'` |
| `ReviewSubmittedForManager.php:36` | `"/performance/reviews/{$this->reviewId}"` | `"/admin/performance/reviews/{$this->reviewId}"` |
| `ReviewSubmittedForCalibration.php:37` | `"/performance/reviews/{$this->reviewId}"` | `'/admin/performance/reviews/pending-calibration'` |
| `ReviewCalibrated.php:39` | `"/performance/reviews/{$this->reviewId}"` | `"/admin/performance/reviews/{$this->reviewId}"` |

**Step 4: Run tests to verify they pass**

Run: `cd team-sync-be && ./vendor/bin/pest tests/Feature/Performance/PerformanceNotificationTest.php`
Expected: ALL PASS

**Step 5: Commit**

```bash
git add -A && git commit -m "fix: correct all 7 performance notification action_url deep links to match FE routes"
```

---

### Task 2: Wire `ReviewCycleStarted` into `generateReviews()`

**Files:**
- Modify: `team-sync-be/app/Http/Controllers/PerformanceReviewCycleController.php:59-108`
- Test: `team-sync-be/tests/Feature/Performance/PerformanceNotificationTest.php`

**Step 1: Write failing integration test**

```php
public function test_review_cycle_started_notification_sent_when_reviews_generated(): void
{
    Notification::fake();

    [$hrUser] = $this->createUserWithProfile('hr');
    [$employeeUser, $employeeProfile] = $this->createUserWithProfile('staff');

    // Employee needs active job info
    \App\Models\JobInformation::factory()->create([
        'staff_member_id' => $employeeProfile->id,
        'status' => 'active',
    ]);

    $cycle = PerformanceReviewCycle::factory()->active()->create();

    Sanctum::actingAs($hrUser);

    $this->postJson("/api/v1/performance/review-cycles/{$cycle->id}/generate-reviews");

    Notification::assertSentTo($employeeUser, ReviewCycleStarted::class);
}
```

**Step 2: Run test to verify it fails**

Run: `cd team-sync-be && ./vendor/bin/pest tests/Feature/Performance/PerformanceNotificationTest.php --filter="review_cycle_started_notification_sent"`
Expected: FAIL — notification not dispatched

**Step 3: Wire notification in `PerformanceReviewCycleController::generateReviews()`**

In `PerformanceReviewCycleController.php`, after the `foreach` loop (after line 96), add:

```php
use App\Notifications\Performance\ReviewCycleStarted;

// Inside generateReviews(), after the foreach loop:
foreach ($staffMembers as $staffMember) {
    if ($staffMember->user) {
        $staffMember->user->notify(new ReviewCycleStarted(
            cycleId: $cycle->id,
            cycleName: $cycle->name,
            startDate: $cycle->start_date->format('Y-m-d'),
            endDate: $cycle->end_date->format('Y-m-d'),
        ));
    }
}
```

**Step 4: Run test to verify it passes**

Run: `cd team-sync-be && ./vendor/bin/pest tests/Feature/Performance/PerformanceNotificationTest.php --filter="review_cycle_started_notification_sent"`
Expected: PASS

**Step 5: Commit**

```bash
git add -A && git commit -m "feat: wire ReviewCycleStarted notification into generateReviews()"
```

---

### Task 3: Wire `ReviewSubmittedForManager` into `submitSelfAssessment()`

**Files:**
- Modify: `team-sync-be/app/Http/Controllers/PerformanceReviewController.php:86-99`
- Test: `team-sync-be/tests/Feature/Performance/PerformanceNotificationTest.php`

**Step 1: Write failing integration test**

```php
public function test_review_submitted_for_manager_notification_sent_on_self_assessment(): void
{
    Notification::fake();

    [$managerUser, $managerProfile] = $this->createUserWithProfile('manager');
    [$employeeUser, $employeeProfile] = $this->createUserWithProfile('staff');

    $cycle = PerformanceReviewCycle::factory()->active()->create();
    $section = \App\Models\PerformanceReviewSection::create([
        'name' => 'S1', 'weight' => 100, 'order' => 1, 'is_active' => true,
    ]);

    $review = PerformanceReview::create([
        'cycle_id' => $cycle->id,
        'staff_member_id' => $employeeProfile->id,
        'reviewer_id' => $managerProfile->id,
        'status' => 'pending_self',
    ]);

    Sanctum::actingAs($employeeUser);

    $this->postJson("/api/v1/performance/reviews/{$review->id}/self-assessment", [
        'responses' => [
            ['section_id' => $section->id, 'rating' => 4, 'comments' => 'Good'],
        ],
    ]);

    Notification::assertSentTo($managerUser, ReviewSubmittedForManager::class);
}
```

**Step 2: Run test to verify it fails**

Run: `cd team-sync-be && ./vendor/bin/pest tests/Feature/Performance/PerformanceNotificationTest.php --filter="review_submitted_for_manager_notification_sent"`
Expected: FAIL

**Step 3: Wire notification in `PerformanceReviewController::submitSelfAssessment()`**

After line 97 (`$review = $this->repository->submitSelfAssessment(...)`), add:

```php
use App\Notifications\Performance\ReviewSubmittedForManager;

// After submitSelfAssessment repository call:
$review->load(['reviewer.user', 'cycle', 'staffMember.user']);
if ($review->reviewer?->user) {
    $review->reviewer->user->notify(new ReviewSubmittedForManager(
        reviewId: $review->id,
        employeeName: $user->name ?? 'Employee',
        cycleName: $review->cycle?->name ?? 'Review Cycle',
    ));
}
```

**Step 4: Run test to verify it passes**

Run: `cd team-sync-be && ./vendor/bin/pest tests/Feature/Performance/PerformanceNotificationTest.php --filter="review_submitted_for_manager_notification_sent"`
Expected: PASS

**Step 5: Commit**

```bash
git add -A && git commit -m "feat: wire ReviewSubmittedForManager notification into submitSelfAssessment()"
```

---

### Task 4: Wire `ReviewSubmittedForCalibration` into `submitManagerAssessment()`

**Files:**
- Modify: `team-sync-be/app/Http/Controllers/PerformanceReviewController.php:102-108`
- Test: `team-sync-be/tests/Feature/Performance/PerformanceNotificationTest.php`

**Step 1: Write failing integration test**

```php
public function test_review_submitted_for_calibration_notification_sent_on_manager_assessment(): void
{
    Notification::fake();

    [$hrUser] = $this->createUserWithProfile('hr');
    [$managerUser, $managerProfile] = $this->createUserWithProfile('manager');
    [$employeeUser, $employeeProfile] = $this->createUserWithProfile('staff');

    $cycle = PerformanceReviewCycle::factory()->active()->create();
    $section = \App\Models\PerformanceReviewSection::create([
        'name' => 'S1', 'weight' => 100, 'order' => 1, 'is_active' => true,
    ]);

    $review = PerformanceReview::create([
        'cycle_id' => $cycle->id,
        'staff_member_id' => $employeeProfile->id,
        'reviewer_id' => $managerProfile->id,
        'status' => 'pending_manager',
        'self_assessment_submitted_at' => now(),
    ]);

    Sanctum::actingAs($managerUser);

    $this->postJson("/api/v1/performance/reviews/{$review->id}/manager-assessment", [
        'responses' => [
            ['section_id' => $section->id, 'rating' => 4, 'comments' => 'Good'],
        ],
    ]);

    // HR user should receive the calibration notification
    Notification::assertSentTo($hrUser, ReviewSubmittedForCalibration::class);
}
```

**Step 2: Run test to verify it fails**

Run: `cd team-sync-be && ./vendor/bin/pest tests/Feature/Performance/PerformanceNotificationTest.php --filter="review_submitted_for_calibration_notification_sent"`
Expected: FAIL

**Step 3: Wire notification in `PerformanceReviewController::submitManagerAssessment()`**

After line 105 (`$review = $this->repository->submitManagerAssessment(...)`), add:

```php
use App\Notifications\Performance\ReviewSubmittedForCalibration;
use App\Models\User;

// After submitManagerAssessment repository call:
$review->load(['cycle', 'staffMember.user']);
$hrUsers = User::role('hr')->get();
$managerName = Auth::user()->name ?? 'Manager';
foreach ($hrUsers as $hrUser) {
    $hrUser->notify(new ReviewSubmittedForCalibration(
        reviewId: $review->id,
        employeeName: $review->staffMember?->user?->name ?? 'Employee',
        managerName: $managerName,
        cycleName: $review->cycle?->name ?? 'Review Cycle',
    ));
}
```

**Step 4: Run test to verify it passes**

Run: `cd team-sync-be && ./vendor/bin/pest tests/Feature/Performance/PerformanceNotificationTest.php --filter="review_submitted_for_calibration_notification_sent"`
Expected: PASS

**Step 5: Commit**

```bash
git add -A && git commit -m "feat: wire ReviewSubmittedForCalibration notification into submitManagerAssessment()"
```

---

### Task 5: Wire `ReviewCalibrated` into `calibrateReview()`

**Files:**
- Modify: `team-sync-be/app/Http/Controllers/PerformanceReviewController.php:124-129`
- Test: `team-sync-be/tests/Feature/Performance/PerformanceNotificationTest.php`

**Step 1: Write failing integration test**

```php
public function test_review_calibrated_notification_sent_on_calibration(): void
{
    Notification::fake();

    [$hrUser] = $this->createUserWithProfile('hr');
    [$managerUser, $managerProfile] = $this->createUserWithProfile('manager');
    [$employeeUser, $employeeProfile] = $this->createUserWithProfile('staff');

    $cycle = PerformanceReviewCycle::factory()->active()->create();
    $section = \App\Models\PerformanceReviewSection::create([
        'name' => 'S1', 'weight' => 100, 'order' => 1, 'is_active' => true,
    ]);

    $review = PerformanceReview::create([
        'cycle_id' => $cycle->id,
        'staff_member_id' => $employeeProfile->id,
        'reviewer_id' => $managerProfile->id,
        'status' => 'pending_calibration',
        'self_assessment_submitted_at' => now(),
        'manager_assessment_submitted_at' => now(),
    ]);

    // Create a response so calibration has data
    \App\Models\PerformanceReviewResponse::create([
        'review_id' => $review->id,
        'section_id' => $section->id,
        'manager_rating' => 4,
    ]);

    Sanctum::actingAs($hrUser);

    $this->postJson("/api/v1/performance/reviews/{$review->id}/calibrate", [
        'responses' => [
            ['section_id' => $section->id, 'rating' => 4],
        ],
    ]);

    // Employee should receive the calibrated notification
    Notification::assertSentTo($employeeUser, ReviewCalibrated::class);
}
```

**Step 2: Run test to verify it fails**

Run: `cd team-sync-be && ./vendor/bin/pest tests/Feature/Performance/PerformanceNotificationTest.php --filter="review_calibrated_notification_sent"`
Expected: FAIL

**Step 3: Wire notification in `PerformanceReviewController::calibrateReview()`**

After line 127 (`$review = $this->repository->calibrateReview(...)`), add:

```php
use App\Notifications\Performance\ReviewCalibrated;

// After calibrateReview repository call:
$review->load(['staffMember.user', 'cycle', 'outcomeRule']);
if ($review->staffMember?->user) {
    $review->staffMember->user->notify(new ReviewCalibrated(
        reviewId: $review->id,
        cycleName: $review->cycle?->name ?? 'Review Cycle',
        finalRating: (float) ($review->final_rating ?? 0),
        outcome: $review->outcomeRule?->outcome_label,
    ));
}
```

**Step 4: Run test to verify it passes**

Run: `cd team-sync-be && ./vendor/bin/pest tests/Feature/Performance/PerformanceNotificationTest.php --filter="review_calibrated_notification_sent"`
Expected: PASS

**Step 5: Commit**

```bash
git add -A && git commit -m "feat: wire ReviewCalibrated notification into calibrateReview()"
```

---

### Task 6: Create `GoalProgressUpdated` Notification Class + Wire

**Files:**
- Create: `team-sync-be/app/Notifications/Performance/GoalProgressUpdated.php`
- Modify: `team-sync-be/app/Http/Controllers/PerformanceGoalController.php:129-141`
- Test: `team-sync-be/tests/Feature/Performance/PerformanceNotificationTest.php`

**Step 1: Write failing tests**

```php
// Unit test
public function test_goal_progress_updated_notification_uses_database_channel_only(): void
{
    $notification = new \App\Notifications\Performance\GoalProgressUpdated(
        goalId: 1,
        goalTitle: 'Complete OKRs',
        employeeName: 'Jane Employee',
        progressPercentage: 75,
    );

    $channels = $notification->via(new \stdClass);
    $this->assertEquals(['database'], $channels);
}

public function test_goal_progress_updated_notification_has_correct_structure(): void
{
    $notification = new \App\Notifications\Performance\GoalProgressUpdated(
        goalId: 42,
        goalTitle: 'Complete OKRs',
        employeeName: 'Jane Employee',
        progressPercentage: 75,
    );

    $data = $notification->toArray(new \stdClass);
    $this->assertEquals('performance', $data['category']);
    $this->assertArrayHasKey('title', $data);
    $this->assertStringContainsString('Jane Employee', $data['body']);
    $this->assertEquals('/admin/performance/goals/42', $data['action_url']);
    $this->assertEquals(42, $data['goal_id']);
}

// Integration test
public function test_goal_progress_updated_notification_sent_to_manager_on_progress_update(): void
{
    Notification::fake();

    [$managerUser, $managerProfile] = $this->createUserWithProfile('manager');
    [$employeeUser, $employeeProfile] = $this->createUserWithProfile('staff');

    // Manager assigned the goal to employee
    $goal = PerformanceGoal::create([
        'staff_member_id' => $employeeProfile->id,
        'assigned_by' => $managerProfile->id,
        'title' => 'Complete Q2 OKRs',
        'goal_type' => 'okr',
        'start_date' => now()->toDateString(),
        'due_date' => now()->addMonths(3)->toDateString(),
        'status' => 'in_progress',
    ]);

    Sanctum::actingAs($employeeUser);

    $this->postJson("/api/v1/performance/goals/{$goal->id}/progress", [
        'progress_percentage' => 75,
        'notes' => 'Making good progress',
    ]);

    Notification::assertSentTo($managerUser, \App\Notifications\Performance\GoalProgressUpdated::class);
}
```

**Step 2: Run tests to verify they fail**

Run: `cd team-sync-be && ./vendor/bin/pest tests/Feature/Performance/PerformanceNotificationTest.php --filter="goal_progress_updated"`
Expected: FAIL — class doesn't exist

**Step 3: Create `GoalProgressUpdated` notification class**

Create `team-sync-be/app/Notifications/Performance/GoalProgressUpdated.php`:

```php
<?php

namespace App\Notifications\Performance;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class GoalProgressUpdated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected int $goalId,
        protected string $goalTitle,
        protected string $employeeName,
        protected int $progressPercentage,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'category' => 'performance',
            'title' => 'Progress Goal Diperbarui',
            'body' => "{$this->employeeName} memperbarui progress goal \"{$this->goalTitle}\" menjadi {$this->progressPercentage}%.",
            'action_url' => "/admin/performance/goals/{$this->goalId}",
            'goal_id' => $this->goalId,
            'goal_title' => $this->goalTitle,
            'employee_name' => $this->employeeName,
            'progress_percentage' => $this->progressPercentage,
        ];
    }
}
```

**Step 4: Wire in `PerformanceGoalController::addProgressUpdate()`**

After line 139 (`$update = $this->repository->addProgressUpdate(...)`), add:

```php
use App\Notifications\Performance\GoalProgressUpdated;
use App\Models\StaffMemberProfile;

// Notify the goal assigner (manager) if the updater is the goal owner
if ($goal->assigned_by && $goal->assigned_by !== $user->staffMemberProfile?->id) {
    $assignerProfile = StaffMemberProfile::with('user')->find($goal->assigned_by);
    if ($assignerProfile?->user) {
        $assignerProfile->user->notify(new GoalProgressUpdated(
            goalId: $goal->id,
            goalTitle: $goal->title,
            employeeName: $user->name ?? 'Employee',
            progressPercentage: (int) ($request->validated('progress_percentage') ?? 0),
        ));
    }
}
```

**Step 5: Run tests to verify they pass**

Run: `cd team-sync-be && ./vendor/bin/pest tests/Feature/Performance/PerformanceNotificationTest.php --filter="goal_progress_updated"`
Expected: PASS

**Step 6: Commit**

```bash
git add -A && git commit -m "feat: create GoalProgressUpdated notification + wire into addProgressUpdate()"
```

---

### Task 7: Full Regression + Final Commit

**Step 1: Run full BE test suite**

Run: `cd team-sync-be && ./vendor/bin/pest`
Expected: ALL PASS (408+ tests)

**Step 2: Run full FE test suite**

Run: `cd team-sync-fe && npm run test`
Expected: ALL PASS (211 tests)

**Step 3: Update task tracker**

Update `task.md` with Phase F status.

**Step 4: Final commit**

```bash
git add -A && git commit -m "feat: Phase F — notification wiring + deep link fixes complete (12 items)"
```
