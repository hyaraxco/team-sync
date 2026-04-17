<?php

namespace Tests\Feature\Project;

use App\Enums\Department;
use App\Enums\EmploymentType;
use App\Enums\JobStatus;
use App\Enums\ProjectPriority;
use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Enums\SkillLevel;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TeamStatus;
use App\Enums\WorkLocation;
use App\Models\EmployeeProfile;
use App\Models\Project;
use App\Models\ProjectTask;
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

class ProjectSquadSummaryTest extends TestCase
{
    use RefreshDatabase;

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
    }

    public function test_manager_can_fetch_project_squad_summary(): void
    {
        $managerAccount = $this->createUserWithProfileAndTeam(
            'manager',
            'Raka Manager',
            'manager.summary@teamsync.test',
            'Product Manager',
            null
        );
        $manager = $managerAccount['user'];
        $managerProfile = $managerAccount['profile'];

        $frontendTeam = $this->createTeam('Mobile Frontend Engineering');
        $backendTeam = $this->createTeam('Mobile Backend Engineering');
        $uiuxTeam = $this->createTeam('Mobile UI/UX Design');
        $qaTeam = $this->createTeam('Mobile QA Testing');
        $pmTeam = $this->createTeam('Mobile Product Management');

        $frontendA = $this->createUserWithProfileAndTeam(
            'employee',
            'Frontend A',
            'fe-a@teamsync.test',
            'Frontend Engineer',
            $frontendTeam
        )['profile'];
        $frontendB = $this->createUserWithProfileAndTeam(
            'employee',
            'Frontend B',
            'fe-b@teamsync.test',
            'Frontend Engineer',
            $frontendTeam
        )['profile'];
        $backendA = $this->createUserWithProfileAndTeam(
            'employee',
            'Backend A',
            'be-a@teamsync.test',
            'Backend Engineer',
            $backendTeam
        )['profile'];
        $backendB = $this->createUserWithProfileAndTeam(
            'employee',
            'Backend B',
            'be-b@teamsync.test',
            'Backend Engineer',
            $backendTeam
        )['profile'];
        $uiuxA = $this->createUserWithProfileAndTeam(
            'employee',
            'UIUX A',
            'uiux-a@teamsync.test',
            'UI/UX Designer',
            $uiuxTeam
        )['profile'];
        $qaA = $this->createUserWithProfileAndTeam(
            'employee',
            'QA A',
            'qa-a@teamsync.test',
            'QA Tester',
            $qaTeam
        )['profile'];
        $pmA = $this->createUserWithProfileAndTeam(
            'employee',
            'PM A',
            'pm-a@teamsync.test',
            'Project Manager',
            $pmTeam
        )['profile'];

        $project = Project::query()->create([
            'name' => 'Squad Summary Project',
            'type' => ProjectType::MOBILE_APP->value,
            'priority' => ProjectPriority::HIGH->value,
            'status' => ProjectStatus::ACTIVE->value,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(4)->toDateString(),
            'description' => 'Project for squad summary endpoint test',
            'project_leader_id' => $managerProfile->id,
        ]);

        $project->teams()->sync([
            $frontendTeam->id => ['assigned_at' => now()],
            $backendTeam->id => ['assigned_at' => now()],
            $uiuxTeam->id => ['assigned_at' => now()],
            $qaTeam->id => ['assigned_at' => now()],
            $pmTeam->id => ['assigned_at' => now()],
        ]);

        $this->createTask($project, 'FE task 1', $frontendA, TaskStatus::TODO->value);
        $this->createTask($project, 'FE task 2', $frontendB, TaskStatus::REJECTED->value);
        $this->createTask($project, 'BE task 1', $backendA, TaskStatus::IN_PROGRESS->value);
        $this->createTask($project, 'BE task 2', $backendB, TaskStatus::DONE->value);
        $this->createTask($project, 'UX task', $uiuxA, TaskStatus::REVIEW->value);
        $this->createTask($project, 'QA task', $qaA, TaskStatus::TODO->value);
        $this->createTask($project, 'PM task 1', $pmA, TaskStatus::IN_PROGRESS->value);
        $this->createTask($project, 'PM task 2', $pmA, TaskStatus::REVIEW->value);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/projects/'.$project->id.'/squad-summary');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.project.id', $project->id)
            ->assertJsonPath('data.headcount.total', 7)
            ->assertJsonPath('data.headcount.by_stream.frontend', 2)
            ->assertJsonPath('data.headcount.by_stream.backend', 2)
            ->assertJsonPath('data.headcount.by_stream.uiux', 1)
            ->assertJsonPath('data.headcount.by_stream.qa', 1)
            ->assertJsonPath('data.headcount.by_stream.pm', 1)
            ->assertJsonPath('data.tasks.total', 8)
            ->assertJsonPath('data.tasks.by_status.todo', 2)
            ->assertJsonPath('data.tasks.by_status.in_progress', 2)
            ->assertJsonPath('data.tasks.by_status.review', 2)
            ->assertJsonPath('data.tasks.by_status.done', 1)
            ->assertJsonPath('data.tasks.by_status.rejected', 1)
            ->assertJsonPath('data.tasks.by_stream.frontend', 2)
            ->assertJsonPath('data.tasks.by_stream.backend', 2)
            ->assertJsonPath('data.tasks.by_stream.uiux', 1)
            ->assertJsonPath('data.tasks.by_stream.qa', 1)
            ->assertJsonPath('data.tasks.by_stream.pm', 2);

        $teamBreakdown = collect($response->json('data.headcount.by_team'))->keyBy('team_name');

        $this->assertSame(2, $teamBreakdown['Mobile Frontend Engineering']['members_count']);
        $this->assertSame(2, $teamBreakdown['Mobile Backend Engineering']['members_count']);
        $this->assertSame(1, $teamBreakdown['Mobile UI/UX Design']['members_count']);
        $this->assertSame(1, $teamBreakdown['Mobile QA Testing']['members_count']);
        $this->assertSame(1, $teamBreakdown['Mobile Product Management']['members_count']);
    }

    public function test_employee_without_project_membership_is_forbidden(): void
    {
        $managerAccount = $this->createUserWithProfileAndTeam(
            'manager',
            'Allowed Manager',
            'allowed.manager@teamsync.test',
            'Product Manager',
            null
        );
        $managerProfile = $managerAccount['profile'];

        $assignedTeam = $this->createTeam('Assigned Team');
        $memberProfile = $this->createUserWithProfileAndTeam(
            'employee',
            'Project Member',
            'project.member@teamsync.test',
            'Frontend Engineer',
            $assignedTeam
        )['profile'];

        $outsideTeam = $this->createTeam('Outside Team');
        $outsideUser = $this->createUserWithProfileAndTeam(
            'employee',
            'Outside Employee',
            'outside.employee@teamsync.test',
            'QA Tester',
            $outsideTeam
        )['user'];

        $project = Project::query()->create([
            'name' => 'Membership Protected Project',
            'type' => ProjectType::MOBILE_APP->value,
            'priority' => ProjectPriority::MEDIUM->value,
            'status' => ProjectStatus::ACTIVE->value,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(3)->toDateString(),
            'description' => 'Project used to validate membership checks',
            'project_leader_id' => $managerProfile->id,
        ]);

        $project->teams()->sync([
            $assignedTeam->id => ['assigned_at' => now()],
        ]);

        $this->createTask($project, 'Member task', $memberProfile, TaskStatus::TODO->value);

        Sanctum::actingAs($outsideUser);

        $this->getJson('/api/v1/projects/'.$project->id.'/squad-summary')
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Forbidden');
    }

    private function createTeam(string $name): Team
    {
        return Team::query()->create([
            'name' => $name,
            'expected_size' => 6,
            'description' => $name.' description',
            'icon' => 'team-icons/activity.png',
            'department' => Department::DEVELOPMENT->value,
            'status' => TeamStatus::ACTIVE->value,
            'responsibilities' => ['Collaborate', 'Deliver sprint tasks'],
        ]);
    }

    /**
     * @return array{user: User, profile: EmployeeProfile}
     */
    private function createUserWithProfileAndTeam(
        string $role,
        string $name,
        string $email,
        string $jobTitle,
        ?Team $team
    ): array {
        $user = User::factory()->create([
            'name' => $name,
            'email' => $email,
        ]);
        $user->syncRoles([$role]);

        $profile = EmployeeProfile::factory()->forUser($user)->create();

        $profile->jobInformation()->create([
            'employee_id' => $profile->id,
            'job_title' => $jobTitle,
            'team_id' => $team?->id,
            'years_experience' => 5,
            'status' => JobStatus::ACTIVE->value,
            'employment_type' => EmploymentType::FULL_TIME->value,
            'work_location' => WorkLocation::HYBRID->value,
            'start_date' => now()->subYear()->toDateString(),
            'monthly_salary' => 12000000,
            'skill_level' => SkillLevel::INTERMEDIATE->value,
        ]);

        if ($team) {
            TeamMember::query()->create([
                'team_id' => $team->id,
                'employee_id' => $profile->id,
                'joined_at' => now()->subMonths(4),
                'left_at' => null,
            ]);
        }

        return [
            'user' => $user,
            'profile' => $profile,
        ];
    }

    private function createTask(
        Project $project,
        string $name,
        EmployeeProfile $assignee,
        string $status
    ): ProjectTask {
        return ProjectTask::query()->create([
            'project_id' => $project->id,
            'name' => $name,
            'description' => $name.' description',
            'assignee_id' => $assignee->id,
            'priority' => TaskPriority::MEDIUM->value,
            'status' => $status,
            'due_date' => now()->addDays(7)->toDateString(),
            'rejected_reason' => $status === TaskStatus::REJECTED->value ? 'Need revision' : null,
            'rejected_by' => null,
            'rejected_at' => null,
        ]);
    }
}
