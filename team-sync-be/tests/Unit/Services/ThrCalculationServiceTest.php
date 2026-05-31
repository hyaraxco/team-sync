<?php

namespace Tests\Unit\Services;

use App\Models\JobInformation;
use App\Models\StaffMemberProfile;
use App\Models\ThrPayroll;
use App\Services\Payroll\TaxCalculationService;
use App\Services\Payroll\ThrCalculationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ThrCalculationServiceTest extends TestCase
{
    use RefreshDatabase;

    private ThrCalculationService $service;

    private TaxCalculationService $taxService;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'staff', 'guard_name' => 'sanctum']);

        $this->taxService = $this->createMock(TaxCalculationService::class);
        $this->service = new ThrCalculationService($this->taxService);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Eligibility Checks
    // ─────────────────────────────────────────────────────────────────────────

    public function test_eligible_employee_with_active_status_and_12_months_tenure(): void
    {
        $employee = StaffMemberProfile::factory()->create([
            'ptkp_status' => 'TK/0',
            'npwp' => '12.345.678.9-012.345',
        ]);

        JobInformation::factory()->active()->fullTime()->create([
            'staff_member_id' => $employee->id,
            'start_date' => Carbon::now()->subMonths(12)->toDateString(),
            'monthly_salary' => 10_000_000,
        ]);

        $this->mockTaxCalculation(10_000_000, 'TK/0', true);

        $result = $this->service->calculateForEmployee($employee, Carbon::now());

        $this->assertTrue($result['eligible']);
        $this->assertEquals(12, $result['tenure_months']);
        $this->assertEquals(1.0, $result['proration_factor']);
        $this->assertEquals(10_000_000, $result['gross_thr_amount']);
        $this->assertNull($result['ineligibility_reason']);
    }

    public function test_ineligible_when_job_information_missing(): void
    {
        $employee = StaffMemberProfile::factory()->create();

        $result = $this->service->calculateForEmployee($employee, Carbon::now());

        $this->assertFalse($result['eligible']);
        $this->assertEquals(0, $result['tenure_months']);
        $this->assertEquals('No job information or start date found', $result['ineligibility_reason']);
    }

    public function test_ineligible_when_status_not_active(): void
    {
        $employee = StaffMemberProfile::factory()->create();

        JobInformation::factory()->create([
            'staff_member_id' => $employee->id,
            'status' => 'inactive',
            'monthly_salary' => 10_000_000,
            'start_date' => Carbon::now()->subMonths(12)->toDateString(),
        ]);

        $result = $this->service->calculateForEmployee($employee, Carbon::now());

        $this->assertFalse($result['eligible']);
        $this->assertEquals('Employee is not active', $result['ineligibility_reason']);
    }

    public function test_ineligible_when_monthly_salary_zero(): void
    {
        $employee = StaffMemberProfile::factory()->create();

        JobInformation::factory()->active()->create([
            'staff_member_id' => $employee->id,
            'monthly_salary' => 0,
            'start_date' => Carbon::now()->subMonths(12)->toDateString(),
        ]);

        $result = $this->service->calculateForEmployee($employee, Carbon::now());

        $this->assertFalse($result['eligible']);
        $this->assertEquals('Monthly salary is zero or not set', $result['ineligibility_reason']);
    }

    public function test_ineligible_when_tenure_less_than_one_month(): void
    {
        $employee = StaffMemberProfile::factory()->create();

        JobInformation::factory()->active()->create([
            'staff_member_id' => $employee->id,
            'monthly_salary' => 10_000_000,
            'start_date' => Carbon::now()->subDays(15)->toDateString(),
        ]);

        $result = $this->service->calculateForEmployee($employee, Carbon::now());

        $this->assertFalse($result['eligible']);
        $this->assertEquals(0, $result['tenure_months']);
        $this->assertStringContainsString('Tenure less than', $result['ineligibility_reason']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Proration Factor
    // ─────────────────────────────────────────────────────────────────────────

    public function test_proration_factor_for_6_months_tenure(): void
    {
        $employee = StaffMemberProfile::factory()->create([
            'ptkp_status' => 'TK/0',
            'npwp' => '12.345.678.9-012.345',
        ]);

        JobInformation::factory()->active()->create([
            'staff_member_id' => $employee->id,
            'start_date' => Carbon::now()->subMonthsNoOverflow(6)->toDateString(),
            'monthly_salary' => 10_000_000,
        ]);

        $this->mockTaxCalculation(10_000_000, 'TK/0', true);

        $result = $this->service->calculateForEmployee($employee, Carbon::now());

        $this->assertTrue($result['eligible']);
        $this->assertEquals(6, $result['tenure_months']);
        $this->assertEquals(0.5, $result['proration_factor']);
        $this->assertEqualsWithDelta(5_000_000, $result['gross_thr_amount'], 0.01);
    }

    public function test_proration_factor_full_for_12_or_more_months(): void
    {
        $employee = StaffMemberProfile::factory()->create([
            'ptkp_status' => 'TK/0',
            'npwp' => '12.345.678.9-012.345',
        ]);

        JobInformation::factory()->active()->create([
            'staff_member_id' => $employee->id,
            'start_date' => Carbon::now()->subMonths(24)->toDateString(),
            'monthly_salary' => 10_000_000,
        ]);

        $this->mockTaxCalculation(10_000_000, 'TK/0', true);

        $result = $this->service->calculateForEmployee($employee, Carbon::now());

        $this->assertTrue($result['eligible']);
        $this->assertEquals(24, $result['tenure_months']);
        $this->assertEquals(1.0, $result['proration_factor']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PPh21 Calculation
    // ─────────────────────────────────────────────────────────────────────────

    public function test_pph21_is_subtracted_from_gross_thr(): void
    {
        $employee = StaffMemberProfile::factory()->create([
            'ptkp_status' => 'TK/0',
            'npwp' => '12.345.678.9-012.345',
        ]);

        JobInformation::factory()->active()->create([
            'staff_member_id' => $employee->id,
            'start_date' => Carbon::now()->subMonths(12)->toDateString(),
            'monthly_salary' => 10_000_000,
        ]);

        // The service calls calculateMonthlyPph21 twice:
        // 1) for regular salary only (lower tax)
        // 2) for salary + THR spread (higher tax)
        // The difference is the PPh21 on THR
        $this->taxService
            ->method('calculateMonthlyPph21')
            ->willReturnCallback(function (float $salary) {
                $annualPph21 = $salary <= 10_000_000
                    ? 3_000_000
                    : 4_500_000;

                return [
                    'pph21_monthly' => $annualPph21 / 12,
                    'has_npwp' => true,
                    'ptkp_status' => 'TK/0',
                    'meta' => [
                        'pph21_annual' => $annualPph21,
                    ],
                ];
            });

        $result = $this->service->calculateForEmployee($employee, Carbon::now());

        $this->assertTrue($result['eligible']);
        $this->assertEquals(10_000_000, $result['gross_thr_amount']);
        // 4_500_000 - 3_000_000 = 1_500_000 (PPh21 on THR)
        $this->assertEquals(1_500_000, $result['pph21_amount']);
        $this->assertEqualsWithDelta(
            $result['gross_thr_amount'] - $result['pph21_amount'],
            $result['net_thr_amount'],
            0.01
        );
    }

    public function test_no_npwp_increases_tax_amount(): void
    {
        $employeeWithNpwp = StaffMemberProfile::factory()->create([
            'ptkp_status' => 'TK/0',
            'npwp' => '12.345.678.9-012.345',
        ]);

        JobInformation::factory()->active()->create([
            'staff_member_id' => $employeeWithNpwp->id,
            'start_date' => Carbon::now()->subMonths(12)->toDateString(),
            'monthly_salary' => 10_000_000,
        ]);

        $employeeNoNpwp = StaffMemberProfile::factory()->create([
            'ptkp_status' => 'TK/0',
            'npwp' => null,
        ]);

        JobInformation::factory()->active()->create([
            'staff_member_id' => $employeeNoNpwp->id,
            'start_date' => Carbon::now()->subMonths(12)->toDateString(),
            'monthly_salary' => 10_000_000,
        ]);

        // Base annual tax: 3_000_000
        $this->taxService
            ->method('calculateMonthlyPph21')
            ->willReturnCallback(function (float $salary, ?string $ptkp, bool $hasNpwp) {
                $annualPph21 = $salary <= 10_000_000 ? 3_000_000 : 4_500_000;
                if (! $hasNpwp) {
                    $annualPph21 *= 1.20;
                }

                return [
                    'pph21_monthly' => $annualPph21 / 12,
                    'has_npwp' => $hasNpwp,
                    'ptkp_status' => $ptkp,
                    'meta' => [
                        'pph21_annual' => $annualPph21,
                    ],
                ];
            });

        $resultWithNpwp = $this->service->calculateForEmployee($employeeWithNpwp, Carbon::now());
        $resultNoNpwp = $this->service->calculateForEmployee($employeeNoNpwp, Carbon::now());

        $this->assertTrue($resultWithNpwp['eligible']);
        $this->assertTrue($resultNoNpwp['eligible']);
        // No NPWP should result in higher tax (20% surcharge)
        $this->assertGreaterThan($resultWithNpwp['pph21_amount'], $resultNoNpwp['pph21_amount']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Payment Deadline
    // ─────────────────────────────────────────────────────────────────────────

    public function test_payment_deadline_is_7_days_before_holiday(): void
    {
        $holiday = Carbon::parse('2026-06-15');

        $deadline = $this->service->calculatePaymentDeadline($holiday);

        $this->assertEquals('2026-06-08', $deadline->toDateString());
    }

    // ─────────────────────────────────────────────────────────────────────────
    // getEligibleEmployees — Religion Filter
    // ─────────────────────────────────────────────────────────────────────────

    public function test_get_eligible_employees_matches_exact_religion(): void
    {
        $employee = StaffMemberProfile::factory()->create(['religion' => 'islam']);

        JobInformation::factory()->active()->fullTime()->create([
            'staff_member_id' => $employee->id,
            'monthly_salary' => 10_000_000,
        ]);

        $result = $this->service->getEligibleEmployees(ThrPayroll::EVENT_IDUL_FITRI);

        $this->assertCount(1, $result);
        $this->assertEquals($employee->id, $result->first()->id);
    }

    public function test_get_eligible_employees_matches_case_insensitive_religion(): void
    {
        $employee = StaffMemberProfile::factory()->create(['religion' => 'Kristen']);

        JobInformation::factory()->active()->fullTime()->create([
            'staff_member_id' => $employee->id,
            'monthly_salary' => 10_000_000,
        ]);

        $result = $this->service->getEligibleEmployees(ThrPayroll::EVENT_NATAL);

        $this->assertCount(1, $result);
        $this->assertEquals($employee->id, $result->first()->id);
    }

    public function test_get_eligible_employees_matches_trimmed_religion(): void
    {
        $employee = StaffMemberProfile::factory()->create(['religion' => '  hindu  ']);

        JobInformation::factory()->active()->fullTime()->create([
            'staff_member_id' => $employee->id,
            'monthly_salary' => 10_000_000,
        ]);

        $result = $this->service->getEligibleEmployees(ThrPayroll::EVENT_NYEPI);

        $this->assertCount(1, $result);
        $this->assertEquals($employee->id, $result->first()->id);
    }

    public function test_get_eligible_employees_excludes_null_religion(): void
    {
        $employee = StaffMemberProfile::factory()->create(['religion' => null]);

        JobInformation::factory()->active()->fullTime()->create([
            'staff_member_id' => $employee->id,
            'monthly_salary' => 10_000_000,
        ]);

        $result = $this->service->getEligibleEmployees(ThrPayroll::EVENT_IDUL_FITRI);

        $this->assertCount(0, $result);
    }

    public function test_get_eligible_employees_excludes_unmapped_religion(): void
    {
        $employee = StaffMemberProfile::factory()->create(['religion' => 'protestan']);

        JobInformation::factory()->active()->fullTime()->create([
            'staff_member_id' => $employee->id,
            'monthly_salary' => 10_000_000,
        ]);

        $result = $this->service->getEligibleEmployees(ThrPayroll::EVENT_NATAL);

        $this->assertCount(0, $result);
    }

    public function test_get_eligible_employees_excludes_inactive_employees(): void
    {
        $employee = StaffMemberProfile::factory()->create(['religion' => 'islam']);

        JobInformation::factory()->create([
            'staff_member_id' => $employee->id,
            'status' => 'inactive',
            'monthly_salary' => 10_000_000,
        ]);

        $result = $this->service->getEligibleEmployees(ThrPayroll::EVENT_IDUL_FITRI);

        $this->assertCount(0, $result);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function mockTaxCalculation(float $salary, string $ptkpStatus, bool $hasNpwp): void
    {
        $this->taxService
            ->method('calculateMonthlyPph21')
            ->willReturn([
                'pph21_monthly' => 0,
                'has_npwp' => $hasNpwp,
                'ptkp_status' => $ptkpStatus,
                'meta' => [
                    'pph21_annual' => 0,
                ],
            ]);
    }
}
