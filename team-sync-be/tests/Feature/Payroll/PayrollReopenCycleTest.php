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
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PayrollReopenCycleTest extends TestCase
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
        Notification::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_reopen_approved_then_re_approve_and_mark_paid_full_cycle(): void
    {
        $finance = $this->actingAsRole('finance');
        $payroll = $this->createPayrollWithDetail(status: 'approved');

        // Step 1: Reopen
        $this->postJson("/api/v1/payrolls/{$payroll->id}/reopen", [
            'reason' => 'Need to adjust salary before payment.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'pending');

        $this->assertSame('pending', $payroll->fresh()->status);
        $this->assertNull($payroll->fresh()->payment_date);

        // Step 2: Re-approve
        $this->postJson("/api/v1/payrolls/{$payroll->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->assertSame('approved', $payroll->fresh()->status);

        // Step 3: Mark as paid
        $this->postJson("/api/v1/payrolls/{$payroll->id}/mark-as-paid", [
            'payment_date' => '2026-06-01',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'paid');

        $freshPayroll = $payroll->fresh();
        $this->assertSame('paid', $freshPayroll->status);
        $this->assertSame('2026-06-01', optional($freshPayroll->payment_date)->format('Y-m-d'));

        // Verify audit trail has all events in correct order
        $events = PayrollActivityLog::where('payroll_id', $payroll->id)
            ->orderBy('occurred_at')
            ->pluck('event_type')
            ->all();

        $this->assertSame(['reopened_for_correction', 'approved', 'marked_paid'], $events);
    }

    public function test_reopen_paid_then_re_approve_and_re_pay_preserves_detail_data(): void
    {
        $finance = $this->actingAsRole('finance');
        $payroll = $this->createPayrollWithDetail(status: 'paid', paymentDate: '2026-05-30');
        $detail = $payroll->payrollDetails->first();
        $originalFinalSalary = $detail->final_salary;

        // Reopen from paid
        $this->postJson("/api/v1/payrolls/{$payroll->id}/reopen", [
            'reason' => 'Bank account correction requires recalculation.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'pending');

        // payment_date should be cleared
        $this->assertNull($payroll->fresh()->payment_date);

        // Detail data should still be intact
        $detail->refresh();
        $this->assertEquals($originalFinalSalary, $detail->final_salary);

        // Re-approve and re-pay
        $this->postJson("/api/v1/payrolls/{$payroll->id}/approve")->assertOk();
        $this->postJson("/api/v1/payrolls/{$payroll->id}/mark-as-paid", [
            'payment_date' => '2026-06-05',
        ])->assertOk();

        $freshPayroll = $payroll->fresh();
        $this->assertSame('paid', $freshPayroll->status);
        $this->assertSame('2026-06-05', optional($freshPayroll->payment_date)->format('Y-m-d'));
    }

    public function test_reopen_then_edit_detail_then_re_approve(): void
    {
        $finance = $this->actingAsRole('finance');
        $payroll = $this->createPayrollWithDetail(status: 'approved');
        $detail = $payroll->payrollDetails->first();

        // Reopen
        $this->postJson("/api/v1/payrolls/{$payroll->id}/reopen", [
            'reason' => 'Salary needs manual adjustment for bonus.',
        ])->assertOk();

        // Edit detail while pending
        $this->putJson("/api/v1/payroll-details/{$detail->id}", [
            'final_salary' => 11000000,
            'notes' => 'Added performance bonus.',
        ])->assertOk();

        $detail->refresh();
        $this->assertEquals(11000000, $detail->final_salary);
        $this->assertSame('Added performance bonus.', $detail->notes);

        // Re-approve — edited salary should persist
        $this->postJson("/api/v1/payrolls/{$payroll->id}/approve")->assertOk();
        $this->assertSame('approved', $payroll->fresh()->status);

        $detail->refresh();
        $this->assertEquals(11000000, $detail->final_salary);
    }

    public function test_cannot_reopen_processing_payroll(): void
    {
        $this->actingAsRole('finance');
        $payroll = $this->createPayrollWithDetail(status: 'processing');

        $this->postJson("/api/v1/payrolls/{$payroll->id}/reopen", [
            'reason' => 'Trying to reopen processing payroll.',
        ])
            ->assertStatus(400)
            ->assertJsonPath('message', 'Processing payroll cannot be reopened for correction');

        $this->assertSame('processing', $payroll->fresh()->status);
    }

    public function test_reopen_reason_is_captured_in_audit_log(): void
    {
        $finance = $this->actingAsRole('finance');
        $payroll = $this->createPayrollWithDetail(status: 'approved');
        $reason = 'Deduction rate was misconfigured for part-time employees.';

        $this->postJson("/api/v1/payrolls/{$payroll->id}/reopen", [
            'reason' => $reason,
        ])->assertOk();

        $log = PayrollActivityLog::query()
            ->where('payroll_id', $payroll->id)
            ->where('event_type', 'reopened_for_correction')
            ->firstOrFail();

        $this->assertSame($reason, $log->metadata['reason'] ?? null);
        $this->assertSame('approved', $log->metadata['previous_status'] ?? null);
        $this->assertSame($finance->id, $log->actor_id);
    }

    public function test_sequential_reopen_on_pending_payroll_is_rejected(): void
    {
        $this->actingAsRole('finance');
        $payroll = $this->createPayrollWithDetail(status: 'approved');

        // First reopen succeeds
        $this->postJson("/api/v1/payrolls/{$payroll->id}/reopen", [
            'reason' => 'First reopen.',
        ])->assertOk();

        // Second reopen on already-pending fails
        $this->postJson("/api/v1/payrolls/{$payroll->id}/reopen", [
            'reason' => 'Second reopen attempt.',
        ])
            ->assertStatus(400)
            ->assertJsonPath('message', 'Payroll is already in pending status');
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
            'account_holder_name' => 'Reopen Cycle User',
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
            'notes' => 'Reopen cycle seed',
        ]);

        return $payroll->load('payrollDetails');
    }
}
