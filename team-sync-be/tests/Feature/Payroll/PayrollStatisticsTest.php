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
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PayrollStatisticsTest extends TestCase
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

    public function test_statistics_endpoint_returns_expected_structure(): void
    {
        Carbon::setTestNow('2026-05-15 10:00:00');
        $this->actingAsRole('finance');

        $response = $this->getJson('/api/v1/payrolls/statistics')
            ->assertOk();

        $data = $response->json('data');

        // Verify all expected keys are present in the response
        $this->assertArrayHasKey('total_payroll', $data);
        $this->assertArrayHasKey('pending_review', $data);
        $this->assertArrayHasKey('finalized', $data);
        $this->assertArrayHasKey('total_amount', $data);
        $this->assertArrayHasKey('average_salary', $data);
        $this->assertArrayHasKey('deductions', $data);
        $this->assertArrayHasKey('paid_payrolls', $data);
        $this->assertArrayHasKey('pending_payrolls', $data);
        $this->assertArrayHasKey('salary_change', $data);
        $this->assertArrayHasKey('total_salary_current_month', $data);
        $this->assertArrayHasKey('total_salary_last_month', $data);
    }

    public function test_statistics_counts_paid_and_pending_payrolls_correctly(): void
    {
        Carbon::setTestNow('2026-05-15 10:00:00');
        $this->actingAsRole('finance');

        // Create paid payroll (any month — these aggregates are not month-dependent)
        $paidPayroll = Payroll::create([
            'salary_month' => '2026-03-01',
            'status' => 'paid',
            'payment_date' => '2026-03-28',
        ]);
        $this->seedPayrollDetails($paidPayroll, [
            ['original' => 10000000, 'final' => 9500000],
        ]);

        $pendingPayroll = Payroll::create([
            'salary_month' => '2026-04-01',
            'status' => 'pending',
        ]);
        $this->seedPayrollDetails($pendingPayroll, [
            ['original' => 8000000, 'final' => 7800000],
        ]);

        $repository = app(\App\Interfaces\PayrollRepositoryInterface::class);
        $data = $repository->getStatistics();

        // paid_payrolls counts all paid in the current year (2026)
        $this->assertSame(1, $data['paid_payrolls']);
        // pending_payrolls counts all pending globally
        $this->assertSame(1, $data['pending_payrolls']);
    }

    public function test_statistics_returns_zero_salary_change_when_no_last_month(): void
    {
        Carbon::setTestNow('2026-05-15 10:00:00');
        $this->actingAsRole('finance');

        $payroll = Payroll::create([
            'salary_month' => '2026-05-01',
            'status' => 'pending',
        ]);
        $this->seedPayrollDetails($payroll, [
            ['original' => 10000000, 'final' => 9500000],
        ]);

        $response = $this->getJson('/api/v1/payrolls/statistics')
            ->assertOk();

        $this->assertEquals(0, $response->json('data.salary_change'));
    }

    public function test_payroll_statistics_returns_per_payroll_aggregates(): void
    {
        $this->actingAsRole('finance');

        $payroll = Payroll::create([
            'salary_month' => '2026-05-01',
            'status' => 'approved',
        ]);

        $this->seedPayrollDetails($payroll, [
            ['original' => 15000000, 'final' => 14000000, 'attended' => 22, 'sick' => 0, 'absent' => 0],
            ['original' => 8000000, 'final' => 7500000, 'attended' => 18, 'sick' => 2, 'absent' => 2],
            ['original' => 12000000, 'final' => 11000000, 'attended' => 20, 'sick' => 1, 'absent' => 1],
        ]);

        $response = $this->getJson("/api/v1/payrolls/{$payroll->id}/statistics")
            ->assertOk();

        $data = $response->json('data');
        $this->assertSame($payroll->id, $data['payroll_id']);
        $this->assertSame(3, $data['total_employees']);
        $this->assertEquals(32500000, $data['total_amount']); // 14M + 7.5M + 11M
        $this->assertEquals(35000000, $data['total_original_salary']);
        $this->assertEquals(2500000, $data['total_deductions']); // 35M - 32.5M
        $this->assertEquals(14000000, $data['highest_salary']);
        $this->assertEquals(7500000, $data['lowest_salary']);
        $this->assertSame(60, $data['total_attended_days']); // 22 + 18 + 20
        $this->assertSame(3, $data['total_sick_days']);
        $this->assertSame(3, $data['total_absent_days']);
    }

    public function test_analytics_returns_trend_data_for_multiple_months(): void
    {
        Carbon::setTestNow('2026-06-15 10:00:00');
        Cache::flush();
        $this->actingAsRole('finance');

        // Seed 3 months of payrolls
        foreach (['2026-04-01', '2026-05-01', '2026-06-01'] as $month) {
            $payroll = Payroll::create([
                'salary_month' => $month,
                'status' => 'paid',
                'payment_date' => Carbon::parse($month)->endOfMonth()->toDateString(),
            ]);
            $this->seedPayrollDetails($payroll, [
                ['original' => 10000000, 'final' => 9500000],
            ]);
        }

        $response = $this->getJson('/api/v1/payrolls/analytics?months=6')
            ->assertOk();

        $data = $response->json('data');
        $this->assertSame(3, $data['periods_returned']);
        $this->assertCount(3, $data['trends']);

        // Verify trends are sorted chronologically
        $trendMonths = array_column($data['trends'], 'salary_month');
        $this->assertSame(['2026-04-01', '2026-05-01', '2026-06-01'], $trendMonths);

        // Each trend should have the right structure
        $firstTrend = $data['trends'][0];
        $this->assertArrayHasKey('label', $firstTrend);
        $this->assertArrayHasKey('employee_count', $firstTrend);
        $this->assertArrayHasKey('total_amount', $firstTrend);
        $this->assertArrayHasKey('deduction_rate', $firstTrend);
    }

    public function test_analytics_growth_metrics_calculated_correctly(): void
    {
        Carbon::setTestNow('2026-06-15 10:00:00');
        Cache::flush();
        $this->actingAsRole('finance');

        // April: 1 employee, 10M
        $payrollApril = Payroll::create([
            'salary_month' => '2026-04-01',
            'status' => 'paid',
            'payment_date' => '2026-04-30',
        ]);
        $this->seedPayrollDetails($payrollApril, [
            ['original' => 10000000, 'final' => 10000000],
        ]);

        // June: 2 employees, 24M total
        $payrollJune = Payroll::create([
            'salary_month' => '2026-06-01',
            'status' => 'paid',
            'payment_date' => '2026-06-30',
        ]);
        $this->seedPayrollDetails($payrollJune, [
            ['original' => 12000000, 'final' => 12000000],
            ['original' => 12000000, 'final' => 12000000],
        ]);

        $response = $this->getJson('/api/v1/payrolls/analytics?months=6')
            ->assertOk();

        $growth = $response->json('data.growth_metrics');
        // (24M - 10M) / 10M * 100 = 140%
        $this->assertEquals(140.0, $growth['salary_growth_percentage']);
        // 2 - 1 = 1
        $this->assertSame(1, $growth['headcount_change']);
    }

    public function test_analytics_returns_empty_when_no_payrolls(): void
    {
        Carbon::setTestNow('2026-05-15 10:00:00');
        Cache::flush();
        $this->actingAsRole('finance');

        $response = $this->getJson('/api/v1/payrolls/analytics?months=6')
            ->assertOk();

        $this->assertSame(0, $response->json('data.periods_returned'));
        $this->assertEmpty($response->json('data.trends'));
    }

    private function actingAsRole(string $roleName): User
    {
        $user = User::factory()->create();
        $role = Role::findByName($roleName, 'sanctum');
        $user->assignRole($role);

        Sanctum::actingAs($user);

        return $user;
    }

    private function seedPayrollDetails(Payroll $payroll, array $rows): void
    {
        foreach ($rows as $row) {
            $user = User::factory()->create([
                'email' => 'employee+'.uniqid().'@teamsync.com',
            ]);

            $employee = StaffMemberProfile::withoutSyncingToSearch(function () use ($user) {
                return StaffMemberProfile::factory()->for($user)->create();
            });

            PayrollDetail::create([
                'payroll_id' => $payroll->id,
                'staff_member_id' => $employee->id,
                'original_salary' => $row['original'],
                'final_salary' => $row['final'],
                'attended_days' => $row['attended'] ?? 20,
                'sick_days' => $row['sick'] ?? 0,
                'absent_days' => $row['absent'] ?? 0,
                'deduction_amount' => max(0, $row['original'] - $row['final']),
                'notes' => 'Statistics test seed',
            ]);
        }
    }
}
