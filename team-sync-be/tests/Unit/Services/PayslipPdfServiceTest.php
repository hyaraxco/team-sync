<?php

use App\Models\Attendance;
use App\Models\AttendancePeriod;
use App\Models\JobInformation;
use App\Models\Payroll;
use App\Models\PayrollAdjustment;
use App\Models\PayrollDetail;
use App\Models\StaffMemberProfile;
use App\Models\Team;
use App\Services\Payroll\TaxCalculationService;
use App\Services\PayslipPdfService;
use Barryvdh\DomPDF\PDF;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/*
 |--------------------------------------------------------------------------
 | PayslipPdfService Unit Tests
 |
 | Tests the PayslipPdfService which generates payslip PDF content.
 | The service has one public method (render) and three private helpers
 | (calculateBpjsBreakdown, formatRupiah, formatSignedRupiah) that are
 | exercised indirectly through render().
 |
 | Dependencies mocked:
 |  - TaxCalculationService (constructor-injected, PHPUnit mock)
 |  - PDF (barryvdh/laravel-dompdf, bound as mock in container)
 |--------------------------------------------------------------------------
 */

class PayslipPdfServiceTest extends TestCase
{
    use RefreshDatabase;

    private PayslipPdfService $service;

    private \PHPUnit\Framework\MockObject\MockObject|TaxCalculationService $taxService;

    /** @var array<string, mixed> Captured view data passed to PDF::loadView */
    private array $capturedViewData = [];

    /** @var MockInterface&PDF Mock PDF instance */
    private $mockPdf;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed roles required by StaffMemberProfile factory
        Role::create(['name' => 'staff', 'guard_name' => 'sanctum']);

        // Mock TaxCalculationService to avoid DB dependencies on tax/bpjs tables
        $this->taxService = $this->createMock(TaxCalculationService::class);
        $this->service = new PayslipPdfService($this->taxService);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Configure the TaxCalculationService mock with default return values.
     */
    private function stubTaxService(
        float $pph21Monthly = 500_000,
        float $employeeShareBpjs = 350_000,
        array $bpjsBreakdown = [],
    ): void {
        $bpjsBreakdown = $bpjsBreakdown ?: [
            'jht_employee' => 200_000,
            'jht_employer' => 370_000,
            'jkk_employer' => 24_000,
            'jkm_employer' => 30_000,
            'jp_employee' => 100_000,
            'jp_employer' => 200_000,
            'bpjs_kesehatan_employee' => 50_000,
            'bpjs_kesehatan_employer' => 200_000,
        ];

        $this->taxService
            ->method('calculateMonthlyTer')
            ->willReturn([
                'pph21_monthly' => $pph21Monthly,
                'has_npwp' => true,
                'ptkp_status' => 'TK/0',
                'ter_category' => 'A',
                'ter_rate' => 0.04,
                'meta' => ['gross_monthly' => 10_000_000, 'ter_category' => 'A', 'ter_rate' => 0.04, 'ter_rate_pct' => '4%'],
            ]);

        $this->taxService
            ->method('calculateBpjs')
            ->willReturn([
                'employee_share' => $employeeShareBpjs,
                'employer_share' => 700_000,
                'breakdown' => $bpjsBreakdown,
            ]);
    }

    /**
     * Bind a mock PDF instance in the container so the Pdf facade resolves it.
     * Captures view data passed to loadView().
     */
    private function mockPdfFacade(): void
    {
        $this->capturedViewData = [];
        $capturedViewData = &$this->capturedViewData;

        $this->mockPdf = Mockery::mock(PDF::class);
        $this->mockPdf->shouldReceive('setPaper')->once()->with('a4', 'portrait');
        $this->mockPdf->shouldReceive('output')->once()->andReturn('fake-pdf-content');
        $this->mockPdf->shouldReceive('loadView')->once()->andReturnUsing(
            function (string $view, array $data) use (&$capturedViewData) {
                $capturedViewData = $data;

                return $this->mockPdf;
            }
        );

        // Bind the mock into the container under the facade accessor key
        app()->instance('dompdf.wrapper', $this->mockPdf);
    }

