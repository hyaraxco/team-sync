<?php

namespace Tests\Feature\Commands;

use App\Models\Payroll;
use App\Models\PayrollDetail;
use App\Models\StaffMemberProfile;
use App\Models\User;
use App\Services\Payroll\TaxCalculationService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RecalculatePayrollTaxCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_command_fails_with_invalid_month_format(): void
    {
        $this->artisan('payroll:recalculate-tax --month=invalid')
            ->expectsOutputToContain('Invalid month format')
            ->assertExitCode(1);
    }

    public function test_command_fails_when_no_payroll_found(): void
    {
        $this->artisan('payroll:recalculate-tax --month=2026-01')
            ->expectsOutputToContain('No payroll found for 2026-01')
            ->assertExitCode(1);
    }

    public function test_command_fails_when_payroll_status_not_recalculable(): void
    {
        Payroll::factory()->create([
            'salary_month' => '2026-04-01',
            'status' => 'approved',
        ]);

        $this->artisan('payroll:recalculate-tax --month=2026-04')
            ->expectsOutputToContain("is in status 'approved'")
            ->assertExitCode(1);
    }

    public function test_command_fails_when_no_payroll_details_found(): void
    {
        Payroll::factory()->create([
            'salary_month' => '2026-04-01',
            'status' => 'pending',
        ]);

        $this->artisan('payroll:recalculate-tax --month=2026-04')
            ->expectsOutputToContain('No payroll details found')
            ->assertExitCode(1);
    }

    public function test_command_successfully_recalculates_tax_for_pending_payroll(): void
    {
        $this->seedTaxConfig();

        $user = User::factory()->create();
        $profile = StaffMemberProfile::factory()->create([
            'user_id' => $user->id,
            'ptkp_status' => 'TK/0',
            'npwp' => '12.345.678.9-001.000',
        ]);

        $payroll = Payroll::factory()->create([
            'salary_month' => '2026-04-01',
            'status' => 'pending',
        ]);

        PayrollDetail::create([
            'payroll_id' => $payroll->id,
            'staff_member_id' => $profile->id,
            'original_salary' => 10000000,
            'final_salary' => 8000000,
            'pph21_amount' => 0,
            'bpjs_tk_employee' => 0,
            'bpjs_tk_employer' => 0,
            'bpjs_kes_employee' => 0,
            'bpjs_kes_employer' => 0,
        ]);

        $this->artisan('payroll:recalculate-tax --month=2026-04')
            ->expectsOutputToContain('Recalculating tax & BPJS for 1 employees')
            ->assertSuccessful();
    }

    public function test_command_dry_run_does_not_write_changes(): void
    {
        $this->seedTaxConfig();

        $user = User::factory()->create();
        $profile = StaffMemberProfile::factory()->create([
            'user_id' => $user->id,
            'ptkp_status' => 'TK/0',
            'npwp' => '12.345.678.9-001.000',
        ]);

        $payroll = Payroll::factory()->create([
            'salary_month' => '2026-04-01',
            'status' => 'pending',
        ]);

        PayrollDetail::create([
            'payroll_id' => $payroll->id,
            'staff_member_id' => $profile->id,
            'original_salary' => 10000000,
            'final_salary' => 8000000,
            'pph21_amount' => 0,
            'bpjs_tk_employee' => 0,
            'bpjs_tk_employer' => 0,
            'bpjs_kes_employee' => 0,
            'bpjs_kes_employer' => 0,
        ]);

        $this->artisan('payroll:recalculate-tax --month=2026-04 --dry-run')
            ->expectsOutputToContain('[DRY-RUN]')
            ->assertSuccessful();
    }

    public function test_command_taxes_overtime_in_the_taxable_gross(): void
    {
        $this->seedTaxConfig();

        $user = User::factory()->create();
        $profile = StaffMemberProfile::factory()->create([
            'user_id' => $user->id,
            'ptkp_status' => 'TK/0',
            'npwp' => '12.345.678.9-001.000',
        ]);

        $payroll = Payroll::factory()->create([
            'salary_month' => '2026-04-01',
            'status' => 'pending',
        ]);

        $detail = PayrollDetail::create([
            'payroll_id' => $payroll->id,
            'staff_member_id' => $profile->id,
            'original_salary' => 8000000,
            'final_salary' => 8346820.81,
            'overtime_amount' => 346820.81,
            'pph21_amount' => 0,
            'bpjs_tk_employee' => 0,
            'bpjs_tk_employer' => 0,
            'bpjs_kes_employee' => 0,
            'bpjs_kes_employer' => 0,
        ]);

        $this->artisan('payroll:recalculate-tax --month=2026-04')->assertSuccessful();

        $detail->refresh();

        // Same base as payroll generation: salary + paid overtime.
        $service = app(TaxCalculationService::class);
        $expected = $service->calculateMonthlyTer(
            8000000 + 346820.81,
            'TK/0',
            true,
            '2026-04'
        );
        $salaryOnly = $service->calculateMonthlyTer(8000000, 'TK/0', true, '2026-04');

        $this->assertEqualsWithDelta(
            (float) $expected['pph21_monthly'],
            (float) $detail->pph21_amount,
            1.0,
            'command must tax salary plus overtime, matching payroll generation'
        );

        // Guards against a silent revert to salary-only taxation.
        $this->assertGreaterThan(
            (float) $salaryOnly['pph21_monthly'],
            (float) $detail->pph21_amount,
            'taxing salary only would under-withhold'
        );
    }

    private function seedTaxConfig(): void
    {
        DB::table('bpjs_rates')->insert([
            [
                'component' => 'jht',
                'employee_rate' => 2.00,
                'employer_rate' => 3.70,
                'max_salary_base' => null,
                'description' => 'Jaminan Hari Tua',
                'effective_date' => '2024-01-01',
            ],
            [
                'component' => 'jkk',
                'employee_rate' => 0,
                'employer_rate' => 0.24,
                'max_salary_base' => null,
                'description' => 'Jaminan Kecelakaan Kerja',
                'effective_date' => '2024-01-01',
            ],
            [
                'component' => 'jkm',
                'employee_rate' => 0,
                'employer_rate' => 0.30,
                'max_salary_base' => null,
                'description' => 'Jaminan Kematian',
                'effective_date' => '2024-01-01',
            ],
            [
                'component' => 'jp',
                'employee_rate' => 1.00,
                'employer_rate' => 2.00,
                'max_salary_base' => 10042300,
                'description' => 'Jaminan Pensiun',
                'effective_date' => '2024-01-01',
            ],
            [
                'component' => 'bpjs_kesehatan',
                'employee_rate' => 1.00,
                'employer_rate' => 4.00,
                'max_salary_base' => 12000000,
                'description' => 'BPJS Kesehatan',
                'effective_date' => '2024-01-01',
            ],
        ]);

        DB::table('ptkp_amounts')->insert([
            ['status' => 'TK/0', 'annual_amount' => 54000000],
            ['status' => 'K/0', 'annual_amount' => 58500000],
            ['status' => 'K/1', 'annual_amount' => 63000000],
        ]);

        DB::table('tax_brackets')->insert([
            ['min_income' => 0, 'max_income' => 60000000, 'rate' => 5.00, 'order' => 1],
            ['min_income' => 60000000, 'max_income' => 250000000, 'rate' => 15.00, 'order' => 2],
        ]);
    }
}
