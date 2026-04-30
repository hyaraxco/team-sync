<?php

namespace Tests\Feature\Payroll;

use App\Models\Payroll;
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

class PayrollNotificationHandoffTest extends TestCase
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

    public function test_payroll_paid_notification_has_correct_action_url(): void
    {
        [$payroll, $detail, $staffMemberProfile] = $this->createPaidPayrollWithDetail();

        $notification = new PayrollPaid($detail);
        $data = $notification->toArray($staffMemberProfile->user);

        $expectedUrl = '/admin/my-payroll/' . $detail->id;
        $this->assertSame($expectedUrl, $data['action_url']);
    }

    public function test_notification_delivery_summary_includes_delivery_rate(): void
    {
        $this->actingAsRole('finance');
        [$payroll, $detail, $staffMemberProfile] = $this->createPaidPayrollWithDetail();

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

        $response = $this->getJson("/api/v1/payrolls/{$payroll->id}/notification-deliveries")
            ->assertOk();

        $summary = $response->json('data.summary');
        $this->assertArrayHasKey('delivery_rate', $summary);
        $this->assertEquals(100.0, $summary['delivery_rate']);
    }

    public function test_delivery_rate_is_zero_when_no_sent_notifications(): void
    {
        $this->actingAsRole('finance');
        [$payroll, $detail, $staffMemberProfile] = $this->createPaidPayrollWithDetail();

        PayrollNotificationDelivery::create([
            'payroll_id' => $payroll->id,
            'payroll_detail_id' => $detail->id,
            'staff_member_id' => $staffMemberProfile->id,
            'recipient_email' => $staffMemberProfile->user->email,
            'channel' => 'mail',
            'trigger_type' => PayrollNotificationDelivery::TRIGGER_AUTO_PAID,
            'delivery_status' => PayrollNotificationDelivery::STATUS_FAILED,
            'failure_reason' => 'SMTP timeout',
        ]);

        $response = $this->getJson("/api/v1/payrolls/{$payroll->id}/notification-deliveries")
            ->assertOk();

        $summary = $response->json('data.summary');
        $this->assertEquals(0.0, $summary['delivery_rate']);
    }

    public function test_delivery_summary_includes_per_employee_details(): void
    {
        $this->actingAsRole('finance');
        [$payroll, $detail, $staffMemberProfile] = $this->createPaidPayrollWithDetail();

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

        $response = $this->getJson("/api/v1/payrolls/{$payroll->id}/notification-deliveries")
            ->assertOk();

        $latestByEmployee = $response->json('data.latest_by_employee');
        $this->assertCount(1, $latestByEmployee);

        $employeeDelivery = $latestByEmployee[0];
        $this->assertArrayHasKey('employee_name', $employeeDelivery);
        $this->assertArrayHasKey('recipient_email', $employeeDelivery);
        $this->assertArrayHasKey('delivery_status', $employeeDelivery);
        $this->assertArrayHasKey('sent_at', $employeeDelivery);
        $this->assertArrayHasKey('failure_reason', $employeeDelivery);
        $this->assertSame($staffMemberProfile->user->name, $employeeDelivery['employee_name']);
        $this->assertSame($staffMemberProfile->user->email, $employeeDelivery['recipient_email']);
        $this->assertSame('sent', $employeeDelivery['delivery_status']);
        $this->assertNotNull($employeeDelivery['sent_at']);
        $this->assertNull($employeeDelivery['failure_reason']);
    }

    public function test_payroll_paid_notification_action_url_matches_frontend_route(): void
    {
        [$payroll, $detail, $staffMemberProfile] = $this->createPaidPayrollWithDetail();

        $notification = new PayrollPaid($detail);
        $data = $notification->toArray($staffMemberProfile->user);

        // The action_url must match the FE route pattern: /admin/my-payroll/{payroll_detail_id}
        $this->assertMatchesRegularExpression(
            '#^/admin/my-payroll/\d+$#',
            $data['action_url']
        );
        $this->assertSame((int) $detail->id, $data['payroll_detail_id']);
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
            'email' => 'employee+' . uniqid() . '@teamsync.com',
        ]);

        $staffMemberProfile = StaffMemberProfile::withoutSyncingToSearch(function () use ($user) {
            return StaffMemberProfile::factory()->for($user)->create();
        });

        $staffMemberProfile->bankInformation()->create([
            'staff_member_id' => $staffMemberProfile->id,
            'bank_name' => 'BCA',
            'account_number' => '7778889990',
            'account_holder_name' => 'Handoff Test User',
        ]);

        $payroll = Payroll::create([
            'salary_month' => '2026-06-01',
            'status' => 'paid',
            'payment_date' => '2026-06-30',
        ]);

        $detail = PayrollDetail::create([
            'payroll_id' => $payroll->id,
            'staff_member_id' => $staffMemberProfile->id,
            'original_salary' => 10000000,
            'final_salary' => 9500000,
            'attended_days' => 20,
            'sick_days' => 0,
            'absent_days' => 1,
            'notes' => 'Notification handoff test seed',
        ]);

        return [$payroll, $detail, $staffMemberProfile];
    }
}
