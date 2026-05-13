<?php

namespace Tests\Feature\Leave;

use App\Models\LeaveRequest;
use App\Models\StaffMemberProfile;
use Carbon\Carbon;
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

class LeaveRequestQuotaValidationTest extends TestCase
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
        Carbon::setTestNow('2026-04-10 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_store_rejects_when_annual_leave_quota_exceeded(): void
    {
        $employee = $this->createEmployee('full_time');

        // Seeded quota for full_time annual_leave is 12 days.
        // Use up all 12 days with approved requests in the same year.
        LeaveRequest::create([
            'staff_member_id' => $employee->id,
            'leave_type' => 'annual_leave',
            'start_date' => '2026-04-13',
            'end_date' => '2026-04-24', // 10 weekdays
            'total_days' => 12,
            'reason' => 'Extended break',
            'status' => 'approved',
        ]);

        LeaveRequest::create([
            'staff_member_id' => $employee->id,
            'leave_type' => 'annual_leave',
            'start_date' => '2026-04-27',
            'end_date' => '2026-04-28', // 2 weekdays
            'total_days' => 2,
            'reason' => 'Short trip',
            'status' => 'approved',
        ]);

        $response = $this->postJson('/api/v1/leave-requests', [
            'leave_type' => 'annual_leave',
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-01',
            'reason' => 'Trying to exceed quota',
            'emergency_contact' => '08123456789',
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('success', false);
    }

    public function test_store_rejects_when_per_occurrence_quota_exceeded(): void
    {
        $employee = $this->createEmployee('full_time');

        // compassionate_leave is per_occurrence with 3 days max.
        // Request 5 days which exceeds the 3-day per-occurrence limit.
        $response = $this->postJson('/api/v1/leave-requests', [
            'leave_type' => 'compassionate_leave',
            'start_date' => '2026-04-13',
            'end_date' => '2026-04-17', // 5 weekdays, exceeds 3-day per_occurrence limit
            'reason' => 'Family emergency requiring extended leave',
            'emergency_contact' => '08123456789',
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('success', false);
    }

    public function test_store_rejects_when_leave_type_not_eligible(): void
    {
        // intern is not eligible for maternity_leave per seeder
        $employee = $this->createEmployee('intern');

        $response = $this->postJson('/api/v1/leave-requests', [
            'leave_type' => 'maternity_leave',
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-05',
            'reason' => 'Maternity leave',
            'emergency_contact' => '08123456789',
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('success', false);
    }

    public function test_store_allows_valid_leave_within_quota(): void
    {
        $employee = $this->createEmployee('full_time');

        // annual_leave quota is 12 days, requesting 1 day should succeed.
        $response = $this->postJson('/api/v1/leave-requests', [
            'leave_type' => 'annual_leave',
            'start_date' => '2026-04-13',
            'end_date' => '2026-04-13',
            'reason' => 'Personal day',
            'emergency_contact' => '08123456789',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true);
    }

    public function test_store_allows_unlimited_leave_types_without_quota_check(): void
    {
        $employee = $this->createEmployee('full_time');

        // sick_leave is unlimited for full_time — should always pass quota check.
        $response = $this->postJson('/api/v1/leave-requests', [
            'leave_type' => 'sick_leave',
            'start_date' => '2026-04-13',
            'end_date' => '2026-04-13',
            'reason' => 'Feeling unwell',
            'emergency_contact' => '08123456789',
        ]);

        // Proof-related errors are filtered out at submission time,
        // so this should succeed even without proof uploaded.
        $response->assertCreated()
            ->assertJsonPath('success', true);
    }

    public function test_store_allows_leave_request_when_only_pending_requests_exist(): void
    {
        $employee = $this->createEmployee('full_time');

        // annual_leave quota is 12 days. Create pending requests that don't count
        // toward the quota (only approved requests count).
        LeaveRequest::create([
            'staff_member_id' => $employee->id,
            'leave_type' => 'annual_leave',
            'start_date' => '2026-04-13',
            'end_date' => '2026-04-13',
            'total_days' => 1,
            'reason' => 'Pending request',
            'status' => 'pending',
        ]);

        $response = $this->postJson('/api/v1/leave-requests', [
            'leave_type' => 'annual_leave',
            'start_date' => '2026-04-14',
            'end_date' => '2026-04-14',
            'reason' => 'Another day off',
            'emergency_contact' => '08123456789',
        ]);

        $response->assertCreated()
            ->assertJsonPath('success', true);
    }

    public function test_store_rejects_when_no_working_days_in_request(): void
    {
        $employee = $this->createEmployee('full_time');

        // Request only on a weekend — 0 working days.
        $response = $this->postJson('/api/v1/leave-requests', [
            'leave_type' => 'annual_leave',
            'start_date' => '2026-04-12', // Sunday
            'end_date' => '2026-04-12',
            'reason' => 'Weekend leave',
            'emergency_contact' => '08123456789',
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('success', false);
    }

    private function createEmployee(string $employmentType): StaffMemberProfile
    {
        $employee = StaffMemberProfile::withoutSyncingToSearch(function () {
            return StaffMemberProfile::factory()->create();
        });

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

        $user = $employee->user;
        $user->assignRole(Role::findByName('staff', 'sanctum'));

        Sanctum::actingAs($user);

        return $employee;
    }
}
