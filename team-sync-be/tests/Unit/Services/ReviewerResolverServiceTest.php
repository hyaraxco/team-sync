<?php

namespace Tests\Unit\Services;

use App\Models\ReviewerRule;
use App\Models\StaffMemberProfile;
use App\Models\Team;
use App\Models\User;
use App\Services\Performance\ReviewerResolverService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReviewerResolverServiceTest extends TestCase
{
    use RefreshDatabase;

    private ReviewerResolverService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ReviewerResolverService;
        // Create required roles
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'sanctum']);
        Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'sanctum']);
        Role::firstOrCreate(['name' => 'hr', 'guard_name' => 'sanctum']);
    }

    private function createEmployeeWithRole(string $roleName)
    {
        $user = User::factory()->create();
        $user->assignRole($roleName);

        return StaffMemberProfile::factory()->create([
            'user_id' => $user->id,
        ]);
    }

    public function test_resolves_to_manager_in_same_team_if_rule_exists()
    {
        // Rule: Staff is reviewed by Manager
        ReviewerRule::create([
            'reviewee_role' => 'staff',
            'reviewer_role' => 'manager',
            'priority' => 1,
            'is_active' => true,
        ]);

        $team = Team::factory()->create();

        $staff = $this->createEmployeeWithRole('staff');
        $staff->teams()->attach($team->id);

        $manager = $this->createEmployeeWithRole('manager');
        $manager->teams()->attach($team->id);

        $resolved = $this->service->resolve($staff);

        $this->assertNotNull($resolved);
        $this->assertEquals($manager->id, $resolved->id);
    }

    public function test_resolves_to_any_manager_if_no_same_team_manager()
    {
        ReviewerRule::create([
            'reviewee_role' => 'staff',
            'reviewer_role' => 'manager',
            'priority' => 1,
            'is_active' => true,
        ]);

        $staff = $this->createEmployeeWithRole('staff');
        $manager = $this->createEmployeeWithRole('manager');

        // Not in the same team, but should fallback to any manager
        $resolved = $this->service->resolve($staff);

        $this->assertNotNull($resolved);
        $this->assertEquals($manager->id, $resolved->id);
    }

    public function test_returns_null_if_no_matching_reviewer_role_exists()
    {
        ReviewerRule::create([
            'reviewee_role' => 'staff',
            'reviewer_role' => 'manager',
            'priority' => 1,
            'is_active' => true,
        ]);

        $staff = $this->createEmployeeWithRole('staff');
        // No manager created

        $resolved = $this->service->resolve($staff);

        $this->assertNull($resolved);
    }

    public function test_respects_rule_priority()
    {
        // Rule 1: Staff -> Manager (Priority 1)
        ReviewerRule::create([
            'reviewee_role' => 'staff',
            'reviewer_role' => 'manager',
            'priority' => 1,
            'is_active' => true,
        ]);

        // Rule 2: Staff -> HR (Priority 2)
        ReviewerRule::create([
            'reviewee_role' => 'staff',
            'reviewer_role' => 'hr',
            'priority' => 2,
            'is_active' => true,
        ]);

        $staff = $this->createEmployeeWithRole('staff');
        $hr = $this->createEmployeeWithRole('hr');

        // Since no manager exists, Priority 1 fails, so it falls back to Priority 2 (HR)
        $resolved = $this->service->resolve($staff);

        $this->assertNotNull($resolved);
        $this->assertEquals($hr->id, $resolved->id);
    }

    public function test_resolve_many_returns_assignments_array()
    {
        ReviewerRule::create([
            'reviewee_role' => 'staff',
            'reviewer_role' => 'manager',
            'priority' => 1,
            'is_active' => true,
        ]);

        $staff1 = $this->createEmployeeWithRole('staff');
        $staff2 = $this->createEmployeeWithRole('staff');
        $manager = $this->createEmployeeWithRole('manager');

        $staffCollection = collect([$staff1, $staff2]);

        $assignments = $this->service->resolveMany($staffCollection);

        $this->assertCount(2, $assignments);
        $this->assertEquals($manager->id, $assignments[$staff1->id]);
        $this->assertEquals($manager->id, $assignments[$staff2->id]);
    }
}
