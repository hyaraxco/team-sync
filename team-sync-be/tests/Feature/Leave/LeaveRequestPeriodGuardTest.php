<?php

namespace Tests\Feature\Leave;

use App\Models\AttendancePeriod;
use App\Models\LeaveRequest;
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

class LeaveRequestPeriodGuardTest extends TestCase
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
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // SQLite cannot verify row-lock serialization; a real concurrent check requires an isolated MySQL lane.
    public function test_employee_cannot_submit_overlapping_leave_request(): void
    {
        Carbon::setTestNow('2026-04-10 09:00:00');
        $user = $this->actingAsEmployee();
        $employeeId = $user->staffMemberProfile->id;

        LeaveRequest::create([
            'staff_member_id' => $employeeId,
            'leave_type' => 'annual_leave',
            'start_date' => '2026-04-13',
            'end_date' => '2026-04-15',
            'total_days' => 3,
            'reason' => 'Existing request',
            'status' => 'pending',
        ]);

        $response = $this->postJson('/api/v1/leave-requests', [
            'leave_type' => 'annual_leave',
            'start_date' => '2026-04-15',
            'end_date' => '2026-04-16',
            'reason' => 'Overlapping request',
            'emergency_contact' => '08123456789',
        ]);

        $response->assertStatus(400);
        $this->assertStringContainsString('overlapping leave request', (string) $response->json('message'));
        $this->assertSame(1, LeaveRequest::where('staff_member_id', $employeeId)->count());
    }

    public function test_employee_cannot_submit_leave_request_when_period_is_review(): void
    {
        Carbon::setTestNow('2026-04-10 09:00:00');

        AttendancePeriod::create([
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'cutoff_date' => '2026-04-05',
            'status' => AttendancePeriod::STATUS_REVIEW,
        ]);

        $this->actingAsEmployee();

        $response = $this->postJson('/api/v1/leave-requests', [
            'leave_type' => 'annual_leave',
            'start_date' => '2026-04-10',
            'end_date' => '2026-04-10',
            'reason' => 'Need time off',
            'emergency_contact' => '08123456789',
        ]);

        $response->assertStatus(400);
        $this->assertStringContainsString('no longer open', (string) $response->json('message'));
    }

    public function test_employee_cannot_submit_leave_request_when_period_is_locked(): void
    {
        Carbon::setTestNow('2026-04-10 09:00:00');

        AttendancePeriod::create([
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'cutoff_date' => '2026-04-05',
            'status' => AttendancePeriod::STATUS_LOCKED,
            'locked_at' => now(),
        ]);

        $this->actingAsEmployee();

        $response = $this->postJson('/api/v1/leave-requests', [
            'leave_type' => 'annual_leave',
            'start_date' => '2026-04-10',
            'end_date' => '2026-04-10',
            'reason' => 'Need time off',
            'emergency_contact' => '08123456789',
        ]);

        $response->assertStatus(400);
        $this->assertStringContainsString('no longer open', (string) $response->json('message'));
    }

    private function actingAsEmployee(): User
    {
        $employee = StaffMemberProfile::withoutSyncingToSearch(function () {
            return StaffMemberProfile::factory()->create();
        });

        $employee->jobInformation()->create([
            'staff_member_id' => $employee->id,
            'job_title' => 'QA Engineer',
            'years_experience' => 3,
            'status' => 'active',
            'employment_type' => 'full_time',
            'work_location' => 'remote',
            'start_date' => '2024-01-01',
            'monthly_salary' => 9000000,
            'skill_level' => 'intermediate',
        ]);

        $user = $employee->user;
        $user->assignRole(Role::findByName('staff', 'sanctum'));

        Sanctum::actingAs($user);

        return $user;
    }
}
