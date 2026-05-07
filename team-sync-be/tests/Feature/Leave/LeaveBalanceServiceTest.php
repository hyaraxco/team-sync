<?php

namespace Tests\Feature\Leave;

use App\Models\LeaveEntitlement;
use App\Models\LeaveRequest;
use App\Models\StaffMemberProfile;
use App\Models\User;
use App\Services\Attendance\LeaveBalanceService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\ActivatesLicense;
use Tests\TestCase;

class LeaveBalanceServiceTest extends TestCase
{
    use ActivatesLicense, RefreshDatabase;

    private LeaveBalanceService $service;

    private StaffMemberProfile $employee;

    protected function setUp(): void
    {
        parent::setUp();

        $this->activateTestLicense();

        $this->seed([
            RoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->service = app(LeaveBalanceService::class);

        $user = User::factory()->create();
        $user->assignRole('staff');

        $this->employee = StaffMemberProfile::factory()->create([
            'user_id' => $user->id,
        ]);

        $this->employee->jobInformation()->create([
            'monthly_salary' => 8_000_000,
            'start_date' => now()->subYear(),
            'status' => 'active',
            'employment_type' => 'full_time',
            'job_title' => 'Developer',
            'team_id' => null,
            'work_location' => 'office',
        ]);
    }

    public function test_returns_empty_collection_for_nonexistent_employee(): void
    {
        $balances = $this->service->getEmployeeBalances(99999);

        $this->assertTrue($balances->isEmpty());
    }

    public function test_returns_balances_with_correct_structure(): void
    {
        LeaveEntitlement::create([
            'employment_type' => 'full_time',
            'leave_type' => 'annual_leave',
            'is_eligible' => true,
            'is_paid' => true,
            'quota_scope' => 'annual',
            'quota_days' => 12,
            'requires_attachment' => false,
            'requires_reason' => false,
        ]);

        $balances = $this->service->getEmployeeBalances($this->employee->id);

        $this->assertNotEmpty($balances);
        $first = $balances->first();
        $this->assertArrayHasKey('leave_type', $first);
        $this->assertArrayHasKey('quota_scope', $first);
        $this->assertArrayHasKey('quota_days', $first);
        $this->assertArrayHasKey('used_days', $first);
        $this->assertArrayHasKey('remaining_days', $first);
        $this->assertArrayHasKey('is_paid', $first);
        $this->assertArrayHasKey('requires_attachment', $first);
    }

    public function test_calculates_used_days_from_approved_leave_requests(): void
    {
        LeaveEntitlement::create([
            'employment_type' => 'full_time',
            'leave_type' => 'annual_leave',
            'is_eligible' => true,
            'is_paid' => true,
            'quota_scope' => 'annual',
            'quota_days' => 12,
            'requires_attachment' => false,
            'requires_reason' => false,
        ]);

        // Create an approved leave request (Mon-Fri = 5 working days)
        LeaveRequest::create([
            'staff_member_id' => $this->employee->id,
            'leave_type' => 'annual_leave',
            'status' => 'approved',
            'start_date' => now()->startOfYear()->next('Monday')->toDateString(),
            'end_date' => now()->startOfYear()->next('Monday')->addDays(4)->toDateString(),
            'total_days' => 5,
            'reason' => 'Vacation',
        ]);

        $balances = $this->service->getEmployeeBalances($this->employee->id);
        $annual = $balances->firstWhere('leave_type', 'annual_leave');

        $this->assertGreaterThan(0, $annual['used_days']);
        $this->assertLessThan(12, $annual['remaining_days']);
    }

    public function test_does_not_count_pending_leave_requests(): void
    {
        LeaveEntitlement::create([
            'employment_type' => 'full_time',
            'leave_type' => 'annual_leave',
            'is_eligible' => true,
            'is_paid' => true,
            'quota_scope' => 'annual',
            'quota_days' => 12,
            'requires_attachment' => false,
            'requires_reason' => false,
        ]);

        // Create a pending leave request
        LeaveRequest::create([
            'staff_member_id' => $this->employee->id,
            'leave_type' => 'annual_leave',
            'status' => 'pending',
            'start_date' => now()->startOfYear()->next('Monday')->toDateString(),
            'end_date' => now()->startOfYear()->next('Monday')->addDays(4)->toDateString(),
            'total_days' => 5,
            'reason' => 'Vacation',
        ]);

        $balances = $this->service->getEmployeeBalances($this->employee->id);
        $annual = $balances->firstWhere('leave_type', 'annual_leave');

        $this->assertEquals(0, $annual['used_days']);
        $this->assertEquals(12, $annual['remaining_days']);
    }

    public function test_sick_leave_uses_sick_leave_type(): void
    {
        LeaveEntitlement::create([
            'employment_type' => 'full_time',
            'leave_type' => 'sick_leave',
            'is_eligible' => true,
            'is_paid' => true,
            'quota_scope' => 'annual',
            'quota_days' => 14,
            'requires_attachment' => true,
            'requires_reason' => true,
        ]);

        $balances = $this->service->getEmployeeBalances($this->employee->id);
        $sick = $balances->firstWhere('leave_type', 'sick_leave');

        $this->assertNotNull($sick);
        $this->assertEquals(14, $sick['quota_days']);
        $this->assertEquals(0, $sick['used_days']);
        $this->assertTrue($sick['requires_attachment']);
    }

    public function test_api_endpoint_returns_balances_for_authenticated_staff(): void
    {
        LeaveEntitlement::create([
            'employment_type' => 'full_time',
            'leave_type' => 'annual_leave',
            'is_eligible' => true,
            'is_paid' => true,
            'quota_scope' => 'annual',
            'quota_days' => 12,
            'requires_attachment' => false,
            'requires_reason' => false,
        ]);

        $user = $this->employee->user;
        $user->givePermissionTo('leave-request-my-requests');
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/my-leave-balances');

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $response->assertJsonStructure([
            'data' => [
                '*' => ['leave_type', 'quota_days', 'used_days', 'remaining_days'],
            ],
        ]);
    }
}
