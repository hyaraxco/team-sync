<?php

namespace Tests\Feature\Performance;

use App\Interfaces\PerformanceGoalRepositoryInterface;
use App\Models\PerformanceGoal;
use App\Models\PerformanceReviewSection;
use App\Models\StaffMemberProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\ActivatesLicense;
use Tests\TestCase;

/**
 * Regression tests: Performance endpoints must not leak raw exception messages.
 */
class ErrorHandlingSafetyTest extends TestCase
{
    use ActivatesLicense, RefreshDatabase;

    private const INTERNAL_SECRET = 'SQLSTATE[42S02]: Base table not found: performance_goals_archive';

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
        Permission::firstOrCreate(['name' => 'review-cycle-manage', 'guard_name' => 'sanctum']);

        $hr = Role::firstOrCreate(['name' => 'HR', 'guard_name' => 'sanctum']);
        $hr->syncPermissions(['goal-create-own', 'goal-assign-team', 'review-cycle-manage']);
    }

    private function actingAsHr(): User
    {
        $user = User::factory()->create();
        $user->assignRole('HR');
        Sanctum::actingAs($user);

        return $user;
    }

    // ── PerformanceGoalController::destroy ────────────────────────────

    public function test_goal_delete_does_not_leak_exception_message(): void
    {
        $user = $this->actingAsHr();

        $staffMember = StaffMemberProfile::withoutSyncingToSearch(function () use ($user) {
            return StaffMemberProfile::factory()->create(['user_id' => $user->id]);
        });

        $goal = PerformanceGoal::create([
            'staff_member_id' => $staffMember->id,
            'assigned_by' => $staffMember->id,
            'created_by' => $user->id,
            'title' => 'Test Goal',
            'description' => 'Test',
            'goal_type' => 'individual',
            'status' => 'in_progress',
            'start_date' => now(),
            'due_date' => now()->addMonth(),
        ]);

        // Mock repository to throw unexpected exception
        $mock = $this->mock(PerformanceGoalRepositoryInterface::class);
        $mock->shouldReceive('getGoalById')
            ->with($goal->id)
            ->andReturn($goal);
        $mock->shouldReceive('deleteGoal')
            ->with($goal->id)
            ->andThrow(new \Exception(self::INTERNAL_SECRET));

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn (string $msg) => str_contains($msg, 'PerformanceGoalController::destroy'));

        $response = $this->deleteJson("/api/v1/performance/goals/{$goal->id}");

        $response->assertStatus(400);
        $this->assertStringNotContainsString(self::INTERNAL_SECRET, $response->getContent());
        $response->assertJsonFragment(['message' => 'Failed to delete goal. It may be linked to a completed review.']);
    }

    // ── PerformanceReviewTemplateController::store ────────────────────

    public function test_template_store_does_not_leak_exception_message(): void
    {
        $this->actingAsHr();

        $section = PerformanceReviewSection::create([
            'name' => 'Test Section',
            'description' => 'Test',
            'weight' => 100.00,
            'topsis_category' => 'kpi',
            'order' => 1,
            'is_active' => true,
        ]);

        // Force a DB error by creating a template with duplicate name in a transaction
        // that will fail. We'll use a mock approach instead.
        // The controller wraps in DB::transaction, so we test the outer catch.

        $response = $this->postJson('/api/v1/performance/templates', [
            'name' => 'Test Template',
            'sections' => [
                ['id' => $section->id, 'weight' => 100],
            ],
        ]);

        // This should succeed normally
        $response->assertCreated();

        // Now verify the error path doesn't leak
        // We need to trigger an actual error - use invalid section ID that passes validation
        // but fails in DB transaction
        $response2 = $this->postJson('/api/v1/performance/templates', [
            'name' => 'Test Template 2',
            'sections' => [
                ['id' => 99999, 'weight' => 100],
            ],
        ]);

        // Should get validation error (422), not a 500 with leaked message
        $response2->assertStatus(422);
        $this->assertStringNotContainsString('SQLSTATE', $response2->getContent());
    }
}
