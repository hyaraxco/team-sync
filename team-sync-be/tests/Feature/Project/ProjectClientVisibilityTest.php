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

class ProjectClientVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private Team $team;

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

        $this->project = Project::create([
            'name' => 'LP3ES Project',
            'type' => ProjectType::MOBILE_APP->value,
            'priority' => ProjectPriority::HIGH->value,
            'status' => ProjectStatus::ACTIVE->value,
            'start_date' => now()->toDateString(),
            'budget' => 2500000000,
            'client_name' => 'LP3ES',
            'client_contact' => 'PIC LP3ES / 0812',
        ]);
        $this->project->teams()->sync([$this->team->id => ['assigned_at' => now()]]);
    }

    public function test_manager_sees_client_identity(): void
    {
        Sanctum::actingAs($this->userWithRole('manager'));

        $this->getJson('/api/v1/projects/'.$this->project->id)
            ->assertOk()
            ->assertJsonPath('data.client_name', 'LP3ES')
            ->assertJsonPath('data.client_contact', 'PIC LP3ES / 0812');
    }

    public function test_hr_sees_client_identity(): void
    {
        Sanctum::actingAs($this->userWithRole('hr'));

        $this->getJson('/api/v1/projects/'.$this->project->id)
            ->assertOk()
            ->assertJsonPath('data.client_name', 'LP3ES');
    }

    public function test_staff_on_the_project_does_not_receive_client_identity(): void
    {
        Sanctum::actingAs($this->userWithRole('staff', onProjectTeam: true));

        $response = $this->getJson('/api/v1/projects/'.$this->project->id)->assertOk();

        $this->assertArrayNotHasKey('client_name', $response->json('data'));
        $this->assertArrayNotHasKey('client_contact', $response->json('data'));
    }

    public function test_staff_on_the_project_does_not_receive_budget(): void
    {
        Sanctum::actingAs($this->userWithRole('staff', onProjectTeam: true));

        $response = $this->getJson('/api/v1/projects/'.$this->project->id)->assertOk();

        $this->assertArrayNotHasKey('budget', $response->json('data'));
    }

    public function test_staff_project_list_omits_budget_and_client(): void
    {
        Sanctum::actingAs($this->userWithRole('staff', onProjectTeam: true));

        $project = $this->getJson('/api/v1/projects')->assertOk()->json('data.0');

        $this->assertArrayNotHasKey('budget', $project);
        $this->assertArrayNotHasKey('client_name', $project);
    }

    public function test_project_leader_who_is_staff_sees_client_identity(): void
    {
        $leader = $this->userWithRole('staff', onProjectTeam: true);
        $this->project->update(['project_leader_id' => $leader->staffMemberProfile->id]);
        Sanctum::actingAs($leader);

        $this->getJson('/api/v1/projects/'.$this->project->id)
            ->assertOk()
            ->assertJsonPath('data.client_name', 'LP3ES')
            ->assertJsonPath('data.budget', 2500000000);
    }

    public function test_client_identity_is_stored_from_the_create_path(): void
    {
        $this->project->update([
            'client_name' => 'Bank Mandiri',
            'client_contact' => 'vendor@mandiri.test',
        ]);

        $this->assertDatabaseHas('projects', [
            'id' => $this->project->id,
            'client_name' => 'Bank Mandiri',
            'client_contact' => 'vendor@mandiri.test',
        ]);
    }

    private function userWithRole(string $role, bool $onProjectTeam = false): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        // Staff reach a project only through team membership; managers and HR bypass it.
        if ($onProjectTeam) {
            $profile = StaffMemberProfile::factory()->forUser($user)->create();

            $profile->jobInformation()->create([
                'staff_member_id' => $profile->id,
                'job_title' => 'Engineer',
                'team_id' => $this->team->id,
                'status' => JobStatus::ACTIVE->value,
                'employment_type' => EmploymentType::FULL_TIME->value,
                'work_location' => WorkLocation::OFFICE->value,
                'start_date' => now()->subYear()->toDateString(),
                'monthly_salary' => 10000000,
            ]);

            TeamMember::create([
                'team_id' => $this->team->id,
                'staff_member_id' => $profile->id,
                'joined_at' => now()->subYear(),
            ]);
        }

        return $user;
    }
}
