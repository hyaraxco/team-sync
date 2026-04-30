<?php

namespace Tests\Feature\Payroll;

use App\Models\Attendance;
use App\Models\AttendancePeriod;
use App\Models\StaffMemberProfile;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PayrollReadinessTeamSummaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_team_summary_returns_correct_grouping(): void
    {
        Carbon::setTestNow('2026-04-15 09:00:00');
        $this->actingAsRole('hr');

        $month = Carbon::parse('2026-04-01');

        AttendancePeriod::create([
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'cutoff_date' => '2026-04-25',
            'status' => 'review',
        ]);

        $teamA = Team::factory()->create(['name' => 'Engineering']);
        $teamB = Team::factory()->create(['name' => 'Marketing']);

        $this->createActiveEmployeeWithAttendanceInTeam($month, $teamA);
        $this->createActiveEmployeeWithAttendanceInTeam($month, $teamA);
        $this->createActiveEmployeeWithAttendanceInTeam($month, $teamB);

        $response = $this->getJson('/api/v1/payrolls/readiness-dashboard/team-summary?salary_month=2026-04');

        $response->assertStatus(200);
        $response->assertJsonPath('success', true);
        $response->assertJsonStructure([
            'data' => [
                'salary_month',
                'teams' => [
                    '*' => ['team_name', 'total', 'ready', 'warning', 'blocked', 'coverage_pct'],
                ],
                'unassigned' => ['total', 'ready', 'warning', 'blocked', 'coverage_pct'],
            ],
        ]);

        $teams = $response->json('data.teams');
        $teamNames = array_column($teams, 'team_name');

        $this->assertContains('Engineering', $teamNames);
        $this->assertContains('Marketing', $teamNames);

        $engineering = collect($teams)->firstWhere('team_name', 'Engineering');
        $this->assertEquals(2, $engineering['total']);

        $marketing = collect($teams)->firstWhere('team_name', 'Marketing');
        $this->assertEquals(1, $marketing['total']);
    }

    public function test_permission_guard_rejects_non_payroll_create_user(): void
    {
        Carbon::setTestNow('2026-04-15 09:00:00');
        $this->actingAsRole('staff');

        $response = $this->getJson('/api/v1/payrolls/readiness-dashboard/team-summary?salary_month=2026-04');

        $response->assertStatus(403);
    }

    public function test_empty_data_returns_empty_teams_array(): void
    {
        Carbon::setTestNow('2026-04-15 09:00:00');
        $this->actingAsRole('hr');

        AttendancePeriod::create([
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'cutoff_date' => '2026-04-25',
            'status' => 'review',
        ]);

        $response = $this->getJson('/api/v1/payrolls/readiness-dashboard/team-summary?salary_month=2026-04');

        $response->assertStatus(200);
        $response->assertJsonPath('data.teams', []);
        $response->assertJsonPath('data.unassigned.total', 0);
    }

    private function actingAsRole(string $roleName): User
    {
        $user = User::factory()->create();
        $role = Role::findByName($roleName, 'sanctum');
        $user->assignRole($role);

        Sanctum::actingAs($user);

        return $user;
    }

    private function createActiveEmployeeWithAttendanceInTeam(Carbon $month, Team $team): StaffMemberProfile
    {
        return StaffMemberProfile::withoutSyncingToSearch(function () use ($month, $team) {
            $employee = StaffMemberProfile::factory()->create();
            $startDate = $month->copy()->startOfMonth();
            $endDate = $month->copy()->endOfMonth();

            $employee->jobInformation()->create([
                'staff_member_id' => $employee->id,
                'job_title' => 'Software Engineer',
                'years_experience' => 3,
                'status' => 'active',
                'employment_type' => 'full_time',
                'work_location' => 'remote',
                'start_date' => '2024-01-01',
                'monthly_salary' => 10000000,
                'skill_level' => 'intermediate',
                'team_id' => $team->id,
            ]);

            $cursor = $startDate->copy();
            while ($cursor->lte($endDate)) {
                if ($cursor->isWeekday()) {
                    Attendance::create([
                        'staff_member_id' => $employee->id,
                        'date' => $cursor->toDateString(),
                        'check_in' => $cursor->copy()->setTime(8, 0)->toDateTimeString(),
                        'check_out' => $cursor->copy()->setTime(17, 0)->toDateTimeString(),
                        'status' => 'present',
                        'work_location' => 'office',
                    ]);
                }
                $cursor->addDay();
            }

            return $employee;
        });
    }
}
