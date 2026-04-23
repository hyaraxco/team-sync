<?php

namespace Tests\Feature\Performance;

use App\Models\JobInformation;
use App\Models\PerformanceReviewCycle;
use App\Models\ReviewerRule;
use App\Models\StaffMemberProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class GenerateReviewsFeatureTest extends TestCase
{
    use RefreshDatabase;

    private User $hrAdmin;
    private StaffMemberProfile $hrProfile;
    private StaffMemberProfile $manager;
    private StaffMemberProfile $staff;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup Roles
        Role::firstOrCreate(['name' => 'hr', 'guard_name' => 'sanctum']);
        Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'sanctum']);
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'sanctum']);

        // Setup Permissions
        Permission::firstOrCreate(['name' => 'review-cycle-manage', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'review-assign-reviewer', 'guard_name' => 'sanctum']);

        // Assign Permissions to HR role
        $hrRole = Role::findByName('hr');
        $hrRole->givePermissionTo(['review-cycle-manage', 'review-assign-reviewer']);

        // Setup HR Admin
        $this->hrAdmin = User::factory()->create();
        $this->hrAdmin->assignRole('hr');
        $this->hrProfile = StaffMemberProfile::factory()->create(['user_id' => $this->hrAdmin->id]);
        JobInformation::factory()->create(['staff_member_id' => $this->hrProfile->id, 'status' => 'active']);

        // Setup Manager
        $managerUser = User::factory()->create();
        $managerUser->assignRole('manager');
        $this->manager = StaffMemberProfile::factory()->create(['user_id' => $managerUser->id]);
        JobInformation::factory()->create(['staff_member_id' => $this->manager->id, 'status' => 'active']);

        // Setup Staff
        $staffUser = User::factory()->create();
        $staffUser->assignRole('staff');
        $this->staff = StaffMemberProfile::factory()->create(['user_id' => $staffUser->id]);
        JobInformation::factory()->create(['staff_member_id' => $this->staff->id, 'status' => 'active']);

        // Setup Rules
        ReviewerRule::create([
            'reviewee_role' => 'staff',
            'reviewer_role' => 'manager',
            'priority' => 1,
            'is_active' => true,
        ]);
        ReviewerRule::create([
            'reviewee_role' => 'manager',
            'reviewer_role' => 'hr',
            'priority' => 1,
            'is_active' => true,
        ]);
    }

    public function test_hr_can_auto_generate_reviews()
    {
        $cycle = PerformanceReviewCycle::factory()->active()->create();

        $response = $this->actingAs($this->hrAdmin)
            ->postJson("/api/v1/performance/cycles/{$cycle->id}/generate-reviews");

        $response->assertStatus(200);
        $response->assertJsonPath('data.generated_count', 3); // HR, Manager, Staff

        // Verify reviews were created with correct reviewers
        $this->assertDatabaseHas('performance_reviews', [
            'cycle_id' => $cycle->id,
            'staff_member_id' => $this->staff->id,
            'reviewer_id' => $this->manager->id,
            'status' => 'pending_self',
        ]);

        $this->assertDatabaseHas('performance_reviews', [
            'cycle_id' => $cycle->id,
            'staff_member_id' => $this->manager->id,
            'reviewer_id' => $this->hrProfile->id,
        ]);

        // HR might not have a rule or falls back to null, checking that a review exists
        $this->assertDatabaseHas('performance_reviews', [
            'cycle_id' => $cycle->id,
            'staff_member_id' => $this->hrProfile->id,
        ]);
    }

    public function test_hr_can_manually_assign_reviewer()
    {
        $cycle = PerformanceReviewCycle::factory()->active()->create();

        // Create a review manually or via generation
        $review = \App\Models\PerformanceReview::create([
            'cycle_id' => $cycle->id,
            'staff_member_id' => $this->staff->id,
            'reviewer_id' => null, // no reviewer
            'status' => 'pending_self',
        ]);

        $response = $this->actingAs($this->hrAdmin)
            ->putJson("/api/v1/performance/reviews/{$review->id}/assign-reviewer", [
                'reviewer_id' => $this->manager->id,
            ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('performance_reviews', [
            'id' => $review->id,
            'reviewer_id' => $this->manager->id,
        ]);
    }
}
