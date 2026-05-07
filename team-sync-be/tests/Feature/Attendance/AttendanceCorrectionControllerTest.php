<?php

namespace Tests\Feature\Attendance;

use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\AttendancePeriod;
use App\Models\StaffMemberProfile;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AttendanceCorrectionControllerTest extends TestCase
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

    public function test_authorized_user_can_list_paginated_corrections(): void
    {
        $this->actingAsRole('hr');
        $employee = $this->createEmployee();
        $attendance = $this->createAttendanceFor($employee);

        AttendanceCorrection::create([
            'attendance_id' => $attendance->id,
            'staff_member_id' => $employee->id,
            'requested_check_in' => '2026-04-10 08:45:00',
            'reason' => 'Missed fingerprint sync',
            'status' => 'pending',
        ]);

        $this->getJson('/api/v1/attendance-corrections/all/paginated?row_per_page=10&status=pending')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.current_page', 1)
            ->assertJsonPath('data.data.0.status', 'pending');
    }

    public function test_employee_can_submit_attendance_correction(): void
    {
        $employee = $this->actingAsEmployee();
        $attendance = $this->createAttendanceFor($employee);

        $this->postJson('/api/v1/attendance-corrections', [
            'attendance_id' => $attendance->id,
            'requested_check_in' => '2026-04-10 09:00:00',
            'reason' => 'Turnstile was offline',
        ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.reason', 'Turnstile was offline');

        $this->assertDatabaseHas('attendance_corrections', [
            'attendance_id' => $attendance->id,
            'staff_member_id' => $employee->id,
            'reason' => 'Turnstile was offline',
        ]);
    }

    public function test_reject_requires_review_notes(): void
    {
        $this->actingAsRole('hr');

        $this->postJson('/api/v1/attendance-corrections/1/reject', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['review_notes']);
    }

    private function actingAsRole(string $roleName): User
    {
        $user = User::factory()->create();
        $role = Role::findByName($roleName, 'sanctum');
        $user->assignRole($role);

        Sanctum::actingAs($user);

        return $user;
    }

    private function actingAsEmployee(): StaffMemberProfile
    {
        $employee = $this->createEmployee();
        $user = User::query()->findOrFail($employee->user_id);
        $user->assignRole(Role::findByName('staff', 'sanctum'));

        Sanctum::actingAs($user);

        return $employee;
    }

    private function createEmployee(): StaffMemberProfile
    {
        return StaffMemberProfile::withoutSyncingToSearch(function () {
            $employee = StaffMemberProfile::factory()->create();

            $employee->jobInformation()->create([
                'staff_member_id' => $employee->id,
                'job_title' => 'Operations Staff',
                'status' => 'active',
                'employment_type' => 'full_time',
                'work_location' => 'office',
                'start_date' => '2024-01-01',
                'monthly_salary' => 7000000,
            ]);

            return $employee;
        });
    }

    private function createAttendanceFor(StaffMemberProfile $employee): Attendance
    {
        $period = AttendancePeriod::firstOrCreate([
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'cutoff_date' => '2026-04-25',
        ], [
            'status' => AttendancePeriod::STATUS_OPEN,
        ]);

        return Attendance::create([
            'staff_member_id' => $employee->id,
            'attendance_period_id' => $period->id,
            'date' => '2026-04-10',
            'check_in' => '2026-04-10 08:30:00',
            'status' => 'late',
            'notes' => 'Created for correction test',
        ]);
    }
}
