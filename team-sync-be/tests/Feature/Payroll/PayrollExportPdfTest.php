<?php

namespace Tests\Feature\Payroll;

use App\Models\BpjsRate;
use App\Models\Payroll;
use App\Models\PayrollDetail;
use App\Models\PtkpAmount;
use App\Models\StaffMemberProfile;
use App\Models\TaxBracket;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\ActivatesLicense;
use Tests\TestCase;

class PayrollExportPdfTest extends TestCase
{
    use ActivatesLicense, RefreshDatabase;

    private Payroll $payroll;

    protected function setUp(): void
    {
        parent::setUp();

        $this->activateTestLicense();
        $this->seedTaxInfrastructure();

        $this->seed([
            RoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->payroll = $this->createPayrollWithDetail();
    }

    public function test_finance_can_export_payroll_pdf_zip(): void
    {
        $this->actingAsRole('finance');

        $response = $this->get("/api/v1/payrolls/{$this->payroll->id}/export-pdf");

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/zip');
        $response->assertHeader('Content-Disposition');

        $zipPath = tempnam(sys_get_temp_dir(), 'payroll-payslips-zip-');
        file_put_contents($zipPath, $response->streamedContent());

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($zipPath));
        $this->assertSame(1, $zip->numFiles);
        $this->assertStringEndsWith('.pdf', (string) $zip->getNameIndex(0));
        $this->assertStringStartsWith('%PDF', (string) $zip->getFromIndex(0));
        $zip->close();
        unlink($zipPath);
    }

    public function test_export_pdf_returns_404_for_nonexistent_payroll(): void
    {
        $this->actingAsRole('finance');

        $response = $this->getJson('/api/v1/payrolls/99999/export-pdf');

        $response->assertStatus(404);
        $response->assertJson(['success' => false]);
    }

    public function test_export_pdf_returns_404_for_payroll_without_details(): void
    {
        $this->actingAsRole('finance');

        $emptyPayroll = Payroll::factory()->create([
            'salary_month' => '2026-03-01',
            'status' => 'paid',
            'payment_date' => '2026-03-28',
        ]);

        $response = $this->getJson("/api/v1/payrolls/{$emptyPayroll->id}/export-pdf");

        $response->assertStatus(404);
        $response->assertJson(['success' => false]);
    }

    public function test_staff_cannot_export_payroll_pdf(): void
    {
        $user = User::factory()->create();
        $user->assignRole('staff');
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/payrolls/{$this->payroll->id}/export-pdf");

        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_cannot_export_payroll_pdf(): void
    {
        $response = $this->getJson("/api/v1/payrolls/{$this->payroll->id}/export-pdf");

        $this->assertContains($response->status(), [401, 500]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function actingAsRole(string $roleName): User
    {
        $user = User::factory()->create();
        $role = Role::findByName($roleName, 'sanctum');
        $user->assignRole($role);

        Sanctum::actingAs($user);

        return $user;
    }

    private function createPayrollWithDetail(): Payroll
    {
        $staffMemberProfile = StaffMemberProfile::withoutSyncingToSearch(function () {
            $profile = StaffMemberProfile::factory()->create();
            $profile->jobInformation()->create([
                'monthly_salary' => 10_000_000,
                'start_date' => now()->subMonths(12),
                'status' => 'active',
                'employment_type' => 'permanent',
                'job_title' => 'Software Engineer',
                'team_id' => null,
                'work_location' => 'office',
            ]);

            return $profile;
        });

        $payroll = Payroll::factory()->create([
            'salary_month' => '2026-04-01',
            'status' => 'paid',
            'payment_date' => '2026-04-28',
        ]);

        PayrollDetail::create([
            'payroll_id' => $payroll->id,
            'staff_member_id' => $staffMemberProfile->id,
            'original_salary' => 10_000_000,
            'final_salary' => 9_500_000,
            'effective_working_days' => 22,
            'attended_days' => 20,
            'present_days' => 20,
            'late_days' => 0,
            'sick_days' => 1,
            'absent_days' => 1,
            'paid_leave_days' => 0,
            'unpaid_leave_days' => 0,
            'deduction_days' => 1,
            'deduction_amount' => 500_000,
            'overtime_hours' => 0,
            'overtime_amount' => 0,
        ]);

        return $payroll;
    }

    private function seedTaxInfrastructure(): void
    {
        TaxBracket::create(['order' => 1, 'min_income' => 0, 'max_income' => 60_000_000, 'rate' => 5]);
        TaxBracket::create(['order' => 2, 'min_income' => 60_000_000, 'max_income' => 250_000_000, 'rate' => 15]);
        TaxBracket::create(['order' => 3, 'min_income' => 250_000_000, 'max_income' => 500_000_000, 'rate' => 25]);
        TaxBracket::create(['order' => 4, 'min_income' => 500_000_000, 'max_income' => 5_000_000_000, 'rate' => 30]);
        TaxBracket::create(['order' => 5, 'min_income' => 5_000_000_000, 'max_income' => null, 'rate' => 35]);

        PtkpAmount::create(['status' => 'TK/0', 'annual_amount' => 54_000_000]);
        PtkpAmount::create(['status' => 'K/0', 'annual_amount' => 58_500_000]);

        BpjsRate::create(['component' => 'jht', 'employee_rate' => 2, 'employer_rate' => 3.7, 'max_salary_base' => null]);
        BpjsRate::create(['component' => 'jp', 'employee_rate' => 1, 'employer_rate' => 2, 'max_salary_base' => 9_559_600]);
        BpjsRate::create(['component' => 'jkk', 'employee_rate' => 0, 'employer_rate' => 0.24, 'max_salary_base' => null]);
        BpjsRate::create(['component' => 'jkm', 'employee_rate' => 0, 'employer_rate' => 0.3, 'max_salary_base' => null]);
        BpjsRate::create(['component' => 'bpjs_kesehatan', 'employee_rate' => 1, 'employer_rate' => 4, 'max_salary_base' => 12_000_000]);
    }
}
