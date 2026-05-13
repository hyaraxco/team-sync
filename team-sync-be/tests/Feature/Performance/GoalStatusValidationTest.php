<?php

namespace Tests\Feature\Performance;

use App\Models\PerformanceGoal;
use App\Models\StaffMemberProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\ActivatesLicense;
use Tests\TestCase;

class GoalStatusValidationTest extends TestCase
{
    use ActivatesLicense, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->activateTestLicense();
        $this->seedRolesAndPermissions();
    }

    private function seedRolesAndPermissions(): void
    {
        Permission::firstOrCreate(['name' => 'goal-create-own', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'goal-assign-team', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'feedback-give', 'guard_name' => 'sanctum']);

        $staff = Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'sanctum']);
        $staff->syncPermissions(['goal-create-own', 'feedback-give']);

        $hr = Role::firstOrCreate(['name' => 'HR', 'guard_name' => 'sanctum']);
        $hr->syncPermissions(['goal-create-own', 'goal-assign-team', 'feedback-give']);
    }

    private function actingAsHr(): User
    {
        $user = User::factory()->create();
        $user->assignRole('HR');
        Sanctum::actingAs($user);

        return $user;
    }

    private function actingAsStaff(): User
    {
        $user = User::factory()->create();
        $user->assignRole('staff');
        Sanctum::actingAs($user);

        return $user;
    }

    // ── Task 5.2: Goal status validation ─────────────────────────────

    public function test_create_goal_rejects_invalid_status(): void
    {
        $this->actingAsHr();

        $staffMember = StaffMemberProfile::withoutSyncingToSearch(function () {
            return StaffMemberProfile::factory()->create();
        });

        $response = $this->postJson('/api/v1/performance/goals', [
            'staff_member_id' => $staffMember->id,
            'title' => 'Test Goal',
            'goal_type' => 'okr',
            'start_date' => now()->toDateString(),
            'due_date' => now()->addMonth()->toDateString(),
            'status' => 'invalid_status',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('status');
    }

    public function test_create_goal_accepts_valid_statuses(): void
    {
        $this->actingAsHr();

        $staffMember = StaffMemberProfile::withoutSyncingToSearch(function () {
            return StaffMemberProfile::factory()->create();
        });

        foreach (['not_started', 'in_progress', 'at_risk', 'completed', 'cancelled'] as $status) {
            $response = $this->postJson('/api/v1/performance/goals', [
                'staff_member_id' => $staffMember->id,
                'title' => "Test Goal - {$status}",
                'goal_type' => 'okr',
                'start_date' => now()->toDateString(),
                'due_date' => now()->addMonth()->toDateString(),
                'status' => $status,
            ]);

            $response->assertCreated();
            $this->assertEquals($status, $response->json('data.status'));
        }
    }

    public function test_update_goal_rejects_invalid_status(): void
    {
        $user = $this->actingAsHr();

        $staffMember = StaffMemberProfile::withoutSyncingToSearch(function () use ($user) {
            return StaffMemberProfile::factory()->create(['user_id' => $user->id]);
        });

        $goal = PerformanceGoal::factory()->create([
            'staff_member_id' => $staffMember->id,
            'created_by' => $user->id,
            'assigned_by' => $staffMember->id,
        ]);

        $response = $this->putJson("/api/v1/performance/goals/{$goal->id}", [
            'status' => 'bogus_status',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('status');
    }

    public function test_update_goal_accepts_valid_statuses(): void
    {
        $user = $this->actingAsHr();

        $staffMember = StaffMemberProfile::withoutSyncingToSearch(function () use ($user) {
            return StaffMemberProfile::factory()->create(['user_id' => $user->id]);
        });

        $goal = PerformanceGoal::factory()->create([
            'staff_member_id' => $staffMember->id,
            'created_by' => $user->id,
            'assigned_by' => $staffMember->id,
        ]);

        $response = $this->putJson("/api/v1/performance/goals/{$goal->id}", [
            'status' => 'completed',
        ]);

        $response->assertOk();
        $this->assertEquals('completed', $response->json('data.status'));
    }

    public function test_create_goal_defaults_status_when_omitted(): void
    {
        $this->actingAsHr();

        $staffMember = StaffMemberProfile::withoutSyncingToSearch(function () {
            return StaffMemberProfile::factory()->create();
        });

        $response = $this->postJson('/api/v1/performance/goals', [
            'staff_member_id' => $staffMember->id,
            'title' => 'Goal without status',
            'goal_type' => 'kpi',
            'start_date' => now()->toDateString(),
            'due_date' => now()->addMonth()->toDateString(),
        ]);

        $response->assertCreated();
        $this->assertEquals('not_started', $response->json('data.status'));
    }

    // ── Task 5.3: completion_percentage range validation ──────────────

    public function test_progress_update_rejects_completion_percentage_over_100(): void
    {
        $user = $this->actingAsHr();

        $staffMember = StaffMemberProfile::withoutSyncingToSearch(function () use ($user) {
            return StaffMemberProfile::factory()->create(['user_id' => $user->id]);
        });

        $goal = PerformanceGoal::factory()->create([
            'staff_member_id' => $staffMember->id,
            'created_by' => $user->id,
            'assigned_by' => $staffMember->id,
        ]);

        $response = $this->postJson("/api/v1/performance/goals/{$goal->id}/update-progress", [
            'update_type' => 'completion',
            'completion_percentage' => 150,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('completion_percentage');
    }

    public function test_progress_update_rejects_negative_completion_percentage(): void
    {
        $user = $this->actingAsHr();

        $staffMember = StaffMemberProfile::withoutSyncingToSearch(function () use ($user) {
            return StaffMemberProfile::factory()->create(['user_id' => $user->id]);
        });

        $goal = PerformanceGoal::factory()->create([
            'staff_member_id' => $staffMember->id,
            'created_by' => $user->id,
            'assigned_by' => $staffMember->id,
        ]);

        $response = $this->postJson("/api/v1/performance/goals/{$goal->id}/update-progress", [
            'update_type' => 'completion',
            'completion_percentage' => -10,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('completion_percentage');
    }

    public function test_progress_update_accepts_valid_completion_percentage(): void
    {
        $user = $this->actingAsHr();

        $staffMember = StaffMemberProfile::withoutSyncingToSearch(function () use ($user) {
            return StaffMemberProfile::factory()->create(['user_id' => $user->id]);
        });

        $goal = PerformanceGoal::factory()->create([
            'staff_member_id' => $staffMember->id,
            'created_by' => $user->id,
            'assigned_by' => $staffMember->id,
        ]);

        $response = $this->postJson("/api/v1/performance/goals/{$goal->id}/update-progress", [
            'update_type' => 'completion',
            'completion_percentage' => 75,
        ]);

        $response->assertCreated();
    }

    public function test_progress_update_accepts_boundary_values(): void
    {
        $user = $this->actingAsHr();

        $staffMember = StaffMemberProfile::withoutSyncingToSearch(function () use ($user) {
            return StaffMemberProfile::factory()->create(['user_id' => $user->id]);
        });

        $goal = PerformanceGoal::factory()->create([
            'staff_member_id' => $staffMember->id,
            'created_by' => $user->id,
            'assigned_by' => $staffMember->id,
        ]);

        // Test 0 (minimum boundary)
        $response = $this->postJson("/api/v1/performance/goals/{$goal->id}/update-progress", [
            'update_type' => 'completion',
            'completion_percentage' => 0,
        ]);
        $response->assertCreated();

        // Test 100 (maximum boundary)
        $response = $this->postJson("/api/v1/performance/goals/{$goal->id}/update-progress", [
            'update_type' => 'completion',
            'completion_percentage' => 100,
        ]);
        $response->assertCreated();
    }

    // ── Task 5.4: Feedback feedback_type validation ──────────────────

    public function test_create_feedback_rejects_invalid_feedback_type(): void
    {
        $user = $this->actingAsHr();

        $staffMember = StaffMemberProfile::withoutSyncingToSearch(function () use ($user) {
            return StaffMemberProfile::factory()->create(['user_id' => $user->id]);
        });

        $response = $this->postJson('/api/v1/performance/feedback', [
            'staff_member_id' => $staffMember->id,
            'feedback_type' => 'maybe',
            'content' => 'This is test feedback.',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('feedback_type');
    }

    public function test_create_feedback_accepts_valid_feedback_types(): void
    {
        $user = $this->actingAsHr();

        $staffMember = StaffMemberProfile::withoutSyncingToSearch(function () use ($user) {
            return StaffMemberProfile::factory()->create(['user_id' => $user->id]);
        });

        foreach (['positive', 'constructive', 'general'] as $type) {
            $response = $this->postJson('/api/v1/performance/feedback', [
                'staff_member_id' => $staffMember->id,
                'feedback_type' => $type,
                'content' => "Feedback of type {$type}.",
            ]);

            $response->assertCreated();
            $this->assertEquals($type, $response->json('data.feedback_type'));
        }
    }

    public function test_create_feedback_requires_feedback_type(): void
    {
        $user = $this->actingAsHr();

        $staffMember = StaffMemberProfile::withoutSyncingToSearch(function () use ($user) {
            return StaffMemberProfile::factory()->create(['user_id' => $user->id]);
        });

        $response = $this->postJson('/api/v1/performance/feedback', [
            'staff_member_id' => $staffMember->id,
            'content' => 'Feedback without type.',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('feedback_type');
    }
}
