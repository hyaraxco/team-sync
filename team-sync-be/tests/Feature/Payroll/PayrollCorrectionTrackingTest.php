<?php

namespace Tests\Feature\Payroll;

use App\Models\Payroll;
use App\Models\PayrollDetail;
use App\Models\StaffMemberProfile;
use App\Models\User;
use App\Notifications\PayrollCorrected;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\ActivatesLicense;
use Tests\TestCase;

class PayrollCorrectionTrackingTest extends TestCase
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

    public function test_correction_count_increments_on_reopen(): void
    {
        $this->actingAsRole('finance');
        $payroll = $this->createPayrollWithDetail(status: 'approved');

        $this->assertSame(0, (int) $payroll->correction_count);

        // First reopen
        $this->postJson("/api/v1/payrolls/{$payroll->id}/reopen", [
            'reason' => 'First correction needed for salary adjustment.',
        ])->assertOk();

        $payroll->refresh();
        $this->assertSame(1, (int) $payroll->correction_count);

        // Re-approve and re-pay
        $this->postJson("/api/v1/payrolls/{$payroll->id}/approve")->assertOk();
        $this->postJson("/api/v1/payrolls/{$payroll->id}/mark-as-paid", [
            'payment_date' => '2026-05-15',
        ])->assertOk();

        // Second reopen from paid should be rejected
        $this->postJson("/api/v1/payrolls/{$payroll->id}/reopen", [
            'reason' => 'Second correction needed for bank account fix.',
        ])->assertStatus(400);

        $payroll->refresh();
        $this->assertSame(1, (int) $payroll->correction_count);
    }

    public function test_correction_count_returned_in_resource(): void
    {
        $this->actingAsRole('finance');
        $payroll = $this->createPayrollWithDetail(status: 'approved');

        // Reopen to increment
        $this->postJson("/api/v1/payrolls/{$payroll->id}/reopen", [
            'reason' => 'Correction needed for deduction rate.',
        ])->assertOk();

        $response = $this->getJson("/api/v1/payrolls/{$payroll->id}");

        $response->assertOk()
            ->assertJsonPath('data.correction_count', 1);
    }

    public function test_correction_notification_sent_when_corrected_payroll_is_repaid(): void
    {
        $this->actingAsRole('finance');
        $payroll = $this->createPayrollWithDetail(status: 'approved');
        $employeeUser = $payroll->payrollDetails->first()->staffMember->user;

        // Reopen (correction_count becomes 1)
        $this->postJson("/api/v1/payrolls/{$payroll->id}/reopen", [
            'reason' => 'Bank account was wrong, need to correct.',
        ])->assertOk();

        // Re-approve
        $this->postJson("/api/v1/payrolls/{$payroll->id}/approve")->assertOk();

        // Re-pay — should trigger PayrollCorrected notification
        $this->postJson("/api/v1/payrolls/{$payroll->id}/mark-as-paid", [
            'payment_date' => '2026-05-16',
        ])->assertOk();

        Notification::assertSentTo(
            $employeeUser,
            PayrollCorrected::class,
            function (PayrollCorrected $notification) {
                $array = $notification->toArray($notification);

                return $array['correction_count'] === 1;
            }
        );
    }

    public function test_normal_payroll_does_not_send_correction_notification(): void
    {
        $this->actingAsRole('finance');
        $payroll = $this->createPayrollWithDetail(status: 'approved');

        // Mark as paid without any reopen (correction_count = 0)
        $this->postJson("/api/v1/payrolls/{$payroll->id}/mark-as-paid", [
            'payment_date' => '2026-05-15',
        ])->assertOk();

        $employeeUser = $payroll->payrollDetails->first()->staffMember->user;
        Notification::assertNotSentTo($employeeUser, PayrollCorrected::class);
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
            'account_holder_name' => 'Correction Test User',
        ]);

        $payroll = Payroll::create([
            'salary_month' => '2026-05-01',
            'status' => $status,
            'payment_date' => $paymentDate,
            'correction_count' => 0,
        ]);

        PayrollDetail::create([
            'payroll_id' => $payroll->id,
            'staff_member_id' => $staffMemberProfile->id,
            'original_salary' => 10000000,
            'final_salary' => 9500000,
            'attended_days' => 20,
            'sick_days' => 1,
            'absent_days' => 1,
            'notes' => 'Correction tracking test',
        ]);

        return $payroll->load('payrollDetails.staffMember.user');
    }
}
