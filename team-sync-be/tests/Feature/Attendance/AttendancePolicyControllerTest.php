<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendancePolicy;
use App\Models\User;
use Database\Seeders\AttendancePolicySeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AttendancePolicyControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,
            AttendancePolicySeeder::class,
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_unauthenticated_user_cannot_access_policies(): void
    {
        $this->getJson('/api/v1/attendance-policies')
            ->assertUnauthorized();
    }

    public function test_user_without_permission_cannot_access_policies(): void
    {
        $user = $this->actingAsRole('staff');

        $this->getJson('/api/v1/attendance-policies')
            ->assertForbidden();
    }

    public function test_authorized_user_can_list_policies(): void
    {
        $this->actingAsRole('hr');

        $response = $this->getJson('/api/v1/attendance-policies')
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'employment_type',
                        'work_days_per_week',
                        'work_start_time',
                    ]
                ]
            ]);

        $this->assertGreaterThan(0, count($response->json('data')));
    }

    public function test_authorized_user_can_update_policy(): void
    {
        $this->actingAsRole('hr');

        $policy = AttendancePolicy::first();

        $response = $this->putJson("/api/v1/attendance-policies/{$policy->id}", [
            'work_days_per_week' => 4,
            'late_grace_minutes' => 30,
        ])
            ->assertOk()
            ->assertJsonPath('data.work_days_per_week', 4)
            ->assertJsonPath('data.late_grace_minutes', 30);

        $this->assertDatabaseHas('attendance_policies', [
            'id' => $policy->id,
            'work_days_per_week' => 4,
            'late_grace_minutes' => 30,
        ]);
    }

    public function test_validation_prevents_invalid_policy_updates(): void
    {
        $this->actingAsRole('hr');

        $policy = AttendancePolicy::first();

        $this->putJson("/api/v1/attendance-policies/{$policy->id}", [
            'work_days_per_week' => 8, // Invalid: max is 7
            'late_grace_minutes' => -5, // Invalid: min is 0
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['work_days_per_week', 'late_grace_minutes']);
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
