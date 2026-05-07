<?php

namespace Tests\Feature\Payroll;

use App\Models\Payroll;
use App\Models\PayrollApprovalPolicy;
use App\Models\PayrollDetail;
use App\Models\StaffMemberProfile;
use App\Models\User;
use App\Services\Payroll\OvertimeCalculationService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\ActivatesLicense;
use Tests\TestCase;

class PayrollEdgeCaseTest extends TestCase
{
    use ActivatesLicense, RefreshDatabase;

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
        Notification::fake();
    }

    public function test_negative_salary_produces_zero_overtime(): void
    {
        $service = app(OvertimeCalculationService::class);

        // Negative salary should produce zero hourly rate
        $this->assertSame(0.0, $service->getHourlyRate(-5000000));
        $this->assertSame(0.0, $service->getHourlyRate(0));

        // With negative salary, overtime pay should be zero
        $records = collect([
            (object) ['hours' => 2.0, 'overtime_type' => 'workday', 'date' => '2026-05-01'],
        ]);

        $result = $service->calculateOvertimePay(-5000000, $records);

        $this->assertSame(0.0, $result['total_amount']);
    }

    public function test_overtime_hours_capped_at_4_per_record(): void
    {
        $service = app(OvertimeCalculationService::class);

        // Create a record with 10 hours (exceeds the 4-hour cap)
        $records = collect([
            (object) ['hours' => 10.0, 'overtime_type' => 'workday', 'date' => '2026-05-01'],
        ]);

        $result = $service->calculateOvertimePay(5000000.0, $records);

        // Hours should be capped at 4.0
        $this->assertSame(4.0, $result['total_hours']);

        // Verify the breakdown also reflects the cap
        $this->assertSame(4.0, $result['breakdown'][0]['hours']);
    }

    public function test_negative_overtime_hours_produce_zero(): void
    {
        $service = app(OvertimeCalculationService::class);

        $records = collect([
            (object) ['hours' => -3.0, 'overtime_type' => 'workday', 'date' => '2026-05-01'],
        ]);

        $result = $service->calculateOvertimePay(5000000.0, $records);

        $this->assertSame(0.0, $result['total_hours']);
        $this->assertSame(0.0, $result['total_amount']);
    }

    public function test_duplicate_reconciliation_resolution_rejected(): void
    {
        $this->actingAsRole('finance');

        $payroll = Payroll::create([
            'salary_month' => '2026-06-01',
            'status' => 'approved',
            'correction_count' => 0,
        ]);

        $user = User::factory()->create();
        $staffMember = StaffMemberProfile::withoutSyncingToSearch(function () use ($user) {
            return StaffMemberProfile::factory()->for($user)->create();
        });

        // First resolution should succeed
        $response = $this->postJson("/api/v1/payrolls/{$payroll->id}/reconciliation/resolve", [
            'staff_member_id' => $staffMember->id,
            'exception_type' => 'missing_bank_account',
            'resolution_action' => 'acknowledged',
            'reason' => 'Will be updated before payment.',
        ]);

        $response->assertOk();

        // Second resolution for the same exception should be rejected
        $response = $this->postJson("/api/v1/payrolls/{$payroll->id}/reconciliation/resolve", [
            'staff_member_id' => $staffMember->id,
            'exception_type' => 'missing_bank_account',
            'resolution_action' => 'acknowledged',
            'reason' => 'Duplicate attempt.',
        ]);

        $response->assertStatus(400)
            ->assertJsonFragment(['message' => 'This exception has already been resolved.']);
    }

    public function test_multi_step_approval_not_bypassed_on_second_call(): void
    {
        // Create two policies requiring different roles
        $this->ensureRoleExists('director');
        $this->ensureRoleExists('cfo');

        PayrollApprovalPolicy::create([
            'name' => 'Director Approval',
            'min_amount' => 1000000,
            'max_amount' => null,
            'required_role' => 'director',
            'approval_order' => 1,
            'is_active' => true,
        ]);

        PayrollApprovalPolicy::create([
            'name' => 'CFO Approval',
            'min_amount' => 1000000,
            'max_amount' => null,
            'required_role' => 'cfo',
            'approval_order' => 2,
            'is_active' => true,
        ]);

        // Create payroll with high amount
        $payroll = $this->createPayrollWithDetail(status: 'pending', totalSalary: 50000000);

        // First call: director approves
        $director = $this->actingAsRole('director');
        $response = $this->postJson("/api/v1/payrolls/{$payroll->id}/approve");
        $response->assertOk();

        // Payroll should still be pending (CFO hasn't approved)
        $this->assertSame('pending', $payroll->fresh()->status);

        // Second call: director tries again — should NOT bypass and directly approve
        $response = $this->postJson("/api/v1/payrolls/{$payroll->id}/approve");

        // Director has no pending approval left, so this should fail
        $response->assertStatus(400);

        // Payroll should STILL be pending
        $this->assertSame('pending', $payroll->fresh()->status);

        // Now CFO approves — this should complete the approval
        $cfo = $this->actingAsRole('cfo');
        $response = $this->postJson("/api/v1/payrolls/{$payroll->id}/approve");
        $response->assertOk();

        // Now payroll should be approved
        $this->assertSame('approved', $payroll->fresh()->status);
    }

    private function actingAsRole(string $roleName): User
    {
        $this->ensureRoleExists($roleName);

        $user = User::factory()->create();
        $role = Role::findByName($roleName, 'sanctum');
        $user->assignRole($role);

        // Give custom roles the payroll-edit permission so they can submit approvals
        if (! in_array($roleName, ['finance', 'hr', 'manager', 'staff'], true)) {
            $permission = Permission::firstOrCreate([
                'name' => 'payroll-edit',
                'guard_name' => 'sanctum',
            ]);
            $role->givePermissionTo($permission);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        Sanctum::actingAs($user);

        return $user;
    }

    private function ensureRoleExists(string $roleName): void
    {
        if (! Role::where('name', $roleName)->where('guard_name', 'sanctum')->exists()) {
            Role::create(['name' => $roleName, 'guard_name' => 'sanctum']);
        }
    }

    private function createPayrollWithDetail(string $status = 'pending', float $totalSalary = 9500000): Payroll
    {
        $user = User::factory()->create([
            'email' => 'employee+'.uniqid().'@teamsync.com',
        ]);

        $staffMemberProfile = StaffMemberProfile::withoutSyncingToSearch(function () use ($user) {
            return StaffMemberProfile::factory()->for($user)->create();
        });

        $staffMemberProfile->bankInformation()->create([
            'staff_member_id' => $staffMemberProfile->id,
            'bank_name' => 'BCA',
            'account_number' => '1234567890',
            'account_holder_name' => 'Edge Case Test User',
        ]);

        $payroll = Payroll::create([
            'salary_month' => '2026-07-01',
            'status' => $status,
            'correction_count' => 0,
        ]);

        PayrollDetail::create([
            'payroll_id' => $payroll->id,
            'staff_member_id' => $staffMemberProfile->id,
            'original_salary' => $totalSalary,
            'final_salary' => $totalSalary,
            'attended_days' => 22,
            'sick_days' => 0,
            'absent_days' => 0,
            'notes' => 'Edge case test',
        ]);

        return $payroll->load('payrollDetails.staffMember.user');
    }
}
