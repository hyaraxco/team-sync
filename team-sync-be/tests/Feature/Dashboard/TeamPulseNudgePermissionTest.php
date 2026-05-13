<?php

namespace Tests\Feature\Dashboard;

use App\Models\Company;
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

/**
 * Verifies that the POST /dashboard/team-pulse/{staffMemberId}/nudge endpoint
 * enforces permission checks: only users with 'review-manager-submit' permission
 * (manager/superadmin) may nudge team members.
 */
class TeamPulseNudgePermissionTest extends TestCase
{
    use ActivatesLicense, RefreshDatabase;

    private int $sequence = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,
        ]);

        Company::query()->create([
            'name' => 'PT Permission Test',
            'slug' => 'perm-test',
            'domain' => 'permtest.local',
            'timezone' => 'Asia/Jakarta',
            'locale' => 'id',
            'currency' => 'IDR',
            'is_active' => true,
            'settings' => [],
        ]);

        $this->activateTestLicense(['attendance', 'performance']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_staff_user_without_permission_gets_403_on_nudge(): void
    {
        [$managerUser] = $this->createUserWithRole('manager', 'Nudge Mgr');
        $team = Team::factory()->active()->create(['team_lead_id' => $managerUser->id]);
        [$staffUser, $staffProfile] = $this->createUserWithRole('staff', 'Nudge Staff', $team->id);
        TeamMember::factory()->forTeam($team)->forEmployee($staffProfile)->create();

        Sanctum::actingAs($staffUser);

        $this->postJson("/api/v1/dashboard/team-pulse/{$staffProfile->id}/nudge", [
            'message' => 'Hello from staff',
        ])->assertForbidden();
    }

    public function test_user_with_permission_can_nudge_team_member(): void
    {
        [$managerUser] = $this->createUserWithRole('manager', 'Nudge Mgr 2');
        $team = Team::factory()->active()->create(['team_lead_id' => $managerUser->id]);
        [$staffUser, $staffProfile] = $this->createUserWithRole('staff', 'Nudge Staff 2', $team->id);
        TeamMember::factory()->forTeam($team)->forEmployee($staffProfile)->create();

        Sanctum::actingAs($managerUser);

        $this->postJson("/api/v1/dashboard/team-pulse/{$staffProfile->id}/nudge", [
            'message' => 'Hi, any blockers today?',
        ])->assertOk();
    }

    public function test_unauthenticated_user_gets_401_on_nudge(): void
    {
        $this->postJson('/api/v1/dashboard/team-pulse/1/nudge', [
            'message' => 'Hello',
        ])->assertUnauthorized();
    }

    /**
     * @return array{0: User, 1: StaffMemberProfile}
     */
    private function createUserWithRole(string $role, string $name, ?int $teamId = null): array
    {
        $seq = ++$this->sequence;

        $user = User::factory()->create([
            'name' => $name,
            'email' => sprintf('nudge.%d@test.example', $seq),
        ]);
        $user->syncRoles([$role]);

        $profile = StaffMemberProfile::factory()->forUser($user)->create([
            'code' => sprintf('NDG%03d', $seq),
            'identity_number' => str_pad((string) (94000000000000 + $seq), 14, '0', STR_PAD_LEFT),
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
