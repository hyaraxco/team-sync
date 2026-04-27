<?php

namespace Tests\Feature\Performance;

use App\Models\JobInformation;
use App\Models\PerformanceFeedback;
use App\Models\PerformanceGoal;
use App\Models\PerformanceReview;
use App\Models\PerformanceReviewCycle;
use App\Models\PerformanceReviewTemplate;
use App\Models\ReviewerRule;
use App\Models\StaffMemberProfile;
use App\Models\User;
use App\Notifications\Performance\FeedbackReceived;
use App\Notifications\Performance\GoalAssigned;
use App\Notifications\Performance\GoalDeadlineApproaching;
use App\Notifications\Performance\ReviewCalibrated;
use App\Notifications\Performance\ReviewCycleStarted;
use App\Notifications\Performance\ReviewSubmittedForManager;
use App\Notifications\Performance\ReviewSubmittedForCalibration;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PerformanceNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function createUserWithProfile(string $roleName): array
    {
        $user = User::factory()->create();
        $role = Role::findByName(strtolower($roleName), 'sanctum');
        $user->assignRole($role);

        $profile = StaffMemberProfile::factory()->create([
            'user_id' => $user->id,
        ]);

        return [$user, $profile];
    }

    // ─── 1. FeedbackReceived ────────────────────────────────────────

    public function test_feedback_received_notification_is_sent_when_feedback_created(): void
    {
        Notification::fake();

        [$giverUser, $giverProfile] = $this->createUserWithProfile('manager');
        [$recipientUser, $recipientProfile] = $this->createUserWithProfile('staff');

        Sanctum::actingAs($giverUser);

        $this->postJson('/api/v1/performance/feedback', [
            'staff_member_id' => $recipientProfile->id,
            'feedback_type' => 'positive',
            'content' => 'Great work on the project!',
        ]);

        Notification::assertSentTo(
            $recipientUser,
            FeedbackReceived::class
        );
    }

    public function test_feedback_received_notification_uses_database_channel_only(): void
    {
        $notification = new FeedbackReceived(
            feedbackId: 1,
            giverName: 'John Manager',
            feedbackType: 'positive',
            contentPreview: 'Great work...',
        );

        $channels = $notification->via(new \stdClass);

        $this->assertEquals(['database'], $channels);
    }

    public function test_feedback_received_notification_has_correct_array_structure(): void
    {
        $notification = new FeedbackReceived(
            feedbackId: 1,
            giverName: 'John Manager',
            feedbackType: 'positive',
            contentPreview: 'Great work on the project!',
        );

        $data = $notification->toArray(new \stdClass);

        $this->assertEquals('performance', $data['category']);
        $this->assertArrayHasKey('title', $data);
        $this->assertArrayHasKey('body', $data);
        $this->assertArrayHasKey('action_url', $data);
        $this->assertEquals(1, $data['feedback_id']);
    }

    // ─── 2. GoalAssigned ────────────────────────────────────────────

    public function test_goal_assigned_notification_is_sent_when_manager_creates_goal_for_employee(): void
    {
        Notification::fake();

        [$managerUser, $managerProfile] = $this->createUserWithProfile('manager');
        [$employeeUser, $employeeProfile] = $this->createUserWithProfile('staff');

        Sanctum::actingAs($managerUser);

        $this->postJson('/api/v1/performance/goals', [
            'staff_member_id' => $employeeProfile->id,
            'title' => 'Complete Q2 OKRs',
            'description' => 'Achieve all Q2 objectives',
            'goal_type' => 'okr',
            'start_date' => now()->toDateString(),
            'due_date' => now()->addMonths(3)->toDateString(),
        ]);

        Notification::assertSentTo(
            $employeeUser,
            GoalAssigned::class
        );
    }

    public function test_goal_assigned_notification_uses_database_channel_only(): void
    {
        $notification = new GoalAssigned(
            goalId: 1,
            goalTitle: 'Complete Q2 OKRs',
            assignedByName: 'John Manager',
        );

        $channels = $notification->via(new \stdClass);

        $this->assertEquals(['database'], $channels);
    }

    // ─── 3. GoalDeadlineApproaching ─────────────────────────────────

    public function test_goal_deadline_approaching_notification_uses_database_channel_only(): void
    {
        $notification = new GoalDeadlineApproaching(
            goalId: 1,
            goalTitle: 'Complete Q2 OKRs',
            dueDate: now()->addDays(3)->toDateString(),
            daysRemaining: 3,
        );

        $channels = $notification->via(new \stdClass);

        $this->assertEquals(['database'], $channels);
    }

    public function test_goal_deadline_approaching_notification_has_correct_array_structure(): void
    {
        $notification = new GoalDeadlineApproaching(
            goalId: 1,
            goalTitle: 'Complete Q2 OKRs',
            dueDate: '2026-05-01',
            daysRemaining: 3,
        );

        $data = $notification->toArray(new \stdClass);

        $this->assertEquals('performance', $data['category']);
        $this->assertStringContainsString('3', $data['body']);
        $this->assertEquals(1, $data['goal_id']);
    }

    // ─── 4. ReviewCycleStarted ──────────────────────────────────────

    public function test_review_cycle_started_notification_uses_database_channel_only(): void
    {
        $notification = new ReviewCycleStarted(
            cycleId: 1,
            cycleName: '2026 Annual Review',
            startDate: '2026-01-01',
            endDate: '2026-12-31',
        );

        $channels = $notification->via(new \stdClass);

        $this->assertEquals(['database'], $channels);
    }

    public function test_review_cycle_started_notification_has_correct_array_structure(): void
    {
        $notification = new ReviewCycleStarted(
            cycleId: 1,
            cycleName: '2026 Annual Review',
            startDate: '2026-01-01',
            endDate: '2026-12-31',
        );

        $data = $notification->toArray(new \stdClass);

        $this->assertEquals('performance', $data['category']);
        $this->assertArrayHasKey('title', $data);
        $this->assertArrayHasKey('body', $data);
        $this->assertEquals(1, $data['cycle_id']);
    }

    // ─── 5. ReviewSubmittedForManager ────────────────────────────────

    public function test_review_submitted_for_manager_notification_uses_database_channel_only(): void
    {
        $notification = new ReviewSubmittedForManager(
            reviewId: 1,
            employeeName: 'Jane Employee',
            cycleName: '2026 Annual Review',
        );

        $channels = $notification->via(new \stdClass);

        $this->assertEquals(['database'], $channels);
    }

    public function test_review_submitted_for_manager_notification_has_correct_array_structure(): void
    {
        $notification = new ReviewSubmittedForManager(
            reviewId: 1,
            employeeName: 'Jane Employee',
            cycleName: '2026 Annual Review',
        );

        $data = $notification->toArray(new \stdClass);

        $this->assertEquals('performance', $data['category']);
        $this->assertStringContainsString('Jane Employee', $data['body']);
        $this->assertEquals(1, $data['review_id']);
    }

    // ─── 6. ReviewSubmittedForCalibration ────────────────────────────

    public function test_review_submitted_for_calibration_notification_uses_database_channel_only(): void
    {
        $notification = new ReviewSubmittedForCalibration(
            reviewId: 1,
            employeeName: 'Jane Employee',
            managerName: 'John Manager',
            cycleName: '2026 Annual Review',
        );

        $channels = $notification->via(new \stdClass);

        $this->assertEquals(['database'], $channels);
    }

    // ─── 7. ReviewCalibrated ────────────────────────────────────────

    public function test_review_calibrated_notification_uses_database_channel_only(): void
    {
        $notification = new ReviewCalibrated(
            reviewId: 1,
            cycleName: '2026 Annual Review',
            finalRating: 4.5,
            outcome: 'Exceeds Expectations',
        );

        $channels = $notification->via(new \stdClass);

        $this->assertEquals(['database'], $channels);
    }

    public function test_review_calibrated_notification_has_correct_array_structure(): void
    {
        $notification = new ReviewCalibrated(
            reviewId: 1,
            cycleName: '2026 Annual Review',
            finalRating: 4.5,
            outcome: 'Exceeds Expectations',
        );

        $data = $notification->toArray(new \stdClass);

        $this->assertEquals('performance', $data['category']);
        $this->assertArrayHasKey('title', $data);
        $this->assertArrayHasKey('body', $data);
        $this->assertEquals(1, $data['review_id']);
        $this->assertEquals(4.5, $data['final_rating']);
    }

    // ─── Deep Link Validation ───────────────────────────────────────

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

    // ─── Wiring Integration Tests ────────────────────────────────────

    public function test_review_cycle_started_notification_sent_when_reviews_generated(): void
    {
        Notification::fake();

        // Setup HR user with review-cycle-manage permission
        [$hrUser] = $this->createUserWithProfile('hr');
        Permission::firstOrCreate(['name' => 'review-cycle-manage', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'review-assign-reviewer', 'guard_name' => 'sanctum']);
        $hrRole = Role::findByName('hr', 'sanctum');
        $hrRole->givePermissionTo(['review-cycle-manage', 'review-assign-reviewer']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Setup employee with active job info
        [$employeeUser, $employeeProfile] = $this->createUserWithProfile('staff');
        JobInformation::factory()->create([
            'staff_member_id' => $employeeProfile->id,
            'status' => 'active',
        ]);

        // Setup reviewer rule so generation doesn't fail
        ReviewerRule::create([
            'reviewee_role' => 'staff',
            'reviewer_role' => 'hr',
            'priority' => 1,
            'is_active' => true,
        ]);

        $cycle = PerformanceReviewCycle::factory()->active()->create();

        Sanctum::actingAs($hrUser);

        $response = $this->postJson("/api/v1/performance/cycles/{$cycle->id}/generate-reviews");
        $response->assertStatus(200);

        Notification::assertSentTo($employeeUser, ReviewCycleStarted::class);
    }
}
