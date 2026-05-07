<?php

namespace Tests\Feature\Notification;

use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\StaffMemberProfile;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\ActivatesLicense;
use Tests\TestCase;

class TaskAssignmentNotificationsTest extends TestCase
{
    use ActivatesLicense, RefreshDatabase;

    private int $profileSequence = 1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            PermissionSeeder::class,
            RoleSeeder::class,
            RolePermissionSeeder::class,
        ]);

        $this->activateTestLicense();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_manager_creating_task_with_assignee_notifies_non_manager_employee(): void
    {
        [$manager] = $this->createUserWithRoleAndProfile('manager', 'Task Manager');
        [$employee, $staffMemberProfile] = $this->createUserWithRoleAndProfile('staff', 'Task Employee');
        $project = $this->createProject($staffMemberProfile->id);

        Sanctum::actingAs($manager);

        $response = $this->postJson('/api/v1/project-tasks', [
            'project_id' => $project->id,
            'name' => 'Prepare sprint report',
            'description' => 'Build report and update progress.',
            'assignee_id' => $staffMemberProfile->id,
            'priority' => 'medium',
            'status' => 'todo',
            'due_date' => now()->addDay()->toDateString(),
        ])->assertCreated();

        $taskId = (int) $response->json('data.id');

        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/my-notifications?limit=10')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'New Task Assigned')
            ->assertJsonPath('data.0.action_url', '/admin/projects/'.$project->id)
            ->assertJsonPath('data.0.data.task_id', $taskId)
            ->assertJsonPath('data.0.data.is_reassignment', false);
    }

    public function test_reassigning_task_notifies_new_assignee_as_reassignment(): void
    {
        [$manager] = $this->createUserWithRoleAndProfile('manager', 'Assignment Manager');
        [$employeeA, $staffMemberProfileA] = $this->createUserWithRoleAndProfile('staff', 'Employee Alpha');
        [$employeeB, $staffMemberProfileB] = $this->createUserWithRoleAndProfile('staff', 'Employee Beta');
        $project = $this->createProject($staffMemberProfileA->id);

        Sanctum::actingAs($manager);

        $createResponse = $this->postJson('/api/v1/project-tasks', [
            'project_id' => $project->id,
            'name' => 'Draft QA checklist',
            'description' => 'Prepare test matrix for release.',
            'assignee_id' => $staffMemberProfileA->id,
            'priority' => 'high',
            'status' => 'todo',
            'due_date' => now()->addDays(2)->toDateString(),
        ])->assertCreated();

        $taskId = (int) $createResponse->json('data.id');

        $this->putJson('/api/v1/project-tasks/'.$taskId, [
            'assignee_id' => $staffMemberProfileB->id,
        ])->assertOk();

        Sanctum::actingAs($employeeB);
        $this->getJson('/api/v1/my-notifications?limit=10')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Task Reassigned')
            ->assertJsonPath('data.0.data.task_id', $taskId)
            ->assertJsonPath('data.0.data.is_reassignment', true);

        Sanctum::actingAs($employeeA);
        $payload = $this->getJson('/api/v1/my-notifications?limit=10')
            ->assertOk()
            ->json('data');

        $this->assertFalse(
            collect($payload)->contains(function (array $item) use ($taskId): bool {
                $data = is_array($item['data'] ?? null) ? $item['data'] : [];

                return (int) ($data['task_id'] ?? 0) === $taskId
                    && (bool) ($data['is_reassignment'] ?? false) === true;
            })
        );
    }

    public function test_assignment_notification_is_not_sent_to_manager_assignee(): void
    {
        [$actor] = $this->createUserWithRoleAndProfile('manager', 'Actor Manager');
        [$managerAssignee, $managerProfile] = $this->createUserWithRoleAndProfile('manager', 'Assignee Manager');
        [, $staffMemberProfile] = $this->createUserWithRoleAndProfile('staff', 'Leader Employee');
        $project = $this->createProject($staffMemberProfile->id);

        Sanctum::actingAs($actor);

        $this->postJson('/api/v1/project-tasks', [
            'project_id' => $project->id,
            'name' => 'Manager-only assignment',
            'description' => 'Should not notify manager recipient in this phase.',
            'assignee_id' => $managerProfile->id,
            'priority' => 'medium',
            'status' => 'todo',
            'due_date' => now()->addDay()->toDateString(),
        ])->assertCreated();

        Sanctum::actingAs($managerAssignee);

        $this->getJson('/api/v1/my-notifications?limit=10')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_reassigning_rejected_task_resets_status_to_backlog_and_clears_rejection_metadata(): void
    {
        [$manager] = $this->createUserWithRoleAndProfile('manager', 'Workflow Manager');
        [, $staffMemberProfileA] = $this->createUserWithRoleAndProfile('staff', 'Owner Employee');
        [, $staffMemberProfileB] = $this->createUserWithRoleAndProfile('staff', 'Replacement Employee');
        $project = $this->createProject($staffMemberProfileA->id);

        Sanctum::actingAs($manager);

        $createResponse = $this->postJson('/api/v1/project-tasks', [
            'project_id' => $project->id,
            'name' => 'Revise API contract',
            'description' => 'Implement request format changes.',
            'assignee_id' => $staffMemberProfileA->id,
            'priority' => 'high',
            'status' => 'review',
            'due_date' => now()->addDays(3)->toDateString(),
        ])->assertCreated();

        $taskId = (int) $createResponse->json('data.id');

        $this->putJson('/api/v1/project-tasks/'.$taskId, [
            'status' => 'rejected',
            'rejected_reason' => 'Needs backend response adjustment',
        ])->assertOk();

        $reassignResponse = $this->putJson('/api/v1/project-tasks/'.$taskId, [
            'assignee_id' => $staffMemberProfileB->id,
        ])->assertOk();

        $reassignResponse
            ->assertJsonPath('data.assignee_id', $staffMemberProfileB->id)
            ->assertJsonPath('data.status', 'todo')
            ->assertJsonPath('data.rejected_reason', null)
            ->assertJsonPath('data.rejected_by', null)
            ->assertJsonPath('data.rejected_at', null);

        $task = ProjectTask::query()->findOrFail($taskId);

        $this->assertSame('todo', $task->status);
        $this->assertSame((int) $staffMemberProfileB->id, (int) $task->assignee_id);
        $this->assertNull($task->rejected_reason);
        $this->assertNull($task->rejected_by);
        $this->assertNull($task->rejected_at);
    }

    /**
     * @return array{0: User, 1: StaffMemberProfile}
     */
    private function createUserWithRoleAndProfile(string $role, string $name): array
    {
        $sequence = $this->profileSequence++;

        $user = User::factory()->create([
            'name' => $name,
            'email' => sprintf('%s.%d@example.test', str_replace(' ', '.', strtolower($role)), $sequence),
        ]);
        $user->syncRoles([$role]);

        $profile = StaffMemberProfile::factory()->forUser($user)->create([
            'code' => sprintf('%s%03d', strtoupper(substr($role, 0, 3)), $sequence),
            'identity_number' => str_pad((string) (88000000000000 + $sequence), 14, '0', STR_PAD_LEFT),
        ]);

        $profile->jobInformation()->create([
            'job_title' => 'Software Engineer',
            'team_id' => null,
            'years_experience' => 3,
            'status' => 'active',
            'employment_type' => 'full_time',
            'work_location' => 'remote',
            'start_date' => now()->subYear()->toDateString(),
            'monthly_salary' => 10000000,
            'skill_level' => 'intermediate',
        ]);

        return [$user, $profile];
    }

    private function createProject(int $projectLeaderId): Project
    {
        return Project::query()->create([
            'name' => 'Notification Project '.uniqid(),
            'type' => 'web_development',
            'priority' => 'medium',
            'status' => 'active',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'description' => 'Project for notification role flow test.',
            'project_leader_id' => $projectLeaderId,
        ]);
    }
}
