<?php

namespace Tests\Feature\Attendance;

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

class OvertimeFormRequestTest extends TestCase
{
    use ActivatesLicense, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->activateTestLicense();
    }

    private function actingAsHr(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName('superadmin', 'sanctum'));
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_validates_status_value(): void
    {
        $this->actingAsHr();

        $response = $this->getJson('/api/v1/overtime?per_page=15&status=invalid_status');

        $response->assertUnprocessable();
    }

    public function test_validates_date_format(): void
    {
        $this->actingAsHr();

        $response = $this->getJson('/api/v1/overtime?per_page=15&date_from=not-a-date');

        $response->assertUnprocessable();
    }

    public function test_validates_per_page_is_integer(): void
    {
        $this->actingAsHr();

        $response = $this->getJson('/api/v1/overtime?per_page=abc');

        $response->assertUnprocessable();
    }

    public function test_accepts_valid_params(): void
    {
        $this->actingAsHr();

        $response = $this->getJson('/api/v1/overtime?per_page=15&status=pending&date_from=2026-01-01&date_to=2026-12-31');

        $response->assertOk();
    }

    public function test_accepts_search_param(): void
    {
        $this->actingAsHr();

        $response = $this->getJson('/api/v1/overtime?per_page=15&search=John');

        $response->assertOk();
    }
}
