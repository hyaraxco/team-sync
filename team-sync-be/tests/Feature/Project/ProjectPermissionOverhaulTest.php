<?php

namespace Tests\Feature\Project;

use App\Enums\Department;
use App\Enums\EmploymentType;
use App\Enums\JobStatus;
use App\Enums\ProjectPriority;
use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TeamStatus;
use App\Enums\WorkLocation;
use App\Models\Project;
use App\Models\ProjectTask;
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

class ProjectPermissionOverhaulTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    private Team $otherTeam;

    private Project $project;

    private User $managerUser;

    private StaffMemberProfile $managerProfile;

    private User $hrUser;

    private User $financeUser;

    private StaffMemberProfile $financeProfile;

    private User $staffUser;

    private StaffMemberProfile $staffProfile;

    private User $plUser;

    private StaffMemberProfile $plProfile;

    private User $outsiderUser;

    private StaffMemberProfile $outsiderProfile;

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

        $this->otherTeam = Team::create([
            'name' => 'Operations',
            'expected_size' => 4,
            'description' => 'Ops team',
            'icon' => 'team-icons/activity.png',
            'status' => TeamStatus::ACTIVE->value,
            'department' => Department::DEVELOPMENT->value,
            'responsibilities' => ['ops'],
        ]);

        [$this->managerUser, $this->managerProfile] = $this->makeUser('manager', 'Manager', 'manager@test.com', $this->team, 'senior');
        [$this->hrUser] = $this->makeUser('hr', 'HR', 'hr@test.com', $this->team, 'mid');
        [$this->financeUser, $this->financeProfile] = $this->makeUser('finance', 'Finance', 'finance@test.com', $this->team, 'mid');
        [$this->plUser, $this->plProfile] = $this->makeUser('staff', 'PL Staff', 'pl@test.com', $this->team, 'senior');
        [$this->staffUser, $this->staffProfile] = $this->makeUser('staff', 'Staff A', 'staff@test.com', $this->team, 'junior');
        [$this->outsiderUser, $this->outsiderProfile] = $this->makeUser('staff', 'Outsider', 'outsider@test.com', $this->otherTeam, 'mid');

        // Project led by plProfile, with team A assigned. Members: pl, staff, manager, finance.
        $this->project = Project::create([
            'name' => 'Permission Overhaul Project',
            'type' => ProjectType::WEB_DEVELOPMENT->value,
            'priority' => ProjectPriority::HIGH->value,
            'status' => ProjectStatus::ACTIVE->value,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(3)->toDateString(),
            'description' => 'Test project for permission overhaul.',
            'project_leader_id' => $this->plProfile->id,
        ]);
        $this->project->teams()->sync([$this->team->id => ['assigned_at' => now()]]);
    }

    // ─── ProjectResource flags ───────────────────────────────────────────

    public function test_project_resource_marks_project_leader(): void
    {
        Sanctum::actingAs($this->plUser);

        $response = $this->getJson('/api/v1/projects/'.$this->project->id);

        $response->assertOk()
            ->assertJsonPath('data.is_project_leader', true)
            ->assertJsonPath('data.can_create_task', true);
    }

    public function test_project_resource_flags_for_manager(): void
    {
        Sanctum::actingAs($this->managerUser);

        $response = $this->getJson('/api/v1/projects/'.$this->project->id);

        $response->assertOk()
            ->assertJsonPath('data.is_project_leader', false)
            ->assertJsonPath('data.can_create_task', true);
    }

    public function test_project_resource_flags_for_regular_staff(): void
    {
        Sanctum::actingAs($this->staffUser);

        $response = $this->getJson('/api/v1/projects/'.$this->project->id);

        $response->assertOk()
            ->assertJsonPath('data.is_project_leader', false)
            ->assertJsonPath('data.can_create_task', false);
    }

    // ─── Project leader can create tasks; regular staff cannot ───────────

    public function test_project_leader_can_create_task_in_own_project(): void
    {
        Sanctum::actingAs($this->plUser);

        $response = $this->postJson('/api/v1/project-tasks', [
            'project_id' => $this->project->id,
            'name' => 'PL-created task',
            'assignee_id' => $this->staffProfile->id,
            'priority' => TaskPriority::MEDIUM->value,
            'status' => TaskStatus::TODO->value,
        ]);

        $response->assertCreated();
    }

    public function test_project_leader_cannot_create_task_in_other_project(): void
    {
        $other = Project::create([
            'name' => 'Other Project',
            'type' => ProjectType::WEB_DEVELOPMENT->value,
            'priority' => ProjectPriority::LOW->value,
            'status' => ProjectStatus::ACTIVE->value,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(2)->toDateString(),
            'description' => 'Project led by manager.',
            'project_leader_id' => $this->managerProfile->id,
        ]);
        $other->teams()->sync([$this->otherTeam->id => ['assigned_at' => now()]]);

        Sanctum::actingAs($this->plUser);

        $response = $this->postJson('/api/v1/project-tasks', [
            'project_id' => $other->id,
            'name' => 'PL outsider attempt',
            'priority' => TaskPriority::LOW->value,
            'status' => TaskStatus::TODO->value,
        ]);

        $response->assertForbidden();
    }

    public function test_hr_cannot_create_task(): void
    {
        Sanctum::actingAs($this->hrUser);

        $response = $this->postJson('/api/v1/project-tasks', [
            'project_id' => $this->project->id,
            'name' => 'HR attempt',
            'priority' => TaskPriority::LOW->value,
            'status' => TaskStatus::TODO->value,
        ]);

        $response->assertForbidden();
    }

    public function test_finance_cannot_create_task(): void
    {
        Sanctum::actingAs($this->financeUser);

        $response = $this->postJson('/api/v1/project-tasks', [
            'project_id' => $this->project->id,
            'name' => 'Finance attempt',
            'priority' => TaskPriority::LOW->value,
            'status' => TaskStatus::TODO->value,
        ]);

        $response->assertForbidden();
    }

    // ─── Project members endpoint ────────────────────────────────────────

    public function test_get_project_members_returns_active_team_members(): void
    {
        Sanctum::actingAs($this->managerUser);

        $response = $this->getJson('/api/v1/projects/'.$this->project->id.'/members');

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($this->plProfile->id, $ids);
        $this->assertContains($this->staffProfile->id, $ids);
        $this->assertNotContains($this->outsiderProfile->id, $ids);
    }

    public function test_get_project_members_denies_outsider(): void
    {
        // Outsider is staff in otherTeam, not a member of $this->project.
        Sanctum::actingAs($this->outsiderUser);

        $this->getJson('/api/v1/projects/'.$this->project->id.'/members')
            ->assertForbidden();
    }

    // ─── Eligible leaders endpoint ───────────────────────────────────────

    public function test_get_eligible_leaders_filters_by_seniority(): void
    {
        Sanctum::actingAs($this->managerUser);

        $response = $this->getJson(
            '/api/v1/projects/'.$this->project->id.'/eligible-leaders?seniority_level=senior'
        );

        $response->assertOk();
        $ids = collect($response->json('data.members'))->pluck('id')->all();
        $this->assertContains($this->plProfile->id, $ids);
        $this->assertNotContains($this->staffProfile->id, $ids);
        $this->assertNull($response->json('data.warning'));
    }

    public function test_get_eligible_leaders_falls_back_with_warning(): void
    {
        Sanctum::actingAs($this->managerUser);

        $response = $this->getJson(
            '/api/v1/projects/'.$this->project->id.'/eligible-leaders?seniority_level=principal'
        );

        $response->assertOk();
        $this->assertNotNull($response->json('data.warning'));
        $this->assertNotEmpty($response->json('data.members'));
    }

    public function test_get_eligible_leaders_denies_non_manager(): void
    {
        // HR has read-only access; Spatie middleware requires project-edit which HR lacks
        Sanctum::actingAs($this->hrUser);

        $this->getJson('/api/v1/projects/'.$this->project->id.'/eligible-leaders')
            ->assertForbidden();
    }

    public function test_get_eligible_leaders_denies_staff(): void
    {
        Sanctum::actingAs($this->staffUser);

        $this->getJson('/api/v1/projects/'.$this->project->id.'/eligible-leaders')
            ->assertForbidden();
    }

    // ─── Update project leader endpoint ──────────────────────────────────

    public function test_manager_can_update_project_leader(): void
    {
        Sanctum::actingAs($this->managerUser);

        $response = $this->putJson('/api/v1/projects/'.$this->project->id.'/leader', [
            'project_leader_id' => $this->staffProfile->id,
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('projects', [
            'id' => $this->project->id,
            'project_leader_id' => $this->staffProfile->id,
        ]);
    }

    public function test_manager_cannot_assign_non_member_as_project_leader(): void
    {
        Sanctum::actingAs($this->managerUser);

        $response = $this->putJson('/api/v1/projects/'.$this->project->id.'/leader', [
            'project_leader_id' => $this->outsiderProfile->id,
        ]);

        $response->assertStatus(422);
    }

    public function test_non_manager_cannot_update_project_leader(): void
    {
        Sanctum::actingAs($this->staffUser);

        $this->putJson('/api/v1/projects/'.$this->project->id.'/leader', [
            'project_leader_id' => $this->plProfile->id,
        ])->assertForbidden();
    }

    // ─── Review-feedback gap fixes ───────────────────────────────────────

    public function test_manager_cannot_assign_resigned_staff_as_project_leader(): void
    {
        // Resign the candidate
        $this->staffProfile->jobInformation()->update(['status' => JobStatus::RESIGNED->value]);

        Sanctum::actingAs($this->managerUser);

        $response = $this->putJson('/api/v1/projects/'.$this->project->id.'/leader', [
            'project_leader_id' => $this->staffProfile->id,
        ]);

        $response->assertStatus(422);
    }

    public function test_get_project_members_excludes_staff_who_left_team(): void
    {
        // Mark staffProfile as having left the team
        TeamMember::where('staff_member_id', $this->staffProfile->id)
            ->update(['left_at' => now()->subDay()]);

        Sanctum::actingAs($this->managerUser);

        $response = $this->getJson('/api/v1/projects/'.$this->project->id.'/members');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertNotContains(
            $this->staffProfile->id,
            $ids,
            'Staff who left the team must not appear in project members'
        );
    }

    public function test_get_project_members_includes_project_leader_not_on_any_team(): void
    {
        // Drop PL from team_members and from jobInformation.team_id; PL stays only as project_leader_id.
        TeamMember::where('staff_member_id', $this->plProfile->id)->delete();
        $this->plProfile->jobInformation()->update(['team_id' => $this->otherTeam->id]);

        Sanctum::actingAs($this->managerUser);

        $response = $this->getJson('/api/v1/projects/'.$this->project->id.'/members');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains(
            $this->plProfile->id,
            $ids,
            'Project leader must always appear in members list'
        );
    }

    public function test_user_without_staff_profile_cannot_create_task(): void
    {
        // Create a manager-role user with no profile.
        $orphan = User::create([
            'name' => 'Orphan Manager',
            'email' => 'orphan-manager@test.com',
            'password' => bcrypt('password'),
        ]);
        $orphan->assignRole('manager');

        Sanctum::actingAs($orphan);

        $response = $this->postJson('/api/v1/project-tasks', [
            'project_id' => $this->project->id,
            'name' => 'Orphan attempt',
            'priority' => TaskPriority::LOW->value,
            'status' => TaskStatus::TODO->value,
        ]);

        $response->assertForbidden();
    }

    // ─── HR read-only oversight (policy alignment with seeder) ───────────

    public function test_hr_cannot_update_task(): void
    {
        $task = ProjectTask::create([
            'project_id' => $this->project->id,
            'name' => 'HR update attempt',
            'assignee_id' => $this->staffProfile->id,
            'priority' => TaskPriority::MEDIUM->value,
            'status' => TaskStatus::REVIEW->value,
        ]);

        Sanctum::actingAs($this->hrUser);

        $response = $this->putJson('/api/v1/project-tasks/'.$task->id, [
            'status' => 'done',
        ]);

        $response->assertForbidden();
    }

    public function test_hr_cannot_delete_task(): void
    {
        $task = ProjectTask::create([
            'project_id' => $this->project->id,
            'name' => 'HR delete attempt',
            'assignee_id' => $this->staffProfile->id,
            'priority' => TaskPriority::MEDIUM->value,
            'status' => TaskStatus::TODO->value,
        ]);

        Sanctum::actingAs($this->hrUser);

        $response = $this->deleteJson('/api/v1/project-tasks/'.$task->id);

        $response->assertForbidden();
    }

    // ─── Helpers ─────────────────────────────────────────────────────────

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
