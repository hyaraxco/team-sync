<?php

namespace Tests\Feature\Payroll;

use App\Models\EmployeeProfile;
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

class PayrollAnalyticsTest extends TestCase
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

    public function test_finance_can_retrieve_latest_six_payroll_analytics_periods(): void
    {
        Carbon::setTestNow('2026-04-12 09:00:00');
        $this->actingAsRole('finance');

        $this->createPayrollWithDetail('2025-09-01', 'paid', 9000000, 1000000);
        $this->createPayrollWithDetail('2025-10-01', 'approved', 10000000, 1100000);
        $this->createPayrollWithDetail('2025-11-01', 'pending', 11000000, 1200000);
        $this->createPayrollWithDetail('2025-12-01', 'paid', 12000000, 1300000);
        $this->createPayrollWithDetail('2026-01-01', 'reopened', 13000000, 1400000);
        $this->createPayrollWithDetail('2026-02-01', 'approved', 15000000, 1500000);
        $this->createPayrollWithDetail('2026-03-01', 'paid', 16000000, 1700000);
        $this->createPayrollWithDetail('2026-04-01', 'approved', 17000000, 1900000);

        $response = $this->getJson('/api/v1/payrolls/analytics?months=6')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.periods_requested', 6)
            ->assertJsonPath('data.periods_returned', 6)
            ->assertJsonPath('data.status_scope.0', 'approved')
            ->assertJsonPath('data.status_scope.1', 'paid')
            ->assertJsonPath('data.reporting_period.start_month', '2025-09-01')
            ->assertJsonPath('data.reporting_period.end_month', '2026-04-01')
            ->assertJsonPath('data.summary.total_payroll_batches', 6);

        $trends = $response->json('data.trends') ?? [];

        $this->assertCount(6, $trends);
        $this->assertSame(
            ['2025-09-01', '2025-10-01', '2025-12-01', '2026-02-01', '2026-03-01', '2026-04-01'],
            array_map(fn (array $trend) => $trend['salary_month'] ?? null, $trends)
        );

        $this->assertSame(9000000.0, (float) ($trends[0]['total_amount'] ?? 0));
        $this->assertSame(17000000.0, (float) ($trends[5]['total_amount'] ?? 0));
    }

    public function test_analytics_returns_empty_trends_when_no_approved_or_paid_payrolls_exist(): void
    {
        $this->actingAsRole('finance');
        $this->createPayrollWithDetail('2026-04-01', 'pending', 8000000, 750000);

        $response = $this->getJson('/api/v1/payrolls/analytics')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.periods_requested', 6)
            ->assertJsonPath('data.periods_returned', 0)
            ->assertJsonPath('data.summary.total_payroll_batches', 0)
            ->assertJsonPath('data.summary.total_amount', 0)
            ->assertJsonPath('data.summary.total_deductions', 0);

        $this->assertSame([], $response->json('data.trends'));
        $this->assertNull($response->json('data.reporting_period.start_month'));
        $this->assertNull($response->json('data.reporting_period.end_month'));
    }

    private function actingAsRole(string $roleName): User
    {
        $user = User::factory()->create();
        $role = Role::findByName($roleName, 'sanctum');
        $user->assignRole($role);

        Sanctum::actingAs($user);

        return $user;
    }

    private function createPayrollWithDetail(
        string $salaryMonth,
        string $status,
        int $finalSalary,
        int $deductionAmount
    ): void {
        $user = User::factory()->create([
            'email' => 'analytics+'.uniqid().'@teamsync.com',
        ]);

        $employeeProfile = EmployeeProfile::withoutSyncingToSearch(function () use ($user) {
            return EmployeeProfile::factory()->for($user)->create();
        });

        $payroll = Payroll::create([
            'salary_month' => $salaryMonth,
            'status' => $status,
        ]);

        PayrollDetail::create([
            'payroll_id' => $payroll->id,
            'employee_id' => $employeeProfile->id,
            'original_salary' => $finalSalary + $deductionAmount,
            'final_salary' => $finalSalary,
            'attended_days' => 20,
            'sick_days' => 0,
            'absent_days' => 1,
            'deduction_amount' => $deductionAmount,
            'notes' => 'Analytics test fixture',
        ]);
    }
}
