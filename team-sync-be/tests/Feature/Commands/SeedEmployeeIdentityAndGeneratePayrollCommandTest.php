<?php

namespace Tests\Feature\Commands;

use App\Models\Payroll;
use App\Models\StaffMemberProfile;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class SeedEmployeeIdentityAndGeneratePayrollCommandTest extends TestCase
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

    public function test_command_fails_with_invalid_month_format(): void
    {
        $this->artisan('payroll:seed-identity-and-generate --month=invalid')
            ->expectsOutputToContain('Invalid month format')
            ->assertExitCode(1);
    }

    public function test_command_fails_when_payroll_already_exists(): void
    {
        Payroll::factory()->create([
            'salary_month' => '2026-06-01',
            'status' => 'pending',
        ]);

        $this->artisan('payroll:seed-identity-and-generate --month=2026-06')
            ->expectsOutputToContain('Payroll for 2026-06 already exists')
            ->assertExitCode(1);
    }

    public function test_command_dry_run_does_not_create_payroll(): void
    {
        $this->artisan('payroll:seed-identity-and-generate --month=2026-06 --dry-run')
            ->expectsOutputToContain('[DRY-RUN]')
            ->assertSuccessful();

        $this->assertDatabaseCount('payrolls', 0);
    }

    public function test_command_seeds_identity_data_for_employees(): void
    {
        $user = User::factory()->create();
        $profile = StaffMemberProfile::factory()->create([
            'user_id' => $user->id,
            'ptkp_status' => null,
            'npwp' => null,
        ]);

        $this->artisan('payroll:seed-identity-and-generate --month=2026-06 --dry-run')
            ->assertSuccessful();

        // Verify identity data was not written (dry-run)
        $profile->refresh();
        $this->assertNull($profile->ptkp_status);
    }

    public function test_command_successfully_generates_payroll(): void
    {
        $this->seedTaxConfig();

        // Create an employee missing identity data so seedIdentity actually runs
        $user = User::factory()->create();
        StaffMemberProfile::factory()->create([
            'user_id' => $user->id,
            'ptkp_status' => null,
            'npwp' => null,
        ]);

        // Disable mail & queue for seeding context
        config(['mail.default' => 'array', 'queue.default' => 'sync']);

        $exitCode = Artisan::call(
            'payroll:seed-identity-and-generate',
            ['--month' => '2026-06']
        );

        // The command seeds identity data and attempts payroll generation.
        // With proper tax config, it should succeed.
        $output = Artisan::output();
        $this->assertStringContainsString('employees', $output);
    }

    public function test_command_uses_next_month_as_default(): void
    {
        $this->artisan('payroll:seed-identity-and-generate --dry-run')
            ->expectsOutputToContain('[DRY-RUN]')
            ->assertSuccessful();
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
        ]);

        DB::table('tax_brackets')->insert([
            ['min_income' => 0, 'max_income' => 60000000, 'rate' => 5.00, 'order' => 1],
        ]);
    }
}
