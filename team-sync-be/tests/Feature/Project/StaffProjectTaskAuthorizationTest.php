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

class StaffProjectTaskAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private Team $teamA;

    private Team $teamB;

    private Project $projectA;

    private Project $projectB;

    private User $staffUserA;

    private StaffMemberProfile $staffProfileA;

    private User $staffUserB;

    private StaffMemberProfile $staffProfileB;

    private User $managerUser;

    private StaffMemberProfile $managerProfile;

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

        // Create teams
        $this->teamA = Team::create([
            'name' => 'Team Alpha',
            'expected_size' => 5,
            'description' => 'Team Alpha',
            'icon' => 'team-icons/activity.png',
            'status' => TeamStatus::ACTIVE->value,
            'department' => Department::DEVELOPMENT->value,
            'responsibilities' => ['development'],
        ]);

        $this->teamB = Team::create([
            'name' => 'Team Beta',
            'expected_size' => 5,
            'description' => 'Team Beta',
            'icon' => 'team-icons/activity.png',
            'status' => TeamStatus::ACTIVE->value,
            'department' => Department::DEVELOPMENT->value,
            'responsibilities' => ['testing'],
        ]);

        // Create manager
        $managerData = $this->createUserWithProfile('manager', 'Manager', 'manager@test.com', $this->teamA);
        $this->managerUser = $managerData['user'];
        $this->managerProfile = $managerData['profile'];

        // Create staff A (member of teamA)
        $staffDataA = $this->createUserWithProfile('staff', 'Staff A', 'staffa@test.com', $this->teamA);
        $this->staffUserA = $staffDataA['user'];
        $this->staffProfileA = $staffDataA['profile'];

        // Create staff B (member of teamB)
        $staffDataB = $this->createUserWithProfile('staff', 'Staff B', 'staffb@test.com', $this->teamB);
        $this->staffUserB = $staffDataB['user'];
        $this->staffProfileB = $staffDataB['profile'];

        // Create projects
        $this->projectA = Project::create([
            'name' => 'Project Alpha',
            'type' => ProjectType::WEB_DEVELOPMENT->value,
            'priority' => ProjectPriority::HIGH->value,
            'status' => ProjectStatus::ACTIVE->value,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(3)->toDateString(),
            'description' => 'Project for Team Alpha',
            'project_leader_id' => $this->managerProfile->id,
        ]);
        $this->projectA->teams()->sync([$this->teamA->id => ['assigned_at' => now()]]);

        $this->projectB = Project::create([
            'name' => 'Project Beta (Confidential)',
            'type' => ProjectType::MOBILE_APP->value,
            'priority' => ProjectPriority::MEDIUM->value,
            'status' => ProjectStatus::ACTIVE->value,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(6)->toDateString(),
            'description' => 'Confidential project for Team Beta',
            'project_leader_id' => $this->managerProfile->id,
        ]);
        $this->projectB->teams()->sync([$this->teamB->id => ['assigned_at' => now()]]);
    }

    // ─── Finding #1: Staff cannot create task on non-member project ─────

    public function test_staff_cannot_create_task_on_non_member_project(): void
    {
        Sanctum::actingAs($this->staffUserA);

        $response = $this->postJson('/api/v1/project-tasks', [
            'project_id' => $this->projectB->id,
            'name' => 'Sneaky Task',
            'priority' => TaskPriority::MEDIUM->value,
            'status' => TaskStatus::TODO->value,
        ]);

        $response->assertForbidden();
    }

    public function test_staff_can_create_task_on_own_project(): void
    {
        Sanctum::actingAs($this->staffUserA);

        $response = $this->postJson('/api/v1/project-tasks', [
            'project_id' => $this->projectA->id,
            'name' => 'Legitimate Task',
            'priority' => TaskPriority::MEDIUM->value,
            'status' => TaskStatus::TODO->value,
        ]);

        $response->assertCreated();
    }

    // ─── Finding #1: Staff cannot assign task to others ─────────────────

    public function test_staff_cannot_assign_task_to_other_employee(): void
    {
        Sanctum::actingAs($this->staffUserA);

        $response = $this->postJson('/api/v1/project-tasks', [
            'project_id' => $this->projectA->id,
            'name' => 'Task for someone else',
            'assignee_id' => $this->staffProfileB->id,
            'priority' => TaskPriority::MEDIUM->value,
            'status' => TaskStatus::TODO->value,
        ]);

        $response->assertForbidden();
    }

    public function test_staff_can_assign_task_to_self(): void
    {
        Sanctum::actingAs($this->staffUserA);

        $response = $this->postJson('/api/v1/project-tasks', [
            'project_id' => $this->projectA->id,
            'name' => 'My own task',
            'assignee_id' => $this->staffProfileA->id,
            'priority' => TaskPriority::MEDIUM->value,
            'status' => TaskStatus::TODO->value,
        ]);

        $response->assertCreated();
    }

    // ─── Finding #2: Staff cannot create task with status done ──────────

    public function test_staff_cannot_create_task_with_done_status(): void
    {
        Sanctum::actingAs($this->staffUserA);

        $response = $this->postJson('/api/v1/project-tasks', [
            'project_id' => $this->projectA->id,
            'name' => 'Already done task',
            'priority' => TaskPriority::MEDIUM->value,
            'status' => TaskStatus::DONE->value,
        ]);

        $response->assertForbidden();
    }

    public function test_staff_cannot_create_task_with_review_status(): void
    {
        Sanctum::actingAs($this->staffUserA);

        $response = $this->postJson('/api/v1/project-tasks', [
            'project_id' => $this->projectA->id,
            'name' => 'Skip to review',
            'priority' => TaskPriority::MEDIUM->value,
            'status' => TaskStatus::REVIEW->value,
        ]);

        $response->assertForbidden();
    }

    // ─── Finding #3: Staff cannot access project statistics ─────────────

    public function test_staff_cannot_access_project_statistics(): void
    {
        Sanctum::actingAs($this->staffUserA);

        $this->getJson('/api/v1/projects/statistics')
            ->assertForbidden();
    }

    public function test_staff_cannot_access_squad_summary(): void
    {
        Sanctum::actingAs($this->staffUserA);

        $this->getJson('/api/v1/projects/'.$this->projectA->id.'/squad-summary')
            ->assertForbidden();
    }

    public function test_manager_can_access_project_statistics(): void
    {
        // Note: getStatistics uses MySQL YEAR() function which doesn't work in SQLite.
        // This test verifies the permission middleware allows manager through.
        // The 500 is from SQLite incompatibility, not authorization.
        Sanctum::actingAs($this->managerUser);

        $response = $this->getJson('/api/v1/projects/statistics');

        // Manager has project-statistic permission, so should NOT get 403
        $this->assertNotEquals(403, $response->getStatusCode());
    }

    // ─── Finding #4: Staff without profile sees empty projects ──────────

    public function test_staff_without_profile_sees_no_projects(): void
    {
        $orphanUser = User::create([
            'name' => 'Orphan Staff',
            'email' => 'orphan@test.com',
            'password' => bcrypt('password'),
        ]);
        $orphanUser->assignRole('staff');

        Sanctum::actingAs($orphanUser);

        $response = $this->getJson('/api/v1/projects');
        $response->assertOk();

        $data = $response->json('data');
        $this->assertEmpty($data);
    }

    // ─── Finding #5: Staff cannot comment on task in review status ──────

    public function test_staff_cannot_comment_on_task_in_review_status(): void
    {
        $task = ProjectTask::create([
            'project_id' => $this->projectA->id,
            'name' => 'Task under review',
            'assignee_id' => $this->staffProfileA->id,
            'priority' => TaskPriority::MEDIUM->value,
            'status' => TaskStatus::REVIEW->value,
        ]);

        Sanctum::actingAs($this->staffUserA);

        $response = $this->postJson('/api/v1/project-tasks/'.$task->id.'/comments', [
            'comment' => 'Trying to add comment during review',
        ]);

        $response->assertForbidden();
    }

    public function test_staff_can_comment_on_task_in_progress(): void
    {
        $task = ProjectTask::create([
            'project_id' => $this->projectA->id,
            'name' => 'Task in progress',
            'assignee_id' => $this->staffProfileA->id,
            'priority' => TaskPriority::MEDIUM->value,
            'status' => TaskStatus::IN_PROGRESS->value,
        ]);

        Sanctum::actingAs($this->staffUserA);

        $response = $this->postJson('/api/v1/project-tasks/'.$task->id.'/comments', [
            'comment' => 'Progress update',
        ]);

        $response->assertCreated();
    }

    // ─── Manager can still do everything ────────────────────────────────

    public function test_manager_can_create_task_with_any_status(): void
    {
        Sanctum::actingAs($this->managerUser);

        $response = $this->postJson('/api/v1/project-tasks', [
            'project_id' => $this->projectA->id,
            'name' => 'Manager task done',
            'priority' => TaskPriority::MEDIUM->value,
            'status' => TaskStatus::DONE->value,
        ]);

        $response->assertCreated();
    }

    public function test_manager_can_assign_task_to_project_member(): void
    {
        Sanctum::actingAs($this->managerUser);

        $response = $this->postJson('/api/v1/project-tasks', [
            'project_id' => $this->projectA->id,
            'name' => 'Assigned to staff A (project member)',
            'assignee_id' => $this->staffProfileA->id,
            'priority' => TaskPriority::MEDIUM->value,
            'status' => TaskStatus::TODO->value,
        ]);

        $response->assertCreated();
    }

    public function test_manager_cannot_assign_task_to_non_project_member(): void
    {
        Sanctum::actingAs($this->managerUser);

        $response = $this->postJson('/api/v1/project-tasks', [
            'project_id' => $this->projectA->id,
            'name' => 'Assigned to staff B (NOT project member)',
            'assignee_id' => $this->staffProfileB->id,
            'priority' => TaskPriority::MEDIUM->value,
            'status' => TaskStatus::TODO->value,
        ]);

        $response->assertForbidden();
    }

    // ─── Helpers ────────────────────────────────────────────────────────

    private function createUserWithProfile(string $role, string $name, string $email, Team $team): array
    {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => bcrypt('password'),
        ]);
        $user->assignRole($role);

        $profile = StaffMemberProfile::factory()->forUser($user)->create();

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

        return ['user' => $user, 'profile' => $profile];
    }
}
