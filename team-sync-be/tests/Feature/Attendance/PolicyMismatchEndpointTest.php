<?php

namespace Tests\Feature\Attendance;

use App\Models\Attendance;
use App\Models\AttendancePeriod;
use App\Models\AttendancePolicyMismatch;
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

class PolicyMismatchEndpointTest extends TestCase
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

    public function test_unauthenticated_user_cannot_access_policy_mismatches(): void
    {
        $this->getJson('/api/v1/attendance-policy-mismatches')
            ->assertUnauthorized();
    }

    public function test_user_without_permission_cannot_access_policy_mismatches(): void
    {
        $this->actingAsRole('staff');

        $this->getJson('/api/v1/attendance-policy-mismatches')
            ->assertForbidden();
    }

    public function test_authorized_user_can_list_policy_mismatches(): void
    {
        $this->actingAsRole('hr');

        $employee = StaffMemberProfile::factory()->create();
        $period = AttendancePeriod::factory()->create(['status' => 'open']);
        $attendance = $this->createAttendance($employee, $period);

        AttendancePolicyMismatch::create([
            'attendance_id' => $attendance->id,
            'staff_member_id' => $employee->id,
            'mismatch_date' => now()->toDateString(),
            'planned_work_mode' => 'office',
            'actual_work_mode' => 'remote',
            'status' => AttendancePolicyMismatch::STATUS_PENDING_REVIEW,
        ]);

        $this->withoutExceptionHandling();

        $response = $this->getJson('/api/v1/attendance-policy-mismatches')
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'mismatch_date',
                            'planned_work_mode',
                            'actual_work_mode',
                            'status',
                        ]
                    ]
                ]
            ]);

        $this->assertCount(1, $response->json('data.data'));
    }

    public function test_can_filter_mismatches_by_status(): void
    {
        $this->actingAsRole('hr');

        $employee = StaffMemberProfile::factory()->create();
        $period = AttendancePeriod::factory()->create(['status' => 'open']);
        $attendance = $this->createAttendance($employee, $period);

        AttendancePolicyMismatch::create([
            'attendance_id' => $attendance->id,
            'staff_member_id' => $employee->id,
            'mismatch_date' => now()->toDateString(),
            'planned_work_mode' => 'office',
            'actual_work_mode' => 'remote',
            'status' => AttendancePolicyMismatch::STATUS_PENDING_REVIEW,
        ]);

        $attendance2 = $this->createAttendance($employee, $period, now()->subDay());
        AttendancePolicyMismatch::create([
            'attendance_id' => $attendance2->id,
            'staff_member_id' => $employee->id,
            'mismatch_date' => now()->subDay()->toDateString(),
            'planned_work_mode' => 'office',
            'actual_work_mode' => 'remote',
            'status' => AttendancePolicyMismatch::STATUS_RESOLVED,
        ]);

        $this->withoutExceptionHandling();

        $response = $this->getJson('/api/v1/attendance-policy-mismatches?status=pending_review')
            ->assertOk();

        $this->assertCount(1, $response->json('data.data'));
        $this->assertEquals('pending_review', $response->json('data.data.0.status'));
    }

    public function test_mismatches_are_paginated(): void
    {
        $this->actingAsRole('hr');

        $employee = StaffMemberProfile::factory()->create();
        $period = AttendancePeriod::factory()->create(['status' => 'open']);

        for ($i = 0; $i < 20; $i++) {
            $attendance = $this->createAttendance($employee, $period, now()->subDays($i));
            AttendancePolicyMismatch::create([
                'attendance_id' => $attendance->id,
                'staff_member_id' => $employee->id,
                'mismatch_date' => now()->subDays($i)->toDateString(),
                'planned_work_mode' => 'office',
                'actual_work_mode' => 'remote',
                'status' => AttendancePolicyMismatch::STATUS_PENDING_REVIEW,
            ]);
        }

        $this->withoutExceptionHandling();

        $response = $this->getJson('/api/v1/attendance-policy-mismatches?per_page=5')
            ->assertOk();

        $this->assertCount(5, $response->json('data.data'));
    }

    private function actingAsRole(string $roleName): User
    {
        $user = User::factory()->create();
        $role = Role::findByName($roleName, 'sanctum');
        $user->assignRole($role);

        Sanctum::actingAs($user);

        return $user;
    }

    private function createAttendance(StaffMemberProfile $employee, AttendancePeriod $period, $date = null): Attendance
    {
        return Attendance::create([
            'staff_member_id' => $employee->id,
            'attendance_period_id' => $period->id,
            'date' => $date ?? now(),
            'check_in' => ($date ?? now())->copy()->setTime(8, 0),
            'status' => 'present',
        ]);
    }
}
