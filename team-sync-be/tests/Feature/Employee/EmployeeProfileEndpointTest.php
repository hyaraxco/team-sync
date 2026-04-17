<?php

namespace Tests\Feature\Employee;

use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Database\Seeders\MinimalPayrollE2ESeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class EmployeeProfileEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MinimalPayrollE2ESeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_employee_my_profile_payload_matches_the_fields_used_by_the_ui(): void
    {
        Sanctum::actingAs(User::where('email', 'agung@teamsync.com')->firstOrFail());

        $this->getJson('/api/v1/my-profile')
            ->assertOk()
            ->assertJsonFragment(['code' => 'EMP001'])
            ->assertJsonFragment(['email' => 'agung@teamsync.com'])
            ->assertJsonFragment(['full_name' => 'Agung Emergency Contact'])
            ->assertJsonFragment(['job_title' => 'Software Engineer']);
    }

    public function test_internal_admin_roles_can_access_their_own_profile_payload(): void
    {
        $cases = [
            ['email' => 'tasyia@teamsync.com', 'code' => 'HR001'],
            ['email' => 'dwimeta@teamsync.com', 'code' => 'FIN001'],
            ['email' => 'yudhis@teamsync.com', 'code' => 'MGR001'],
        ];

        foreach ($cases as $case) {
            Sanctum::actingAs(User::where('email', $case['email'])->firstOrFail());

            $this->getJson('/api/v1/my-profile')
                ->assertOk()
                ->assertJsonFragment(['code' => $case['code']])
                ->assertJsonFragment(['email' => $case['email']]);
        }
    }

    public function test_my_team_endpoint_falls_back_to_active_team_member_when_job_information_team_is_missing(): void
    {
        $user = User::where('email', 'agung@teamsync.com')->firstOrFail();
        $employee = $user->employeeProfile;

        $teamId = $employee->jobInformation?->team_id
            ?? Team::query()->value('id')
            ?? Team::factory()->create()->id;

        TeamMember::query()->updateOrCreate(
            [
                'team_id' => $teamId,
                'employee_id' => $employee->id,
            ],
            [
                'joined_at' => now(),
                'left_at' => null,
            ]
        );

        $activeTeamMembership = TeamMember::query()
            ->where('employee_id', $employee->id)
            ->whereNull('left_at')
            ->with('team')
            ->orderByDesc('joined_at')
            ->firstOrFail();

        $employee->jobInformation()->update([
            'team_id' => null,
        ]);

        Sanctum::actingAs($user);

        $this->getJson('/api/v1/my-team')
            ->assertOk()
            ->assertJsonPath('data.id', $activeTeamMembership->team_id)
            ->assertJsonPath('data.name', $activeTeamMembership->team->name);

        $this->assertSame(
            (int) $activeTeamMembership->team_id,
            (int) $employee->jobInformation()->firstOrFail()->team_id
        );
    }
}
