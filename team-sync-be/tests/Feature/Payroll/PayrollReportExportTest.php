<?php

namespace Tests\Feature\Payroll;

use App\Models\StaffMemberProfile;
use App\Models\Payroll;
use App\Models\PayrollDetail;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PayrollReportExportTest extends TestCase
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

    public function test_finance_can_export_monthly_pending_report(): void
    {
        $this->actingAsRole('finance');
        $this->createPayrollWithDetail('2026-04-01', 'pending');

        $this->get('/api/v1/payrolls/export-report?status=pending&period_type=monthly&month=2026-04')
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=Payroll_Report_2026-04_Pending.xlsx');
    }

    public function test_finance_can_export_monthly_paid_report(): void
    {
        $this->actingAsRole('finance');
        $this->createPayrollWithDetail('2026-05-01', 'paid', '2026-05-28');

        $this->get('/api/v1/payrolls/export-report?status=paid&period_type=monthly&month=2026-05')
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=Payroll_Report_2026-05_Paid.xlsx');
    }

    public function test_finance_can_export_monthly_paid_detail_report(): void
    {
        $this->actingAsRole('finance');
        $this->createPayrollWithDetail('2026-05-01', 'paid', '2026-05-28');

        $this->get('/api/v1/payrolls/export-report?report_type=detail&status=paid&period_type=monthly&month=2026-05')
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=Payroll_Report_2026-05_Paid_Detail.xlsx');
    }

    public function test_hr_can_export_yearly_report_with_all_statuses(): void
    {
        $this->actingAsRole('hr');
        $this->createPayrollWithDetail('2026-03-01', 'pending');
        $this->createPayrollWithDetail('2026-04-01', 'paid', '2026-04-28');

        $this->get('/api/v1/payrolls/export-report?status=all&period_type=yearly&year=2026')
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename=Payroll_Report_2026_All.xlsx');
    }

    public function test_export_report_requires_month_for_monthly_and_year_for_yearly(): void
    {
        $this->actingAsRole('finance');

        $this->getJson('/api/v1/payrolls/export-report?status=all&period_type=monthly')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['month']);

        $this->getJson('/api/v1/payrolls/export-report?status=all&period_type=yearly')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['year']);
    }

    public function test_report_rows_detail_type_returns_employee_level_rows(): void
    {
        $this->actingAsRole('finance');
        $this->createPayrollWithDetail('2026-05-01', 'paid', '2026-05-28');

        $repository = app(\App\Interfaces\PayrollRepositoryInterface::class);
        $rows = $repository->getPayrollReportRows([
            'report_type' => 'detail',
            'status' => 'paid',
            'period_type' => 'monthly',
            'month' => '2026-05',
        ]);

        $this->assertNotEmpty($rows);
        $firstRow = $rows->first();
        $this->assertArrayHasKey('employee_name', $firstRow);
        $this->assertArrayHasKey('employee_code', $firstRow);
        $this->assertArrayHasKey('original_salary', $firstRow);
        $this->assertArrayHasKey('final_salary', $firstRow);
        $this->assertArrayHasKey('attended_days', $firstRow);
    }

    public function test_report_rows_summary_type_returns_payroll_level_rows(): void
    {
        $this->actingAsRole('finance');
        $this->createPayrollWithDetail('2026-05-01', 'paid', '2026-05-28');

        $repository = app(\App\Interfaces\PayrollRepositoryInterface::class);
        $rows = $repository->getPayrollReportRows([
            'report_type' => 'summary',
            'status' => 'all',
            'period_type' => 'monthly',
            'month' => '2026-05',
        ]);

        $this->assertNotEmpty($rows);
        $firstRow = $rows->first();
        $this->assertArrayHasKey('payroll_id', $firstRow);
        $this->assertArrayHasKey('period', $firstRow);
        $this->assertArrayHasKey('total_employee', $firstRow);
        $this->assertArrayHasKey('total_amount', $firstRow);
    }

    public function test_report_rows_status_filter_excludes_non_matching(): void
    {
        $this->actingAsRole('finance');
        $this->createPayrollWithDetail('2026-05-01', 'pending');
        $this->createPayrollWithDetail('2026-06-01', 'paid', '2026-06-28');

        $repository = app(\App\Interfaces\PayrollRepositoryInterface::class);
        $paidRows = $repository->getPayrollReportRows([
            'report_type' => 'summary',
            'status' => 'paid',
            'period_type' => 'yearly',
            'year' => '2026',
        ]);

        // Should only include paid payroll, not pending
        $this->assertCount(1, $paidRows);
        $this->assertSame('Paid', $paidRows->first()['status']);
    }

    public function test_report_rows_yearly_aggregates_all_months(): void
    {
        $this->actingAsRole('finance');
        $this->createPayrollWithDetail('2026-03-01', 'paid', '2026-03-28');
        $this->createPayrollWithDetail('2026-04-01', 'paid', '2026-04-28');
        $this->createPayrollWithDetail('2026-05-01', 'paid', '2026-05-28');

        $repository = app(\App\Interfaces\PayrollRepositoryInterface::class);
        $rows = $repository->getPayrollReportRows([
            'report_type' => 'summary',
            'status' => 'all',
            'period_type' => 'yearly',
            'year' => '2026',
        ]);

        $this->assertCount(3, $rows);
    }

    private function actingAsRole(string $roleName): User
    {
        $user = User::factory()->create();
        $role = Role::findByName($roleName, 'sanctum');
        $user->assignRole($role);

        Sanctum::actingAs($user);

        return $user;
    }

    private function createPayrollWithDetail(string $salaryMonth, string $status, ?string $paymentDate = null): void
    {
        $staffMemberProfile = StaffMemberProfile::withoutSyncingToSearch(function () {
            return StaffMemberProfile::factory()->create();
        });

        $payroll = Payroll::create([
            'salary_month' => $salaryMonth,
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
            'notes' => 'Export report test',
        ]);
    }
}

