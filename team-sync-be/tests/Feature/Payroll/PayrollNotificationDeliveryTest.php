<?php

namespace Tests\Feature\Payroll;

use App\Models\Payroll;
use App\Models\PayrollDetail;
use App\Models\PayrollNotificationDelivery;
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

class PayrollNotificationDeliveryTest extends TestCase
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

    public function test_delivery_summary_returns_correct_counts(): void
    {
        $finance = $this->actingAsRole('finance');
        [$payroll, $detail, $staffMemberProfile] = $this->createPaidPayrollWithDetail();

        // Seed delivery records manually
        PayrollNotificationDelivery::create([
            'payroll_id' => $payroll->id,
            'payroll_detail_id' => $detail->id,
            'staff_member_id' => $staffMemberProfile->id,
            'recipient_email' => $staffMemberProfile->user->email,
            'channel' => 'mail',
            'trigger_type' => PayrollNotificationDelivery::TRIGGER_AUTO_PAID,
            'delivery_status' => PayrollNotificationDelivery::STATUS_SENT,
            'sent_at' => now(),
        ]);

        PayrollNotificationDelivery::create([
            'payroll_id' => $payroll->id,
            'payroll_detail_id' => $detail->id,
            'staff_member_id' => $staffMemberProfile->id,
            'recipient_email' => $staffMemberProfile->user->email,
            'channel' => 'mail',
            'trigger_type' => PayrollNotificationDelivery::TRIGGER_MANUAL_RESEND,
            'delivery_status' => PayrollNotificationDelivery::STATUS_FAILED,
            'failure_reason' => 'SMTP timeout',
        ]);

        $response = $this->getJson("/api/v1/payrolls/{$payroll->id}/notification-deliveries")
            ->assertOk();

        $summary = $response->json('data.summary');
        $this->assertSame(1, $summary['total_recipients']);
        $this->assertSame(2, $summary['total_attempts']);
        $this->assertSame(1, $summary['sent_count']);
        $this->assertSame(1, $summary['failed_count']);
        $this->assertSame(0, $summary['skipped_count']);
        $this->assertSame(1, $summary['auto_attempt_count']);
        $this->assertSame(1, $summary['manual_attempt_count']);
    }

    public function test_delivery_summary_groups_latest_by_employee(): void
    {
        $this->actingAsRole('finance');
        [$payroll, $detail, $staffMemberProfile] = $this->createPaidPayrollWithDetail();

        // Two deliveries for same employee/detail
        PayrollNotificationDelivery::create([
            'payroll_id' => $payroll->id,
            'payroll_detail_id' => $detail->id,
            'staff_member_id' => $staffMemberProfile->id,
            'recipient_email' => $staffMemberProfile->user->email,
            'channel' => 'mail',
            'trigger_type' => PayrollNotificationDelivery::TRIGGER_AUTO_PAID,
            'delivery_status' => PayrollNotificationDelivery::STATUS_SENT,
            'sent_at' => now()->subMinutes(5),
        ]);

        PayrollNotificationDelivery::create([
            'payroll_id' => $payroll->id,
            'payroll_detail_id' => $detail->id,
            'staff_member_id' => $staffMemberProfile->id,
            'recipient_email' => $staffMemberProfile->user->email,
            'channel' => 'mail',
            'trigger_type' => PayrollNotificationDelivery::TRIGGER_MANUAL_RESEND,
            'delivery_status' => PayrollNotificationDelivery::STATUS_SENT,
            'sent_at' => now(),
        ]);

        $response = $this->getJson("/api/v1/payrolls/{$payroll->id}/notification-deliveries")
            ->assertOk();

        $latestByEmployee = $response->json('data.latest_by_employee');
        $this->assertCount(1, $latestByEmployee);
        $this->assertSame(2, $latestByEmployee[0]['attempt_count']);
        $this->assertSame(
            '/admin/my-payroll/'.$detail->id,
            $latestByEmployee[0]['payslip_path']
        );
    }

    public function test_delivery_summary_empty_when_no_records(): void
    {
        $this->actingAsRole('finance');
        [$payroll] = $this->createPaidPayrollWithDetail();

        $response = $this->getJson("/api/v1/payrolls/{$payroll->id}/notification-deliveries")
            ->assertOk();

        $summary = $response->json('data.summary');
        $this->assertSame(0, $summary['total_attempts']);
        $this->assertSame(0, $summary['sent_count']);
        $this->assertSame(0, $summary['failed_count']);
        $this->assertNull($summary['last_attempt_at']);
        $this->assertNull($summary['last_sent_at']);

        $this->assertEmpty($response->json('data.latest_by_employee'));
    }

    public function test_delivery_summary_includes_skipped_status(): void
    {
        $this->actingAsRole('finance');
        [$payroll, $detail, $staffMemberProfile] = $this->createPaidPayrollWithDetail();

        PayrollNotificationDelivery::create([
            'payroll_id' => $payroll->id,
            'payroll_detail_id' => $detail->id,
            'staff_member_id' => $staffMemberProfile->id,
            'recipient_email' => '',
            'channel' => 'mail',
            'trigger_type' => PayrollNotificationDelivery::TRIGGER_AUTO_PAID,
            'delivery_status' => PayrollNotificationDelivery::STATUS_SKIPPED,
        ]);

        $response = $this->getJson("/api/v1/payrolls/{$payroll->id}/notification-deliveries")
            ->assertOk();

        $this->assertSame(1, $response->json('data.summary.skipped_count'));
        $this->assertSame(0, $response->json('data.summary.sent_count'));
    }

    private function actingAsRole(string $roleName): User
    {
        $user = User::factory()->create();
        $role = Role::findByName($roleName, 'sanctum');
        $user->assignRole($role);

        Sanctum::actingAs($user);

        return $user;
    }

    private function createPaidPayrollWithDetail(): array
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
            'account_number' => '7778889990',
            'account_holder_name' => 'Notification Test User',
        ]);

        $payroll = Payroll::create([
            'salary_month' => '2026-05-01',
            'status' => 'paid',
            'payment_date' => '2026-05-30',
        ]);

        $detail = PayrollDetail::create([
            'payroll_id' => $payroll->id,
            'staff_member_id' => $staffMemberProfile->id,
            'original_salary' => 10000000,
            'final_salary' => 9500000,
            'attended_days' => 20,
            'sick_days' => 0,
            'absent_days' => 1,
            'notes' => 'Notification delivery test seed',
        ]);

        return [$payroll, $detail, $staffMemberProfile];
    }
}
