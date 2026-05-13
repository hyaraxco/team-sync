<?php

namespace Tests\Feature\Payroll;

use App\Jobs\GeneratePayrollJob;
use App\Models\Attendance;
use App\Models\Payroll;
use App\Models\PayrollDetail;
use App\Models\StaffMemberProfile;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\ActivatesLicense;
use Tests\TestCase;

class PayrollAuthorizationTest extends TestCase
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
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_hr_has_readiness_view_only_no_payroll_operations(): void
    {
        Queue::fake();
        Carbon::setTestNow('2026-05-28 09:00:00');
        $user = $this->actingAsRole('hr');
        $payrollDetail = $this->createPayrollDetail();
        $this->createActiveEmployeeWithAttendance('2026-05');

        // HR can view readiness dashboard (payroll-readiness-view)
        $this->getJson('/api/v1/payrolls/readiness-dashboard?salary_month=2026-05')
            ->assertOk();

        // HR CANNOT generate payroll (Finance owns this)
        $this->postJson('/api/v1/payrolls/generate', [
            'salary_month' => '2026-05',
        ])->assertForbidden();
        $this->getJson('/api/v1/payrolls/generate-readiness?salary_month=2026-05')
            ->assertForbidden();

        Queue::assertNothingPushed();

        // HR CANNOT edit, approve, process, or view statistics
        $this->putJson("/api/v1/payroll-details/{$payrollDetail->id}", [
            'notes' => 'HR should not edit payroll figures',
        ])->assertForbidden();
        $this->postJson("/api/v1/payrolls/{$payrollDetail->payroll_id}/approve")
            ->assertForbidden();
        $this->postJson("/api/v1/payrolls/{$payrollDetail->payroll_id}/mark-as-paid", [
            'payment_date' => '2026-05-30',
        ])->assertForbidden();
        $this->postJson("/api/v1/payrolls/{$payrollDetail->payroll_id}/resend-notifications")
            ->assertForbidden();
        $this->postJson("/api/v1/payrolls/{$payrollDetail->payroll_id}/reopen", [
            'reason' => 'Need payroll correction before payment.',
        ])->assertForbidden();

        $this->getJson('/api/v1/payrolls/statistics')->assertForbidden();
        $this->getJson('/api/v1/payrolls/analytics')->assertForbidden();
        $this->getJson('/api/v1/payroll-settings')->assertForbidden();
        $this->getJson('/api/v1/payroll-settings/history')->assertForbidden();

        // HR also cannot list/export payrolls (no payroll-list)
        $this->getJson('/api/v1/payrolls/all/paginated')->assertForbidden();

        $this->assertTrue($user->hasPermissionTo('payroll-readiness-view', 'sanctum'));
        $this->assertFalse($user->hasPermissionTo('payroll-create', 'sanctum'));
        $this->assertFalse($user->hasPermissionTo('payroll-list', 'sanctum'));
        $this->assertFalse($user->hasPermissionTo('payroll-edit', 'sanctum'));
        $this->assertFalse($user->hasPermissionTo('payroll-process', 'sanctum'));
        $this->assertFalse($user->hasPermissionTo('payroll-statistics', 'sanctum'));
    }

    public function test_finance_can_generate_edit_process_and_view_statistics(): void
    {
        Queue::fake();
        Carbon::setTestNow('2026-05-28 09:00:00');
        $user = $this->actingAsRole('finance');
        $payrollDetail = $this->createPayrollDetail();
        $this->createActiveEmployeeWithAttendance('2026-05');

        $this->postJson('/api/v1/payrolls/generate', [
            'salary_month' => '2026-05',
        ])->assertOk();
        $this->getJson('/api/v1/payrolls/generate-readiness?salary_month=2026-05')
            ->assertOk();
        $this->getJson('/api/v1/payrolls/readiness-dashboard?salary_month=2026-05')
            ->assertOk();

        Queue::assertPushed(GeneratePayrollJob::class);

        $this->putJson("/api/v1/payroll-details/{$payrollDetail->id}", [
            'notes' => 'Reviewed by Finance',
            'final_salary' => 9800000,
        ])->assertOk();

        $this->postJson("/api/v1/payrolls/{$payrollDetail->payroll_id}/approve")
            ->assertOk();
        $this->postJson("/api/v1/payrolls/{$payrollDetail->payroll_id}/mark-as-paid", [
            'payment_date' => '2026-05-30',
        ])->assertOk();
        $this->postJson("/api/v1/payrolls/{$payrollDetail->payroll_id}/resend-notifications")
            ->assertOk();
        $this->postJson("/api/v1/payrolls/{$payrollDetail->payroll_id}/reopen", [
            'reason' => 'Need payroll correction for audit compliance.',
        ])->assertStatus(400);

        $this->getJson('/api/v1/payrolls/statistics')->assertOk();
        $this->getJson('/api/v1/payrolls/analytics')->assertOk();
        $this->getJson('/api/v1/payroll-settings')->assertOk();
        $this->getJson('/api/v1/payroll-settings/history')->assertOk();
        $this->get("/api/v1/payrolls/{$payrollDetail->payroll_id}/export-excel")->assertOk();
        $this->getJson('/api/v1/payrolls/export-report?status=all&period_type=yearly&year=2026')->assertOk();
        $this->getJson("/api/v1/payrolls/{$payrollDetail->payroll_id}/activity-logs")->assertOk();
        $this->getJson("/api/v1/payrolls/{$payrollDetail->payroll_id}/notification-deliveries")->assertOk();

        $this->assertTrue($user->hasPermissionTo('payroll-create', 'sanctum'));
        $this->assertTrue($user->hasPermissionTo('payroll-edit', 'sanctum'));
        $this->assertTrue($user->hasPermissionTo('payroll-process', 'sanctum'));
        $this->assertTrue($user->hasPermissionTo('payroll-statistics', 'sanctum'));
    }

    public function test_manager_has_no_payroll_access(): void
    {
        Queue::fake();
        $user = $this->actingAsRole('manager');
        $payrollDetail = $this->createPayrollDetail();

        $this->getJson('/api/v1/payrolls/all/paginated')->assertForbidden();
        $this->postJson('/api/v1/payrolls/generate', [
            'salary_month' => '2026-05',
        ])->assertForbidden();
        $this->getJson('/api/v1/payrolls/generate-readiness?salary_month=2026-05')
            ->assertForbidden();
        $this->getJson('/api/v1/payrolls/readiness-dashboard?salary_month=2026-05')
            ->assertForbidden();
        $this->getJson('/api/v1/payrolls/statistics')->assertForbidden();
        $this->getJson('/api/v1/payrolls/analytics')->assertForbidden();
        $this->getJson('/api/v1/payroll-settings')->assertForbidden();
        $this->getJson('/api/v1/payroll-settings/history')->assertForbidden();
        $this->get("/api/v1/payrolls/{$payrollDetail->payroll_id}/export-excel")->assertForbidden();
        $this->getJson('/api/v1/payrolls/export-report?status=all&period_type=yearly&year=2026')->assertForbidden();
        $this->getJson("/api/v1/payrolls/{$payrollDetail->payroll_id}/activity-logs")->assertForbidden();
        $this->getJson("/api/v1/payrolls/{$payrollDetail->payroll_id}/notification-deliveries")->assertForbidden();
        $this->postJson("/api/v1/payrolls/{$payrollDetail->payroll_id}/approve")
            ->assertForbidden();
        $this->postJson("/api/v1/payrolls/{$payrollDetail->payroll_id}/mark-as-paid", [
            'payment_date' => '2026-05-30',
        ])->assertForbidden();
        $this->postJson("/api/v1/payrolls/{$payrollDetail->payroll_id}/resend-notifications")
            ->assertForbidden();
        $this->postJson("/api/v1/payrolls/{$payrollDetail->payroll_id}/reopen", [
            'reason' => 'Need payroll correction before approval.',
        ])->assertForbidden();

        Queue::assertNothingPushed();

        $this->assertFalse($user->hasPermissionTo('payroll-menu', 'sanctum'));
        $this->assertFalse($user->hasPermissionTo('payroll-list', 'sanctum'));
        $this->assertFalse($user->hasPermissionTo('payroll-create', 'sanctum'));
        $this->assertFalse($user->hasPermissionTo('payroll-edit', 'sanctum'));
        $this->assertFalse($user->hasPermissionTo('payroll-process', 'sanctum'));
        $this->assertFalse($user->hasPermissionTo('payroll-statistics', 'sanctum'));
    }

    public function test_employee_has_no_admin_payroll_access(): void
    {
        Queue::fake();
        $user = $this->actingAsRole('staff');
        $payrollDetail = $this->createPayrollDetail();

        $this->getJson('/api/v1/payrolls/all/paginated')->assertForbidden();
        $this->postJson('/api/v1/payrolls/generate', [
            'salary_month' => '2026-05',
        ])->assertForbidden();
        $this->getJson('/api/v1/payrolls/generate-readiness?salary_month=2026-05')
            ->assertForbidden();
        $this->getJson('/api/v1/payrolls/readiness-dashboard?salary_month=2026-05')
            ->assertForbidden();
        $this->getJson('/api/v1/payrolls/statistics')->assertForbidden();
        $this->getJson('/api/v1/payrolls/analytics')->assertForbidden();
        $this->getJson('/api/v1/payroll-settings')->assertForbidden();
        $this->getJson('/api/v1/payroll-settings/history')->assertForbidden();
        $this->getJson("/api/v1/payrolls/{$payrollDetail->payroll_id}/details")->assertForbidden();
        $this->get("/api/v1/payrolls/{$payrollDetail->payroll_id}/export-excel")->assertForbidden();
        $this->getJson('/api/v1/payrolls/export-report?status=all&period_type=yearly&year=2026')->assertForbidden();
        $this->getJson("/api/v1/payrolls/{$payrollDetail->payroll_id}/activity-logs")->assertForbidden();
        $this->getJson("/api/v1/payrolls/{$payrollDetail->payroll_id}/notification-deliveries")->assertForbidden();
        $this->postJson("/api/v1/payrolls/{$payrollDetail->payroll_id}/approve")
            ->assertForbidden();
        $this->postJson("/api/v1/payrolls/{$payrollDetail->payroll_id}/mark-as-paid", [
            'payment_date' => '2026-05-30',
        ])->assertForbidden();
        $this->postJson("/api/v1/payrolls/{$payrollDetail->payroll_id}/resend-notifications")
            ->assertForbidden();
        $this->postJson("/api/v1/payrolls/{$payrollDetail->payroll_id}/reopen", [
            'reason' => 'Need payroll correction before approval.',
        ])->assertForbidden();

        Queue::assertNothingPushed();

        $this->assertFalse($user->hasPermissionTo('payroll-menu', 'sanctum'));
        $this->assertFalse($user->hasPermissionTo('payroll-list', 'sanctum'));
        $this->assertFalse($user->hasPermissionTo('payroll-create', 'sanctum'));
        $this->assertFalse($user->hasPermissionTo('payroll-edit', 'sanctum'));
        $this->assertFalse($user->hasPermissionTo('payroll-process', 'sanctum'));
        $this->assertFalse($user->hasPermissionTo('payroll-statistics', 'sanctum'));
    }

    private function actingAsRole(string $roleName): User
    {
        $user = User::factory()->create();
        $role = Role::findByName($roleName, 'sanctum');
        $user->assignRole($role);

        Sanctum::actingAs($user);

        return $user;
    }

    private function createPayrollDetail(): PayrollDetail
    {
        $staffMemberProfile = StaffMemberProfile::withoutSyncingToSearch(function () {
            return StaffMemberProfile::factory()->create();
        });

        $staffMemberProfile->bankInformation()->create([
            'staff_member_id' => $staffMemberProfile->id,
            'bank_name' => 'BCA',
            'account_number' => '1122334455',
            'account_holder_name' => 'Payroll Authorization User',
            'account_type' => 'saving',
        ]);

        $payroll = Payroll::create([
            'salary_month' => '2026-04-01',
            'status' => 'pending',
        ]);

        return PayrollDetail::create([
            'payroll_id' => $payroll->id,
            'staff_member_id' => $staffMemberProfile->id,
            'original_salary' => 10000000,
            'final_salary' => 9500000,
            'attended_days' => 20,
            'sick_days' => 1,
            'absent_days' => 1,
            'notes' => 'Initial draft',
        ]);
    }

    private function createActiveEmployeeWithAttendance(string $salaryMonth): StaffMemberProfile
    {
        return StaffMemberProfile::withoutSyncingToSearch(function () use ($salaryMonth) {
            $staffMemberProfile = StaffMemberProfile::factory()->create();
            $month = Carbon::createFromFormat('Y-m', $salaryMonth)->startOfMonth();
            $monthEnd = $month->copy()->endOfMonth();

            $staffMemberProfile->jobInformation()->create([
                'staff_member_id' => $staffMemberProfile->id,
                'job_title' => 'HR Specialist',
                'years_experience' => 4,
                'status' => 'active',
                'employment_type' => 'full_time',
                'work_location' => 'office',
                'start_date' => '2024-01-01',
                'monthly_salary' => 8500000,
                'skill_level' => 'advanced',
            ]);

            $cursor = $month->copy();
            while ($cursor->lte($monthEnd)) {
                if (! $cursor->isWeekend()) {
                    Attendance::create([
                        'staff_member_id' => $staffMemberProfile->id,
                        'date' => $cursor->toDateString(),
                        'check_in' => $cursor->format('Y-m-d').' 08:00:00',
                        'check_out' => $cursor->format('Y-m-d').' 17:00:00',
                        'status' => 'present',
                        'notes' => 'Authorization rule attendance seed',
                    ]);
                }

                $cursor->addDay();
            }

            return $staffMemberProfile;
        });
    }
}
