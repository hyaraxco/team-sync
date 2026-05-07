<?php

namespace Tests\Feature\Project;

use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectTaskAttachment;
use App\Models\ProjectTaskComment;
use App\Models\StaffMemberProfile;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StaffTaskCollaborationSecurityTest extends TestCase
{
    use RefreshDatabase;

    private User $staffUser;

    private User $otherStaffUser;

    private User $managerUser;

    private StaffMemberProfile $staffProfile;

    private StaffMemberProfile $otherStaffProfile;

    private StaffMemberProfile $managerProfile;

    private Project $project;

    private ProjectTask $task;

    private Team $team;

    protected function setUp(): void
    {
        parent::setUp();

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

        $this->otherStaffUser = User::factory()->create();
        $this->otherStaffUser->assignRole('staff');

        $this->managerUser = User::factory()->create();
        $this->managerUser->assignRole('manager');

        // Create profiles
        $this->staffProfile = StaffMemberProfile::factory()->create(['user_id' => $this->staffUser->id]);
        $this->otherStaffProfile = StaffMemberProfile::factory()->create(['user_id' => $this->otherStaffUser->id]);
        $this->managerProfile = StaffMemberProfile::factory()->create(['user_id' => $this->managerUser->id]);

        // Create team and project
        $this->team = Team::factory()->create();
        $this->project = Project::factory()->create([
            'project_leader_id' => $this->managerProfile->id,
        ]);
        $this->project->teams()->attach($this->team->id);

        // Both staff are team members
        TeamMember::create([
            'team_id' => $this->team->id,
            'staff_member_id' => $this->staffProfile->id,
            'joined_at' => now(),
        ]);
        TeamMember::create([
            'team_id' => $this->team->id,
            'staff_member_id' => $this->otherStaffProfile->id,
            'joined_at' => now(),
        ]);

        // Create task assigned to staffUser
        $this->task = ProjectTask::factory()->create([
            'project_id' => $this->project->id,
            'assignee_id' => $this->staffProfile->id,
            'status' => 'in_progress',
            'due_date' => now()->addDays(7)->format('Y-m-d'),
        ]);
    }

    // ─── Comment Ownership ──────────────────────────────────────────────

    public function test_staff_cannot_update_other_users_comment(): void
    {
        $comment = ProjectTaskComment::create([
            'project_task_id' => $this->task->id,
            'staff_member_id' => $this->otherStaffProfile->id,
            'comment' => 'Original comment by other staff',
        ]);

        $response = $this->actingAs($this->staffUser, 'sanctum')
            ->putJson("/api/v1/project-tasks/{$this->task->id}/comments/{$comment->id}", [
                'comment' => 'Hijacked comment',
            ]);

        $response->assertStatus(403);
        $response->assertJson(['message' => 'You can only edit your own comments.']);
    }

    public function test_staff_can_update_own_comment(): void
    {
        $comment = ProjectTaskComment::create([
            'project_task_id' => $this->task->id,
            'staff_member_id' => $this->staffProfile->id,
            'comment' => 'My original comment',
        ]);

        $response = $this->actingAs($this->staffUser, 'sanctum')
            ->putJson("/api/v1/project-tasks/{$this->task->id}/comments/{$comment->id}", [
                'comment' => 'My updated comment',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('project_task_comments', [
            'id' => $comment->id,
            'comment' => 'My updated comment',
        ]);
    }

    public function test_staff_cannot_delete_other_users_comment(): void
    {
        $comment = ProjectTaskComment::create([
            'project_task_id' => $this->task->id,
            'staff_member_id' => $this->otherStaffProfile->id,
            'comment' => 'Other staff comment',
        ]);

        $response = $this->actingAs($this->staffUser, 'sanctum')
            ->deleteJson("/api/v1/project-tasks/{$this->task->id}/comments/{$comment->id}");

        $response->assertStatus(403);
        $response->assertJson(['message' => 'You can only delete your own comments.']);
    }

    public function test_staff_can_delete_own_comment(): void
    {
        $comment = ProjectTaskComment::create([
            'project_task_id' => $this->task->id,
            'staff_member_id' => $this->staffProfile->id,
            'comment' => 'My comment to delete',
        ]);

        $response = $this->actingAs($this->staffUser, 'sanctum')
            ->deleteJson("/api/v1/project-tasks/{$this->task->id}/comments/{$comment->id}");

        $response->assertStatus(200);
    }

    public function test_manager_can_update_any_comment(): void
    {
        $comment = ProjectTaskComment::create([
            'project_task_id' => $this->task->id,
            'staff_member_id' => $this->staffProfile->id,
            'comment' => 'Staff comment',
        ]);

        $response = $this->actingAs($this->managerUser, 'sanctum')
            ->putJson("/api/v1/project-tasks/{$this->task->id}/comments/{$comment->id}", [
                'comment' => 'Manager edited this',
            ]);

        $response->assertStatus(200);
    }

    public function test_manager_can_delete_any_comment(): void
    {
        $comment = ProjectTaskComment::create([
            'project_task_id' => $this->task->id,
            'staff_member_id' => $this->staffProfile->id,
            'comment' => 'Staff comment to be deleted by manager',
        ]);

        $response = $this->actingAs($this->managerUser, 'sanctum')
            ->deleteJson("/api/v1/project-tasks/{$this->task->id}/comments/{$comment->id}");

        $response->assertStatus(200);
    }

    // ─── Attachment Ownership ───────────────────────────────────────────

    public function test_staff_cannot_delete_other_users_attachment(): void
    {
        $attachment = ProjectTaskAttachment::create([
            'project_task_id' => $this->task->id,
            'staff_member_id' => $this->otherStaffProfile->id,
            'file_name' => 'other_file.pdf',
            'file_path' => 'attachments/other_file.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
        ]);

        $response = $this->actingAs($this->staffUser, 'sanctum')
            ->deleteJson("/api/v1/project-tasks/{$this->task->id}/attachments/{$attachment->id}");

        $response->assertStatus(403);
        $response->assertJson(['message' => 'You can only delete your own attachments.']);
    }

    public function test_staff_can_delete_own_attachment(): void
    {
        $attachment = ProjectTaskAttachment::create([
            'project_task_id' => $this->task->id,
            'staff_member_id' => $this->staffProfile->id,
            'file_name' => 'my_file.pdf',
            'file_path' => 'attachments/my_file.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
        ]);

        $response = $this->actingAs($this->staffUser, 'sanctum')
            ->deleteJson("/api/v1/project-tasks/{$this->task->id}/attachments/{$attachment->id}");

        $response->assertStatus(200);
    }

    public function test_manager_can_delete_any_attachment(): void
    {
        $attachment = ProjectTaskAttachment::create([
            'project_task_id' => $this->task->id,
            'staff_member_id' => $this->staffProfile->id,
            'file_name' => 'staff_file.pdf',
            'file_path' => 'attachments/staff_file.pdf',
            'file_size' => 1024,
            'mime_type' => 'application/pdf',
        ]);

        $response = $this->actingAs($this->managerUser, 'sanctum')
            ->deleteJson("/api/v1/project-tasks/{$this->task->id}/attachments/{$attachment->id}");

        $response->assertStatus(200);
    }

    // ─── Task Status Lock ───────────────────────────────────────────────

    public function test_staff_cannot_comment_on_review_status_task(): void
    {
        $this->task->update(['status' => 'review']);

        $response = $this->actingAs($this->staffUser, 'sanctum')
            ->postJson("/api/v1/project-tasks/{$this->task->id}/comments", [
                'comment' => 'Trying to comment on review task',
            ]);

        $response->assertStatus(403);
    }

    public function test_staff_cannot_update_comment_on_review_status_task(): void
    {
        $comment = ProjectTaskComment::create([
            'project_task_id' => $this->task->id,
            'staff_member_id' => $this->staffProfile->id,
            'comment' => 'My comment',
        ]);

        $this->task->update(['status' => 'review']);

        $response = $this->actingAs($this->staffUser, 'sanctum')
            ->putJson("/api/v1/project-tasks/{$this->task->id}/comments/{$comment->id}", [
                'comment' => 'Trying to update on review task',
            ]);

        $response->assertStatus(403);
    }

    // ─── Task Show Policy ───────────────────────────────────────────────

    public function test_staff_cannot_view_task_from_non_member_project(): void
    {
        $otherTeam = Team::factory()->create();
        $otherProject = Project::factory()->create([
            'project_leader_id' => $this->managerProfile->id,
        ]);
        $otherProject->teams()->attach($otherTeam->id);

        $otherTask = ProjectTask::factory()->create([
            'project_id' => $otherProject->id,
            'assignee_id' => $this->otherStaffProfile->id,
            'status' => 'todo',
            'due_date' => now()->addDays(7)->format('Y-m-d'),
        ]);

        $response = $this->actingAs($this->staffUser, 'sanctum')
            ->getJson("/api/v1/project-tasks/{$otherTask->id}");

        $response->assertStatus(403);
    }

    public function test_staff_can_view_task_from_own_project(): void
    {
        $response = $this->actingAs($this->staffUser, 'sanctum')
            ->getJson("/api/v1/project-tasks/{$this->task->id}");

        $response->assertStatus(200);
    }
}
