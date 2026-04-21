<?php

namespace Tests\Feature\Payroll;

use App\Models\StaffMemberProfile;
use App\Models\Payroll;
use App\Models\PayrollDetail;
use App\Models\User;
use App\Notifications\PayrollPaid;
use Carbon\Carbon;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PayrollReconciliationTest extends TestCase
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
            $response->json('data.summary.critical_employee_ids') ?? []
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
            'employee_id' => $warningEmployee->id,
            'bank_name' => 'BCA',
            'account_number' => '1010101010',
            'account_holder_name' => 'Warning Employee',
            'account_type' => 'saving',
        ]);

        PayrollDetail::create([
            'payroll_id' => $payroll->id,
            'employee_id' => $warningEmployee->id,
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
                'employee_id' => $staffMemberProfile->id,
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
            'employee_id' => $staffMemberProfile->id,
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
