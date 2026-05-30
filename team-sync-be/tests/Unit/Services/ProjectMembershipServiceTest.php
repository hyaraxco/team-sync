<?php

namespace Tests\Unit\Services;

use App\Models\Project;
use App\Models\StaffMemberProfile;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Services\ProjectMembershipService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ProjectMembershipServiceTest extends TestCase
{
    use RefreshDatabase;

    private ProjectMembershipService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->service = app(ProjectMembershipService::class);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // isMember
    // ─────────────────────────────────────────────────────────────────────────

    public function test_is_member_returns_false_when_project_null(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($this->service->isMember($user, null));
    }

    public function test_is_member_returns_false_when_user_has_no_profile(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create();

        $this->assertFalse($this->service->isMember($user, $project));
    }

    public function test_is_member_returns_true_when_user_is_project_leader(): void
    {
        $user = User::factory()->create();
        $profile = StaffMemberProfile::factory()->create(['user_id' => $user->id]);
        $project = Project::factory()->create(['project_leader_id' => $profile->id]);

        $this->assertTrue($this->service->isMember($user, $project));
    }

    public function test_is_member_returns_true_when_user_team_assigned_to_project(): void
    {
        $user = User::factory()->create();
        $profile = StaffMemberProfile::factory()->create(['user_id' => $user->id]);
        $team = Team::factory()->create();
        TeamMember::factory()->create([
            'team_id' => $team->id,
            'staff_member_id' => $profile->id,
        ]);
        $project = Project::factory()->create();
        $project->teams()->attach($team->id);

        $this->assertTrue($this->service->isMember($user, $project));
    }

    public function test_is_member_returns_false_when_user_not_in_project_team(): void
    {
        $user = User::factory()->create();
        $profile = StaffMemberProfile::factory()->create(['user_id' => $user->id]);
        $project = Project::factory()->create();

        $this->assertFalse($this->service->isMember($user, $project));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // isMemberById
    // ─────────────────────────────────────────────────────────────────────────

    public function test_is_member_by_id_returns_false_when_project_null(): void
    {
        $this->assertFalse($this->service->isMemberById(1, null));
    }

    public function test_is_member_by_id_returns_true_when_profile_is_leader(): void
    {
        $profile = StaffMemberProfile::factory()->create();
        $project = Project::factory()->create(['project_leader_id' => $profile->id]);

        $this->assertTrue($this->service->isMemberById($profile->id, $project));
    }

    public function test_is_member_by_id_returns_true_when_team_assigned(): void
    {
        $profile = StaffMemberProfile::factory()->create();
        $team = Team::factory()->create();
        TeamMember::factory()->create([
            'team_id' => $team->id,
            'staff_member_id' => $profile->id,
        ]);
        $project = Project::factory()->create();
        $project->teams()->attach($team->id);

        $this->assertTrue($this->service->isMemberById($profile->id, $project));
    }

    public function test_is_member_by_id_returns_false_when_not_member(): void
    {
        $profile = StaffMemberProfile::factory()->create();
        $project = Project::factory()->create();

        $this->assertFalse($this->service->isMemberById($profile->id, $project));
    }
}
