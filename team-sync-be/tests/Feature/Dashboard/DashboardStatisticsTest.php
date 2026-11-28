<?php

namespace Tests\Feature\Dashboard;

use App\Models\Attendance;
use App\Models\Company;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\ProjectTeam;
use App\Models\StaffMemberProfile;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\ActivatesLicense;
use Tests\TestCase;

class DashboardStatisticsTest extends TestCase
{
    use ActivatesLicense, RefreshDatabase;

    private int $profileSequence = 1;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-07-15 10:00:00');

        $this->seed([
            RoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,
        ]);

        Company::query()->create([
            'name' => 'PT Test',
            'slug' => 'test',
            'domain' => 'test.local',
            'timezone' => 'Asia/Jakarta',
            'locale' => 'id',
            'currency' => 'IDR',
            'is_active' => true,
            'settings' => [],
        ]);

        $this->activateTestLicense(['attendance', 'performance']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_get_statistics_returns_correct_counts(): void
    {
        [$hrUser] = $this->createUserWithRoleAndProfile('hr', 'HR Admin');

        // Create employees: 2 this month, 1 last month
        $this->createUserWithRoleAndProfile('staff', 'Staff One');
        $this->createUserWithRoleAndProfile('staff', 'Staff Two');
        [$_, $oldProfile] = $this->createUserWithRoleAndProfile('staff', 'Old Staff');
        StaffMemberProfile::where('id', $oldProfile->id)
            ->update(['created_at' => now()->subMonths(2)]);

        // Create teams: 1 this month, 1 old
        Team::factory()->active()->create(['created_at' => now()]);
        Team::factory()->active()->create(['created_at' => now()->subMonths(2)]);

        // Create attendance this month
        Attendance::factory()->create([
            'staff_member_id' => $oldProfile->id,
            'date' => now()->toDateString(),
            'status' => 'present',
            'created_at' => now(),
        ]);

        // Create project
        $project = Project::factory()->create([
            'project_leader_id' => $oldProfile->id,
            'status' => 'active',
            'created_at' => now(),
        ]);

        // Create completed task (yesterday)
        ProjectTask::query()->create([
            'project_id' => $project->id,
            'name' => 'Done task',
            'description' => 'A completed task',
            'assignee_id' => $oldProfile->id,
            'priority' => 'medium',
            'status' => 'done',
            'due_date' => now()->toDateString(),
            'updated_at' => Carbon::yesterday(),
        ]);

        // Non-done task must NOT be counted as completed
        ProjectTask::query()->create([
            'project_id' => $project->id,
            'name' => 'WIP task',
            'description' => 'Still in progress',
            'assignee_id' => $oldProfile->id,
            'priority' => 'medium',
            'status' => 'in_progress',
            'due_date' => now()->toDateString(),
        ]);

        Sanctum::actingAs($hrUser);

        $response = $this->getJson('/api/v1/dashboard/statistics');
        $response->assertOk();

        $data = $response->json('data');

        // 4 total profiles: hrUser + 3 staff (setUp creates none)
        $this->assertSame(4, $data['employees']['total']);
        // 3 created this month (hrUser, Staff One, Staff Two); Old Staff was backdated
        $this->assertSame(3, $data['employees']['added_this_month']);

        $this->assertSame(2, $data['teams']['total']);
        $this->assertSame(1, $data['teams']['new_teams']);

        $this->assertSame(1, $data['tasks']['completed']);

        $this->assertSame(1, $data['projects']['active']);
        $this->assertSame(1, $data['projects']['new_projects']);

        $this->assertSame(1.7, $data['attendance']['rate']);
        $this->assertArrayHasKey('change', $data['attendance']);
    }

    public function test_get_employee_statistics_returns_attendance_and_tasks(): void
    {
        [$staffUser, $staffProfile] = $this->createUserWithRoleAndProfile('staff', 'My Staff');

        // Attendance with check_in/check_out for hours calculation
        Attendance::factory()->create([
            'staff_member_id' => $staffProfile->id,
            'date' => now()->toDateString(),
            'status' => 'present',
            'check_in' => now()->setTime(9, 0)->toDateTimeString(),
            'check_out' => now()->setTime(17, 0)->toDateTimeString(),
        ]);

        Attendance::factory()->create([
            'staff_member_id' => $staffProfile->id,
            'date' => now()->subDay()->toDateString(),
            'status' => 'absent',
            'check_in' => null,
            'check_out' => null,
        ]);

        // Tasks
        $project = Project::factory()->create([
            'project_leader_id' => $staffProfile->id,
            'status' => 'active',
        ]);

        ProjectTask::query()->create([
            'project_id' => $project->id,
            'name' => 'Done task',
            'description' => 'Completed',
            'assignee_id' => $staffProfile->id,
            'priority' => 'medium',
            'status' => 'done',
            'due_date' => now()->toDateString(),
            'updated_at' => Carbon::yesterday(),
        ]);

        ProjectTask::query()->create([
            'project_id' => $project->id,
            'name' => 'WIP task',
            'description' => 'In progress',
            'assignee_id' => $staffProfile->id,
            'priority' => 'medium',
            'status' => 'in_progress',
            'due_date' => now()->addDays(3)->toDateString(),
        ]);

        // Project assignment via team
        $team = Team::factory()->active()->create();
        TeamMember::factory()->forTeam($team)->forEmployee($staffProfile)->create();
        ProjectTeam::create([
            'project_id' => $project->id,
            'team_id' => $team->id,
        ]);

        Sanctum::actingAs($staffUser);

        $response = $this->getJson('/api/v1/dashboard/my-statistics');
        $response->assertOk();

        $data = $response->json('data');

        $this->assertSame(1, $data['attendance']['present_days']);
        $this->assertSame(1, $data['attendance']['absent_days']);
        // TIMESTAMPDIFF shim: 8 hours = 480 minutes → 8.0 hours
        $this->assertEquals(8.0, $data['attendance']['total_hours']);

        $this->assertSame(1, $data['tasks']['done']);
        $this->assertSame(1, $data['tasks']['in_progress']);
        $this->assertSame(0, $data['tasks']['todo']);

        $this->assertSame(1, $data['projects']['assigned_active']);
        $this->assertSame(1, $data['projects']['led_active']);
    }

    public function test_today_attendance_overview_shows_checked_in_and_absent(): void
    {
        [$hrUser] = $this->createUserWithRoleAndProfile('hr', 'HR Overview');
        [$_, $presentProfile] = $this->createUserWithRoleAndProfile('staff', 'Present Staff');
        [$_, $absentProfile] = $this->createUserWithRoleAndProfile('staff', 'Absent Staff');

        Attendance::factory()->create([
            'staff_member_id' => $presentProfile->id,
            'date' => now()->toDateString(),
            'status' => 'present',
            'check_in' => now()->setTime(9, 0)->toDateTimeString(),
        ]);

        Sanctum::actingAs($hrUser);

        $response = $this->getJson('/api/v1/dashboard/today-attendance-overview');
        $response->assertOk();

        $data = $response->json('data');

        $this->assertSame(now()->toDateString(), $data['date']);
        // 3 total profiles (hr + 2 staff), but only presentProfile checked in
        $this->assertSame(1, $data['checked_in_count']);
        $this->assertSame(2, $data['not_checked_in_count']);
        $this->assertSame(1, $data['status_breakdown']['present']);
    }

    public function test_get_statistics_handles_empty_database(): void
    {
        [$hrUser] = $this->createUserWithRoleAndProfile('hr', 'HR Empty');

        // BUG: crossJoin with no teams → employees.total = 0 even when employees exist
        Sanctum::actingAs($hrUser);

        $response = $this->getJson('/api/v1/dashboard/statistics');
        $response->assertOk();

        $data = $response->json('data');

        $this->assertSame(0, $data['employees']['total']);
        $this->assertSame(0, $data['tasks']['completed']);
        $this->assertSame(0, $data['projects']['active']);
        $this->assertSame(0, $data['performance']['promotion_eligible']);
        $this->assertSame(0, $data['performance']['pip_required']);
    }

    /**
     * @return array{0: User, 1: StaffMemberProfile}
     */
    private function createUserWithRoleAndProfile(string $role, string $name): array
    {
        $sequence = $this->profileSequence++;

        $user = User::factory()->create([
            'name' => $name,
            'email' => sprintf('dash.%s.%d@example.test', strtolower(str_replace(' ', '.', $role)), $sequence),
        ]);
        $user->syncRoles([$role]);

        $profile = StaffMemberProfile::factory()->forUser($user)->create([
            'code' => sprintf('DS%03d', $sequence),
            'identity_number' => str_pad((string) (95000000000000 + $sequence), 14, '0', STR_PAD_LEFT),
        ]);

        $profile->jobInformation()->create([
            'job_title' => 'Engineer',
            'status' => 'active',
            'employment_type' => 'full_time',
            'work_location' => 'office',
            'start_date' => now()->subYear()->toDateString(),
            'monthly_salary' => 10000000,
        ]);

        return [$user, $profile];
    }
}
