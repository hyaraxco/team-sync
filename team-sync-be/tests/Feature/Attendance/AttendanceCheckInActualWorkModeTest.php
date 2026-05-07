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

class AttendanceCheckInActualWorkModeTest extends TestCase
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

    public function test_hybrid_employee_check_in_persists_actual_work_mode(): void
    {
        $employee = $this->actingAsStaffWithWorkLocation('hybrid');

        $response = $this->postJson('/api/v1/attendances/check-in', [
            'check_in_lat' => -6.2,
            'check_in_long' => 106.8,
            'actual_work_mode' => 'remote',
            'notes' => 'Working from home today',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.actual_work_mode', 'remote');

        $this->assertDatabaseHas('attendances', [
            'staff_member_id' => $employee->id,
            'actual_work_mode' => 'remote',
            'notes' => 'Working from home today',
        ]);
    }

    public function test_office_employee_check_in_defaults_actual_work_mode_to_office(): void
    {
        $employee = $this->actingAsStaffWithWorkLocation('office');

        $response = $this->postJson('/api/v1/attendances/check-in', [
            'check_in_lat' => -6.2,
            'check_in_long' => 106.8,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.actual_work_mode', 'office');

        $this->assertDatabaseHas('attendances', [
            'staff_member_id' => $employee->id,
            'actual_work_mode' => 'office',
        ]);
    }

    public function test_check_in_rejects_invalid_actual_work_mode(): void
    {
        $this->actingAsStaffWithWorkLocation('hybrid');

        $response = $this->postJson('/api/v1/attendances/check-in', [
            'check_in_lat' => -6.2,
            'check_in_long' => 106.8,
            'actual_work_mode' => 'cafe',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['actual_work_mode']);
    }

    public function test_staff_member_id_payload_is_ignored_on_check_in(): void
    {
        $employee = $this->actingAsStaffWithWorkLocation('office');
        $otherEmployee = StaffMemberProfile::withoutSyncingToSearch(function () {
            return StaffMemberProfile::factory()->create();
        });

        $response = $this->postJson('/api/v1/attendances/check-in', [
            'staff_member_id' => $otherEmployee->id,
            'check_in_lat' => -6.2,
            'check_in_long' => 106.8,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.staff_member_id', $employee->id);

        $this->assertDatabaseHas('attendances', [
            'staff_member_id' => $employee->id,
        ]);
        $this->assertDatabaseMissing('attendances', [
            'staff_member_id' => $otherEmployee->id,
        ]);
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
