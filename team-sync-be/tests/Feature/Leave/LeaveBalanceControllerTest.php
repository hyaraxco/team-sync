<?php

namespace Tests\Feature\Leave;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class LeaveBalanceControllerTest extends TestCase
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

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_get_my_balances_gracefully_handles_user_without_employee_profile(): void
    {
        // Create a user without an employee profile
        $user = User::factory()->create();
        
        // Give them the explicit permission needed to pass the controller middleware
        $user->givePermissionTo('leave-request-my-requests');

        Sanctum::actingAs($user);

        // Assert they do not have an employee profile
        $this->assertNull($user->employee);

        // Act
        $response = $this->getJson('/api/v1/my-leave-balances');

        // Assert gracefully falls back to 404 instead of 500 error
        $response->assertStatus(404);
        $response->assertJson([
            'success' => false,
            'message' => 'Employee profile not found.',
            'data' => []
        ]);
    }
}
