<?php

namespace Tests\Feature\Payroll;

use App\Interfaces\PayrollRepositoryInterface;
use App\Models\Attendance;
use App\Models\JobInformation;
use App\Models\Payroll;
use App\Models\PayrollActivityLog;
use App\Models\PayrollDetail;
use App\Models\PayrollNotificationDelivery;
use App\Models\StaffMemberProfile;
use App\Models\User;
use App\Notifications\PayrollPaid;
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

class PayrollAuditTrailTest extends TestCase
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

    public function test_repository_logs_update_approve_and_mark_as_paid_events(): void
    {
        $actor = $this->createRoleUser('finance');
        $payroll = $this->createPayrollWithDetail();
        $repository = app(PayrollRepositoryInterface::class);

        $repository->updatePayrollDetail((string) $payroll->payrollDetails->first()->id, [
            'notes' => 'Finance reviewed this draft',
            'final_salary' => 9200000,
        ], $actor->id);

        $repository->approvePayroll((string) $payroll->id, $actor->id);
        $repository->markAsPaid((string) $payroll->id, '2026-04-28', $actor->id);

        $events = PayrollActivityLog::where('payroll_id', $payroll->id)
            ->orderBy('occurred_at')
            ->pluck('event_type')
            ->all();

        $this->assertSame(['detail_updated', 'approved', 'marked_paid'], $events);
        $this->assertDatabaseHas('payroll_activity_logs', [
            'payroll_id' => $payroll->id,
            'event_type' => 'approved',
            'actor_id' => $actor->id,
        ]);
        $this->assertDatabaseHas('payroll_activity_logs', [
            'payroll_id' => $payroll->id,
            'event_type' => 'marked_paid',
            'actor_id' => $actor->id,
        ]);
    }

    public function test_repository_logs_generated_event_for_new_payroll_draft(): void
    {
        Carbon::setTestNow('2026-04-28 09:00:00');
        $hr = $this->createRoleUser('hr');
        $employee = StaffMemberProfile::withoutSyncingToSearch(function () {
            return StaffMemberProfile::factory()->create();
        });

        JobInformation::factory()
            ->forEmployee($employee)
            ->active()
            ->state([
                'monthly_salary' => 10000000,
                'status' => 'active',
            ])
            ->create();

        $month = now()->startOfMonth();
        $this->seedFullMonthAttendance($employee, $month);

        $payroll = app(PayrollRepositoryInterface::class)->generatePayroll($month->format('Y-m'), $hr->id);

        $this->assertDatabaseHas('payroll_activity_logs', [
            'payroll_id' => $payroll->id,
            'event_type' => 'generated',
            'actor_id' => $hr->id,
        ]);

        $generatedLog = PayrollActivityLog::query()
            ->where('payroll_id', $payroll->id)
            ->where('event_type', 'generated')
            ->firstOrFail();

        $this->assertSame((int) $payroll->payroll_setting_version_id, (int) ($generatedLog->metadata['settings_version_id'] ?? 0));
        $this->assertSame(1, (int) ($generatedLog->metadata['settings_version_number'] ?? 0));
        $this->assertSame('auto_business_days', $generatedLog->metadata['settings_snapshot']['working_days_mode'] ?? null);
        $this->assertSame(25, (int) ($generatedLog->metadata['settings_snapshot']['attendance_cutoff_day'] ?? 0));
        $this->assertSame(1.0, (float) ($generatedLog->metadata['settings_snapshot']['absent_deduction_rate'] ?? 0));
    }

    public function test_export_endpoints_create_audit_trail_entries(): void
    {
        $finance = $this->createRoleUser('finance');
        Sanctum::actingAs($finance);

        $payroll = $this->createPayrollWithDetail(status: 'paid', paymentDate: '2026-04-28');

        $this->get("/api/v1/payrolls/{$payroll->id}/export-excel")->assertOk();
        $this->get('/api/v1/payrolls/export-report?status=paid&period_type=monthly&month=2026-04')->assertOk();

        $this->assertDatabaseHas('payroll_activity_logs', [
            'payroll_id' => $payroll->id,
            'event_type' => 'detail_exported',
            'actor_id' => $finance->id,
        ]);

        $this->assertDatabaseHas('payroll_activity_logs', [
            'payroll_id' => $payroll->id,
            'event_type' => 'report_exported',
            'actor_id' => $finance->id,
        ]);
    }

    public function test_activity_logs_endpoint_returns_entries_in_descending_order_with_actor(): void
    {
        $finance = $this->createRoleUser('finance');
        Sanctum::actingAs($finance);

        $payroll = $this->createPayrollWithDetail();

        PayrollActivityLog::create([
            'payroll_id' => $payroll->id,
            'actor_id' => $finance->id,
            'event_type' => 'generated',
            'title' => 'Payroll draft generated',
            'description' => 'Generated by HR.',
            'metadata' => ['salary_month' => '2026-04'],
            'occurred_at' => now()->subHour(),
        ]);

        PayrollActivityLog::create([
            'payroll_id' => $payroll->id,
            'actor_id' => $finance->id,
            'event_type' => 'marked_paid',
            'title' => 'Payroll marked as paid',
            'description' => 'Paid by Finance.',
            'metadata' => ['payment_date' => '2026-04-28'],
            'occurred_at' => now(),
        ]);

        $this->getJson("/api/v1/payrolls/{$payroll->id}/activity-logs")
            ->assertOk()
            ->assertJsonPath('data.0.event_type', 'marked_paid')
            ->assertJsonPath('data.0.actor.email', $finance->email)
            ->assertJsonPath('data.1.event_type', 'generated');
    }

    public function test_finance_can_resend_notifications_for_paid_payroll_and_log_the_event(): void
    {
        Notification::fake();
        $finance = $this->createRoleUser('finance');
        Sanctum::actingAs($finance);

        $payroll = $this->createPayrollWithDetail(status: 'paid', paymentDate: '2026-04-28');
        $employeeUser = $payroll->payrollDetails->first()->staffMember->user;

        $this->postJson("/api/v1/payrolls/{$payroll->id}/resend-notifications")
            ->assertOk()
            ->assertJsonPath('data.id', $payroll->id);

        Notification::assertSentTo($employeeUser, PayrollPaid::class);
        $this->assertDatabaseHas('payroll_notification_deliveries', [
            'payroll_id' => $payroll->id,
            'payroll_detail_id' => $payroll->payrollDetails->first()->id,
            'trigger_type' => PayrollNotificationDelivery::TRIGGER_MANUAL_RESEND,
            'delivery_status' => PayrollNotificationDelivery::STATUS_SENT,
        ]);
        $this->assertDatabaseHas('payroll_activity_logs', [
            'payroll_id' => $payroll->id,
            'event_type' => 'notifications_resent',
            'actor_id' => $finance->id,
        ]);
        $this->assertSame('paid', $payroll->fresh()->status);
    }

    public function test_resend_notifications_requires_paid_payroll(): void
    {
        Notification::fake();
        $finance = $this->createRoleUser('finance');
        Sanctum::actingAs($finance);

        $payroll = $this->createPayrollWithDetail(status: 'pending');

        $this->postJson("/api/v1/payrolls/{$payroll->id}/resend-notifications")
            ->assertStatus(400)
            ->assertJsonPath('message', 'Notifications can only be resent for paid payrolls');

        Notification::assertNothingSent();
        $this->assertDatabaseMissing('payroll_activity_logs', [
            'payroll_id' => $payroll->id,
            'event_type' => 'notifications_resent',
        ]);
    }

    public function test_mark_paid_then_resend_sends_two_notifications_for_same_employee(): void
    {
        Notification::fake();
        $finance = $this->createRoleUser('finance');
        Sanctum::actingAs($finance);

        $payroll = $this->createPayrollWithDetail(status: 'approved');
        $employeeUser = $payroll->payrollDetails->first()->staffMember->user;

        $this->postJson("/api/v1/payrolls/{$payroll->id}/mark-as-paid", [
            'payment_date' => '2026-04-28',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'paid');

        $this->postJson("/api/v1/payrolls/{$payroll->id}/resend-notifications")
            ->assertOk()
            ->assertJsonPath('data.id', $payroll->id);

        Notification::assertSentToTimes($employeeUser, PayrollPaid::class, 2);
        $this->assertDatabaseCount('payroll_notification_deliveries', 2);
        $this->assertDatabaseHas('payroll_notification_deliveries', [
            'payroll_id' => $payroll->id,
            'payroll_detail_id' => $payroll->payrollDetails->first()->id,
            'trigger_type' => PayrollNotificationDelivery::TRIGGER_AUTO_PAID,
            'delivery_status' => PayrollNotificationDelivery::STATUS_SENT,
        ]);
        $this->assertDatabaseHas('payroll_notification_deliveries', [
            'payroll_id' => $payroll->id,
            'payroll_detail_id' => $payroll->payrollDetails->first()->id,
            'trigger_type' => PayrollNotificationDelivery::TRIGGER_MANUAL_RESEND,
            'delivery_status' => PayrollNotificationDelivery::STATUS_SENT,
        ]);

        $this->getJson("/api/v1/payrolls/{$payroll->id}/notification-deliveries")
            ->assertOk()
            ->assertJsonPath('data.summary.total_recipients', 1)
            ->assertJsonPath('data.summary.total_attempts', 2)
            ->assertJsonPath('data.summary.sent_count', 2)
            ->assertJsonPath('data.summary.auto_attempt_count', 1)
            ->assertJsonPath('data.summary.manual_attempt_count', 1)
            ->assertJsonPath('data.latest_by_employee.0.attempt_count', 2)
            ->assertJsonPath('data.latest_by_employee.0.delivery_status', PayrollNotificationDelivery::STATUS_SENT)
            ->assertJsonPath(
                'data.latest_by_employee.0.payslip_path',
                '/admin/my-payroll/'.$payroll->payrollDetails->first()->id
            );

        $this->assertDatabaseHas('payroll_activity_logs', [
            'payroll_id' => $payroll->id,
            'event_type' => 'marked_paid',
            'actor_id' => $finance->id,
        ]);

        $this->assertDatabaseHas('payroll_activity_logs', [
            'payroll_id' => $payroll->id,
            'event_type' => 'notifications_resent',
            'actor_id' => $finance->id,
        ]);
    }

    public function test_finance_can_reopen_paid_payroll_and_activity_log_captures_reason(): void
    {
        $finance = $this->createRoleUser('finance');
        Sanctum::actingAs($finance);

        $payroll = $this->createPayrollWithDetail(status: 'paid', paymentDate: '2026-04-28');
        $reason = 'Correct salary deduction mismatch after audit review.';

        $this->postJson("/api/v1/payrolls/{$payroll->id}/reopen", [
            'reason' => $reason,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'pending');

        $this->assertNull($payroll->fresh()->payment_date);

        $this->assertDatabaseHas('payroll_activity_logs', [
            'payroll_id' => $payroll->id,
            'event_type' => 'reopened_for_correction',
            'actor_id' => $finance->id,
        ]);

        $log = PayrollActivityLog::query()
            ->where('payroll_id', $payroll->id)
            ->where('event_type', 'reopened_for_correction')
            ->latest('occurred_at')
            ->firstOrFail();

        $this->assertSame($reason, $log->metadata['reason'] ?? null);
        $this->assertSame('paid', $log->metadata['previous_status'] ?? null);
        $this->assertSame('2026-04-28', $log->metadata['previous_payment_date'] ?? null);
    }

    private function createRoleUser(string $roleName): User
    {
        $user = User::factory()->create();
        $role = Role::findByName($roleName, 'sanctum');
        $user->assignRole($role);

        return $user;
    }

    private function seedFullMonthAttendance(StaffMemberProfile $employee, Carbon $month): void
    {
        $cursor = $month->copy()->startOfMonth();
        $monthEnd = $month->copy()->endOfMonth();

        while ($cursor->lte($monthEnd)) {
            if (! $cursor->isWeekend()) {
                Attendance::create([
                    'staff_member_id' => $employee->id,
                    'date' => $cursor->toDateString(),
                    'status' => 'present',
                    'check_in' => $cursor->copy()->format('Y-m-d').' 08:00:00',
                    'check_out' => $cursor->copy()->format('Y-m-d').' 17:00:00',
                ]);
            }

            $cursor->addDay();
        }
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
            'account_number' => '9876543210',
            'account_holder_name' => 'Payroll Audit User',
            'account_type' => 'saving',
        ]);

        $payroll = Payroll::create([
            'salary_month' => '2026-04-01',
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
            'notes' => 'Audit trail seed',
        ]);

        return $payroll->load('payrollDetails');
    }
}
