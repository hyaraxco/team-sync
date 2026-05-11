<?php

namespace Tests\Feature\Commands;

use App\Models\PerformanceReview;
use App\Models\PerformanceReviewCycle;
use App\Models\ReviewerRule;
use App\Models\StaffMemberProfile;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class FixReviewerAssignmentsTest extends TestCase
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

    public function test_command_returns_success_when_no_pending_reviews(): void
    {
        $exitCode = Artisan::call('reviews:fix-reviewers');

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('No pending reviews found', Artisan::output());
    }

    public function test_command_fixes_self_assigned_reviewer(): void
    {
        $cycle = $this->createCycle();

        // Create a manager with the manager role
        $managerUser = User::factory()->create();
        $managerUser->assignRole('manager');
        $managerProfile = StaffMemberProfile::factory()->create(['user_id' => $managerUser->id]);

        // Create a staff member with the staff role (self-assigned as reviewer)
        $staffUser = User::factory()->create();
        $staffUser->assignRole('staff');
        $staffProfile = StaffMemberProfile::factory()->create(['user_id' => $staffUser->id]);

        // Create a reviewer rule: staff → manager
        ReviewerRule::create([
            'reviewee_role' => 'staff',
            'reviewer_role' => 'manager',
            'priority' => 1,
            'is_active' => true,
        ]);

        // Create a review where reviewer_id == staff_member_id (self-assignment)
        $review = PerformanceReview::create([
            'cycle_id' => $cycle->id,
            'staff_member_id' => $staffProfile->id,
            'reviewer_id' => $staffProfile->id, // Self-assignment
            'status' => 'pending_self',
        ]);

        $exitCode = Artisan::call('reviews:fix-reviewers');

        $this->assertSame(0, $exitCode);

        $review->refresh();
        $this->assertSame($managerProfile->id, $review->reviewer_id);
    }

    public function test_command_dry_run_does_not_apply_changes(): void
    {
        $cycle = $this->createCycle();

        $managerUser = User::factory()->create();
        $managerUser->assignRole('manager');
        $managerProfile = StaffMemberProfile::factory()->create(['user_id' => $managerUser->id]);

        $staffUser = User::factory()->create();
        $staffUser->assignRole('staff');
        $staffProfile = StaffMemberProfile::factory()->create(['user_id' => $staffUser->id]);

        ReviewerRule::create([
            'reviewee_role' => 'staff',
            'reviewer_role' => 'manager',
            'priority' => 1,
            'is_active' => true,
        ]);

        $review = PerformanceReview::create([
            'cycle_id' => $cycle->id,
            'staff_member_id' => $staffProfile->id,
            'reviewer_id' => $staffProfile->id,
            'status' => 'pending_self',
        ]);

        $exitCode = Artisan::call('reviews:fix-reviewers', ['--dry-run' => true]);

        $this->assertSame(0, $exitCode);

        $review->refresh();
        $this->assertSame($staffProfile->id, $review->reviewer_id); // Unchanged
    }

    public function test_command_skips_reviews_without_staff_member(): void
    {
        $cycle = $this->createCycle();

        // Create a staff member, then soft-delete their profile
        // The command uses with(['staffMember.user']) which won't find soft-deleted records
        $staffUser = User::factory()->create();
        $staffProfile = StaffMemberProfile::factory()->create(['user_id' => $staffUser->id]);
        $profileId = $staffProfile->id;

        // Soft-delete the staff member profile so the command won't find it
        $staffProfile->delete();

        PerformanceReview::create([
            'cycle_id' => $cycle->id,
            'staff_member_id' => $profileId,
            'reviewer_id' => null,
            'status' => 'pending_self',
        ]);

        $exitCode = Artisan::call('reviews:fix-reviewers');

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Skipped: 1', Artisan::output());
    }

    public function test_command_reports_unchanged_when_reviewer_already_correct(): void
    {
        $cycle = $this->createCycle();

        $managerUser = User::factory()->create();
        $managerUser->assignRole('manager');
        $managerProfile = StaffMemberProfile::factory()->create(['user_id' => $managerUser->id]);

        $staffUser = User::factory()->create();
        $staffUser->assignRole('staff');
        $staffProfile = StaffMemberProfile::factory()->create(['user_id' => $staffUser->id]);

        ReviewerRule::create([
            'reviewee_role' => 'staff',
            'reviewer_role' => 'manager',
            'priority' => 1,
            'is_active' => true,
        ]);

        PerformanceReview::create([
            'cycle_id' => $cycle->id,
            'staff_member_id' => $staffProfile->id,
            'reviewer_id' => $managerProfile->id, // Already correct
            'status' => 'pending_manager',
        ]);

        $exitCode = Artisan::call('reviews:fix-reviewers');

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Unchanged: 1', Artisan::output());
        $this->assertStringNotContainsString('Skipped: 1', Artisan::output());
    }

    private function createCycle(): PerformanceReviewCycle
    {
        $creator = User::factory()->create();

        return PerformanceReviewCycle::create([
            'name' => 'Q1 2026 Review',
            'cycle_type' => 'quarterly',
            'start_date' => '2026-01-01',
            'end_date' => '2026-03-31',
            'review_period_start' => '2026-01-01',
            'review_period_end' => '2026-03-31',
            'status' => 'active',
            'created_by' => $creator->id,
        ]);
    }
}
