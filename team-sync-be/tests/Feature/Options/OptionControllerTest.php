<?php

namespace Tests\Feature\Options;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\ActivatesLicense;
use Tests\TestCase;

class OptionControllerTest extends TestCase
{
    use ActivatesLicense, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,
        ]);

        $this->activateTestLicense();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Permission Guards
    // ─────────────────────────────────────────────────────────────────────────

    public function test_unauthenticated_user_cannot_access_options(): void
    {
        $this->getJson('/api/v1/options/departments')
            ->assertUnauthorized();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Option Endpoints (auth:sanctum only, no permission requirement)
    // ─────────────────────────────────────────────────────────────────────────

    public function test_returns_department_options_for_authenticated_user(): void
    {
        $this->actingAsRole('staff');

        $this->getJson('/api/v1/options/departments')
            ->assertOk()
            ->assertJsonStructure(['success', 'message', 'data']);
    }

    public function test_returns_employment_type_options_for_authenticated_user(): void
    {
        $this->actingAsRole('staff');

        $this->getJson('/api/v1/options/employment-types')
            ->assertOk()
            ->assertJsonStructure(['success', 'message', 'data']);
    }

    public function test_returns_job_status_options_for_authenticated_user(): void
    {
        $this->actingAsRole('staff');

        $this->getJson('/api/v1/options/job-statuses')
            ->assertOk()
            ->assertJsonStructure(['success', 'message', 'data']);
    }

    public function test_returns_task_priority_options_for_authenticated_user(): void
    {
        $this->actingAsRole('staff');

        $this->getJson('/api/v1/options/task-priorities')
            ->assertOk()
            ->assertJsonStructure(['success', 'message', 'data']);
    }

    public function test_returns_task_status_options_for_authenticated_user(): void
    {
        $this->actingAsRole('staff');

        $this->getJson('/api/v1/options/task-statuses')
            ->assertOk()
            ->assertJsonStructure(['success', 'message', 'data']);
    }

    public function test_returns_leave_type_options_for_authenticated_user(): void
    {
        $this->actingAsRole('staff');

        $this->getJson('/api/v1/options/leave-types')
            ->assertOk()
            ->assertJsonStructure(['success', 'message', 'data']);
    }

    public function test_returns_work_location_options_for_authenticated_user(): void
    {
        $this->actingAsRole('staff');

        $this->getJson('/api/v1/options/work-locations')
            ->assertOk()
            ->assertJsonStructure(['success', 'message', 'data']);
    }

    public function test_returns_skill_level_options_for_authenticated_user(): void
    {
        $this->actingAsRole('staff');

        $this->getJson('/api/v1/options/skill-levels')
            ->assertOk()
            ->assertJsonStructure(['success', 'message', 'data']);
    }

    public function test_returns_religion_options_for_authenticated_user(): void
    {
        $this->actingAsRole('staff');

        $this->getJson('/api/v1/options/religions')
            ->assertOk()
            ->assertJsonStructure(['success', 'message', 'data']);
    }

    public function test_returns_marital_status_options_for_authenticated_user(): void
    {
        $this->actingAsRole('staff');

        $this->getJson('/api/v1/options/marital-statuses')
            ->assertOk()
            ->assertJsonStructure(['success', 'message', 'data']);
    }

    public function test_returns_blood_type_options_for_authenticated_user(): void
    {
        $this->actingAsRole('staff');

        $this->getJson('/api/v1/options/blood-types')
            ->assertOk()
            ->assertJsonStructure(['success', 'message', 'data']);
    }

    public function test_returns_ptkp_status_options_for_authenticated_user(): void
    {
        $this->actingAsRole('staff');

        $this->getJson('/api/v1/options/ptkp-statuses')
            ->assertOk()
            ->assertJsonStructure(['success', 'message', 'data']);
    }

    public function test_returns_project_task_template_options_for_authenticated_user(): void
    {
        $this->actingAsRole('staff');

        $this->getJson('/api/v1/options/project-task-templates')
            ->assertOk()
            ->assertJsonStructure(['success', 'message', 'data']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helper
    // ─────────────────────────────────────────────────────────────────────────

    private function actingAsRole(string $roleName): User
    {
        $user = User::factory()->create();
        $role = Role::findByName($roleName, 'sanctum');
        $user->assignRole($role);

        Sanctum::actingAs($user);

        return $user;
    }
}
