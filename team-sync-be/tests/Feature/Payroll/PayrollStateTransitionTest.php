<?php

namespace Tests\Feature\Payroll;

use App\Models\StaffMemberProfile;
use App\Models\Payroll;
use App\Models\PayrollDetail;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PayrollStateTransitionTest extends TestCase
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

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_finance_cannot_approve_processing_payroll(): void
    {
        $this->actingAsRole('finance');
        $payroll = $this->createPayrollWithDetail(status: 'processing');

        $this->postJson("/api/v1/payrolls/{$payroll->id}/approve")
            ->assertStatus(400)
            ->assertJsonPath('message', 'Payroll must be pending before it can be approved');

        $this->assertSame('processing', $payroll->fresh()->status);
    }

    public function test_finance_cannot_mark_pending_payroll_as_paid_without_approval(): void
    {
        $this->actingAsRole('finance');
        $payroll = $this->createPayrollWithDetail(status: 'pending');

        $this->postJson("/api/v1/payrolls/{$payroll->id}/mark-as-paid", [
            'payment_date' => '2026-05-30',
        ])
            ->assertStatus(400)
            ->assertJsonPath('message', 'Payroll must be approved before it can be marked as paid');

        $this->assertSame('pending', $payroll->fresh()->status);
    }

    public function test_finance_cannot_approve_payroll_twice(): void
    {
        $this->actingAsRole('finance');
        $payroll = $this->createPayrollWithDetail(status: 'approved');

        $this->postJson("/api/v1/payrolls/{$payroll->id}/approve")
            ->assertStatus(400)
            ->assertJsonPath('message', 'Payroll sudah disetujui');

        $this->assertSame('approved', $payroll->fresh()->status);
    }

    public function test_finance_sequential_approve_requests_only_first_transitions_payroll(): void
    {
        $this->actingAsRole('finance');
        $payroll = $this->createPayrollWithDetail(status: 'pending');

        $this->postJson("/api/v1/payrolls/{$payroll->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->postJson("/api/v1/payrolls/{$payroll->id}/approve")
            ->assertStatus(400)
            ->assertJsonPath('message', 'Payroll sudah disetujui');

        $this->assertSame('approved', $payroll->fresh()->status);
    }

    public function test_finance_cannot_mark_paid_payroll_twice(): void
    {
        $this->actingAsRole('finance');
        $payroll = $this->createPayrollWithDetail(status: 'paid', paymentDate: '2026-05-30');

        $this->postJson("/api/v1/payrolls/{$payroll->id}/mark-as-paid", [
            'payment_date' => '2026-05-31',
        ])
            ->assertStatus(400)
            ->assertJsonPath('message', 'Payroll sudah dibayar');

        $this->assertSame('paid', $payroll->fresh()->status);
    }

    public function test_finance_sequential_mark_paid_requests_keep_first_payment_date(): void
    {
        $this->actingAsRole('finance');
        $payroll = $this->createPayrollWithDetail(status: 'approved');

        $this->postJson("/api/v1/payrolls/{$payroll->id}/mark-as-paid", [
            'payment_date' => '2026-05-30',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'paid');

        $this->postJson("/api/v1/payrolls/{$payroll->id}/mark-as-paid", [
            'payment_date' => '2026-05-31',
        ])
            ->assertStatus(400)
            ->assertJsonPath('message', 'Payroll sudah dibayar');

        $freshPayroll = $payroll->fresh();

        $this->assertSame('paid', $freshPayroll->status);
        $this->assertSame('2026-05-30', (string) optional($freshPayroll->payment_date)->format('Y-m-d'));
    }

    public function test_finance_can_reopen_approved_payroll_for_correction(): void
    {
        $this->actingAsRole('finance');
        $payroll = $this->createPayrollWithDetail(status: 'approved');

        $this->postJson("/api/v1/payrolls/{$payroll->id}/reopen", [
            'reason' => 'Need to correct final salary before payment.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('message', 'Payroll Reopened for Correction Successfully');

        $freshPayroll = $payroll->fresh();
        $this->assertSame('pending', $freshPayroll->status);
        $this->assertNull($freshPayroll->payment_date);
    }

    public function test_finance_can_reopen_paid_payroll_for_correction_and_clear_payment_date(): void
    {
        $this->actingAsRole('finance');
        $payroll = $this->createPayrollWithDetail(status: 'paid', paymentDate: '2026-05-30');

        $this->postJson("/api/v1/payrolls/{$payroll->id}/reopen", [
            'reason' => 'Bank account correction requires payroll recalculation.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'pending');

        $freshPayroll = $payroll->fresh();
        $this->assertSame('pending', $freshPayroll->status);
        $this->assertNull($freshPayroll->payment_date);
    }

    public function test_finance_cannot_reopen_pending_payroll(): void
    {
        $this->actingAsRole('finance');
        $payroll = $this->createPayrollWithDetail(status: 'pending');

        $this->postJson("/api/v1/payrolls/{$payroll->id}/reopen", [
            'reason' => 'Need correction before approval.',
        ])
            ->assertStatus(400)
            ->assertJsonPath('message', 'Payroll is already in pending status');

        $this->assertSame('pending', $payroll->fresh()->status);
    }

    private function actingAsRole(string $roleName): User
    {
        $user = User::factory()->create();
        $role = Role::findByName($roleName, 'sanctum');
        $user->assignRole($role);

        Sanctum::actingAs($user);

        return $user;
    }

    private function createPayrollWithDetail(string $status = 'pending', ?string $paymentDate = null): Payroll
    {
        $staffMemberProfile = StaffMemberProfile::withoutSyncingToSearch(function () {
            return StaffMemberProfile::factory()->create();
        });

        $staffMemberProfile->bankInformation()->create([
            'staff_member_id' => $staffMemberProfile->id,
            'bank_name' => 'BCA',
            'account_number' => '1234567890',
            'account_holder_name' => 'Payroll Transition User',
            'account_type' => 'saving',
        ]);

        $payroll = Payroll::create([
            'salary_month' => '2026-05-01',
            'status' => $status,
            'payment_date' => $paymentDate,
        ]);

        PayrollDetail::create([
            'payroll_id' => $payroll->id,
            'staff_member_id' => $staffMemberProfile->id,
            'original_salary' => 10000000,
            'final_salary' => 9500000,
            'attended_days' => 20,
            'sick_days' => 1,
            'absent_days' => 1,
            'notes' => 'State transition seed',
        ]);

        return $payroll;
    }
}