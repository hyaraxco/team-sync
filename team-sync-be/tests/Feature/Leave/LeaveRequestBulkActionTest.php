<?php

namespace Tests\Feature\Leave;

use App\Models\LeaveEntitlement;
use App\Models\LeaveRequest;
use App\Models\StaffMemberProfile;
use App\Models\User;
use Database\Seeders\LeaveEntitlementSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\ActivatesLicense;
use Tests\TestCase;

class LeaveRequestBulkActionTest extends TestCase
{
    use ActivatesLicense, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,
            LeaveEntitlementSeeder::class,
        ]);

        $this->activateTestLicense();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_hr_can_bulk_approve_pending_leave_requests(): void
    {
        $hr = $this->actingAsHrWithStaffMemberProfile();
        $employee = $this->createEmployee('full_time');
        LeaveEntitlement::query()
            ->where('employment_type', 'full_time')
            ->where('leave_type', 'annual_leave')
            ->update(['quota_days' => 20]);

        $firstRequest = LeaveRequest::create([
            'staff_member_id' => $employee->id,
            'leave_type' => 'annual_leave',
            'start_date' => '2026-04-17',
            'end_date' => '2026-04-17',
            'total_days' => 1,
            'reason' => 'Family event',
            'status' => 'pending',
        ]);

        $secondRequest = LeaveRequest::create([
            'staff_member_id' => $employee->id,
            'leave_type' => 'annual_leave',
            'start_date' => '2026-04-20',
            'end_date' => '2026-04-20',
            'total_days' => 1,
            'reason' => 'Family errand',
            'status' => 'pending',
        ]);

        $this->postJson('/api/v1/leave-requests/bulk-action', [
            'ids' => [$firstRequest->id, $secondRequest->id],
            'action' => 'approve',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.succeeded.0.status', 'approved');

        $this->assertDatabaseHas('leave_requests', [
            'id' => $firstRequest->id,
            'status' => 'approved',
            'approved_by' => $hr->staffMemberProfile->id,
        ]);

        $this->assertDatabaseHas('leave_requests', [
            'id' => $secondRequest->id,
            'status' => 'approved',
            'approved_by' => $hr->staffMemberProfile->id,
        ]);
    }

    public function test_hr_can_bulk_reject_pending_leave_requests(): void
    {
        $hr = $this->actingAsHrWithStaffMemberProfile();
        $employee = $this->createEmployee('full_time');

        $firstRequest = LeaveRequest::create([
            'staff_member_id' => $employee->id,
            'leave_type' => 'annual_leave',
            'start_date' => '2026-04-20',
            'end_date' => '2026-04-20',
            'total_days' => 1,
            'reason' => 'Personal errand',
            'status' => 'pending',
        ]);

        $secondRequest = LeaveRequest::create([
            'staff_member_id' => $employee->id,
            'leave_type' => 'sick_leave',
            'start_date' => '2026-04-21',
            'end_date' => '2026-04-21',
            'total_days' => 1,
            'reason' => 'Medical checkup',
            'status' => 'pending',
        ]);

        $this->postJson('/api/v1/leave-requests/bulk-action', [
            'ids' => [$firstRequest->id, $secondRequest->id],
            'action' => 'reject',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.succeeded.0.status', 'rejected');

        $this->assertDatabaseHas('leave_requests', [
            'id' => $firstRequest->id,
            'status' => 'rejected',
            'approved_by' => $hr->staffMemberProfile->id,
        ]);

        $this->assertDatabaseHas('leave_requests', [
            'id' => $secondRequest->id,
            'status' => 'rejected',
            'approved_by' => $hr->staffMemberProfile->id,
        ]);
    }

    public function test_approve_reject_requires_pending_leave_request(): void
    {
        $this->actingAsHrWithStaffMemberProfile();
        $employee = $this->createEmployee('full_time');

        $leaveRequest = LeaveRequest::create([
            'staff_member_id' => $employee->id,
            'leave_type' => 'annual_leave',
            'start_date' => '2026-04-22',
            'end_date' => '2026-04-22',
            'total_days' => 1,
            'reason' => 'Already decided',
            'status' => 'approved',
        ]);

        $this->postJson("/api/v1/leave-requests/approve/{$leaveRequest->id}")
            ->assertStatus(400)
            ->assertJsonPath('success', false);

        $this->postJson("/api/v1/leave-requests/reject/{$leaveRequest->id}")
            ->assertStatus(400)
            ->assertJsonPath('success', false);
    }

    public function test_approve_reject_requires_pending_leave_request_in_bulk_action(): void
    {
        $this->actingAsHrWithStaffMemberProfile();
        $employee = $this->createEmployee('full_time');

        $leaveRequest = LeaveRequest::create([
            'staff_member_id' => $employee->id,
            'leave_type' => 'annual_leave',
            'start_date' => '2026-04-23',
            'end_date' => '2026-04-23',
            'total_days' => 1,
            'reason' => 'Already decided',
            'status' => 'approved',
        ]);

        $this->postJson('/api/v1/leave-requests/bulk-action', [
            'ids' => [$leaveRequest->id],
            'action' => 'reject',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.failed')
            ->assertJsonCount(0, 'data.succeeded');
    }

    public function test_bulk_approve_validates_leave_entitlement(): void
    {
        $this->actingAsHrWithStaffMemberProfile();
        $employee = $this->createEmployee('full_time');

        $leaveRequest = LeaveRequest::create([
            'staff_member_id' => $employee->id,
            'leave_type' => 'sick_leave',
            'start_date' => '2026-04-24',
            'end_date' => '2026-04-24',
            'total_days' => 1,
            'reason' => 'Fever without proof',
            'status' => 'pending',
        ]);

        $this->postJson('/api/v1/leave-requests/bulk-action', [
            'ids' => [$leaveRequest->id],
            'action' => 'approve',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.failed')
            ->assertJsonCount(0, 'data.succeeded');
    }

    public function test_bulk_action_validates_payload(): void
    {
        $this->actingAsHrWithStaffMemberProfile();

        $this->postJson('/api/v1/leave-requests/bulk-action', [
            'ids' => [],
            'action' => 'archive',
        ])->assertStatus(422);
    }

    private function actingAsHrWithStaffMemberProfile(): User
    {
        $employee = StaffMemberProfile::withoutSyncingToSearch(function () {
            return StaffMemberProfile::factory()->create();
        });

        $employee->jobInformation()->create([
            'staff_member_id' => $employee->id,
            'job_title' => 'HR Specialist',
            'years_experience' => 4,
            'status' => 'active',
            'employment_type' => 'full_time',
            'work_location' => 'office',
            'start_date' => '2024-01-01',
            'monthly_salary' => 12000000,
            'skill_level' => 'expert',
        ]);

        $user = $employee->user;
        $user->syncRoles([Role::findByName('hr', 'sanctum')]);

        Sanctum::actingAs($user);

        return $user;
    }

    private function createEmployee(string $employmentType): StaffMemberProfile
    {
        return StaffMemberProfile::withoutSyncingToSearch(function () use ($employmentType) {
            $employee = StaffMemberProfile::factory()->create();

            $employee->jobInformation()->create([
                'staff_member_id' => $employee->id,
                'job_title' => 'QA Engineer',
                'years_experience' => 3,
                'status' => 'active',
                'employment_type' => $employmentType,
                'work_location' => 'remote',
                'start_date' => '2024-01-01',
                'monthly_salary' => 9000000,
                'skill_level' => 'intermediate',
            ]);

            return $employee;
        });
    }
}
