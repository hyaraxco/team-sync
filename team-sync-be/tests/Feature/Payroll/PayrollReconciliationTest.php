<?php

namespace Tests\Feature\Payroll;

use App\Models\Payroll;
use App\Models\PayrollDetail;
use App\Models\PayrollReconciliationResolution;
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
use Tests\Concerns\ActivatesLicense;
use Tests\TestCase;

class PayrollReconciliationTest extends TestCase
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

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_reconciliation_endpoint_flags_missing_bank_info_as_critical(): void
    {
        $this->actingAsRole('finance');
        [$payroll, $employee] = $this->createApprovedPayrollDetail(withBankInformation: false);

        $response = $this->getJson("/api/v1/payrolls/{$payroll->id}/reconciliation")
            ->assertOk()
            ->assertJsonPath('data.payroll_id', $payroll->id)
            ->assertJsonPath('data.summary.critical_count', 1)
            ->assertJsonPath('data.exceptions.0.severity', 'critical')
            ->assertJsonPath('data.exceptions.0.type', 'missing_bank_account');

        $this->assertContains(
            $employee->id,
            $response->json('data.summary.critical_staff_member_ids') ?? []
        );
    }

    public function test_mark_as_paid_is_blocked_when_critical_reconciliation_issue_exists(): void
    {
        $finance = $this->actingAsRole('finance');
        [$payroll, , $employeeUser] = $this->createApprovedPayrollDetail(withBankInformation: false);

        $this->postJson("/api/v1/payrolls/{$payroll->id}/mark-as-paid", [
            'payment_date' => '2026-05-30',
        ])
            ->assertStatus(400)
            ->assertJsonPath(
                'message',
                'Payroll cannot be marked as paid because 1 critical reconciliation issue(s) remain. Complete employee bank information and regenerate payroll before retrying.'
            );

        $this->assertSame('approved', $payroll->fresh()->status);

        $this->assertDatabaseHas('payroll_activity_logs', [
            'payroll_id' => $payroll->id,
            'event_type' => 'payment_blocked_reconciliation',
            'actor_id' => $finance->id,
        ]);

        Notification::assertNotSentTo($employeeUser, PayrollPaid::class);
    }

    public function test_warning_reconciliation_issues_do_not_block_mark_as_paid_without_critical_items(): void
    {
        $this->actingAsRole('finance');
        [$payroll, , $employeeUser] = $this->createApprovedPayrollDetail(
            withBankInformation: true,
            originalSalary: 10000000,
            finalSalary: 4000000,
            warningFlags: ['unresolved_policy_mismatch']
        );

        $response = $this->getJson("/api/v1/payrolls/{$payroll->id}/reconciliation")
            ->assertOk();

        $summary = $response->json('data.summary') ?? [];
        $exceptionTypes = array_values(array_unique(array_column($response->json('data.exceptions') ?? [], 'type')));

        $this->assertSame(0, $summary['critical_count'] ?? null);
        $this->assertGreaterThan(0, $summary['warning_count'] ?? 0);
        $this->assertContains('excessive_deduction', $exceptionTypes);

        $this->postJson("/api/v1/payrolls/{$payroll->id}/mark-as-paid", [
            'payment_date' => '2026-05-30',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'paid');

        Notification::assertSentTo($employeeUser, PayrollPaid::class);
    }

    public function test_reconciliation_endpoint_supports_server_side_filters_without_affecting_global_summary(): void
    {
        $this->actingAsRole('finance');
        [$payroll] = $this->createApprovedPayrollDetail(withBankInformation: false);

        $warningUser = User::factory()->create([
            'email' => 'employee+'.uniqid().'@teamsync.com',
        ]);

        $warningEmployee = StaffMemberProfile::withoutSyncingToSearch(function () use ($warningUser) {
            return StaffMemberProfile::factory()->for($warningUser)->create();
        });

        $warningEmployee->bankInformation()->create([
            'staff_member_id' => $warningEmployee->id,
            'bank_name' => 'BCA',
            'account_number' => '1010101010',
            'account_holder_name' => 'Warning Employee',
            'account_type' => 'saving',
        ]);

        PayrollDetail::create([
            'payroll_id' => $payroll->id,
            'staff_member_id' => $warningEmployee->id,
            'original_salary' => 10000000,
            'final_salary' => 4000000,
            'attended_days' => 20,
            'sick_days' => 0,
            'absent_days' => 2,
            'deduction_amount' => 6000000,
            'warning_flags' => ['unresolved_policy_mismatch'],
            'notes' => 'Server filter warning fixture',
        ]);

        $criticalResponse = $this->getJson(
            "/api/v1/payrolls/{$payroll->id}/reconciliation?severity=critical"
        )
            ->assertOk();

        $criticalPayload = $criticalResponse->json('data');
        $criticalExceptions = $criticalPayload['exceptions'] ?? [];

        $this->assertNotEmpty($criticalExceptions);
        $this->assertCount(1, $criticalExceptions);
        $this->assertSame('critical', $criticalExceptions[0]['severity']);
        $this->assertSame(1, $criticalPayload['summary']['critical_count'] ?? null);
        $this->assertGreaterThan(0, $criticalPayload['summary']['warning_count'] ?? 0);
        $this->assertGreaterThan(1, $criticalPayload['summary']['total_exception_count'] ?? 0);
        $this->assertSame(1, $criticalPayload['summary']['filtered_exception_count'] ?? null);

        $typeResponse = $this->getJson(
            "/api/v1/payrolls/{$payroll->id}/reconciliation?type=excessive_deduction"
        )
            ->assertOk();

        $typePayload = $typeResponse->json('data');
        $typeExceptions = $typePayload['exceptions'] ?? [];

        $this->assertNotEmpty($typeExceptions);
        $this->assertTrue(
            collect($typeExceptions)->every(fn (array $exception) => ($exception['type'] ?? null) === 'excessive_deduction')
        );
        $this->assertContains('missing_bank_account', $typePayload['available_types'] ?? []);
        $this->assertSame('excessive_deduction', $typePayload['applied_filters']['type'] ?? null);
    }

    public function test_zero_salary_exception_is_detected_when_final_salary_is_zero(): void
    {
        $this->actingAsRole('finance');
        [$payroll] = $this->createApprovedPayrollDetail(
            withBankInformation: true,
            originalSalary: 10000000,
            finalSalary: 0
        );

        $response = $this->getJson("/api/v1/payrolls/{$payroll->id}/reconciliation")
            ->assertOk();

        $exceptions = $response->json('data.exceptions') ?? [];
        $zeroSalaryExceptions = array_filter($exceptions, fn ($e) => $e['type'] === 'zero_salary');

        $this->assertNotEmpty($zeroSalaryExceptions);
        $this->assertSame('critical', array_values($zeroSalaryExceptions)[0]['severity']);
    }

    public function test_salary_decrease_anomaly_warning_is_detected_when_final_below_50_percent(): void
    {
        $this->actingAsRole('finance');
        [$payroll] = $this->createApprovedPayrollDetail(
            withBankInformation: true,
            originalSalary: 10000000,
            finalSalary: 4000000
        );

        $response = $this->getJson("/api/v1/payrolls/{$payroll->id}/reconciliation")
            ->assertOk();

        $exceptions = $response->json('data.exceptions') ?? [];
        $anomalyExceptions = array_filter($exceptions, fn ($e) => $e['type'] === 'salary_decrease_anomaly');

        $this->assertNotEmpty($anomalyExceptions);
        $this->assertSame('warning', array_values($anomalyExceptions)[0]['severity']);
    }

    public function test_resolving_an_exception_creates_a_resolution_record(): void
    {
        $finance = $this->actingAsRole('finance');
        [$payroll, $employee] = $this->createApprovedPayrollDetail(withBankInformation: false);

        $this->postJson("/api/v1/payrolls/{$payroll->id}/reconciliation/resolve", [
            'staff_member_id' => $employee->id,
            'exception_type' => 'missing_bank_account',
            'resolution_action' => 'acknowledged',
            'reason' => 'Bank information will be updated before next payroll cycle.',
        ])
            ->assertOk()
            ->assertJsonPath('data.resolution_action', 'acknowledged')
            ->assertJsonPath('data.staff_member_id', $employee->id)
            ->assertJsonPath('data.exception_type', 'missing_bank_account');

        $this->assertDatabaseHas('payroll_reconciliation_resolutions', [
            'payroll_id' => $payroll->id,
            'staff_member_id' => $employee->id,
            'exception_type' => 'missing_bank_account',
            'resolution_action' => 'acknowledged',
            'resolved_by' => $finance->id,
        ]);
    }

    public function test_resolved_critical_exceptions_do_not_block_mark_as_paid(): void
    {
        $finance = $this->actingAsRole('finance');
        [$payroll, $employee, $employeeUser] = $this->createApprovedPayrollDetail(withBankInformation: false);

        // Resolve the critical exception
        PayrollReconciliationResolution::create([
            'payroll_id' => $payroll->id,
            'staff_member_id' => $employee->id,
            'exception_type' => 'missing_bank_account',
            'resolution_action' => 'waived',
            'reason' => 'Employee will receive payment via cash transfer this month.',
            'resolved_by' => $finance->id,
        ]);

        // Now mark as paid should succeed
        $this->postJson("/api/v1/payrolls/{$payroll->id}/mark-as-paid", [
            'payment_date' => '2026-05-30',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'paid');

        Notification::assertSentTo($employeeUser, PayrollPaid::class);
    }

    public function test_unresolved_critical_exceptions_still_block_mark_as_paid(): void
    {
        $this->actingAsRole('finance');
        [$payroll] = $this->createApprovedPayrollDetail(withBankInformation: false);

        $this->postJson("/api/v1/payrolls/{$payroll->id}/mark-as-paid", [
            'payment_date' => '2026-05-30',
        ])
            ->assertStatus(400);

        $this->assertSame('approved', $payroll->fresh()->status);
    }

    public function test_resolution_history_endpoint_returns_correct_data(): void
    {
        $finance = $this->actingAsRole('finance');
        [$payroll, $employee] = $this->createApprovedPayrollDetail(withBankInformation: false);

        PayrollReconciliationResolution::create([
            'payroll_id' => $payroll->id,
            'staff_member_id' => $employee->id,
            'exception_type' => 'missing_bank_account',
            'resolution_action' => 'resolved',
            'reason' => 'Bank information has been updated in the system.',
            'resolved_by' => $finance->id,
        ]);

        $response = $this->getJson("/api/v1/payrolls/{$payroll->id}/reconciliation/resolutions")
            ->assertOk();

        $resolutions = $response->json('data');
        $this->assertCount(1, $resolutions);
        $this->assertSame('missing_bank_account', $resolutions[0]['exception_type']);
        $this->assertSame('resolved', $resolutions[0]['resolution_action']);
        $this->assertSame($finance->name, $resolutions[0]['resolved_by_name']);
    }

    public function test_reconciliation_payload_includes_resolution_info_for_resolved_exceptions(): void
    {
        $finance = $this->actingAsRole('finance');
        [$payroll, $employee] = $this->createApprovedPayrollDetail(withBankInformation: false);

        PayrollReconciliationResolution::create([
            'payroll_id' => $payroll->id,
            'staff_member_id' => $employee->id,
            'exception_type' => 'missing_bank_account',
            'resolution_action' => 'acknowledged',
            'reason' => 'Will be fixed before next cycle.',
            'resolved_by' => $finance->id,
        ]);

        $response = $this->getJson("/api/v1/payrolls/{$payroll->id}/reconciliation")
            ->assertOk();

        $exceptions = $response->json('data.exceptions') ?? [];
        $bankException = collect($exceptions)->firstWhere('type', 'missing_bank_account');

        $this->assertNotNull($bankException);
        $this->assertNotNull($bankException['resolution']);
        $this->assertSame('acknowledged', $bankException['resolution']['action']);
        $this->assertSame($finance->name, $bankException['resolution']['resolved_by_name']);
        $this->assertSame(0, $response->json('data.summary.unresolved_critical_count'));
    }

    private function actingAsRole(string $roleName): User
    {
        $user = User::factory()->create();
        $role = Role::findByName($roleName, 'sanctum');
        $user->assignRole($role);

        Sanctum::actingAs($user);

        return $user;
    }

    private function createApprovedPayrollDetail(
        bool $withBankInformation,
        int $originalSalary = 10000000,
        int $finalSalary = 9500000,
        array $warningFlags = []
    ): array {
        $user = User::factory()->create([
            'email' => 'employee+'.uniqid().'@teamsync.com',
        ]);

        $staffMemberProfile = StaffMemberProfile::withoutSyncingToSearch(function () use ($user) {
            return StaffMemberProfile::factory()->for($user)->create();
        });

        if ($withBankInformation) {
            $staffMemberProfile->bankInformation()->create([
                'staff_member_id' => $staffMemberProfile->id,
                'bank_name' => 'BCA',
                'account_number' => '9990011223',
                'account_holder_name' => 'Payroll Reconciliation User',
                'account_type' => 'saving',
            ]);
        }

        $payroll = Payroll::create([
            'salary_month' => '2026-05-01',
            'status' => 'approved',
        ]);

        PayrollDetail::create([
            'payroll_id' => $payroll->id,
            'staff_member_id' => $staffMemberProfile->id,
            'original_salary' => $originalSalary,
            'final_salary' => $finalSalary,
            'attended_days' => 20,
            'sick_days' => 0,
            'absent_days' => 2,
            'deduction_amount' => max(0, $originalSalary - $finalSalary),
            'warning_flags' => empty($warningFlags) ? null : $warningFlags,
            'notes' => 'Reconciliation test fixture',
        ]);

        return [$payroll, $staffMemberProfile, $user];
    }
}
