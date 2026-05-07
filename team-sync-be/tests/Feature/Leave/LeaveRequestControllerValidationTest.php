<?php

namespace Tests\Feature\Leave;

use App\Models\StaffMemberProfile;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\ActivatesLicense;
use Tests\TestCase;

class LeaveRequestControllerValidationTest extends TestCase
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
        Carbon::setTestNow('2026-04-10 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_paginated_list_validates_row_per_page(): void
    {
        $this->actingAsRole('hr');

        $this->getJson('/api/v1/leave-requests/all/paginated?row_per_page=0')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['row_per_page']);
    }

    public function test_calendar_endpoint_validates_month_format(): void
    {
        $this->actingAsRole('hr');

        $this->getJson('/api/v1/leave-requests/all/calendar?month=2026/04')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['month']);
    }

    public function test_paginated_list_accepts_valid_params(): void
    {
        $this->actingAsRole('hr');

        $this->getJson('/api/v1/leave-requests/all/paginated?row_per_page=10')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_calendar_endpoint_accepts_valid_month(): void
    {
        $this->actingAsRole('hr');

        $this->getJson('/api/v1/leave-requests/all/calendar?month=2026-04')
            ->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_create_leave_request_ignores_payload_staff_member_id_and_status(): void
    {
        $user = $this->actingAsRole('staff');
        $profile = StaffMemberProfile::factory()->create([
            'user_id' => $user->id,
        ]);
        $otherProfile = StaffMemberProfile::factory()->create();

        $response = $this->postJson('/api/v1/leave-requests', [
            'staff_member_id' => $otherProfile->id,
            'leave_type' => 'annual_leave',
            'start_date' => '2026-04-13',
            'end_date' => '2026-04-13',
            'reason' => 'Personal leave',
            'emergency_contact' => '081234567890',
            'status' => 'approved',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.staff_member_id', (string) $profile->id);

        $this->assertDatabaseHas('leave_requests', [
            'staff_member_id' => $profile->id,
            'status' => 'pending',
            'reason' => 'Personal leave',
        ]);
        $this->assertDatabaseMissing('leave_requests', [
            'staff_member_id' => $otherProfile->id,
            'reason' => 'Personal leave',
        ]);
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
