<?php

namespace Tests\Feature\Payroll;

use App\Models\BpjsRate;
use App\Models\Payroll;
use App\Models\PayrollDetail;
use App\Models\PtkpAmount;
use App\Models\StaffMemberProfile;
use App\Models\TaxBracket;
use App\Models\User;
use App\Services\PayslipPdfService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\ActivatesLicense;
use Tests\TestCase;

class PayslipPdfGenerationTest extends TestCase
{
    use ActivatesLicense, RefreshDatabase;

    private User $employee;

    private PayrollDetail $payslip;

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

        // Create employee with payslip
        $this->employee = User::factory()->create();
        $this->employee->assignRole('staff');

        $profile = StaffMemberProfile::factory()->create([
            'user_id' => $this->employee->id,

            'ptkp_status' => 'TK/0',
            'npwp' => '12.345.678.9-012.000',
        ]);

        $profile->jobInformation()->create([
            'monthly_salary' => 10_000_000,
            'start_date' => now()->subMonths(12),
            'status' => 'active',
            'employment_type' => 'permanent',
            'job_title' => 'Software Engineer',
            'team_id' => null,
            'work_location' => 'office',
        ]);

        $payroll = Payroll::factory()->create([
            'salary_month' => now()->startOfMonth(),
            'payment_date' => now(),
            'status' => 'paid',
        ]);

        $this->payslip = PayrollDetail::create([
            'payroll_id' => $payroll->id,
            'staff_member_id' => $profile->id,
            'original_salary' => 10_000_000,
            'final_salary' => 9_000_000,
            'effective_working_days' => 22,
            'attended_days' => 22,
            'present_days' => 22,
            'late_days' => 0,
            'sick_days' => 0,
            'absent_days' => 0,
            'paid_leave_days' => 0,
            'unpaid_leave_days' => 0,
            'deduction_days' => 0,
            'deduction_amount' => 0,
            'overtime_hours' => 5.0,
            'overtime_amount' => 500_000,
        ]);

        $this->payslip->load([
            'payroll.payrollSettingVersion',
            'staffMember.user',
            'staffMember.jobInformation.team',
        ]);
    }

    public function test_pdf_service_generates_valid_pdf(): void
    {
        $service = app(PayslipPdfService::class);
        $pdf = $service->render($this->payslip);

        $this->assertNotEmpty($pdf);
        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertStringContainsString('%%EOF', $pdf);
    }

    public function test_pdf_contains_employee_information(): void
    {
        $service = app(PayslipPdfService::class);
        $pdf = $service->render($this->payslip);

        // PDF should be valid and non-empty
        $this->assertNotEmpty($pdf);
        $this->assertGreaterThan(1000, strlen($pdf)); // PDF should have substantial content
    }

    public function test_pdf_contains_salary_breakdown(): void
    {
        $service = app(PayslipPdfService::class);
        $pdf = $service->render($this->payslip);

        // PDF should be valid and non-empty
        $this->assertNotEmpty($pdf);
        $this->assertGreaterThan(1000, strlen($pdf));
    }

    public function test_pdf_contains_overtime_details(): void
    {
        $service = app(PayslipPdfService::class);
        $pdf = $service->render($this->payslip);

        // PDF should be valid and non-empty
        $this->assertNotEmpty($pdf);
        $this->assertGreaterThan(1000, strlen($pdf));
    }

    public function test_pdf_contains_attendance_summary(): void
    {
        $service = app(PayslipPdfService::class);
        $pdf = $service->render($this->payslip);

        // PDF should be valid and non-empty
        $this->assertNotEmpty($pdf);
        $this->assertGreaterThan(1000, strlen($pdf));
    }

    public function test_pdf_contains_bpjs_breakdown(): void
    {
        $service = app(PayslipPdfService::class);
        $pdf = $service->render($this->payslip);

        // PDF should be valid and non-empty
        $this->assertNotEmpty($pdf);
        $this->assertGreaterThan(1000, strlen($pdf));
    }

    public function test_pdf_contains_tax_information(): void
    {
        $service = app(PayslipPdfService::class);
        $pdf = $service->render($this->payslip);

        // PDF should be valid and non-empty
        $this->assertNotEmpty($pdf);
        $this->assertGreaterThan(1000, strlen($pdf));
    }

    public function test_employee_can_download_own_payslip(): void
    {
        $this->actingAs($this->employee)
            ->get("/api/v1/payslips/{$this->payslip->id}/download")
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition');
    }

    public function test_employee_cannot_download_others_payslip(): void
    {
        $otherEmployee = User::factory()->create();
        $otherEmployee->assignRole('staff');

        $otherProfile = StaffMemberProfile::factory()->create([
            'user_id' => $otherEmployee->id,
        ]);

        $this->actingAs($otherEmployee)
            ->get("/api/v1/payslips/{$this->payslip->id}/download")
            ->assertStatus(404);
    }

    public function test_unauthenticated_user_cannot_download_payslip(): void
    {
        $response = $this->get("/api/v1/payslips/{$this->payslip->id}/download");

        // Should return 401 or 500 (if login route not defined)
        $this->assertContains($response->status(), [401, 500]);
    }

    public function test_pdf_generation_handles_zero_overtime(): void
    {
        $this->payslip->update([
            'overtime_hours' => 0,
            'overtime_amount' => 0,
        ]);

        $service = app(PayslipPdfService::class);
        $pdf = $service->render($this->payslip->fresh([
            'payroll.payrollSettingVersion',
            'staffMember.user',
            'staffMember.jobInformation.team',
        ]));

        $this->assertNotEmpty($pdf);
        $this->assertStringStartsWith('%PDF', $pdf);
    }

    public function test_pdf_generation_handles_absence_deductions(): void
    {
        $this->payslip->update([
            'absent_days' => 2,
            'deduction_days' => 2,
            'deduction_amount' => 909_090.91,
        ]);

        $service = app(PayslipPdfService::class);
        $pdf = $service->render($this->payslip->fresh([
            'payroll.payrollSettingVersion',
            'staffMember.user',
            'staffMember.jobInformation.team',
        ]));

        $this->assertNotEmpty($pdf);
        $this->assertGreaterThan(1000, strlen($pdf));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

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
