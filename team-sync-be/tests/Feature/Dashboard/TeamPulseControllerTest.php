<?php

namespace Tests\Feature\Dashboard;

use App\Enums\TaskStatus;
use App\Models\Attendance;
use App\Models\Company;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectTaskStatusLog;
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
use Tests\Concerns\ActivatesLicense;
use Tests\TestCase;

class TeamPulseControllerTest extends TestCase
{
    use ActivatesLicense, RefreshDatabase;

    private int $profileSequence = 1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,
        ]);

        Company::query()->create([
            'name' => 'PT Team Sync Nusantara',
            'slug' => 'team-sync',
            'domain' => 'teamsync.local',
            'timezone' => 'Asia/Jakarta',
            'locale' => 'id',
            'currency' => 'IDR',
            'is_active' => true,
            'settings' => [],
        ]);

        $this->activateTestLicense(['attendance', 'performance']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_manager_can_get_team_pulse_sorted_by_risk(): void
    {
        [$managerUser] = $this->createUserWithRoleAndProfile('manager', 'Pulse Manager');

        $team = Team::factory()->active()->create([
            'team_lead_id' => $managerUser->id,
        ]);

        [$redUser, $redProfile] = $this->createUserWithRoleAndProfile('staff', 'Red Staff', $team->id);
        [$yellowUser, $yellowProfile] = $this->createUserWithRoleAndProfile('staff', 'Yellow Staff', $team->id);
        [$greenUser, $greenProfile] = $this->createUserWithRoleAndProfile('staff', 'Green Staff', $team->id);

        TeamMember::factory()->forTeam($team)->forEmployee($redProfile)->create();
        TeamMember::factory()->forTeam($team)->forEmployee($yellowProfile)->create();
        TeamMember::factory()->forTeam($team)->forEmployee($greenProfile)->create();

        $project = Project::factory()->create([
            'project_leader_id' => $redProfile->id,
            'status' => 'active',
        ]);

        Attendance::factory()->create([
            'staff_member_id' => $yellowProfile->id,
            'date' => now()->toDateString(),
            'status' => 'present',
        ]);

        Attendance::factory()->create([
            'staff_member_id' => $greenProfile->id,
            'date' => now()->toDateString(),
            'status' => 'present',
        ]);

        ProjectTask::query()->create([
            'project_id' => $project->id,
            'name' => 'Red task',
            'description' => 'Overdue red task',
            'assignee_id' => $redProfile->id,
            'priority' => 'high',
            'status' => TaskStatus::TODO->value,
            'due_date' => now()->subDay()->toDateString(),
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDays(3),
        ]);

        ProjectTask::query()->create([
            'project_id' => $project->id,
            'name' => 'Yellow due today',
            'description' => 'Due today yellow task',
            'assignee_id' => $yellowProfile->id,
            'priority' => 'medium',
            'status' => TaskStatus::TODO->value,
            'due_date' => now()->toDateString(),
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        ProjectTask::query()->create([
            'project_id' => $project->id,
            'name' => 'Yellow in progress',
            'description' => 'Active yellow task',
            'assignee_id' => $yellowProfile->id,
            'priority' => 'medium',
            'status' => TaskStatus::IN_PROGRESS->value,
            'due_date' => now()->addDay()->toDateString(),
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        $greenTask = ProjectTask::query()->create([
            'project_id' => $project->id,
            'name' => 'Green done task',
            'description' => 'Healthy green task',
            'assignee_id' => $greenProfile->id,
            'priority' => 'low',
            'status' => TaskStatus::DONE->value,
            'due_date' => now()->toDateString(),
            'created_at' => now()->subDay(),
            'updated_at' => now(),
        ]);

        ProjectTaskStatusLog::query()->create([
            'project_task_id' => $greenTask->id,
            'from_status' => TaskStatus::REVIEW->value,
            'to_status' => TaskStatus::DONE->value,
            'changed_by' => $greenProfile->id,
            'reason' => null,
            'changed_at' => now(),
        ]);

        Sanctum::actingAs($managerUser);

        $response = $this->getJson('/api/v1/dashboard/team-pulse')
            ->assertOk()
            ->assertJsonPath('data.summary.red', 1)
            ->assertJsonPath('data.summary.yellow', 1)
            ->assertJsonPath('data.summary.green', 1)
            ->assertJsonCount(3, 'data.staff_members');

        $items = $response->json('data.staff_members');

        $riskByName = collect($items)->mapWithKeys(fn (array $item) => [
            $item['name'] => $item['risk']['level'],
        ]);

        $this->assertSame('red', $riskByName['Red Staff']);
        $this->assertSame('yellow', $riskByName['Yellow Staff']);
        $this->assertSame('green', $riskByName['Green Staff']);
        $this->assertSame('Red Staff', $items[0]['name']);
    }

    public function test_manager_can_send_team_pulse_nudge_to_managed_staff_member(): void
    {
        [$managerUser] = $this->createUserWithRoleAndProfile('manager', 'Nudge Manager');

        $team = Team::factory()->active()->create([
            'team_lead_id' => $managerUser->id,
        ]);

        [$staffUser, $staffProfile] = $this->createUserWithRoleAndProfile('staff', 'Nudged Staff', $team->id);
        TeamMember::factory()->forTeam($team)->forEmployee($staffProfile)->create();

        Sanctum::actingAs($managerUser);

        $response = $this->postJson('/api/v1/dashboard/team-pulse/'.$staffProfile->id.'/nudge', [
            'message' => 'Hi Nudged Staff, ada blocker yang bisa kubantu hari ini?',
        ])->assertOk();

        $response
            ->assertJsonPath('data.staff_member_id', $staffProfile->id)
            ->assertJsonPath('data.staff_member_name', 'Nudged Staff')
            ->assertJsonPath('data.message', 'Hi Nudged Staff, ada blocker yang bisa kubantu hari ini?');

        Sanctum::actingAs($staffUser);

        $this->getJson('/api/v1/my-notifications?limit=5')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Tanya Kabar dari Manager')
            ->assertJsonPath('data.0.data.staff_member_id', $staffProfile->id)
            ->assertJsonPath('data.0.data.actor_name', 'Nudge Manager');
    }

    public function test_staff_cannot_access_team_pulse_endpoint(): void
    {
        [$staffUser] = $this->createUserWithRoleAndProfile('staff', 'Unauthorized Staff');

        Sanctum::actingAs($staffUser);

        $this->getJson('/api/v1/dashboard/team-pulse')->assertForbidden();
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

        $profile = StaffMemberProfile::factory()->forUser($user)->create([
            'code' => sprintf('%s%03d', strtoupper(substr($role, 0, 3)), $sequence),
            'identity_number' => str_pad((string) (93000000000000 + $sequence), 14, '0', STR_PAD_LEFT),
        ]);

        $profile->jobInformation()->create([
            'job_title' => 'Software Engineer',
            'team_id' => $teamId,
            'status' => 'active',
            'employment_type' => 'full_time',
            'work_location' => 'office',
            'start_date' => now()->subYear()->toDateString(),
            'monthly_salary' => 10000000,
            'review_template_id' => null,
        ]);

        return [$user, $profile];
    }
}
