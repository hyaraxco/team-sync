<?php

namespace Tests\Feature\Performance;

use App\Models\PerformanceReview;
use App\Models\PerformanceReviewCycle;
use App\Models\StaffMemberProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\ActivatesLicense;
use Tests\TestCase;

class ReviewCycleGuardsTest extends TestCase
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
        Permission::create(['name' => 'review-cycle-manage', 'guard_name' => 'sanctum']);

        Role::create(['name' => 'HR', 'guard_name' => 'sanctum'])
            ->givePermissionTo(['review-cycle-manage']);

        // Required by StaffMemberProfile factory
        Role::create(['name' => 'staff', 'guard_name' => 'sanctum']);
    }

    private function actingAsHr(): User
    {
        $user = User::factory()->create();
        $role = Role::findByName('HR', 'sanctum');
        $user->assignRole($role);
        Sanctum::actingAs($user);

        return $user;
    }

    /*
    |--------------------------------------------------------------------------
    | createCycle() — Initial Status Guard
    |--------------------------------------------------------------------------
    */

    public function test_create_cycle_defaults_to_draft_status(): void
    {
        $hr = $this->actingAsHr();

        $payload = [
            'name' => 'New Cycle',
            'cycle_type' => 'annual',
            'start_date' => '2026-06-01',
            'end_date' => '2026-12-31',
            'review_period_start' => '2026-01-01',
            'review_period_end' => '2026-05-31',
            'created_by' => $hr->id,
        ];

        $response = $this->postJson('/api/v1/performance/cycles', $payload);

        $response->assertCreated();

        $this->assertDatabaseHas('performance_review_cycles', [
            'name' => 'New Cycle',
            'status' => 'draft',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | updateCycle() — Status Transition Guard
    |--------------------------------------------------------------------------
    */

    public function test_update_cycle_allows_valid_draft_to_active_transition(): void
    {
        $hr = $this->actingAsHr();

        $cycle = PerformanceReviewCycle::factory()->draft()->create([
            'created_by' => $hr->id,
        ]);

        $response = $this->putJson("/api/v1/performance/cycles/{$cycle->id}", [
            'status' => 'active',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('performance_review_cycles', [
            'id' => $cycle->id,
            'status' => 'active',
        ]);
    }

    public function test_update_cycle_allows_valid_active_to_completed_transition(): void
    {
        $hr = $this->actingAsHr();

        $cycle = PerformanceReviewCycle::factory()->active()->create([
            'created_by' => $hr->id,
        ]);

        $response = $this->putJson("/api/v1/performance/cycles/{$cycle->id}", [
            'status' => 'completed',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('performance_review_cycles', [
            'id' => $cycle->id,
            'status' => 'completed',
        ]);
    }

    public function test_update_cycle_allows_cancellation_from_any_status(): void
    {
        $hr = $this->actingAsHr();

        // Cancel from draft
        $draftCycle = PerformanceReviewCycle::factory()->draft()->create([
            'created_by' => $hr->id,
        ]);
        $response = $this->putJson("/api/v1/performance/cycles/{$draftCycle->id}", [
            'status' => 'cancelled',
        ]);
        $response->assertOk();

        // Cancel from active
        $activeCycle = PerformanceReviewCycle::factory()->active()->create([
            'created_by' => $hr->id,
        ]);
        $response = $this->putJson("/api/v1/performance/cycles/{$activeCycle->id}", [
            'status' => 'cancelled',
        ]);
        $response->assertOk();
    }

    public function test_update_cycle_rejects_invalid_draft_to_completed_transition(): void
    {
        $hr = $this->actingAsHr();

        $cycle = PerformanceReviewCycle::factory()->draft()->create([
            'created_by' => $hr->id,
        ]);

        $response = $this->putJson("/api/v1/performance/cycles/{$cycle->id}", [
            'status' => 'completed',
        ]);

        $response->assertStatus(422);

        $this->assertDatabaseHas('performance_review_cycles', [
            'id' => $cycle->id,
            'status' => 'draft',
        ]);
    }

    public function test_update_cycle_rejects_invalid_draft_to_archived_transition(): void
    {
        $hr = $this->actingAsHr();

        $cycle = PerformanceReviewCycle::factory()->draft()->create([
            'created_by' => $hr->id,
        ]);

        $response = $this->putJson("/api/v1/performance/cycles/{$cycle->id}", [
            'status' => 'archived',
        ]);

        $response->assertStatus(422);

        $this->assertDatabaseHas('performance_review_cycles', [
            'id' => $cycle->id,
            'status' => 'draft',
        ]);
    }

    public function test_update_cycle_rejects_invalid_completed_to_active_transition(): void
    {
        $hr = $this->actingAsHr();

        $cycle = PerformanceReviewCycle::factory()->completed()->create([
            'created_by' => $hr->id,
        ]);

        $response = $this->putJson("/api/v1/performance/cycles/{$cycle->id}", [
            'status' => 'active',
        ]);

        $response->assertStatus(422);

        $this->assertDatabaseHas('performance_review_cycles', [
            'id' => $cycle->id,
            'status' => 'completed',
        ]);
    }

    public function test_update_cycle_allows_non_status_field_changes_without_transition(): void
    {
        $hr = $this->actingAsHr();

        $cycle = PerformanceReviewCycle::factory()->active()->create([
            'created_by' => $hr->id,
        ]);

        $response = $this->putJson("/api/v1/performance/cycles/{$cycle->id}", [
            'name' => 'Updated Cycle Name',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('performance_review_cycles', [
            'id' => $cycle->id,
            'name' => 'Updated Cycle Name',
            'status' => 'active',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | deleteCycle() — Reviews Exist Guard
    |--------------------------------------------------------------------------
    */

    public function test_delete_cycle_succeeds_when_no_reviews_exist(): void
    {
        $hr = $this->actingAsHr();

        $cycle = PerformanceReviewCycle::factory()->draft()->create([
            'created_by' => $hr->id,
        ]);

        $response = $this->deleteJson("/api/v1/performance/cycles/{$cycle->id}");

        $response->assertOk();

        $this->assertDatabaseMissing('performance_review_cycles', [
            'id' => $cycle->id,
        ]);
    }

    public function test_delete_cycle_fails_when_reviews_exist(): void
    {
        $hr = $this->actingAsHr();

        $cycle = PerformanceReviewCycle::factory()->active()->create([
            'created_by' => $hr->id,
        ]);

        // Create staff member profiles for the review
        $employee = StaffMemberProfile::factory()->create();
        $reviewer = StaffMemberProfile::factory()->create();

        // Create a review associated with this cycle
        PerformanceReview::create([
            'cycle_id' => $cycle->id,
            'staff_member_id' => $employee->id,
            'reviewer_id' => $reviewer->id,
            'status' => 'pending_self',
        ]);

        $response = $this->deleteJson("/api/v1/performance/cycles/{$cycle->id}");

        $response->assertStatus(422);

        // Cycle should still exist
        $this->assertDatabaseHas('performance_review_cycles', [
            'id' => $cycle->id,
        ]);
    }

    public function test_delete_cycle_error_message_includes_review_count(): void
    {
        $hr = $this->actingAsHr();

        $cycle = PerformanceReviewCycle::factory()->active()->create([
            'created_by' => $hr->id,
        ]);

        // Create 3 reviews with different staff members
        for ($i = 0; $i < 3; $i++) {
            $employee = StaffMemberProfile::factory()->create();
            $reviewer = StaffMemberProfile::factory()->create();
            PerformanceReview::create([
                'cycle_id' => $cycle->id,
                'staff_member_id' => $employee->id,
                'reviewer_id' => $reviewer->id,
                'status' => 'pending_self',
            ]);
        }

        $response = $this->deleteJson("/api/v1/performance/cycles/{$cycle->id}");

        $response->assertStatus(422);
        $response->assertJsonPath('message', fn ($msg) => str_contains($msg, '3 associated review(s)'));
    }
}
