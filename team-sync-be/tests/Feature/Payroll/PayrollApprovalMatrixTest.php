<?php

namespace Tests\Feature\Payroll;

use App\Models\Payroll;
use App\Models\PayrollApproval;
use App\Models\PayrollApprovalPolicy;
use App\Models\PayrollDetail;
use App\Models\StaffMemberProfile;
use App\Models\User;
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

class PayrollApprovalMatrixTest extends TestCase
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

    public function test_single_step_approval_still_works_without_policies(): void
    {
        $this->actingAsRole('finance');
        $payroll = $this->createPayrollWithDetail(status: 'pending', totalSalary: 5000000);

        // No policies exist — should approve directly
        $response = $this->postJson("/api/v1/payrolls/{$payroll->id}/approve");

        $response->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->assertSame('approved', $payroll->fresh()->status);
    }

    public function test_multi_step_blocks_mark_as_paid_until_all_approved(): void
    {
        // Create a policy requiring 'director' role for amounts > 1M
        PayrollApprovalPolicy::create([
            'name' => 'Director Approval',
            'min_amount' => 1000000,
            'max_amount' => null,
            'required_role' => 'director',
            'approval_order' => 1,
            'is_active' => true,
        ]);

        // Create director role if not exists
        $this->ensureRoleExists('director');

        $finance = $this->actingAsRole('finance');
        $payroll = $this->createPayrollWithDetail(status: 'pending', totalSalary: 9500000);

        // Finance tries to approve — multi-step kicks in, stays pending
        $this->postJson("/api/v1/payrolls/{$payroll->id}/approve")->assertOk();

        // Payroll should still be pending (finance doesn't have director role)
        $this->assertSame('pending', $payroll->fresh()->status);

        // Verify approval records were created
        $approvals = PayrollApproval::where('payroll_id', $payroll->id)->get();
        $this->assertCount(1, $approvals);
        $this->assertSame('pending', $approvals->first()->status);

        // Mark as paid should fail
        $this->postJson("/api/v1/payrolls/{$payroll->id}/mark-as-paid", [
            'payment_date' => '2026-06-01',
        ])->assertStatus(400);

        // Now director approves
        $director = $this->actingAsRole('director');
        $this->postJson("/api/v1/payrolls/{$payroll->id}/approvals", [
            'status' => 'approved',
            'notes' => 'Approved by director.',
        ])->assertOk();

        // Now payroll should be approved
        $this->assertSame('approved', $payroll->fresh()->status);

        // Switch back to finance (has payroll-process permission) to mark as paid
        $this->actingAsRole('finance');
        $this->postJson("/api/v1/payrolls/{$payroll->id}/mark-as-paid", [
            'payment_date' => '2026-06-01',
        ])->assertOk();

        $this->assertSame('paid', $payroll->fresh()->status);
    }

    public function test_policy_crud_operations(): void
    {
        $this->actingAsRole('finance');

        // Create
        $response = $this->postJson('/api/v1/payroll-approval-policies', [
            'name' => 'Manager Approval',
            'min_amount' => 5000000,
            'max_amount' => 50000000,
            'required_role' => 'manager',
            'approval_order' => 1,
            'is_active' => true,
        ]);

        $response->assertStatus(201);
        $policyId = $response->json('data.id');

        // List
        $this->getJson('/api/v1/payroll-approval-policies')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        // Update
        $this->putJson("/api/v1/payroll-approval-policies/{$policyId}", [
            'name' => 'Updated Manager Approval',
            'min_amount' => 10000000,
        ])->assertOk();

        $policy = PayrollApprovalPolicy::find($policyId);
        $this->assertSame('Updated Manager Approval', $policy->name);
        $this->assertEquals(10000000, (float) $policy->min_amount);

        // Delete
        $this->deleteJson("/api/v1/payroll-approval-policies/{$policyId}")
            ->assertOk();

        $this->assertNull(PayrollApprovalPolicy::find($policyId));
    }

    public function test_approval_status_endpoint(): void
    {
        $this->actingAsRole('finance');
        $payroll = $this->createPayrollWithDetail(status: 'pending', totalSalary: 9500000);

        $response = $this->getJson("/api/v1/payrolls/{$payroll->id}/approvals");

        $response->assertOk()
            ->assertJsonPath('data.payroll_id', $payroll->id)
            ->assertJsonPath('data.is_multi_step', false);
    }

    public function test_policy_create_validation_rejects_invalid_payload(): void
    {
        $this->actingAsRole('finance');

        $this->postJson('/api/v1/payroll-approval-policies', [
            'name' => '',
            'min_amount' => -1,
            'approval_order' => 0,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'min_amount', 'required_role', 'approval_order']);
    }

    public function test_submit_approval_validation_rejects_invalid_status(): void
    {
        $this->actingAsRole('finance');
        $payroll = $this->createPayrollWithDetail(status: 'pending', totalSalary: 9500000);

        $this->postJson("/api/v1/payrolls/{$payroll->id}/approvals", [
            'status' => 'pending',
            'notes' => str_repeat('x', 1001),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status', 'notes']);
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
        $user = User::factory()->create();

        $staffMemberProfile = StaffMemberProfile::withoutSyncingToSearch(function () use ($user) {
            return StaffMemberProfile::factory()->for($user)->create();
        });

        $staffMemberProfile->bankInformation()->create([
            'staff_member_id' => $staffMemberProfile->id,
            'bank_name' => 'BCA',
            'account_number' => '1234567890',
            'account_holder_name' => 'Approval Matrix User',
        ]);

        $payroll = Payroll::create([
            'salary_month' => '2026-05-01',
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
            'notes' => 'Approval matrix test',
        ]);

        return $payroll->load('payrollDetails.staffMember.user');
    }
}
