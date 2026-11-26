<?php

namespace Tests\Feature\Migrations;

use App\Models\Company;
use App\Models\Payroll;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollMonthUniqueScopeMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const PATH = 'database/migrations/2026_11_26_130000_scope_payroll_salary_month_unique_to_company.php';

    public function test_rollback_refuses_while_two_companies_share_a_salary_month(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        Payroll::create(['company_id' => $companyA->id, 'salary_month' => '2026-04-01', 'status' => 'pending']);
        Payroll::create(['company_id' => $companyB->id, 'salary_month' => '2026-04-01', 'status' => 'pending']);

        $this->expectException(\RuntimeException::class);

        $this->artisan('migrate:rollback', ['--path' => self::PATH, '--realpath' => false]);
    }

    public function test_single_company_install_backfills_legacy_payroll_rows(): void
    {
        $company = Company::factory()->create();
        $legacy = Payroll::create(['company_id' => null, 'salary_month' => '2026-04-01', 'status' => 'pending']);

        $this->artisan('migrate:rollback', ['--path' => self::PATH, '--realpath' => false])
            ->assertSuccessful();
        $this->artisan('migrate', ['--path' => self::PATH, '--realpath' => false])
            ->assertSuccessful();

        $this->assertSame($company->id, $legacy->fresh()->company_id);
    }

    public function test_rollback_then_migrate_roundtrip_keeps_the_composite_key(): void
    {
        $this->artisan('migrate:rollback', ['--path' => self::PATH, '--realpath' => false])
            ->assertSuccessful();
        $this->artisan('migrate', ['--path' => self::PATH, '--realpath' => false])
            ->assertSuccessful();

        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        Payroll::create(['company_id' => $companyA->id, 'salary_month' => '2026-04-01', 'status' => 'pending']);
        Payroll::create(['company_id' => $companyB->id, 'salary_month' => '2026-04-01', 'status' => 'pending']);

        $this->assertSame(2, Payroll::whereDate('salary_month', '2026-04-01')->count());

        $this->expectException(QueryException::class);
        Payroll::create(['company_id' => $companyA->id, 'salary_month' => '2026-04-01', 'status' => 'pending']);
    }
}
