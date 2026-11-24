<?php

namespace Tests\Feature\Performance;

use App\Models\JobInformation;
use App\Models\PerformanceGoal;
use App\Models\StaffMemberProfile;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\ActivatesLicense;
use Tests\TestCase;

class TeamGoalsListingScopeTest extends TestCase
{
    use ActivatesLicense, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->activateTestLicense();
        Role::findOrCreate('staff', 'sanctum');
        foreach (['goal-create-own', 'goal-assign-team'] as $p) {
            Permission::findOrCreate($p, 'sanctum');
        }
    }

    private function actor(string $role = 'staff', bool $profile = true): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate($role, 'sanctum'));
        $user->givePermissionTo('goal-create-own');
        if ($role !== 'staff') {
            $user->givePermissionTo('goal-assign-team');
        }
        if ($profile) {
            StaffMemberProfile::withoutSyncingToSearch(
                fn () => StaffMemberProfile::factory()->create(['user_id' => $user->id])
            );
        }
        Sanctum::actingAs($user);

        return $user;
    }

    private function employeeWithGoal(?int $teamId = null): PerformanceGoal
    {
        $profile = StaffMemberProfile::withoutSyncingToSearch(
            fn () => StaffMemberProfile::factory()->create()
        );
        if ($teamId) {
            TeamMember::factory()->create([
                'team_id' => $teamId,
                'staff_member_id' => $profile->id,
            ]);
        }

        return PerformanceGoal::factory()->create([
            'staff_member_id' => $profile->id,
        ]);
    }

    public function test_manager_sees_goals_of_led_team_members(): void
    {
        $manager = $this->actor('manager');
        $team = Team::factory()->create(['team_lead_id' => $manager->id]);
        $memberGoal = $this->employeeWithGoal($team->id);
        $outsiderGoal = $this->employeeWithGoal();

        $response = $this->getJson('/api/v1/performance/goals/team-goals');
        $response->assertOk();

        $ids = collect($response->json('data.data'))->pluck('id')->all();
        $this->assertContains($memberGoal->id, $ids);
        $this->assertNotContains($outsiderGoal->id, $ids);
    }

    public function test_manager_sees_goals_via_job_information_team(): void
    {
        $manager = $this->actor('manager');
        $team = Team::factory()->create(['team_lead_id' => $manager->id]);

        $profile = StaffMemberProfile::withoutSyncingToSearch(
            fn () => StaffMemberProfile::factory()->create()
        );
        JobInformation::factory()->create([
            'team_id' => $team->id,
            'staff_member_id' => $profile->id,
        ]);
        $goal = PerformanceGoal::factory()->create([
            'staff_member_id' => $profile->id,
        ]);

        $ids = collect(
            $this->getJson('/api/v1/performance/goals/team-goals')->json('data.data')
        )->pluck('id')->all();

        $this->assertContains($goal->id, $ids);
    }

    public function test_manager_does_not_see_goals_of_departed_members(): void
    {
        $manager = $this->actor('manager');
        $team = Team::factory()->create(['team_lead_id' => $manager->id]);

        $profile = StaffMemberProfile::withoutSyncingToSearch(
            fn () => StaffMemberProfile::factory()->create()
        );
        TeamMember::factory()->create([
            'team_id' => $team->id,
            'staff_member_id' => $profile->id,
            'left_at' => now(),
        ]);
        $goal = PerformanceGoal::factory()->create([
            'staff_member_id' => $profile->id,
        ]);

        $ids = collect(
            $this->getJson('/api/v1/performance/goals/team-goals')->json('data.data')
        )->pluck('id')->all();

        $this->assertNotContains($goal->id, $ids);
    }

    public function test_hr_sees_all_goals_company_wide(): void
    {
        $goalA = $this->employeeWithGoal();
        $goalB = $this->employeeWithGoal();

        $this->actor('hr');
        $ids = collect(
            $this->getJson('/api/v1/performance/goals/team-goals')->json('data.data')
        )->pluck('id')->all();

        $this->assertContains($goalA->id, $ids);
        $this->assertContains($goalB->id, $ids);
    }

    public function test_superadmin_sees_all_goals_company_wide(): void
    {
        $goal = $this->employeeWithGoal();

        $this->actor('superadmin');
        $ids = collect(
            $this->getJson('/api/v1/performance/goals/team-goals')->json('data.data')
        )->pluck('id')->all();

        $this->assertContains($goal->id, $ids);
    }
}
