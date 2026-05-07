<?php

namespace Tests\Unit\Policies;

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
use App\Policies\ProjectTaskPolicy;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ProjectTaskPolicyTest extends TestCase
{
    use RefreshDatabase;

    private ProjectTaskPolicy $policy;

    private Team $team;

    private Project $project;

    private User $staffUser;

    private StaffMemberProfile $staffProfile;

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

        $this->policy = new ProjectTaskPolicy;

        $this->team = Team::create([
            'name' => 'Dev Team',
            'expected_size' => 5,
            'description' => 'Dev Team',
            'icon' => 'team-icons/activity.png',
            'status' => TeamStatus::ACTIVE->value,
            'department' => Department::DEVELOPMENT->value,
            'responsibilities' => ['development'],
        ]);

        $managerData = $this->createUserWithProfile('manager', 'Manager', 'manager@test.com');
        $this->managerUser = $managerData['user'];
        $this->managerProfile = $managerData['profile'];

        $staffData = $this->createUserWithProfile('staff', 'Staff', 'staff@test.com');
        $this->staffUser = $staffData['user'];
        $this->staffProfile = $staffData['profile'];

        $this->project = Project::create([
            'name' => 'Test Project',
            'type' => ProjectType::WEB_DEVELOPMENT->value,
            'priority' => ProjectPriority::HIGH->value,
            'status' => ProjectStatus::ACTIVE->value,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(3)->toDateString(),
            'description' => 'Test',
            'project_leader_id' => $this->managerProfile->id,
        ]);
        $this->project->teams()->sync([$this->team->id => ['assigned_at' => now()]]);
    }

    // ─── create ─────────────────────────────────────────────────────────

    public function test_staff_can_create_task_on_own_project(): void
    {
        $response = $this->policy->create($this->staffUser, [
            'project_id' => $this->project->id,
            'status' => 'todo',
        ]);

        $this->assertTrue($response->allowed());
    }

    public function test_staff_cannot_create_task_on_non_member_project(): void
    {
        $otherProject = Project::create([
            'name' => 'Other Project',
            'type' => ProjectType::WEB_DEVELOPMENT->value,
            'priority' => ProjectPriority::MEDIUM->value,
            'status' => ProjectStatus::ACTIVE->value,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(3)->toDateString(),
            'description' => 'Not my project',
            'project_leader_id' => $this->managerProfile->id,
        ]);

        $response = $this->policy->create($this->staffUser, [
            'project_id' => $otherProject->id,
            'status' => 'todo',
        ]);

        $this->assertFalse($response->allowed());
    }

    public function test_staff_cannot_create_with_done_status(): void
    {
        $response = $this->policy->create($this->staffUser, [
            'project_id' => $this->project->id,
            'status' => 'done',
        ]);

        $this->assertFalse($response->allowed());
    }

    public function test_staff_cannot_assign_to_others(): void
    {
        $response = $this->policy->create($this->staffUser, [
            'project_id' => $this->project->id,
            'status' => 'todo',
            'assignee_id' => $this->managerProfile->id,
        ]);

        $this->assertFalse($response->allowed());
    }

    public function test_manager_can_create_with_any_status(): void
    {
        $response = $this->policy->create($this->managerUser, [
            'project_id' => $this->project->id,
            'status' => 'done',
        ]);

        $this->assertTrue($response->allowed());
    }

    // ─── delete ─────────────────────────────────────────────────────────

    public function test_only_manager_can_delete(): void
    {
        $task = $this->createTask();

        $this->assertTrue($this->policy->delete($this->managerUser, $task)->allowed());
        $this->assertFalse($this->policy->delete($this->staffUser, $task)->allowed());
    }

    // ─── collaborate ────────────────────────────────────────────────────

    public function test_staff_cannot_collaborate_on_review_task(): void
    {
        $task = $this->createTask(TaskStatus::REVIEW);

        $response = $this->policy->collaborate($this->staffUser, $task);
        $this->assertFalse($response->allowed());
    }

    public function test_staff_can_collaborate_on_in_progress_task(): void
    {
        $task = $this->createTask(TaskStatus::IN_PROGRESS);

        $response = $this->policy->collaborate($this->staffUser, $task);
        $this->assertTrue($response->allowed());
    }

    public function test_manager_can_collaborate_on_any_status(): void
    {
        $task = $this->createTask(TaskStatus::REVIEW);

        $response = $this->policy->collaborate($this->managerUser, $task);
        $this->assertTrue($response->allowed());
    }

    // ─── update with data (field-level checks) ────────────────────────────

    public function test_staff_cannot_change_task_name(): void
    {
        $task = $this->createTask(TaskStatus::IN_PROGRESS);

        $response = $this->policy->update($this->staffUser, $task, [
            'name' => 'Changed Name',
        ]);
        $this->assertFalse($response->allowed());
    }

    public function test_staff_cannot_reassign_via_update(): void
    {
        $task = $this->createTask(TaskStatus::TODO);

        $response = $this->policy->update($this->staffUser, $task, [
            'assignee_id' => $this->managerProfile->id,
        ]);
        $this->assertFalse($response->allowed());
    }

    public function test_staff_can_transition_todo_to_in_progress_via_update(): void
    {
        $task = $this->createTask(TaskStatus::TODO);

        $response = $this->policy->update($this->staffUser, $task, [
            'status' => 'in_progress',
        ]);
        $this->assertTrue($response->allowed());
    }

    public function test_staff_cannot_transition_todo_to_done_via_update(): void
    {
        $task = $this->createTask(TaskStatus::TODO);

        $response = $this->policy->update($this->staffUser, $task, [
            'status' => 'done',
        ]);
        $this->assertFalse($response->allowed());
    }

    public function test_manager_cannot_reassign_during_review(): void
    {
        $task = $this->createTask(TaskStatus::REVIEW);

        $response = $this->policy->update($this->managerUser, $task, [
            'assignee_id' => $this->managerProfile->id,
        ]);
        $this->assertFalse($response->allowed());
    }

    public function test_reviewer_reject_requires_reason(): void
    {
        $task = $this->createTask(TaskStatus::REVIEW);

        // Without reason
        $response = $this->policy->update($this->managerUser, $task, [
            'status' => 'rejected',
        ]);
        $this->assertFalse($response->allowed());

        // With reason
        $response = $this->policy->update($this->managerUser, $task, [
            'status' => 'rejected',
            'rejected_reason' => 'Needs rework',
        ]);
        $this->assertTrue($response->allowed());
    }

    // ─── reassign ───────────────────────────────────────────────────────

    public function test_staff_cannot_reassign(): void
    {
        $task = $this->createTask();

        $this->assertFalse($this->policy->reassign($this->staffUser, $task)->allowed());
    }

    public function test_manager_can_reassign(): void
    {
        $task = $this->createTask();

        $this->assertTrue($this->policy->reassign($this->managerUser, $task)->allowed());
    }

    // ─── transitionStatus ───────────────────────────────────────────────

    public function test_staff_can_move_todo_to_in_progress(): void
    {
        $task = $this->createTask(TaskStatus::TODO);

        $response = $this->policy->transitionStatus($this->staffUser, $task, 'todo', 'in_progress');
        $this->assertTrue($response->allowed());
    }

    public function test_staff_cannot_move_todo_to_done(): void
    {
        $task = $this->createTask(TaskStatus::TODO);

        $response = $this->policy->transitionStatus($this->staffUser, $task, 'todo', 'done');
        $this->assertFalse($response->allowed());
    }

    public function test_reviewer_can_move_review_to_done(): void
    {
        $task = $this->createTask(TaskStatus::REVIEW);

        $response = $this->policy->transitionStatus($this->managerUser, $task, 'review', 'done');
        $this->assertTrue($response->allowed());
    }

    // ─── Helpers ────────────────────────────────────────────────────────

    private function createTask(TaskStatus $status = TaskStatus::TODO): ProjectTask
    {
        return ProjectTask::create([
            'project_id' => $this->project->id,
            'name' => 'Test Task',
            'assignee_id' => $this->staffProfile->id,
            'priority' => TaskPriority::MEDIUM->value,
            'status' => $status->value,
        ]);
    }

    private function createUserWithProfile(string $role, string $name, string $email): array
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
            'team_id' => $this->team->id,
            'status' => JobStatus::ACTIVE->value,
            'employment_type' => EmploymentType::FULL_TIME->value,
            'work_location' => WorkLocation::OFFICE->value,
            'start_date' => now()->subYear()->toDateString(),
            'monthly_salary' => 10000000,
        ]);

        TeamMember::create([
            'team_id' => $this->team->id,
            'staff_member_id' => $profile->id,
            'joined_at' => now()->subYear(),
        ]);

        return ['user' => $user, 'profile' => $profile];
    }
}
