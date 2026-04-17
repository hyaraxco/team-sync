<?php

namespace Tests\Feature\Employee;

use App\Models\User;
use Database\Seeders\MinimalPayrollE2ESeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class DualRoleSelfServiceAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MinimalPayrollE2ESeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_internal_admin_roles_can_access_personal_attendance_and_leave_endpoints(): void
    {
        foreach (['tasyia@teamsync.com', 'dwimeta@teamsync.com', 'yudhis@teamsync.com'] as $email) {
            $user = User::where('email', $email)->firstOrFail();

            Sanctum::actingAs($user);

            $this->getJson('/api/v1/my-attendances')
                ->assertOk();

            $this->getJson('/api/v1/my-leave-requests')
                ->assertOk();

            $payload = [
                'leave_type' => 'annual_leave',
                'start_date' => now()->addDays(1)->toDateString(),
                'end_date' => now()->addDays(1)->toDateString(),
                'reason' => 'Personal leave for '.$email,
                'emergency_contact' => '081234567890',
            ];

            $this->postJson('/api/v1/leave-requests', $payload)
                ->assertCreated();

            $this->assertDatabaseHas('leave_requests', [
                'employee_id' => $user->employeeProfile->id,
                'reason' => 'Personal leave for '.$email,
            ]);
        }
    }
}
