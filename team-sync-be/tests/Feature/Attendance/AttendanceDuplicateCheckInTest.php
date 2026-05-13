<?php

namespace Tests\Feature\Attendance;

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
use Tests\TestCase;

class AttendanceDuplicateCheckInTest extends TestCase
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
        Carbon::setTestNow('2026-04-10 08:10:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_duplicate_check_in_returns_error(): void
    {
        $employee = $this->actingAsStaffWithWorkLocation('office');

        // First check-in should succeed
        $response1 = $this->postJson('/api/v1/attendances/check-in', [
            'check_in_lat' => -6.2,
            'check_in_long' => 106.8,
        ]);

        $response1->assertCreated();

        // Second check-in on same day should fail
        $response2 = $this->postJson('/api/v1/attendances/check-in', [
            'check_in_lat' => -6.2,
            'check_in_long' => 106.8,
        ]);

        $response2->assertStatus(400)
            ->assertJson([
                'message' => 'Employee sudah check in hari ini',
            ]);
    }

    public function test_check_in_succeeds_on_different_days(): void
    {
        $employee = $this->actingAsStaffWithWorkLocation('office');

        // First check-in on April 10
        $response1 = $this->postJson('/api/v1/attendances/check-in', [
            'check_in_lat' => -6.2,
            'check_in_long' => 106.8,
        ]);

        $response1->assertCreated();

        // Move to next day
        Carbon::setTestNow('2026-04-11 08:10:00');

        // Check-in on April 11 should succeed
        $response2 = $this->postJson('/api/v1/attendances/check-in', [
            'check_in_lat' => -6.2,
            'check_in_long' => 106.8,
        ]);

        $response2->assertCreated();
    }

    private function actingAsStaffWithWorkLocation(string $workLocation): StaffMemberProfile
    {
        $employee = StaffMemberProfile::withoutSyncingToSearch(function () {
            return StaffMemberProfile::factory()->create();
        });

        $employee->jobInformation()->create([
            'staff_member_id' => $employee->id,
            'job_title' => 'Software Engineer',
            'status' => 'active',
            'employment_type' => 'full_time',
            'work_location' => $workLocation,
            'start_date' => '2024-01-01',
            'monthly_salary' => 10000000,
        ]);

        $user = User::query()->findOrFail($employee->user_id);
        $user->assignRole(Role::findByName('staff', 'sanctum'));

        Sanctum::actingAs($user);

        return $employee;
    }
}