    /**
     * Create a PayrollAdjustment with all required NOT NULL fields.
     */
    private function createAdjustment(
        int $staffMemberId,
        int $periodId,
        array $overrides = [],
    ): PayrollAdjustment {
        return PayrollAdjustment::create(array_merge([
            'staff_member_id' => $staffMemberId,
            'source_period_id' => $periodId,
            'target_period_id' => $periodId,
            'source_reference_type' => Attendance::class,
            'source_reference_id' => null,
            'adjustment_kind' => 'paid_leave_reversal',
            'days_delta' => 1,
            'amount_delta' => 100_000,
            'reason' => null,
            'status' => 'applied',
        ], $overrides));
    }

    /**
     * Create a fully-linked PayrollDetail with payroll, employee, and job info.
     */
    private function createPayrollDetail(array $overrides = []): PayrollDetail
    {
        $employee = StaffMemberProfile::factory()->create([
            'ptkp_status' => 'TK/0',
            'npwp' => '12.345.678.9-012.345',
        ]);

        $payroll = Payroll::factory()->create([
            'salary_month' => '2025-03-01',
            'payment_date' => '2025-03-05',
            'status' => 'approved',
        ]);

        $jobInfo = JobInformation::factory()->active()->fullTime()->create([
            'staff_member_id' => $employee->id,
        ]);

        return PayrollDetail::create(array_merge([
            'payroll_id' => $payroll->id,
            'staff_member_id' => $employee->id,
            'original_salary' => 10_000_000,
            'final_salary' => 8_000_000,
            'effective_working_days' => 22,
            'daily_rate' => 454_545.45,
            'attended_days' => 20,
            'present_days' => 18,
            'late_days' => 2,
            'half_day_count' => 0,
            'paid_leave_days' => 1,
            'unpaid_leave_days' => 0,
            'holiday_days' => 8,
            'sick_days' => 0,
            'absent_days' => 0,
            'deduction_days' => 2,
            'deduction_amount' => 500_000,
            'overtime_hours' => 10,
            'overtime_amount' => 750_000,
            'overtime_records_count' => 2,
            'policy_mismatch_days' => 0,
            'warning_flags' => null,
            'notes' => null,
        ], $overrides));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 1. render() Returns String Content
    // ─────────────────────────────────────────────────────────────────────────

    public function test_render_returns_string_content(): void
    {
        $this->stubTaxService();
        $this->mockPdfFacade();

        $detail = $this->createPayrollDetail();

        $result = $this->service->render($detail);

        $this->assertSame('fake-pdf-content', $result);
    }

    public function test_render_calls_pdf_load_view_with_correct_template(): void
    {
        $this->stubTaxService();

        $viewUsed = null;
        $mockPdf = Mockery::mock(PDF::class);
        $mockPdf->shouldReceive('setPaper')->once();
        $mockPdf->shouldReceive('output')->once()->andReturn('content');
        $mockPdf->shouldReceive('loadView')->once()->andReturnUsing(
            function (string $view) use (&$viewUsed, $mockPdf) {
                $viewUsed = $view;

                return $mockPdf;
            }
        );
        app()->instance('dompdf.wrapper', $mockPdf);

        $detail = $this->createPayrollDetail();
        $this->service->render($detail);

        $this->assertSame('exports.payslip-pdf', $viewUsed);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 2. Gross Salary Calculation
    // ─────────────────────────────────────────────────────────────────────────

    public function test_gross_salary_is_basic_plus_overtime(): void
    {
        $this->stubTaxService();
        $this->mockPdfFacade();

        $detail = $this->createPayrollDetail([
            'original_salary' => 10_000_000,
            'overtime_amount' => 750_000,
        ]);

        $this->service->render($detail);

        // Gross = basic(10M) + overtime(750K) + allowances(0) + bonus(0) = 10,750,000
        $this->assertSame('Rp 10.750.000', $this->capturedViewData['grossSalary']);
    }

    public function test_gross_salary_with_zero_overtime(): void
    {
        $this->stubTaxService();
        $this->mockPdfFacade();

        $detail = $this->createPayrollDetail([
            'original_salary' => 8_000_000,
            'overtime_amount' => 0,
        ]);

        $this->service->render($detail);

        $this->assertSame('Rp 8.000.000', $this->capturedViewData['grossSalary']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 3. BPJS Breakdown
    // ─────────────────────────────────────────────────────────────────────────

    public function test_bpjs_breakdown_is_correctly_mapped(): void
    {
        $bpjsBreakdown = [
            'jht_employee' => 200_000,
            'jht_employer' => 370_000,
            'jkk_employer' => 24_000,
            'jkm_employer' => 30_000,
            'jp_employee' => 100_000,
            'jp_employer' => 200_000,
            'bpjs_kesehatan_employee' => 50_000,
            'bpjs_kesehatan_employer' => 200_000,
        ];

        $this->stubTaxService(
            pph21Monthly: 500_000,
            employeeShareBpjs: 350_000,
            bpjsBreakdown: $bpjsBreakdown
        );
        $this->mockPdfFacade();

        $detail = $this->createPayrollDetail([
            'original_salary' => 10_000_000,
        ]);

        $this->service->render($detail);

        $this->assertSame('Rp 200.000', $this->capturedViewData['bpjsJht']);
        $this->assertSame(2, $this->capturedViewData['bpjsJhtRate']);
        $this->assertSame('Rp 100.000', $this->capturedViewData['bpjsJp']);
        $this->assertSame(1, $this->capturedViewData['bpjsJpRate']);
        $this->assertSame('Rp 50.000', $this->capturedViewData['bpjsKesehatan']);
        $this->assertSame(1, $this->capturedViewData['bpjsKesehatanRate']);
        $this->assertSame('Rp 350.000', $this->capturedViewData['totalBpjs']);
    }

    public function test_bpjs_breakdown_with_zero_values(): void
    {
        $bpjsBreakdown = [
            'jht_employee' => 0,
            'jht_employer' => 0,
            'jkk_employer' => 0,
            'jkm_employer' => 0,
            'jp_employee' => 0,
            'jp_employer' => 0,
            'bpjs_kesehatan_employee' => 0,
            'bpjs_kesehatan_employer' => 0,
        ];

        $this->stubTaxService(
            pph21Monthly: 0,
            employeeShareBpjs: 0,
            bpjsBreakdown: $bpjsBreakdown
        );
        $this->mockPdfFacade();

        $detail = $this->createPayrollDetail([
            'original_salary' => 0,
        ]);

        $this->service->render($detail);

        $this->assertSame('Rp 0', $this->capturedViewData['totalBpjs']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 4. Tax Amount
    // ─────────────────────────────────────────────────────────────────────────

    public function test_tax_amount_is_reflected_in_view_data(): void
    {
        $this->stubTaxService(pph21Monthly: 1_500_000);
        $this->mockPdfFacade();

        $detail = $this->createPayrollDetail();
        $this->service->render($detail);

        $this->assertSame('Rp 1.500.000', $this->capturedViewData['tax']);
    }

    public function test_zero_tax_amount(): void
    {
        $this->stubTaxService(pph21Monthly: 0);
        $this->mockPdfFacade();

        $detail = $this->createPayrollDetail();
        $this->service->render($detail);

        $this->assertSame('Rp 0', $this->capturedViewData['tax']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 5. Other Deductions (max guard)
    // ─────────────────────────────────────────────────────────────────────────

    public function test_other_deductions_floor_at_zero_when_exceeding_gross(): void
    {
        // When gross - netSalary - totalBpjs - tax - absenceDeduction < 0,
        // otherDeductions should be 0 (max guard)
        $this->stubTaxService(pph21Monthly: 5_000_000, employeeShareBpjs: 2_000_000);
        $this->mockPdfFacade();

        $detail = $this->createPayrollDetail([
            'original_salary' => 5_000_000,
            'overtime_amount' => 0,
            'final_salary' => 4_500_000,
            'deduction_amount' => 0,
            'deduction_days' => 0,
        ]);

        $this->service->render($detail);

        // Gross=5M, net=4.5M, bpjs=2M, tax=5M, absence=0
        // other = max(0, (5M - 4.5M) - 2M - 5M - 0) = max(0, -6.5M) = 0
        $this->assertSame('Rp 0', $this->capturedViewData['otherDeductions']);
    }

    public function test_other_deductions_positive_when_gross_exceeds_all_deductions(): void
    {
        // Construct a scenario where otherDeductions > 0:
        // gross - netSalary > totalBpjs + tax + absenceDeduction
        $this->stubTaxService(pph21Monthly: 500_000, employeeShareBpjs: 300_000);
        $this->mockPdfFacade();

        $detail = $this->createPayrollDetail([
            'original_salary' => 10_000_000,
            'overtime_amount' => 0,
            'final_salary' => 8_000_000,
            'deduction_amount' => 0,
            'deduction_days' => 0,
        ]);

        $this->service->render($detail);

        // Gross=10M, net=8M, bpjs=300K, tax=500K, absence=0
        // other = max(0, (10M - 8M) - 300K - 500K - 0) = max(0, 1.2M) = 1,200,000
        $this->assertSame('Rp 1.200.000', $this->capturedViewData['otherDeductions']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 6. Adjustments — loaded when relation is eager-loaded
    // ─────────────────────────────────────────────────────────────────────────

    public function test_adjustments_loaded_when_relation_is_eager_loaded(): void
    {
        $this->stubTaxService(pph21Monthly: 0, employeeShareBpjs: 0);
        $this->mockPdfFacade();

        $detail = $this->createPayrollDetail();
        $period = AttendancePeriod::factory()->create();

        // Create adjustments for the employee
        $this->createAdjustment($detail->staff_member_id, $period->id, [
            'adjustment_kind' => 'paid_leave_reversal',
            'days_delta' => 1,
            'amount_delta' => 450_000,
            'reason' => 'Paid leave reversal',
        ]);

        $this->createAdjustment($detail->staff_member_id, $period->id, [
            'adjustment_kind' => 'absence_correction_deduction',
            'days_delta' => -1,
            'amount_delta' => -200_000,
            'reason' => 'Absence correction',
        ]);

        // Eager load the relation
        $detail->load('appliedAdjustments');

        $this->service->render($detail);

        $this->assertCount(2, $this->capturedViewData['adjustments']);
        $this->assertEquals(250_000, $this->capturedViewData['adjustmentTotalAmount']);
        $this->assertSame('+Rp 250.000', $this->capturedViewData['adjustmentTotalFormatted']);
    }

    public function test_adjustment_items_contain_formatted_amount_and_reason(): void
    {
        $this->stubTaxService(pph21Monthly: 0, employeeShareBpjs: 0);
        $this->mockPdfFacade();

        $detail = $this->createPayrollDetail();
        $period = AttendancePeriod::factory()->create();

        $this->createAdjustment($detail->staff_member_id, $period->id, [
            'adjustment_kind' => 'paid_leave_credit',
            'days_delta' => 1,
            'amount_delta' => 300_000,
            'reason' => 'Extra leave credit',
        ]);

        $detail->load('appliedAdjustments');
        $this->service->render($detail);

        $adj = $this->capturedViewData['adjustments'][0];
        $this->assertSame('Extra leave credit', $adj['reason']);
        $this->assertEquals(300_000, $adj['amount_delta']);
        $this->assertSame('+Rp 300.000', $adj['formatted_amount']);
    }

    public function test_adjustment_fallback_reason_when_null(): void
    {
        $this->stubTaxService(pph21Monthly: 0, employeeShareBpjs: 0);
        $this->mockPdfFacade();

        $detail = $this->createPayrollDetail();
        $period = AttendancePeriod::factory()->create();

        $this->createAdjustment($detail->staff_member_id, $period->id, [
            'adjustment_kind' => 'paid_leave_reversal',
            'days_delta' => 1,
            'amount_delta' => 100_000,
            'reason' => null,
        ]);

        $detail->load('appliedAdjustments');
        $this->service->render($detail);

        $this->assertSame('Penyesuaian', $this->capturedViewData['adjustments'][0]['reason']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 7. Adjustments — empty when relation not loaded
    // ─────────────────────────────────────────────────────────────────────────

    public function test_adjustments_empty_when_relation_not_loaded(): void
    {
        $this->stubTaxService(pph21Monthly: 0, employeeShareBpjs: 0);
        $this->mockPdfFacade();

        $detail = $this->createPayrollDetail();
        $period = AttendancePeriod::factory()->create();

        // Create adjustments but do NOT eager-load the relation
        $this->createAdjustment($detail->staff_member_id, $period->id, [
            'adjustment_kind' => 'paid_leave_reversal',
            'days_delta' => 1,
            'amount_delta' => 500_000,
            'reason' => 'Should not appear',
        ]);

        // Do NOT call $detail->load('appliedAdjustments')

        $this->service->render($detail);

        $this->assertIsArray($this->capturedViewData['adjustments']);
        $this->assertCount(0, $this->capturedViewData['adjustments']);
        $this->assertSame(0, $this->capturedViewData['adjustmentTotalAmount']);
        $this->assertSame('Rp 0', $this->capturedViewData['adjustmentTotalFormatted']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 8. Null Employee/JobInformation Fields → 'N/A' Fallback
    // ─────────────────────────────────────────────────────────────────────────

    public function test_null_employee_falls_back_to_na(): void
    {
        $this->stubTaxService(pph21Monthly: 0, employeeShareBpjs: 0);
        $this->mockPdfFacade();

        $detail = $this->createPayrollDetail();

        // Force the staffMember relation to null to simulate missing employee
        $detail->setRelation('staffMember', null);

        $this->service->render($detail);

        $this->assertSame('N/A', $this->capturedViewData['employeeName']);
        $this->assertSame('N/A', $this->capturedViewData['employeeCode']);
        $this->assertSame('N/A', $this->capturedViewData['department']);
    }

    public function test_employee_without_job_information_falls_back_to_na_for_department(): void
    {
        $this->stubTaxService(pph21Monthly: 0, employeeShareBpjs: 0);
        $this->mockPdfFacade();

        $detail = $this->createPayrollDetail();

        // Force jobInformation relation to null (employee exists but no job info)
        $detail->staffMember->setRelation('jobInformation', null);

        $this->service->render($detail);

        $this->assertSame('N/A', $this->capturedViewData['department']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 9. Zero Effective Working Days
    // ─────────────────────────────────────────────────────────────────────────

    public function test_zero_effective_working_days_is_passed_to_view(): void
    {
        $this->stubTaxService(pph21Monthly: 0, employeeShareBpjs: 0);
        $this->mockPdfFacade();

        $detail = $this->createPayrollDetail([
            'effective_working_days' => 0,
            'daily_rate' => 0,
        ]);

        $this->service->render($detail);

        $this->assertSame(0, $this->capturedViewData['effectiveWorkingDays']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 10. formatRupiah — Correct IDR Format
    // ─────────────────────────────────────────────────────────────────────────

    public function test_format_rupiah_large_amount(): void
    {
        $this->stubTaxService(pph21Monthly: 0, employeeShareBpjs: 0);
        $this->mockPdfFacade();

        $detail = $this->createPayrollDetail([
            'original_salary' => 10_000_000,
            'overtime_amount' => 0,
        ]);

        $this->service->render($detail);

        $this->assertSame('Rp 10.000.000', $this->capturedViewData['basicSalary']);
    }

    public function test_format_rupiah_small_amount(): void
    {
        $this->stubTaxService(pph21Monthly: 0, employeeShareBpjs: 0);
        $this->mockPdfFacade();

        $detail = $this->createPayrollDetail([
            'original_salary' => 1_500_000,
            'overtime_amount' => 0,
        ]);

        $this->service->render($detail);

        $this->assertSame('Rp 1.500.000', $this->capturedViewData['basicSalary']);
    }

    public function test_format_rupiah_zero_amount(): void
    {
        $this->stubTaxService(pph21Monthly: 0, employeeShareBpjs: 0);
        $this->mockPdfFacade();

        $detail = $this->createPayrollDetail([
            'original_salary' => 0,
            'overtime_amount' => 0,
        ]);

        $this->service->render($detail);

        $this->assertSame('Rp 0', $this->capturedViewData['basicSalary']);
    }

    public function test_format_rupiah_exact_millions(): void
    {
        $this->stubTaxService(pph21Monthly: 0, employeeShareBpjs: 0);
        $this->mockPdfFacade();

        $detail = $this->createPayrollDetail([
            'original_salary' => 5_000_000,
            'overtime_amount' => 250_000,
        ]);

        $this->service->render($detail);

        // Gross = 5,000,000 + 250,000 = 5,250,000
        $this->assertSame('Rp 5.250.000', $this->capturedViewData['grossSalary']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // 11. formatSignedRupiah — Positive, Negative, Zero
    // ─────────────────────────────────────────────────────────────────────────

    public function test_format_signed_rupiah_positive_value(): void
    {
        $this->stubTaxService(pph21Monthly: 0, employeeShareBpjs: 0);
        $this->mockPdfFacade();

        $detail = $this->createPayrollDetail();
        $period = AttendancePeriod::factory()->create();

        $this->createAdjustment($detail->staff_member_id, $period->id, [
            'adjustment_kind' => 'paid_leave_credit',
            'days_delta' => 2,
            'amount_delta' => 500_000,
            'reason' => 'Bonus',
        ]);

        $detail->load('appliedAdjustments');
        $this->service->render($detail);

        $this->assertSame('+Rp 500.000', $this->capturedViewData['adjustments'][0]['formatted_amount']);
    }

    public function test_format_signed_rupiah_negative_value(): void
    {
        $this->stubTaxService(pph21Monthly: 0, employeeShareBpjs: 0);
        $this->mockPdfFacade();

        $detail = $this->createPayrollDetail();
        $period = AttendancePeriod::factory()->create();

        $this->createAdjustment($detail->staff_member_id, $period->id, [
            'adjustment_kind' => 'absence_correction_deduction',
            'days_delta' => -1,
            'amount_delta' => -300_000,
            'reason' => 'Absence penalty',
        ]);

        $detail->load('appliedAdjustments');
        $this->service->render($detail);

        $this->assertSame('-Rp 300.000', $this->capturedViewData['adjustments'][0]['formatted_amount']);
    }

    public function test_format_signed_rupiah_zero_value(): void
    {
        $this->stubTaxService(pph21Monthly: 0, employeeShareBpjs: 0);
        $this->mockPdfFacade();

        $detail = $this->createPayrollDetail();
        $period = AttendancePeriod::factory()->create();

        $this->createAdjustment($detail->staff_member_id, $period->id, [
            'adjustment_kind' => 'paid_leave_reversal',
            'days_delta' => 0,
            'amount_delta' => 0,
            'reason' => 'No change',
        ]);

        $detail->load('appliedAdjustments');
        $this->service->render($detail);

        // Zero amount should not have + or - prefix
        $this->assertSame('Rp 0', $this->capturedViewData['adjustments'][0]['formatted_amount']);
    }

    public function test_adjustment_total_formatted_with_mixed_values(): void
    {
        $this->stubTaxService(pph21Monthly: 0, employeeShareBpjs: 0);
        $this->mockPdfFacade();

        $detail = $this->createPayrollDetail();
        $period = AttendancePeriod::factory()->create();

        $this->createAdjustment($detail->staff_member_id, $period->id, [
            'adjustment_kind' => 'paid_leave_credit',
            'days_delta' => 1,
            'amount_delta' => 1_000_000,
            'reason' => 'Credit',
        ]);

        $this->createAdjustment($detail->staff_member_id, $period->id, [
            'adjustment_kind' => 'absence_correction_deduction',
            'days_delta' => -1,
            'amount_delta' => -400_000,
            'reason' => 'Deduction',
        ]);

        $detail->load('appliedAdjustments');
        $this->service->render($detail);

        // Total: 1,000,000 + (-400,000) = 600,000
        $this->assertEquals(600_000, $this->capturedViewData['adjustmentTotalAmount']);
        $this->assertSame('+Rp 600.000', $this->capturedViewData['adjustmentTotalFormatted']);
    }

    public function test_adjustment_total_formatted_negative(): void
    {
        $this->stubTaxService(pph21Monthly: 0, employeeShareBpjs: 0);
        $this->mockPdfFacade();

        $detail = $this->createPayrollDetail();
        $period = AttendancePeriod::factory()->create();

        $this->createAdjustment($detail->staff_member_id, $period->id, [
            'adjustment_kind' => 'absence_correction_deduction',
            'days_delta' => -3,
            'amount_delta' => -1_500_000,
            'reason' => 'Big deduction',
        ]);

        $detail->load('appliedAdjustments');
        $this->service->render($detail);

        $this->assertEquals(-1_500_000, $this->capturedViewData['adjustmentTotalAmount']);
        $this->assertSame('-Rp 1.500.000', $this->capturedViewData['adjustmentTotalFormatted']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Additional View Data Assertions
    // ─────────────────────────────────────────────────────────────────────────

    public function test_attendance_days_are_correctly_passed(): void
    {
        $this->stubTaxService(pph21Monthly: 0, employeeShareBpjs: 0);
        $this->mockPdfFacade();

        $detail = $this->createPayrollDetail([
            'attended_days' => 20,
            'present_days' => 18,
            'late_days' => 2,
            'sick_days' => 1,
            'paid_leave_days' => 1,
            'unpaid_leave_days' => 0,
            'absent_days' => 0,
        ]);

        $this->service->render($detail);

        $this->assertSame(20, $this->capturedViewData['attendedDays']);
        $this->assertSame(18, $this->capturedViewData['presentDays']);
        $this->assertSame(2, $this->capturedViewData['lateDays']);
        $this->assertSame(1, $this->capturedViewData['sickDays']);
        $this->assertSame(1, $this->capturedViewData['paidLeaveDays']);
        $this->assertSame(0, $this->capturedViewData['unpaidLeaveDays']);
        $this->assertSame(0, $this->capturedViewData['absentDays']);
    }

    public function test_overtime_hours_formatted_with_one_decimal(): void
    {
        $this->stubTaxService(pph21Monthly: 0, employeeShareBpjs: 0);
        $this->mockPdfFacade();

        $detail = $this->createPayrollDetail([
            'overtime_hours' => 10.5,
            'overtime_amount' => 787_500,
        ]);

        $this->service->render($detail);

        $this->assertSame('10,5', $this->capturedViewData['overtimeHours']);
        $this->assertSame('Rp 787.500', $this->capturedViewData['overtimeAmount']);
    }

    public function test_deduction_days_formatted_with_one_decimal(): void
    {
        $this->stubTaxService(pph21Monthly: 0, employeeShareBpjs: 0);
        $this->mockPdfFacade();

        $detail = $this->createPayrollDetail([
            'deduction_days' => 2.5,
            'deduction_amount' => 1_136_363.64,
        ]);

        $this->service->render($detail);

        $this->assertSame('2,5', $this->capturedViewData['deductionDays']);
    }

    public function test_company_name_from_config(): void
    {
        $this->stubTaxService(pph21Monthly: 0, employeeShareBpjs: 0);
        $this->mockPdfFacade();

        $detail = $this->createPayrollDetail();
        $this->service->render($detail);

        // Should use config('app.name') fallback
        $this->assertNotEmpty($this->capturedViewData['companyName']);
    }

    public function test_notes_from_payroll_detail_are_passed(): void
    {
        $this->stubTaxService(pph21Monthly: 0, employeeShareBpjs: 0);
        $this->mockPdfFacade();

        $detail = $this->createPayrollDetail([
            'notes' => 'Overtime approved by manager',
        ]);

        $this->service->render($detail);

        $this->assertSame('Overtime approved by manager', $this->capturedViewData['notes']);
    }

    public function test_net_salary_is_final_salary_formatted(): void
    {
        $this->stubTaxService(pph21Monthly: 0, employeeShareBpjs: 0);
        $this->mockPdfFacade();

        $detail = $this->createPayrollDetail([
            'final_salary' => 8_000_000,
        ]);

        $this->service->render($detail);

        $this->assertSame('Rp 8.000.000', $this->capturedViewData['netSalary']);
    }

    public function test_total_deductions_sum_includes_all_components(): void
    {
        $this->stubTaxService(pph21Monthly: 500_000, employeeShareBpjs: 350_000);
        $this->mockPdfFacade();

        $detail = $this->createPayrollDetail([
            'original_salary' => 10_000_000,
            'overtime_amount' => 0,
            'final_salary' => 8_000_000,
            'deduction_amount' => 200_000,
            'deduction_days' => 1,
        ]);

        $this->service->render($detail);

        // Gross=10M, net=8M, bpjs=350K, tax=500K, absence=200K
        // other = max(0, (10M - 8M) - 350K - 500K - 200K) = max(0, 950K) = 950,000
        // total = 350K + 500K + 200K + 950K = 2,000,000
        $this->assertSame('Rp 2.000.000', $this->capturedViewData['totalDeductions']);
    }

    public function test_employee_name_uses_user_name_over_full_name(): void
    {
        $this->stubTaxService(pph21Monthly: 0, employeeShareBpjs: 0);
        $this->mockPdfFacade();

        $detail = $this->createPayrollDetail();
        $detail->load('staffMember.user');

        $this->service->render($detail);

        // staffMember->user->name takes priority
        $this->assertSame(
            $detail->staffMember->user->name,
            $this->capturedViewData['employeeName']
        );
    }

    public function test_department_uses_team_name_over_job_title(): void
    {
        $this->stubTaxService(pph21Monthly: 0, employeeShareBpjs: 0);
        $this->mockPdfFacade();

        $employee = StaffMemberProfile::factory()->create();

        $team = Team::factory()->create([
            'name' => 'Engineering',
        ]);

        $jobInfo = JobInformation::factory()->create([
            'staff_member_id' => $employee->id,
            'job_title' => 'Software Engineer',
            'team_id' => $team->id,
        ]);

        $payroll = Payroll::factory()->create(['salary_month' => '2025-03-01']);

        $detail = PayrollDetail::create([
            'payroll_id' => $payroll->id,
            'staff_member_id' => $employee->id,
            'original_salary' => 10_000_000,
            'final_salary' => 8_000_000,
            'effective_working_days' => 22,
            'daily_rate' => 454_545.45,
            'attended_days' => 22,
            'present_days' => 22,
            'late_days' => 0,
            'half_day_count' => 0,
            'paid_leave_days' => 0,
            'unpaid_leave_days' => 0,
            'holiday_days' => 8,
            'sick_days' => 0,
            'absent_days' => 0,
            'deduction_days' => 0,
            'deduction_amount' => 0,
            'overtime_hours' => 0,
            'overtime_amount' => 0,
            'overtime_records_count' => 0,
            'policy_mismatch_days' => 0,
            'warning_flags' => null,
            'notes' => null,
        ]);

        $this->service->render($detail);

        // team.name takes priority when team relation is loaded
        $this->assertSame('Engineering', $this->capturedViewData['department']);
    }
}
