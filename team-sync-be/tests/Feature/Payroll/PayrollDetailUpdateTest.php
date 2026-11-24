<?php

namespace Tests\Feature\Payroll;

use App\Models\Payroll;
use App\Models\PayrollActivityLog;
use App\Models\PayrollDetail;
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

class PayrollDetailUpdateTest extends TestCase
{
    use ActivatesLicense, BuildsCalculatedPayroll, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->activateTestLicense();

        $this->seed([
            RoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,
        ]);

        $this->seedPayrollTaxFixtures();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_finance_can_update_notes_without_changing_derived_salary_on_pending_payroll(): void
    {
        $this->actingAsRole('finance');
        [$payroll, $detail] = $this->createPayrollWithDetail(status: 'pending');

        $calculatedSalary = $detail->final_salary;

        $this->putJson("/api/v1/payroll-details/{$detail->id}", [
            'notes' => 'Reviewed by finance.',
        ])
            ->assertOk();

        $detail->refresh();
        $this->assertSame('Reviewed by finance.', $detail->notes);
        $this->assertEquals($calculatedSalary, $detail->final_salary);
    }

    public function test_derived_amount_edits_are_rejected(): void
    {
        $this->actingAsRole('finance');
        [$payroll, $detail] = $this->createPayrollWithDetail(status: 'pending');
        $calculatedSalary = $detail->final_salary;

        $this->putJson("/api/v1/payroll-details/{$detail->id}", [
            'final_salary' => 9200000,
            'taxable_adjustment_amount' => 1000000,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['final_salary', 'taxable_adjustment_amount']);

        $this->assertEquals($calculatedSalary, $detail->fresh()->final_salary);
        $this->assertEquals(0, $detail->fresh()->taxable_adjustment_amount);
    }

    public function test_cannot_update_detail_on_approved_payroll(): void
    {
        $this->actingAsRole('finance');
        [$payroll, $detail] = $this->createPayrollWithDetail(status: 'approved');

        $this->putJson("/api/v1/payroll-details/{$detail->id}", [
            'notes' => 'Attempt to overwrite reviewed notes.',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Cannot update payroll details for a payroll that has already been approved or paid.');

        // Verify detail unchanged
        $detail->refresh();
        $this->assertSame('Detail update test seed', $detail->notes);
    }

    public function test_cannot_update_detail_on_paid_payroll(): void
    {
        $this->actingAsRole('finance');
        [$payroll, $detail] = $this->createPayrollWithDetail(status: 'paid', paymentDate: '2026-05-30');

        $this->putJson("/api/v1/payroll-details/{$detail->id}", [
            'notes' => 'Attempt edit after payment.',
        ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Cannot update payroll details for a payroll that has already been approved or paid.');

        // Verify detail unchanged
        $detail->refresh();
        $this->assertSame('Detail update test seed', $detail->notes);
    }

    public function test_partial_update_with_only_notes_succeeds(): void
    {
        $this->actingAsRole('finance');
        [$payroll, $detail] = $this->createPayrollWithDetail(status: 'pending');
        $originalSalary = $detail->final_salary;

        $this->putJson("/api/v1/payroll-details/{$detail->id}", [
            'notes' => 'Only updating notes, salary untouched.',
        ])
            ->assertOk();

        $detail->refresh();
        $this->assertSame('Only updating notes, salary untouched.', $detail->notes);
        $this->assertEquals($originalSalary, $detail->final_salary);
    }

    public function test_update_detail_creates_audit_log(): void
    {
        $finance = $this->actingAsRole('finance');
        [$payroll, $detail] = $this->createPayrollWithDetail(status: 'pending');

        $this->putJson("/api/v1/payroll-details/{$detail->id}", [
            'notes' => 'Audit log test.',
        ])->assertOk();

        $this->assertDatabaseHas('payroll_activity_logs', [
            'payroll_id' => $payroll->id,
            'event_type' => 'detail_updated',
            'actor_id' => $finance->id,
        ]);

        $log = PayrollActivityLog::query()
            ->where('payroll_id', $payroll->id)
            ->where('event_type', 'detail_updated')
            ->firstOrFail();

        $this->assertSame($detail->id, $log->metadata['payroll_detail_id'] ?? null);
        $this->assertNotContains('final_salary', $log->metadata['changed_fields'] ?? []);
        $this->assertContains('notes', $log->metadata['changed_fields'] ?? []);
    }

    public function test_update_detail_then_approve_then_pay_preserves_notes_and_calculated_salary(): void
    {
        $this->actingAsRole('finance');
        [$payroll, $detail] = $this->createPayrollWithDetail(status: 'pending');

        $calculatedSalary = $detail->final_salary;

        // Update
        $this->putJson("/api/v1/payroll-details/{$detail->id}", [
            'notes' => 'Verified bank account and payroll calculation.',
        ])->assertOk();

        // Approve
        $this->postJson("/api/v1/payrolls/{$payroll->id}/approve")->assertOk();
        $this->assertSame('approved', $payroll->fresh()->status->value);

        // Mark paid
        $this->postJson("/api/v1/payrolls/{$payroll->id}/mark-as-paid", [
            'payment_date' => '2026-05-15',
        ])->assertOk();

        // Notes and calculated salary persist through the entire lifecycle
        $detail->refresh();
        $this->assertEquals($calculatedSalary, $detail->final_salary);
        $this->assertSame('Verified bank account and payroll calculation.', $detail->notes);
        $this->assertSame('paid', $payroll->fresh()->status->value);
    }

    private function actingAsRole(string $roleName): User
    {
        $user = User::factory()->create();
        $role = Role::findByName($roleName, 'sanctum');
        $user->assignRole($role);

        Sanctum::actingAs($user);

        return $user;
    }

    private function createPayrollWithDetail(string $status = 'pending', ?string $paymentDate = null): array
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
            'account_number' => '5555666677',
            'account_holder_name' => 'Detail Update User',
        ]);

        $payroll = Payroll::create([
            'salary_month' => '2026-05-01',
            'status' => $status === 'approved' ? 'pending' : $status,
            'payroll_setting_version_id' => $this->payrollSettingVersionId(),
            'payment_date' => $paymentDate,
        ]);

        $detail = PayrollDetail::create([
            'payroll_id' => $payroll->id,
            'staff_member_id' => $staffMemberProfile->id,
            'original_salary' => 10000000,
            'final_salary' => 9500000,
            'attended_days' => 20,
            'sick_days' => 1,
            'absent_days' => 1,
            'notes' => 'Detail update test seed',
        ]);

        $this->calculatePayrollFixture($payroll, $status);

        return [$payroll, $detail->fresh()];
    }

    public function test_update_with_stale_updated_at_returns_409(): void
    {
        $this->actingAsRole('finance');
        [$payroll, $detail] = $this->createPayrollWithDetail(status: 'pending');

        // Record original updated_at
        $originalUpdatedAt = $detail->updated_at->toISOString();

        // Advance time to ensure updated_at changes
        Carbon::setTestNow(Carbon::now()->addMinute());

        // Simulate another user updating the record
        $detail->update(['notes' => 'Modified by another user']);

        // Try to update with stale updated_at
        $this->putJson("/api/v1/payroll-details/{$detail->id}", [
            'notes' => 'Attempt to overwrite reviewed notes.',
            'updated_at' => $originalUpdatedAt,
        ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Record was modified by another user. Please refresh and try again.');

        // Verify detail was NOT updated with the stale request
        $detail->refresh();
        $this->assertNotSame('Attempt to overwrite reviewed notes.', $detail->notes);
        $this->assertSame('Modified by another user', $detail->notes);
    }

    public function test_update_with_current_updated_at_succeeds(): void
    {
        $this->actingAsRole('finance');
        [$payroll, $detail] = $this->createPayrollWithDetail(status: 'pending');

        // Use current updated_at
        $currentUpdatedAt = $detail->updated_at->toISOString();

        $this->putJson("/api/v1/payroll-details/{$detail->id}", [
            'notes' => 'Reviewed using the current record version.',
            'updated_at' => $currentUpdatedAt,
        ])
            ->assertOk();

        $detail->refresh();
        $this->assertSame('Reviewed using the current record version.', $detail->notes);
    }

    public function test_update_without_updated_at_skips_lock_check(): void
    {
        $this->actingAsRole('finance');
        [$payroll, $detail] = $this->createPayrollWithDetail(status: 'pending');

        // Simulate another user updating the record
        $detail->update(['notes' => 'Modified by another user']);

        // Update without providing updated_at (backward compatible)
        $this->putJson("/api/v1/payroll-details/{$detail->id}", [
            'notes' => 'Reviewed without an explicit version token.',
        ])
            ->assertOk();

        $detail->refresh();
        $this->assertSame('Reviewed without an explicit version token.', $detail->notes);
    }
}
