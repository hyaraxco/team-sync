<?php

namespace Tests\Feature\Project;

use App\Enums\Department;
use App\Enums\EmploymentType;
use App\Enums\JobStatus;
use App\Enums\ProjectPriority;
use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Enums\TeamStatus;
use App\Enums\WorkLocation;
use App\Models\Project;
use App\Models\StaffMemberProfile;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ProjectMembershipMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    private Project $project;

    private User $financeUser;

    private User $managerUser;

    private User $hrUser;

    private User $staffMember;

    private User $staffOutsider;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('scout.driver', null);

        $this->seed([
            PermissionSeeder::class,
            RoleSeeder::class,
            RolePermissionSeeder::class,
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->team = Team::create([
            'name' => 'Engineering',
            'expected_size' => 5,
            'description' => 'Engineering team',
            'icon' => 'team-icons/activity.png',
            'status' => TeamStatus::ACTIVE->value,
            'department' => Department::DEVELOPMENT->value,
            'responsibilities' => ['development'],
        ]);

        $otherTeam = Team::create([
            'name' => 'Marketing',
            'expected_size' => 3,
            'description' => 'Marketing team',
            'icon' => 'team-icons/activity.png',
            'status' => TeamStatus::ACTIVE->value,
            'department' => Department::DEVELOPMENT->value,
            'responsibilities' => ['marketing'],
        ]);

        // Create project with team assigned
        [$leaderUser, $leaderProfile] = $this->makeUser('staff', 'Leader', 'leader@test.com', $this->team, 'senior');
        $this->project = Project::create([
            'name' => 'Test Project',
            'type' => ProjectType::WEB_DEVELOPMENT->value,
            'priority' => ProjectPriority::HIGH->value,
            'status' => ProjectStatus::ACTIVE->value,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(3)->toDateString(),
            'description' => 'Test project.',
            'project_leader_id' => $leaderProfile->id,
        ]);
        $this->project->teams()->sync([$this->team->id => ['assigned_at' => now()]]);

        // Finance user on the team (member)
        [$this->financeUser] = $this->makeUser('finance', 'Finance', 'finance@test.com', $this->team, 'mid');

        // Manager and HR (bypass membership check)
        [$this->managerUser] = $this->makeUser('manager', 'Manager', 'manager@test.com', $this->team, 'senior');
        [$this->hrUser] = $this->makeUser('hr', 'HR', 'hr@test.com', $this->team, 'mid');

        // Staff member on the team
        [$this->staffMember] = $this->makeUser('staff', 'Staff Member', 'staff@test.com', $this->team, 'junior');

        // Staff outsider on a different team (not a member of this project)
        [$this->staffOutsider] = $this->makeUser('staff', 'Outsider', 'outsider@test.com', $otherTeam, 'mid');
    }

    // ─── Finance membership scoping ──────────────────────────────────────

    public function test_finance_user_blocked_from_non_member_project(): void
    {
        // Create a project that finance is NOT a member of
        $otherTeam = Team::create([
            'name' => 'Design',
            'expected_size' => 3,
            'description' => 'Design team',
            'icon' => 'team-icons/activity.png',
            'status' => TeamStatus::ACTIVE->value,
            'department' => Department::DEVELOPMENT->value,
            'responsibilities' => ['design'],
        ]);

        [$otherLeader, $otherLeaderProfile] = $this->makeUser('staff', 'OtherLeader', 'otherleader@test.com', $otherTeam, 'senior');
        $otherProject = Project::create([
            'name' => 'Other Project',
            'type' => ProjectType::WEB_DEVELOPMENT->value,
            'priority' => ProjectPriority::LOW->value,
            'status' => ProjectStatus::ACTIVE->value,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(2)->toDateString(),
            'description' => 'Project finance is not in.',
            'project_leader_id' => $otherLeaderProfile->id,
        ]);
        $otherProject->teams()->sync([$otherTeam->id => ['assigned_at' => now()]]);

        Sanctum::actingAs($this->financeUser);

        $response = $this->getJson('/api/v1/projects/'.$otherProject->id.'/members');

        $response->assertStatus(403);
    }

    public function test_finance_user_can_access_member_project(): void
    {
        Sanctum::actingAs($this->financeUser);

        $response = $this->getJson('/api/v1/projects/'.$this->project->id.'/members');

        $response->assertOk();
    }

    // ─── Manager/HR bypass ───────────────────────────────────────────────

    public function test_manager_bypasses_membership_check(): void
    {
        Sanctum::actingAs($this->managerUser);

        $response = $this->getJson('/api/v1/projects/'.$this->project->id.'/members');

        $response->assertOk();
    }

    public function test_hr_bypasses_membership_check(): void
    {
        Sanctum::actingAs($this->hrUser);

        $response = $this->getJson('/api/v1/projects/'.$this->project->id.'/members');

        $response->assertOk();
    }

    // ─── Staff membership enforcement ────────────────────────────────────

    public function test_staff_member_can_access_own_project(): void
    {
        Sanctum::actingAs($this->staffMember);

        $response = $this->getJson('/api/v1/projects/'.$this->project->id.'/members');

        $response->assertOk();
    }

    public function test_staff_outsider_blocked_from_non_member_project(): void
    {
        Sanctum::actingAs($this->staffOutsider);

        $response = $this->getJson('/api/v1/projects/'.$this->project->id.'/members');

        $response->assertStatus(403);
    }

    // ─── getProjectMembers includes jobInformation.team_id ───────────────

    public function test_get_project_members_includes_job_info_team_members(): void
    {
        // Create a staff member who is in the team via jobInformation.team_id
        // but NOT in the team_members pivot table
        $user = User::create([
            'name' => 'JobInfo Only',
            'email' => 'jobinfo@test.com',
            'password' => bcrypt('password'),
        ]);
        $user->assignRole('staff');

        $profile = StaffMemberProfile::factory()->forUser($user)->create([
            'seniority_level' => 'junior',
        ]);

        // Only jobInformation.team_id, no TeamMember pivot record
        $profile->jobInformation()->create([
            'staff_member_id' => $profile->id,
            'job_title' => 'Engineer',
            'team_id' => $this->team->id,
            'status' => JobStatus::ACTIVE->value,
            'employment_type' => EmploymentType::FULL_TIME->value,
            'work_location' => WorkLocation::OFFICE->value,
            'start_date' => now()->subYear()->toDateString(),
            'monthly_salary' => 10000000,
        ]);

        Sanctum::actingAs($this->managerUser);

        $response = $this->getJson('/api/v1/projects/'.$this->project->id.'/members');

        $response->assertOk();

        $memberIds = collect($response->json('data'))->pluck('id')->toArray();
        $this->assertContains($profile->id, $memberIds, 'Staff member with jobInformation.team_id should be included in project members');
    }

    private function makeUser(string $role, string $name, string $email, Team $team, string $seniority): array
    {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => bcrypt('password'),
        ]);
        $user->assignRole($role);

        $profile = StaffMemberProfile::factory()->forUser($user)->create([
            'seniority_level' => $seniority,
        ]);

        $profile->jobInformation()->create([
            'staff_member_id' => $profile->id,
            'job_title' => 'Engineer',
            'team_id' => $team->id,
            'status' => JobStatus::ACTIVE->value,
            'employment_type' => EmploymentType::FULL_TIME->value,
            'work_location' => WorkLocation::OFFICE->value,
            'start_date' => now()->subYear()->toDateString(),
            'monthly_salary' => 10000000,
        ]);

        TeamMember::create([
            'team_id' => $team->id,
            'staff_member_id' => $profile->id,
            'joined_at' => now()->subYear(),
        ]);

        return [$user, $profile];
    }
}
