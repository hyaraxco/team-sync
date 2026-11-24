<?php

namespace Tests\Feature\Performance;

use App\Models\JobInformation;
use App\Models\PerformanceGoal;
use App\Models\StaffMemberProfile;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Policies\TeamPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\ActivatesLicense;
use Tests\TestCase;

class GoalAuthorizationTest extends TestCase
{
    use ActivatesLicense, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->activateTestLicense();
        Notification::fake();
        Role::findOrCreate('staff', 'sanctum');
        foreach (['goal-create-own', 'goal-assign-team', 'team-edit', 'team-delete'] as $permission) {
            Permission::findOrCreate($permission, 'sanctum');
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
            StaffMemberProfile::withoutSyncingToSearch(fn () => StaffMemberProfile::factory()->create(['user_id' => $user->id]));
        }
        Sanctum::actingAs($user);

        return $user;
    }

    private function payload(int $staffMemberId): array
    {
        return [
            'staff_member_id' => $staffMemberId,
            'title' => 'Authorized goal',
            'goal_type' => 'okr',
            'start_date' => '2026-01-01',
            'due_date' => '2026-12-01',
        ];
    }

    public function test_staff_cannot_forge_owner_or_assigner(): void
    {
        $other = $this->actor()->staffMemberProfile;
        $user = $this->actor();
        $this->postJson('/api/v1/performance/goals', $this->payload($other->id))->assertForbidden();
        $this->assertDatabaseCount('performance_goals', 0);
        $this->postJson('/api/v1/performance/goals', $this->payload($user->staffMemberProfile->id) + [
            'assigned_by' => $other->id,
            'created_by' => $other->user_id,
        ])->assertCreated()->assertJsonPath('data.assigned_by', null)
            ->assertJsonPath('data.created_by', $user->id);
    }

    public function test_manager_assignment_requires_current_membership_in_a_led_team(): void
    {
        $employee = $this->actor()->staffMemberProfile;
        $manager = $this->actor('manager');
        $team = Team::factory()->create(['team_lead_id' => $manager->id]);
        $this->postJson('/api/v1/performance/goals', $this->payload($employee->id))->assertForbidden();
        $membership = TeamMember::factory()->create(['team_id' => $team->id, 'staff_member_id' => $employee->id]);
        $this->postJson('/api/v1/performance/goals', $this->payload($employee->id) + ['assigned_by' => $employee->id])
            ->assertCreated()->assertJsonPath('data.assigned_by', $manager->staffMemberProfile->id);
        $membership->update(['left_at' => now()]);
        $this->postJson('/api/v1/performance/goals', $this->payload($employee->id))->assertForbidden();
        $membership->update(['left_at' => null]);
        $membership->delete();
        $this->postJson('/api/v1/performance/goals', $this->payload($employee->id))->assertForbidden();
        JobInformation::factory()->create(['team_id' => $team->id, 'staff_member_id' => $employee->id]);
        $this->postJson('/api/v1/performance/goals', $this->payload($employee->id))->assertCreated();
        $team->delete();
        $this->postJson('/api/v1/performance/goals', $this->payload($employee->id))->assertForbidden();
    }

    public function test_goal_id_access_uses_current_team_not_assigner(): void
    {
        $owner = $this->actor();
        $goal = PerformanceGoal::factory()->create(['staff_member_id' => $owner->staffMemberProfile->id]);
        $this->getJson("/api/v1/performance/goals/{$goal->id}")->assertOk();
        $manager = $this->actor('manager');
        $goal->update(['assigned_by' => $manager->staffMemberProfile->id]);
        $url = "/api/v1/performance/goals/{$goal->id}";
        $this->getJson($url)->assertForbidden();
        $this->putJson($url, ['title' => 'Forged'])->assertForbidden();
        $this->deleteJson($url)->assertForbidden();
        $this->getJson("{$url}/updates")->assertForbidden();
        $this->postJson("{$url}/update-progress", ['update_type' => 'completion', 'completion_percentage' => 50])->assertForbidden();
        $this->assertDatabaseHas('performance_goals', ['id' => $goal->id, 'title' => $goal->title]);
        $this->assertDatabaseCount('performance_goal_updates', 0);
        $unledTeam = Team::factory()->create();
        TeamMember::factory()->create(['team_id' => $unledTeam->id, 'staff_member_id' => $manager->staffMemberProfile->id]);
        TeamMember::factory()->create(['team_id' => $unledTeam->id, 'staff_member_id' => $owner->staffMemberProfile->id]);
        $this->getJson($url)->assertForbidden();
        $team = Team::factory()->create(['team_lead_id' => $manager->id]);
        TeamMember::factory()->create(['team_id' => $team->id, 'staff_member_id' => $owner->staffMemberProfile->id]);
        $this->getJson($url)->assertOk();
        $this->putJson($url, ['title' => 'Authorized'])->assertOk();
        $this->postJson("{$url}/update-progress", ['update_type' => 'completion', 'completion_percentage' => 50])->assertCreated();
        $this->getJson("{$url}/updates")->assertOk();
        $this->deleteJson($url)->assertOk();
    }

    public function test_team_leadership_does_not_replace_goal_assignment_permission(): void
    {
        $owner = $this->actor()->staffMemberProfile;
        $staff = $this->actor();
        $team = Team::factory()->create(['team_lead_id' => $staff->id]);
        TeamMember::factory()->create(['team_id' => $team->id, 'staff_member_id' => $owner->id]);
        $goal = PerformanceGoal::factory()->create(['staff_member_id' => $owner->id]);
        $this->postJson('/api/v1/performance/goals', $this->payload($owner->id))->assertForbidden();
        $this->getJson("/api/v1/performance/goals/{$goal->id}")->assertForbidden();
        $staff->revokePermissionTo('goal-create-own');
        $this->postJson('/api/v1/performance/goals', $this->payload($staff->staffMemberProfile->id))->assertForbidden();
    }

    public function test_privileged_roles_keep_company_wide_access_without_a_profile(): void
    {
        $owner = $this->actor()->staffMemberProfile;
        foreach (['hr', 'superadmin'] as $role) {
            $this->actor($role, false);
            $response = $this->postJson('/api/v1/performance/goals', $this->payload($owner->id));
            $response->assertCreated()->assertJsonPath('data.assigned_by', null);
            $url = '/api/v1/performance/goals/'.$response->json('data.id');
            $this->getJson($url)->assertOk();
            $this->putJson($url, ['title' => 'HR update'])->assertOk();
            $this->deleteJson($url)->assertOk();
        }
        $this->actor('staff', false);
        $this->postJson('/api/v1/performance/goals', $this->payload($owner->id))->assertForbidden();
    }

    public function test_team_mutations_require_permission_and_leadership_or_privileged_role(): void
    {
        $manager = $this->actor('manager');
        $manager->givePermissionTo(['team-edit', 'team-delete']);
        $own = Team::factory()->create(['team_lead_id' => $manager->id]);
        $other = Team::factory()->create();
        $policy = new TeamPolicy;
        foreach (['update', 'delete', 'manageMember'] as $action) {
            $this->assertTrue($policy->$action($manager, $own)->allowed());
            $this->assertTrue($policy->$action($manager, $other)->denied());
        }
        $this->putJson("/api/v1/teams/{$other->id}", ['name' => 'Forged'])->assertForbidden();
        $this->deleteJson("/api/v1/teams/{$other->id}")->assertForbidden();

        $employee = StaffMemberProfile::withoutSyncingToSearch(function () {
            $emp = StaffMemberProfile::factory()->create();
            JobInformation::factory()->forEmployee($emp)->create(['team_id' => null]);

            return $emp;
        });
        $this->postJson("/api/v1/teams/{$other->id}/add-member", ['staff_member_id' => $employee->id])->assertForbidden();
        $this->postJson("/api/v1/teams/{$other->id}/remove-member", ['staff_member_id' => $employee->id])->assertForbidden();

        // Own team member management allowed for manager
        $this->postJson("/api/v1/teams/{$own->id}/add-member", ['staff_member_id' => $employee->id])->assertOk();
        $this->assertDatabaseHas('team_members', [
            'team_id' => $own->id,
            'staff_member_id' => $employee->id,
            'left_at' => null,
        ]);
        $this->postJson("/api/v1/teams/{$own->id}/remove-member", ['staff_member_id' => $employee->id])->assertOk();
        $this->assertDatabaseMissing('team_members', [
            'team_id' => $own->id,
            'staff_member_id' => $employee->id,
            'left_at' => null,
        ]);

        foreach (['hr', 'superadmin'] as $role) {
            $user = $this->actor($role, false);
            $this->assertTrue($policy->update($user, $other)->denied());
            $this->assertTrue($policy->delete($user, $other)->denied());
            $this->assertTrue($policy->manageMember($user, $other)->denied());
            $user->givePermissionTo(['team-edit', 'team-delete']);
            $this->assertTrue($policy->update($user, $other)->allowed());
            $this->assertTrue($policy->delete($user, $other)->allowed());
            $this->assertTrue($policy->manageMember($user, $other)->allowed());
        }
        $manager->revokePermissionTo(['team-edit', 'team-delete']);
        $this->assertTrue($policy->update($manager, $own)->denied());
        $this->assertTrue($policy->delete($manager, $own)->denied());
        $this->assertTrue($policy->manageMember($manager, $own)->denied());
    }
}
