<?php

namespace Tests\Feature\Meeting;

use App\Enums\Department;
use App\Models\Company;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Verifies that POST /meetings (store) enforces permission checks:
 * only users with 'meeting-create' permission (HR, superadmin) may create meetings.
 */
class MeetingCreatePermissionTest extends TestCase
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

        Company::query()->create([
            'name' => 'PT Meeting Test',
            'slug' => 'meeting-test',
            'domain' => 'meetingtest.local',
            'timezone' => 'Asia/Jakarta',
            'locale' => 'id',
            'currency' => 'IDR',
            'is_active' => true,
            'settings' => [],
        ]);

        Queue::fake();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_staff_user_without_meeting_create_gets_403(): void
    {
        $this->actingAsRole('staff');

        $this->postJson('/api/v1/meetings', $this->validPayload())
            ->assertForbidden();
    }

    public function test_manager_without_meeting_create_gets_403(): void
    {
        $this->actingAsRole('manager');

        $this->postJson('/api/v1/meetings', $this->validPayload())
            ->assertForbidden();
    }

    public function test_finance_without_meeting_create_gets_403(): void
    {
        $this->actingAsRole('finance');

        $this->postJson('/api/v1/meetings', $this->validPayload())
            ->assertForbidden();
    }

    public function test_hr_with_meeting_create_can_create_meeting(): void
    {
        $this->actingAsRole('hr');

        $response = $this->postJson('/api/v1/meetings', $this->validPayload())
            ->assertStatus(201);

        $response->assertJsonStructure([
            'success',
            'message',
            'data' => ['id', 'title'],
        ]);
    }

    public function test_superadmin_with_meeting_create_can_create_meeting(): void
    {
        $this->actingAsRole('superadmin');

        $this->postJson('/api/v1/meetings', $this->validPayload())
            ->assertStatus(201);
    }

    public function test_unauthenticated_user_gets_401(): void
    {
        $this->postJson('/api/v1/meetings', $this->validPayload())
            ->assertUnauthorized();
    }

    private function actingAsRole(string $roleName): User
    {
        $user = User::factory()->create();
        $role = Role::findByName($roleName, 'sanctum');
        $user->assignRole($role);

        Sanctum::actingAs($user);

        return $user;
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Quarterly All-Hands',
            'description' => 'Company-wide quarterly update.',
            'scheduled_at' => Carbon::now()->addDay()->toDateTimeString(),
            'duration_minutes' => 60,
            'location' => 'https://meet.example.com/main',
            'departments' => [Department::DEVELOPMENT->value],
        ], $overrides);
    }
}
