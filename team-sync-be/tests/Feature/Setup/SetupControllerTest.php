<?php

namespace Tests\Feature\Setup;

use App\Models\Company;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\ActivatesLicense;
use Tests\TestCase;

class SetupControllerTest extends TestCase
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

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_setup_status_returns_needs_setup_when_no_license_and_no_superadmin(): void
    {
        $this->getJson('/api/v1/setup/status')
            ->assertOk()
            ->assertJsonPath('data.needs_setup', true)
            ->assertJsonPath('data.has_license', false)
            ->assertJsonPath('data.has_superadmin', false);
    }

    public function test_setup_status_returns_no_setup_needed_when_all_configured(): void
    {
        $this->activateTestLicense();

        Company::query()->create([
            'name' => 'PT Team Sync',
            'slug' => 'team-sync',
            'domain' => 'teamsync.local',
            'timezone' => 'Asia/Jakarta',
            'locale' => 'id',
            'currency' => 'IDR',
            'is_active' => true,
            'settings' => [],
        ]);

        $user = User::factory()->create();
        $user->assignRole('superadmin');

        $this->getJson('/api/v1/setup/status')
            ->assertOk()
            ->assertJsonPath('data.needs_setup', false)
            ->assertJsonPath('data.has_license', true)
            ->assertJsonPath('data.has_company', true)
            ->assertJsonPath('data.has_superadmin', true);
    }

    public function test_doctor_endpoint_returns_health_checks(): void
    {
        $this->getJson('/api/v1/setup/doctor')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'healthy',
                    'checks' => [
                        '*' => ['label', 'status', 'message'],
                    ],
                ],
            ]);
    }

    public function test_bootstrap_creates_superadmin_and_returns_token(): void
    {
        $this->postJson('/api/v1/setup/bootstrap', [
            'name' => 'Admin Utama',
            'email' => 'admin@teamsync.local',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ])
            ->assertCreated()
            ->assertJsonPath('data.user.name', 'Admin Utama')
            ->assertJsonPath('data.user.email', 'admin@teamsync.local')
            ->assertJsonStructure(['data' => ['token']]);

        $this->assertDatabaseHas('users', ['email' => 'admin@teamsync.local']);

        $user = User::where('email', 'admin@teamsync.local')->first();
        $this->assertTrue($user->hasRole('superadmin'));
    }

    public function test_bootstrap_rejects_duplicate_setup(): void
    {
        $user = User::factory()->create();
        $user->assignRole('superadmin');

        $this->postJson('/api/v1/setup/bootstrap', [
            'name' => 'Another Admin',
            'email' => 'another@teamsync.local',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'SecurePass123!',
        ])
            ->assertStatus(409)
            ->assertJsonPath('success', false);
    }

    public function test_bootstrap_validates_required_fields(): void
    {
        $this->postJson('/api/v1/setup/bootstrap', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_bootstrap_validates_password_confirmation(): void
    {
        $this->postJson('/api/v1/setup/bootstrap', [
            'name' => 'Admin',
            'email' => 'admin@teamsync.local',
            'password' => 'SecurePass123!',
            'password_confirmation' => 'WrongConfirmation',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    }

    public function test_doctor_endpoint_returns_forbidden_when_setup_completed(): void
    {
        $this->activateTestLicense();

        Company::query()->create([
            'name' => 'PT Team Sync',
            'slug' => 'team-sync',
            'domain' => 'teamsync.local',
            'timezone' => 'Asia/Jakarta',
            'locale' => 'id',
            'currency' => 'IDR',
            'is_active' => true,
            'settings' => [],
        ]);

        $user = User::factory()->create();
        $user->assignRole('superadmin');

        $this->getJson('/api/v1/setup/doctor')
            ->assertForbidden()
            ->assertJsonPath('success', false);
    }

    public function test_doctor_endpoint_accessible_when_no_superadmin(): void
    {
        $this->getJson('/api/v1/setup/doctor')
            ->assertOk()
            ->assertJsonPath('data.healthy', true)
            ->assertJsonStructure([
                'data' => [
                    'healthy',
                    'checks' => [
                        '*' => ['label', 'status', 'message'],
                    ],
                ],
            ]);
    }
}
