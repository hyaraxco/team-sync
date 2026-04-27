<?php

namespace Tests\Feature\Attendance;

use App\Models\LeaveEntitlement;
use App\Models\User;
use Database\Seeders\LeaveEntitlementSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class LeaveEntitlementControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,
            LeaveEntitlementSeeder::class,
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_unauthenticated_user_cannot_access_entitlements(): void
    {
        $this->getJson('/api/v1/leave-entitlements')
            ->assertUnauthorized();
    }

    public function test_user_without_permission_cannot_access_entitlements(): void
    {
        $this->actingAsRole('staff');

        $this->getJson('/api/v1/leave-entitlements')
            ->assertForbidden();
    }

    public function test_authorized_user_can_list_grouped_entitlements(): void
    {
        $this->actingAsRole('hr');

        $response = $this->getJson('/api/v1/leave-entitlements')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'items',
                    'grouped'
                ]
            ]);

        $data = $response->json('data');
        $this->assertGreaterThan(0, count($data['items']));
        $this->assertIsArray($data['grouped']);
        
        // Ensure it's grouped by employment type
        $firstItem = $data['items'][0];
        $this->assertArrayHasKey($firstItem['employment_type'], $data['grouped']);
    }

    public function test_authorized_user_can_update_entitlement(): void
    {
        $this->actingAsRole('hr');

        $entitlement = LeaveEntitlement::first();

        $response = $this->putJson("/api/v1/leave-entitlements/{$entitlement->id}", [
            'quota_days' => 20.00,
            'is_paid' => false,
            'requires_attachment' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.quota_days', '20.00')
            ->assertJsonPath('data.is_paid', false)
            ->assertJsonPath('data.requires_attachment', true);

        $this->assertDatabaseHas('leave_entitlements', [
            'id' => $entitlement->id,
            'quota_days' => 20,
            'is_paid' => false,
            'requires_attachment' => true,
        ]);
    }

    public function test_validation_prevents_invalid_entitlement_updates(): void
    {
        $this->actingAsRole('hr');

        $entitlement = LeaveEntitlement::first();

        $this->putJson("/api/v1/leave-entitlements/{$entitlement->id}", [
            'quota_days' => -1, // Invalid: min is 0
            'is_eligible' => 'yes', // Invalid: must be boolean
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quota_days', 'is_eligible']);
    }

    private function actingAsRole(string $roleName): User
    {
        $user = User::factory()->create();
        $role = Role::findByName($roleName, 'sanctum');
        $user->assignRole($role);

        Sanctum::actingAs($user);

        return $user;
    }
}
