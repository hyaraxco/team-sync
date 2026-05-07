<?php

namespace Tests\Feature\Notification;

use App\Models\Attendance;
use App\Models\AttendancePolicyMismatch;
use App\Models\HybridWorkSchedule;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\StaffMemberProfile;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Services\Attendance\AttendanceClassifier;
use App\Services\Attendance\AttendancePolicyMismatchLifecycleService;
use Carbon\Carbon;
use Database\Seeders\AttendancePolicySeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\ActivatesLicense;
use Tests\TestCase;

class ProjectTeamAttendanceNotificationsTest extends TestCase
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
            AttendancePolicySeeder::class,
        ]);

        $this->activateTestLicense();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_employee_moving_task_to_review_notifies_project_leader(): void
    {
        [$managerUser, $managerProfile] = $this->createUserWithRoleAndProfile('manager', 'Project Leader');
        [$employeeUser, $staffMemberProfile] = $this->createUserWithRoleAndProfile('staff', 'Task Assignee');

        $project = Project::query()->create([
            'name' => 'Notif Project '.uniqid(),
            'type' => 'web_development',
            'priority' => 'high',
            'status' => 'active',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'description' => 'Project status notification test',
            'project_leader_id' => $managerProfile->id,
        ]);

        $task = ProjectTask::query()->create([
            'project_id' => $project->id,
            'name' => 'Prepare delivery checklist',
            'description' => 'Checklist for final release',
            'assignee_id' => $staffMemberProfile->id,
            'priority' => 'medium',
            'status' => 'in_progress',
            'due_date' => now()->addDay()->toDateString(),
        ]);

        Sanctum::actingAs($employeeUser);

        $this->putJson('/api/v1/project-tasks/'.$task->id, [
            'status' => 'review',
        ])->assertOk();

        $leaderNotif = $this->latestNotification($managerUser);

        $this->assertSame('Task Ready for Review', $leaderNotif['title']);
        $this->assertSame($task->id, (int) ($leaderNotif['data']['task_id'] ?? 0));
        $this->assertSame('review', (string) ($leaderNotif['data']['to_status'] ?? ''));
    }

    public function test_manager_adding_team_member_notifies_employee(): void
    {
        [$managerUser] = $this->createUserWithRoleAndProfile('manager', 'Team Manager');
        [$employeeUser, $staffMemberProfile] = $this->createUserWithRoleAndProfile('staff', 'Added Employee');

        $team = Team::factory()->active()->create([
            'team_lead_id' => $managerUser->id,
        ]);

        Sanctum::actingAs($managerUser);

        $this->postJson('/api/v1/teams/'.$team->id.'/add-member', [
            'staff_member_id' => $staffMemberProfile->id,
        ])->assertOk();

        $employeeNotif = $this->latestNotification($employeeUser);

        $this->assertSame('Team Member Added', $employeeNotif['title']);
        $this->assertSame($team->id, (int) ($employeeNotif['data']['team_id'] ?? 0));
    }

    public function test_task_comment_and_attachment_notify_related_stakeholders(): void
    {
        [$managerUser, $managerProfile] = $this->createUserWithRoleAndProfile('manager', 'Comment Manager');
        [$employeeUser, $staffMemberProfile] = $this->createUserWithRoleAndProfile('staff', 'Comment Employee');

        $project = Project::query()->create([
            'name' => 'Collab Notif Project '.uniqid(),
            'type' => 'web_development',
            'priority' => 'medium',
            'status' => 'active',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'description' => 'Project collaboration notification test',
            'project_leader_id' => $managerProfile->id,
        ]);

        $task = ProjectTask::query()->create([
            'project_id' => $project->id,
            'name' => 'Implement collaboration alerts',
            'description' => 'Notify stakeholders on comments and attachments',
            'assignee_id' => $staffMemberProfile->id,
            'priority' => 'high',
            'status' => 'in_progress',
            'due_date' => now()->addDays(2)->toDateString(),
        ]);

        Sanctum::actingAs($employeeUser);

        $this->postJson('/api/v1/project-tasks/'.$task->id.'/comments', [
            'comment' => 'Task progress updated and ready for manager visibility.',
        ])->assertCreated();

        $this->post('/api/v1/project-tasks/'.$task->id.'/attachments', [
            'file' => UploadedFile::fake()->create('proof.txt', 12, 'text/plain'),
        ], ['Accept' => 'application/json'])->assertCreated();

        $managerPayload = $this->getNotifications($managerUser);

        $hasCommentNotif = collect($managerPayload)->contains(function (array $item): bool {
            return (string) ($item['title'] ?? '') === 'New Task Comment';
        });

        $hasAttachmentNotif = collect($managerPayload)->contains(function (array $item): bool {
            return (string) ($item['title'] ?? '') === 'New Task Attachment';
        });

        $this->assertTrue($hasCommentNotif);
        $this->assertTrue($hasAttachmentNotif);
    }

    public function test_team_status_change_notifies_related_employee_members(): void
    {
        [$managerUser] = $this->createUserWithRoleAndProfile('manager', 'Status Manager');
        [$employeeUser, $staffMemberProfile] = $this->createUserWithRoleAndProfile('staff', 'Status Employee');

        $team = Team::factory()->active()->create([
            'team_lead_id' => $managerUser->id,
        ]);

        TeamMember::query()->updateOrCreate(
            [
                'team_id' => $team->id,
                'staff_member_id' => $staffMemberProfile->id,
            ],
            [
                'joined_at' => now()->subDays(5),
                'left_at' => null,
            ]
        );

        Sanctum::actingAs($managerUser);

        $this->putJson('/api/v1/teams/'.$team->id, [
            'status' => 'dormant',
        ])->assertOk();

        $employeeNotif = $this->latestNotification($employeeUser);

        $this->assertSame('Team Status Updated', $employeeNotif['title']);
        $this->assertSame($team->id, (int) ($employeeNotif['data']['team_id'] ?? 0));
        $this->assertSame('dormant', (string) ($employeeNotif['data']['to_status'] ?? ''));
    }

    public function test_employee_check_in_and_check_out_send_notifications(): void
    {
        [$employeeUser] = $this->createUserWithRoleAndProfile('staff', 'Attendance Employee');

        Sanctum::actingAs($employeeUser);

        $this->postJson('/api/v1/attendances/check-in', [
            'check_in_lat' => -6.20000000,
            'check_in_long' => 106.81666600,
            'notes' => 'Start working day',
        ])->assertCreated();

        $this->postJson('/api/v1/attendances/check-out', [
            'check_out_lat' => -6.20000000,
            'check_out_long' => 106.81666600,
            'notes' => 'Finish working day',
        ])->assertOk();

        Sanctum::actingAs($employeeUser);

        $payload = $this->getJson('/api/v1/my-notifications?limit=10')
            ->assertOk()
            ->json('data');

        $this->assertIsArray($payload);
        $this->assertNotEmpty($payload);

        $hasCheckIn = collect($payload)->contains(function (array $item): bool {
            return (string) ($item['title'] ?? '') === 'Check-in Recorded';
        });

        $hasCheckOut = collect($payload)->contains(function (array $item): bool {
            return (string) ($item['title'] ?? '') === 'Check-out Recorded';
        });

        $this->assertTrue($hasCheckIn);
        $this->assertTrue($hasCheckOut);
    }

    public function test_mismatch_acknowledge_and_resolve_notify_employee(): void
    {
        [$managerUser] = $this->createUserWithRoleAndProfile('manager', 'Manager Reviewer');
        [$hrUser] = $this->createUserWithRoleAndProfile('hr', 'HR Resolver');

        $team = Team::factory()->active()->create([
            'team_lead_id' => $managerUser->id,
        ]);

        [$employeeUser, $staffMemberProfile] = $this->createUserWithRoleAndProfile(
            'staff',
            'Mismatch Employee',
            $team->id
        );

        $attendance = Attendance::query()->create([
            'staff_member_id' => $staffMemberProfile->id,
            'date' => now()->toDateString(),
            'status' => 'present',
            'check_in' => now()->subHours(8),
            'check_out' => now()->subHours(1),
            'worked_minutes' => 420,
            'actual_work_mode' => 'office',
            'policy_mismatch_flag' => true,
        ]);

        $mismatch = AttendancePolicyMismatch::query()->create([
            'attendance_id' => $attendance->id,
            'staff_member_id' => $staffMemberProfile->id,
            'mismatch_date' => now()->toDateString(),
            'planned_work_mode' => 'remote',
            'actual_work_mode' => 'office',
            'status' => AttendancePolicyMismatch::STATUS_PENDING_REVIEW,
        ]);

        Sanctum::actingAs($managerUser);

        $this->postJson('/api/v1/attendance-policy-mismatches/'.$mismatch->id.'/acknowledge', [
            'resolution_notes' => 'Received and reviewed by manager',
        ])->assertOk();

        Sanctum::actingAs($hrUser);

        $this->postJson('/api/v1/attendance-policy-mismatches/'.$mismatch->id.'/resolve', [
            'resolution_notes' => 'Resolved by HR',
        ])->assertOk();

        Sanctum::actingAs($employeeUser);

        $payload = $this->getJson('/api/v1/my-notifications?limit=10')
            ->assertOk()
            ->json('data');

        $this->assertIsArray($payload);

        $hasAcknowledged = collect($payload)->contains(function (array $item): bool {
            return (string) ($item['title'] ?? '') === 'Attendance Mismatch Acknowledged';
        });

        $hasResolved = collect($payload)->contains(function (array $item): bool {
            return (string) ($item['title'] ?? '') === 'Attendance Mismatch Resolved';
        });

        $this->assertTrue($hasAcknowledged);
        $this->assertTrue($hasResolved);
    }

    public function test_mismatch_created_notifies_employee_and_manager_once(): void
    {
        [$managerUser] = $this->createUserWithRoleAndProfile('manager', 'Mismatch Manager');

        $team = Team::factory()->active()->create([
            'team_lead_id' => $managerUser->id,
        ]);

        [$employeeUser, $staffMemberProfile] = $this->createUserWithRoleAndProfile(
            'staff',
            'Hybrid Employee',
            $team->id
        );

        $staffMemberProfile->jobInformation()->update([
            'work_location' => 'hybrid',
            'team_id' => $team->id,
        ]);

        $targetDate = Carbon::parse('next monday')->startOfDay();

        HybridWorkSchedule::query()->create([
            'staff_member_id' => $staffMemberProfile->id,
            'effective_from' => $targetDate->copy()->subWeek()->toDateString(),
            'effective_until' => null,
            'monday' => 'remote',
            'tuesday' => 'office',
            'wednesday' => 'office',
            'thursday' => 'office',
            'friday' => 'remote',
        ]);

        $attendance = Attendance::query()->create([
            'staff_member_id' => $staffMemberProfile->id,
            'date' => $targetDate->toDateString(),
            'status' => 'present',
            'check_in' => $targetDate->copy()->setTime(9, 15),
            'check_out' => $targetDate->copy()->setTime(17, 0),
            'worked_minutes' => 465,
            'actual_work_mode' => 'office',
            'policy_mismatch_flag' => false,
        ]);

        $classifier = app(AttendanceClassifier::class);

        $classifier->classify($staffMemberProfile->id, $targetDate);

        $this->assertDatabaseHas('attendance_policy_mismatches', [
            'attendance_id' => $attendance->id,
            'staff_member_id' => $staffMemberProfile->id,
            'status' => AttendancePolicyMismatch::STATUS_PENDING_REVIEW,
        ]);

        $employeeDetectedCountAfterFirstClassify = collect($this->getNotifications($employeeUser))
            ->filter(fn (array $item): bool => (string) ($item['title'] ?? '') === 'Attendance Mismatch Detected')
            ->count();

        $managerDetectedCountAfterFirstClassify = collect($this->getNotifications($managerUser))
            ->filter(fn (array $item): bool => (string) ($item['title'] ?? '') === 'Attendance Mismatch Needs Acknowledgement')
            ->count();

        $this->assertSame(1, $employeeDetectedCountAfterFirstClassify);
        $this->assertSame(1, $managerDetectedCountAfterFirstClassify);

        $classifier->classify($staffMemberProfile->id, $targetDate);

        $this->assertSame(1, AttendancePolicyMismatch::query()->where('attendance_id', $attendance->id)->count());

        $employeeDetectedCountAfterSecondClassify = collect($this->getNotifications($employeeUser))
            ->filter(fn (array $item): bool => (string) ($item['title'] ?? '') === 'Attendance Mismatch Detected')
            ->count();

        $managerDetectedCountAfterSecondClassify = collect($this->getNotifications($managerUser))
            ->filter(fn (array $item): bool => (string) ($item['title'] ?? '') === 'Attendance Mismatch Needs Acknowledgement')
            ->count();

        $this->assertSame(1, $employeeDetectedCountAfterSecondClassify);
        $this->assertSame(1, $managerDetectedCountAfterSecondClassify);
    }

    public function test_lp3es_mobile_project_flow_notifies_all_participating_employees_across_teams(): void
    {
        [$managerUser, $managerProfile] = $this->createUserWithRoleAndProfile('manager', 'LP3ES Manager');
        $managerUser->assignRole('staff');

        [$hrUser] = $this->createUserWithRoleAndProfile('hr', 'LP3ES HR');

        [$frontendUser, $frontendProfile] = $this->createUserWithRoleAndProfile('staff', 'LP3ES Frontend Dev');
        [$backendUser, $backendProfile] = $this->createUserWithRoleAndProfile('staff', 'LP3ES Backend Dev');
        [$qaUser, $qaProfile] = $this->createUserWithRoleAndProfile('staff', 'LP3ES QA Engineer');

        $mobileTeam = Team::factory()->active()->create([
            'name' => 'LP3ES Mobile Development',
            'team_lead_id' => $managerUser->id,
        ]);

        $qaTeam = Team::factory()->active()->create([
            'name' => 'LP3ES Quality Assurance',
            'team_lead_id' => $hrUser->id,
        ]);

        $frontendProfile->jobInformation()->update(['team_id' => $mobileTeam->id]);
        $backendProfile->jobInformation()->update(['team_id' => $mobileTeam->id]);
        $qaProfile->jobInformation()->update(['team_id' => $qaTeam->id]);

        Sanctum::actingAs($managerUser);

        $this->postJson('/api/v1/teams/'.$mobileTeam->id.'/add-member', [
            'staff_member_id' => $frontendProfile->id,
        ])->assertOk();

        $this->postJson('/api/v1/teams/'.$mobileTeam->id.'/add-member', [
            'staff_member_id' => $backendProfile->id,
        ])->assertOk();

        Sanctum::actingAs($hrUser);

        $this->postJson('/api/v1/teams/'.$qaTeam->id.'/add-member', [
            'staff_member_id' => $qaProfile->id,
        ])->assertOk();

        Sanctum::actingAs($managerUser);

        $projectResponse = $this->postJson('/api/v1/projects', [
            'name' => 'LP3ES Mobile Client Delivery',
            'type' => 'mobile_app',
            'priority' => 'high',
            'status' => 'active',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonth()->toDateString(),
            'description' => 'LP3ES mobile application delivery process.',
            'project_leader_id' => $managerProfile->id,
            'teams' => [$mobileTeam->id, $qaTeam->id],
        ])->assertCreated();

        $projectId = (int) $projectResponse->json('data.id');

        $frontendTaskResponse = $this->postJson('/api/v1/project-tasks', [
            'project_id' => $projectId,
            'name' => 'LP3ES Process 1 - UI Implementation',
            'description' => 'Build LP3ES mobile user interface process.',
            'assignee_id' => $frontendProfile->id,
            'priority' => 'high',
            'status' => 'todo',
            'due_date' => now()->addDays(7)->toDateString(),
        ])->assertCreated();

        $backendTaskResponse = $this->postJson('/api/v1/project-tasks', [
            'project_id' => $projectId,
            'name' => 'LP3ES Process 2 - API Integration',
            'description' => 'Integrate backend data and authentication flow.',
            'assignee_id' => $backendProfile->id,
            'priority' => 'high',
            'status' => 'todo',
            'due_date' => now()->addDays(8)->toDateString(),
        ])->assertCreated();

        Sanctum::actingAs($hrUser);

        $qaTaskResponse = $this->postJson('/api/v1/project-tasks', [
            'project_id' => $projectId,
            'name' => 'LP3ES Process 3 - QA and UAT Validation',
            'description' => 'Validate release quality and UAT readiness.',
            'assignee_id' => $qaProfile->id,
            'priority' => 'medium',
            'status' => 'todo',
            'due_date' => now()->addDays(9)->toDateString(),
        ])->assertCreated();

        $frontendTaskId = (int) $frontendTaskResponse->json('data.id');
        $backendTaskId = (int) $backendTaskResponse->json('data.id');
        $qaTaskId = (int) $qaTaskResponse->json('data.id');

        Sanctum::actingAs($managerUser);

        $this->postJson('/api/v1/project-tasks/'.$frontendTaskId.'/comments', [
            'comment' => 'Frontend process checkpoint from manager.',
        ])->assertCreated();

        $this->postJson('/api/v1/project-tasks/'.$backendTaskId.'/comments', [
            'comment' => 'Backend process checkpoint from manager.',
        ])->assertCreated();

        $this->postJson('/api/v1/project-tasks/'.$qaTaskId.'/comments', [
            'comment' => 'QA process checkpoint from manager.',
        ])->assertCreated();

        Sanctum::actingAs($hrUser);

        $this->post('/api/v1/project-tasks/'.$qaTaskId.'/attachments', [
            'file' => UploadedFile::fake()->create('lp3es-uat-checklist.txt', 12, 'text/plain'),
        ], ['Accept' => 'application/json'])->assertCreated();

        Sanctum::actingAs($frontendUser);

        $this->putJson('/api/v1/project-tasks/'.$frontendTaskId, [
            'status' => 'in_progress',
        ])->assertOk();

        $this->putJson('/api/v1/project-tasks/'.$frontendTaskId, [
            'status' => 'review',
        ])->assertOk();

        Sanctum::actingAs($backendUser);

        $this->putJson('/api/v1/project-tasks/'.$backendTaskId, [
            'status' => 'in_progress',
        ])->assertOk();

        $this->putJson('/api/v1/project-tasks/'.$backendTaskId, [
            'status' => 'review',
        ])->assertOk();

        Sanctum::actingAs($qaUser);

        $this->putJson('/api/v1/project-tasks/'.$qaTaskId, [
            'status' => 'in_progress',
        ])->assertOk();

        $this->putJson('/api/v1/project-tasks/'.$qaTaskId, [
            'status' => 'review',
        ])->assertOk();

        Sanctum::actingAs($hrUser);

        $this->putJson('/api/v1/project-tasks/'.$frontendTaskId, [
            'status' => 'rejected',
            'rejected_reason' => 'Need tighter accessibility QA for LP3ES.',
        ])->assertOk();

        Sanctum::actingAs($frontendUser);

        $this->putJson('/api/v1/project-tasks/'.$frontendTaskId, [
            'status' => 'in_progress',
        ])->assertOk();

        $this->putJson('/api/v1/project-tasks/'.$frontendTaskId, [
            'status' => 'review',
        ])->assertOk();

        Sanctum::actingAs($managerUser);

        $this->putJson('/api/v1/project-tasks/'.$frontendTaskId, [
            'status' => 'done',
        ])->assertOk();

        $this->putJson('/api/v1/project-tasks/'.$backendTaskId, [
            'status' => 'done',
        ])->assertOk();

        Sanctum::actingAs($hrUser);

        $this->putJson('/api/v1/project-tasks/'.$qaTaskId, [
            'status' => 'done',
        ])->assertOk();

        $frontendPayload = $this->getNotifications($frontendUser, 100);
        $backendPayload = $this->getNotifications($backendUser, 100);
        $qaPayload = $this->getNotifications($qaUser, 100);
        $managerPayload = $this->getNotifications($managerUser, 100);

        foreach ([
            'frontend' => $frontendPayload,
            'backend' => $backendPayload,
            'qa' => $qaPayload,
        ] as $label => $payload) {
            $hasProjectAssignment = collect($payload)->contains(function (array $item) use ($projectId): bool {
                $data = is_array($item['data'] ?? null) ? $item['data'] : [];

                return (string) ($item['title'] ?? '') === 'New Project Assigned'
                    && (int) ($data['project_id'] ?? 0) === $projectId;
            });

            $hasTaskAssignment = collect($payload)->contains(function (array $item) use ($projectId): bool {
                $data = is_array($item['data'] ?? null) ? $item['data'] : [];

                return (string) ($item['title'] ?? '') === 'New Task Assigned'
                    && (int) ($data['project_id'] ?? 0) === $projectId;
            });

            $hasTaskComment = collect($payload)->contains(function (array $item) use ($projectId): bool {
                $data = is_array($item['data'] ?? null) ? $item['data'] : [];

                return (string) ($item['title'] ?? '') === 'New Task Comment'
                    && (int) ($data['project_id'] ?? 0) === $projectId;
            });

            $this->assertTrue($hasProjectAssignment, sprintf('Missing project assignment notification for %s employee.', $label));
            $this->assertTrue($hasTaskAssignment, sprintf('Missing task assignment notification for %s employee.', $label));
            $this->assertTrue($hasTaskComment, sprintf('Missing task comment notification for %s employee.', $label));
        }

        $frontendRejected = collect($frontendPayload)->contains(function (array $item): bool {
            return (string) ($item['title'] ?? '') === 'Task Rejected';
        });

        $qaAttachment = collect($qaPayload)->contains(function (array $item): bool {
            return (string) ($item['title'] ?? '') === 'New Task Attachment';
        });

        $managerReviewCount = collect($managerPayload)
            ->filter(fn (array $item): bool => (string) ($item['title'] ?? '') === 'Task Ready for Review')
            ->count();

        $this->assertTrue($frontendRejected);
        $this->assertTrue($qaAttachment);
        $this->assertGreaterThanOrEqual(3, $managerReviewCount);
    }

    public function test_mismatch_escalation_notifies_employee_and_hr_reviewer(): void
    {
        [$hrUser] = $this->createUserWithRoleAndProfile('hr', 'Escalation HR');
        [$employeeUser, $staffMemberProfile] = $this->createUserWithRoleAndProfile('staff', 'Escalation Employee');

        $mismatchDate = Carbon::parse('2026-04-06')->startOfDay();

        $attendance = Attendance::query()->create([
            'staff_member_id' => $staffMemberProfile->id,
            'date' => $mismatchDate->toDateString(),
            'status' => 'present',
            'check_in' => $mismatchDate->copy()->setTime(9, 10),
            'check_out' => $mismatchDate->copy()->setTime(17, 0),
            'worked_minutes' => 470,
            'actual_work_mode' => 'office',
            'policy_mismatch_flag' => true,
        ]);

        $mismatch = AttendancePolicyMismatch::query()->create([
            'attendance_id' => $attendance->id,
            'staff_member_id' => $staffMemberProfile->id,
            'mismatch_date' => $mismatchDate->toDateString(),
            'planned_work_mode' => 'remote',
            'actual_work_mode' => 'office',
            'status' => AttendancePolicyMismatch::STATUS_PENDING_REVIEW,
        ]);

        $service = app(AttendancePolicyMismatchLifecycleService::class);
        $escalatedCount = $service->escalatePendingReviewMismatches('2026-04-10');

        $this->assertSame(1, $escalatedCount);
        $this->assertSame(AttendancePolicyMismatch::STATUS_ESCALATED_HR, $mismatch->fresh()->status);

        $employeePayload = $this->getNotifications($employeeUser);
        $hrPayload = $this->getNotifications($hrUser);

        $employeeEscalated = collect($employeePayload)->contains(function (array $item): bool {
            return (string) ($item['title'] ?? '') === 'Attendance Mismatch Escalated';
        });

        $hrEscalated = collect($hrPayload)->contains(function (array $item): bool {
            return (string) ($item['title'] ?? '') === 'Attendance Mismatch Escalated to HR';
        });

        $this->assertTrue($employeeEscalated);
        $this->assertTrue($hrEscalated);
    }

    /**
     * @return array{0: User, 1: StaffMemberProfile}
     */
    private function createUserWithRoleAndProfile(string $role, string $name, ?int $teamId = null): array
    {
        $sequence = $this->profileSequence++;

        $user = User::factory()->create([
            'name' => $name,
            'email' => sprintf('%s.%d@example.test', str_replace(' ', '.', strtolower($role)), $sequence),
        ]);
        $user->syncRoles([$role]);

        $profile = StaffMemberProfile::withoutSyncingToSearch(function () use ($user, $role, $sequence, $teamId) {
            $profile = StaffMemberProfile::factory()->forUser($user)->create([
                'code' => sprintf('%s%03d', strtoupper(substr($role, 0, 3)), $sequence),
                'identity_number' => str_pad((string) (90000000000000 + $sequence), 14, '0', STR_PAD_LEFT),
            ]);

            $profile->jobInformation()->create([
                'job_title' => 'Software Engineer',
                'team_id' => $teamId,
                'years_experience' => 3,
                'status' => 'active',
                'employment_type' => 'full_time',
                'work_location' => 'remote',
                'start_date' => now()->subYear()->toDateString(),
                'monthly_salary' => 10000000,
                'skill_level' => 'intermediate',
            ]);

            return $profile;
        });

        return [$user, $profile];
    }

    /**
     * @return array<string, mixed>
     */
    private function latestNotification(User $user): array
    {
        $payload = $this->getNotifications($user);

        $this->assertNotEmpty($payload);

        return $payload[0];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getNotifications(User $user, int $limit = 10): array
    {
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/my-notifications?limit='.$limit)
            ->assertOk();

        $payload = $response->json('data');

        $this->assertIsArray($payload);

        return $payload;
    }
}
