<?php

namespace Tests\Feature\Project;

use App\Enums\Department;
use App\Enums\EmploymentType;
use App\Enums\JobStatus;
use App\Enums\ProjectPriority;
use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Enums\TeamStatus;
use App\Enums\WorkLocation;
use App\Models\Project;
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
use Tests\TestCase;

class ProjectPolicyGapTest extends TestCase
{
    use RefreshDatabase;

    private Team $team;

    private Project $project;

    private User $managerUser;

    private User $hrUser;

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

        $this->team = Team::create([
            'name' => 'Engineering',
            'expected_size' => 5,
            'description' => 'Engineering team',
            'icon' => 'team-icons/activity.png',
            'status' => TeamStatus::ACTIVE->value,
            'department' => Department::DEVELOPMENT->value,
            'responsibilities' => ['development'],
        ]);

        [$this->managerUser] = $this->makeUser('manager', 'Manager', 'manager@test.com', $this->team, 'senior');
        [$this->hrUser] = $this->makeUser('hr', 'HR', 'hr@test.com', $this->team, 'mid');
        [$leaderUser, $leaderProfile] = $this->makeUser('staff', 'Leader', 'leader@test.com', $this->team, 'senior');

        $this->project = Project::create([
            'name' => 'Policy Test Project',
            'type' => ProjectType::WEB_DEVELOPMENT->value,
            'priority' => ProjectPriority::HIGH->value,
            'status' => ProjectStatus::ACTIVE->value,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(3)->toDateString(),
            'description' => 'Test project for policy.',
            'project_leader_id' => $leaderProfile->id,
        ]);
        $this->project->teams()->sync([$this->team->id => ['assigned_at' => now()]]);
    }

    // ─── HR cannot update project (isPrivilegedRole fix) ─────────────────

    public function test_hr_cannot_update_project(): void
    {
        Sanctum::actingAs($this->hrUser);

        $response = $this->putJson('/api/v1/projects/'.$this->project->id, [
            'name' => 'HR Attempted Rename',
            'type' => ProjectType::WEB_DEVELOPMENT->value,
            'priority' => ProjectPriority::HIGH->value,
            'status' => ProjectStatus::ACTIVE->value,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(3)->toDateString(),
            'description' => 'HR trying to edit.',
        ]);

        $response->assertStatus(403);
    }

    public function test_manager_can_update_project(): void
    {
        Sanctum::actingAs($this->managerUser);

        $response = $this->putJson('/api/v1/projects/'.$this->project->id, [
            'name' => 'Manager Renamed Project',
            'type' => ProjectType::WEB_DEVELOPMENT->value,
            'priority' => ProjectPriority::HIGH->value,
            'status' => ProjectStatus::ACTIVE->value,
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(3)->toDateString(),
            'description' => 'Manager editing project.',
        ]);

        $response->assertOk();
    }

    private function makeUser(string $role, string $name, string $email, Team $team, string $seniority): array
    {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => bcrypt('password'),
        ]);
        $user->assignRole($role);

        $profile = StaffMemberProfile::factory()->forUser($user)->create([
            'seniority_level' => $seniority,
        ]);

        $profile->jobInformation()->create([
            'staff_member_id' => $profile->id,
            'job_title' => 'Engineer',
            'team_id' => $team->id,
            'status' => JobStatus::ACTIVE->value,
            'employment_type' => EmploymentType::FULL_TIME->value,
            'work_location' => WorkLocation::OFFICE->value,
            'start_date' => now()->subYear()->toDateString(),
            'monthly_salary' => 10000000,
        ]);

        TeamMember::create([
            'team_id' => $team->id,
            'staff_member_id' => $profile->id,
            'joined_at' => now()->subYear(),
        ]);

        return [$user, $profile];
    }
}
