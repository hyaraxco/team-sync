<?php

namespace Tests\Unit\Policies;

use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\StaffMemberProfile;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Policies\ProjectTaskPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProjectTaskPolicyTest extends TestCase
{
    use RefreshDatabase;

    private ProjectTaskPolicy $policy;

    private User $staffUser;

    private User $managerUser;

    private User $plStaffUser;

    private StaffMemberProfile $staffProfile;

    private StaffMemberProfile $managerProfile;

    private StaffMemberProfile $plStaffProfile;

    private Project $project;

    private Project $plStaffProject;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new ProjectTaskPolicy;

        // Create permissions
        $permissions = [
            'task-list', 'task-create', 'task-edit', 'task-delete',
            'project-list', 'project-create', 'project-edit', 'project-delete',
        ];
        foreach ($permissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'sanctum']);
        }

        // Create roles
        $staffRole = Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'sanctum']);
        $managerRole = Role::firstOrCreate(['name' => 'manager', 'guard_name' => 'sanctum']);

        $staffRole->syncPermissions(['task-list', 'task-create', 'task-edit']);
        $managerRole->syncPermissions($permissions);

        // Create users
        $this->staffUser = User::factory()->create();
        $this->staffUser->assignRole('staff');

        $this->managerUser = User::factory()->create();
        $this->managerUser->assignRole('manager');

        // PL with staff role (not manager)
        $this->plStaffUser = User::factory()->create();
        $this->plStaffUser->assignRole('staff');

        // Create profiles
        $this->staffProfile = StaffMemberProfile::factory()->create(['user_id' => $this->staffUser->id]);
        $this->managerProfile = StaffMemberProfile::factory()->create(['user_id' => $this->managerUser->id]);
        $this->plStaffProfile = StaffMemberProfile::factory()->create(['user_id' => $this->plStaffUser->id]);

        // Create team and project (manager is PL)
        $this->team = Team::factory()->create();
        $this->project = Project::factory()->create([
            'project_leader_id' => $this->managerProfile->id,
        ]);
        $this->project->teams()->attach($this->team->id);

        // Project where PL has staff role
        $this->plStaffProject = Project::factory()->create([
            'project_leader_id' => $this->plStaffProfile->id,
        ]);
        $this->plStaffProject->teams()->attach($this->team->id);

        // Staff is team member
        TeamMember::create([
            'team_id' => $this->team->id,
            'staff_member_id' => $this->staffProfile->id,
            'joined_at' => now(),
        ]);
        TeamMember::create([
            'team_id' => $this->team->id,
            'staff_member_id' => $this->plStaffProfile->id,
            'joined_at' => now(),
        ]);
    }

    private function makeTask(string $status = 'todo', array $overrides = []): ProjectTask
    {
        return ProjectTask::factory()->create(array_merge([
            'project_id' => $this->project->id,
            'assignee_id' => $this->staffProfile->id,
            'status' => $status,
            'due_date' => now()->addDays(7)->format('Y-m-d'),
        ], $overrides));
    }

    // ═══════════════════════════════════════════════════════════════════
    // CREATE
    // ═══════════════════════════════════════════════════════════════════

    public function test_manager_can_create_task_freely(): void
    {
        $response = $this->policy->create($this->managerUser, [
            'project_id' => $this->project->id,
            'assignee_id' => $this->staffProfile->id,
            'status' => 'todo',
        ]);

        $this->assertTrue($response->allowed());
    }

    public function test_staff_can_create_task_self_assign(): void
    {
        $response = $this->policy->create($this->staffUser, [
            'project_id' => $this->project->id,
            'assignee_id' => $this->staffProfile->id,
            'status' => 'todo',
        ]);

        $this->assertTrue($response->allowed());
    }

    public function test_staff_cannot_create_task_assign_to_others(): void
    {
        $response = $this->policy->create($this->staffUser, [
            'project_id' => $this->project->id,
            'assignee_id' => $this->managerProfile->id,
            'status' => 'todo',
        ]);

        $this->assertTrue($response->denied());
    }

    public function test_staff_cannot_create_task_with_non_todo_status(): void
    {
        $response = $this->policy->create($this->staffUser, [
            'project_id' => $this->project->id,
            'assignee_id' => $this->staffProfile->id,
            'status' => 'in_progress',
        ]);

        $this->assertTrue($response->denied());
    }

    public function test_pl_staff_can_create_task_in_own_project(): void
    {
        $response = $this->policy->create($this->plStaffUser, [
            'project_id' => $this->plStaffProject->id,
            'assignee_id' => $this->staffProfile->id,
            'status' => 'todo',
        ]);

        $this->assertTrue($response->allowed());
    }

    // ═══════════════════════════════════════════════════════════════════
    // UPDATE — PL/Manager field edits
    // ═══════════════════════════════════════════════════════════════════

    public function test_manager_can_edit_task_fields_when_todo(): void
    {
        $task = $this->makeTask('todo');

        $response = $this->policy->update($this->managerUser, $task, [
            'name' => 'New Name',
            'description' => 'New Desc',
            'priority' => 'high',
        ]);

        $this->assertTrue($response->allowed());
    }

    public function test_manager_cannot_edit_task_fields_when_in_progress(): void
    {
        $task = $this->makeTask('in_progress');

        $response = $this->policy->update($this->managerUser, $task, [
            'name' => 'Changed Name',
        ]);

        $this->assertTrue($response->denied());
        $this->assertStringContainsString('only be edited when status is "todo"', $response->message());
    }

    public function test_manager_cannot_edit_task_fields_when_review(): void
    {
        $task = $this->makeTask('review');

        $response = $this->policy->update($this->managerUser, $task, [
            'priority' => 'low',
        ]);

        $this->assertTrue($response->denied());
    }

    // ═══════════════════════════════════════════════════════════════════
    // UPDATE — Staff status transitions
    // ═══════════════════════════════════════════════════════════════════

    public function test_staff_can_transition_todo_to_in_progress(): void
    {
        $task = $this->makeTask('todo');

        $response = $this->policy->update($this->staffUser, $task, [
            'status' => 'in_progress',
        ]);

        $this->assertTrue($response->allowed());
    }

    public function test_staff_can_transition_in_progress_to_review(): void
    {
        $task = $this->makeTask('in_progress');

        $response = $this->policy->update($this->staffUser, $task, [
            'status' => 'review',
        ]);

        $this->assertTrue($response->allowed());
    }

    public function test_staff_can_transition_rejected_to_in_progress_when_needs_revision(): void
    {
        $task = $this->makeTask('rejected', ['needs_revision' => true]);

        $response = $this->policy->update($this->staffUser, $task, [
            'status' => 'in_progress',
        ]);

        $this->assertTrue($response->allowed());
    }

    public function test_staff_cannot_transition_rejected_to_in_progress_without_needs_revision(): void
    {
        $task = $this->makeTask('rejected', ['needs_revision' => false]);

        $response = $this->policy->update($this->staffUser, $task, [
            'status' => 'in_progress',
        ]);

        $this->assertTrue($response->denied());
        $this->assertStringContainsString('not marked for revision', $response->message());
    }

    public function test_staff_cannot_transition_todo_to_done(): void
    {
        $task = $this->makeTask('todo');

        $response = $this->policy->update($this->staffUser, $task, [
            'status' => 'done',
        ]);

        $this->assertTrue($response->denied());
    }

    public function test_staff_cannot_transition_in_progress_to_done(): void
    {
        $task = $this->makeTask('in_progress');

        $response = $this->policy->update($this->staffUser, $task, [
            'status' => 'done',
        ]);

        $this->assertTrue($response->denied());
    }

    // ═══════════════════════════════════════════════════════════════════
    // UPDATE — PL/Manager status transitions (review)
    // ═══════════════════════════════════════════════════════════════════

    public function test_manager_can_approve_task_review_to_done(): void
    {
        $task = $this->makeTask('review');

        $response = $this->policy->update($this->managerUser, $task, [
            'status' => 'done',
        ]);

        $this->assertTrue($response->allowed());
    }

    public function test_manager_can_reject_task_with_reason(): void
    {
        $task = $this->makeTask('review');

        $response = $this->policy->update($this->managerUser, $task, [
            'status' => 'rejected',
            'rejected_reason' => 'Needs more work on the UI',
        ]);

        $this->assertTrue($response->allowed());
    }

    public function test_manager_cannot_reject_task_without_reason(): void
    {
        $task = $this->makeTask('review');

        $response = $this->policy->update($this->managerUser, $task, [
            'status' => 'rejected',
        ]);

        $this->assertTrue($response->denied());
        $this->assertStringContainsString('Rejected reason is required', $response->message());
    }

    public function test_manager_can_cancel_todo_task(): void
    {
        $task = $this->makeTask('todo');

        $response = $this->policy->update($this->managerUser, $task, [
            'status' => 'cancelled',
        ]);

        $this->assertTrue($response->allowed());
    }

    public function test_manager_cannot_cancel_in_progress_task(): void
    {
        $task = $this->makeTask('in_progress');

        $response = $this->policy->update($this->managerUser, $task, [
            'status' => 'cancelled',
        ]);

        $this->assertTrue($response->denied());
    }

    // ═══════════════════════════════════════════════════════════════════
    // UPDATE — Staff blocked fields
    // ═══════════════════════════════════════════════════════════════════

    public function test_staff_cannot_change_task_name(): void
    {
        $task = $this->makeTask('in_progress');

        $response = $this->policy->update($this->staffUser, $task, [
            'name' => 'Hijacked Name',
        ]);

        $this->assertTrue($response->denied());
        $this->assertStringContainsString('not allowed to modify name', $response->message());
    }

    public function test_staff_cannot_reassign_via_update(): void
    {
        $task = $this->makeTask('in_progress');

        $response = $this->policy->update($this->staffUser, $task, [
            'assignee_id' => $this->managerProfile->id,
        ]);

        $this->assertTrue($response->denied());
        $this->assertStringContainsString('not allowed to modify assignee_id', $response->message());
    }

    // ═══════════════════════════════════════════════════════════════════
    // DELETE
    // ═══════════════════════════════════════════════════════════════════

    public function test_manager_can_delete_todo_task(): void
    {
        $task = $this->makeTask('todo');

        $response = $this->policy->delete($this->managerUser, $task);

        $this->assertTrue($response->allowed());
    }

    public function test_manager_cannot_delete_in_progress_task(): void
    {
        $task = $this->makeTask('in_progress');

        $response = $this->policy->delete($this->managerUser, $task);

        $this->assertTrue($response->denied());
        $this->assertStringContainsString('only be deleted when status is "todo"', $response->message());
    }

    public function test_staff_cannot_delete_task(): void
    {
        $task = $this->makeTask('todo');

        $response = $this->policy->delete($this->staffUser, $task);

        $this->assertTrue($response->denied());
    }

    // ═══════════════════════════════════════════════════════════════════
    // COLLABORATE
    // ═══════════════════════════════════════════════════════════════════

    public function test_manager_can_collaborate_on_todo_task(): void
    {
        $task = $this->makeTask('todo');

        $response = $this->policy->collaborate($this->managerUser, $task);

        $this->assertTrue($response->allowed());
    }

    public function test_manager_cannot_collaborate_on_in_progress_task(): void
    {
        $task = $this->makeTask('in_progress');

        $response = $this->policy->collaborate($this->managerUser, $task);

        $this->assertTrue($response->denied());
        $this->assertStringContainsString('only allowed when status is "todo" or "rejected"', $response->message());
    }

    public function test_manager_can_collaborate_on_rejected_task(): void
    {
        $task = $this->makeTask('rejected');

        $response = $this->policy->collaborate($this->managerUser, $task);

        $this->assertTrue($response->allowed());
    }

    public function test_staff_can_collaborate_on_in_progress_task(): void
    {
        $task = $this->makeTask('in_progress');

        $response = $this->policy->collaborate($this->staffUser, $task);

        $this->assertTrue($response->allowed());
    }
    public function test_staff_can_collaborate_on_rejected_needs_revision_task(): void
    {
        $task = $this->makeTask('rejected', ['needs_revision' => true]);

        $response = $this->policy->collaborate($this->staffUser, $task);

        $this->assertTrue($response->allowed());
    }

    public function test_staff_cannot_collaborate_on_rejected_without_needs_revision(): void
    {
        $task = $this->makeTask('rejected', ['needs_revision' => false]);

        $response = $this->policy->collaborate($this->staffUser, $task);

        $this->assertTrue($response->denied());
    }

    public function test_staff_cannot_collaborate_on_review_task(): void
    {
        $task = $this->makeTask('review');

        $response = $this->policy->collaborate($this->staffUser, $task);

        $this->assertTrue($response->denied());
    }

    public function test_staff_cannot_collaborate_on_done_task(): void
    {
        $task = $this->makeTask('done');

        $response = $this->policy->collaborate($this->staffUser, $task);

        $this->assertTrue($response->denied());
    }

    // ═══════════════════════════════════════════════════════════════════
    // PL with staff role
    // ═══════════════════════════════════════════════════════════════════

    public function test_pl_staff_can_edit_todo_task_in_own_project(): void
    {
        $task = $this->makeTask('todo', ['project_id' => $this->plStaffProject->id]);

        $response = $this->policy->update($this->plStaffUser, $task, [
            'name' => 'PL Changed Name',
            'description' => 'PL Changed Desc',
            'priority' => 'high',
        ]);

        $this->assertTrue($response->allowed());
    }

    public function test_pl_staff_cannot_edit_in_progress_task_in_own_project(): void
    {
        $task = $this->makeTask('in_progress', ['project_id' => $this->plStaffProject->id]);

        $response = $this->policy->update($this->plStaffUser, $task, [
            'name' => 'PL Changed Name',
        ]);

        $this->assertTrue($response->denied());
    }

    public function test_pl_staff_can_approve_review_task(): void
    {
        $task = $this->makeTask('review', ['project_id' => $this->plStaffProject->id]);

        $response = $this->policy->update($this->plStaffUser, $task, [
            'status' => 'done',
        ]);

        $this->assertTrue($response->allowed());
    }

    public function test_pl_staff_can_reject_review_task_with_reason(): void
    {
        $task = $this->makeTask('review', ['project_id' => $this->plStaffProject->id]);

        $response = $this->policy->update($this->plStaffUser, $task, [
            'status' => 'rejected',
            'rejected_reason' => 'Not good enough',
        ]);

        $this->assertTrue($response->allowed());
    }

    public function test_pl_staff_can_collaborate_on_todo_task(): void
    {
        $task = $this->makeTask('todo', ['project_id' => $this->plStaffProject->id]);

        $response = $this->policy->collaborate($this->plStaffUser, $task);

        $this->assertTrue($response->allowed());
    }

    public function test_pl_staff_cannot_collaborate_on_in_progress_task(): void
    {
        $task = $this->makeTask('in_progress', ['project_id' => $this->plStaffProject->id]);

        $response = $this->policy->collaborate($this->plStaffUser, $task);

        $this->assertTrue($response->denied());
    }

    public function test_pl_staff_can_collaborate_on_rejected_task(): void
    {
        $task = $this->makeTask('rejected', ['project_id' => $this->plStaffProject->id]);

        $response = $this->policy->collaborate($this->plStaffUser, $task);

        $this->assertTrue($response->allowed());
    }
}
