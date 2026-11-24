<?php

namespace Tests\Unit\Services;

use App\Models\JobInformation;
use App\Models\StaffMemberProfile;
use App\Models\ThrPayroll;
use App\Services\Payroll\ThrCalculationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ThrCalculationServiceTest extends TestCase
{
    use RefreshDatabase;

    private ThrCalculationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'staff', 'guard_name' => 'sanctum']);

        $this->service = new ThrCalculationService;
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

        $result = $this->service->calculateForEmployee($employee, Carbon::now());

        $this->assertTrue($result['eligible']);
        $this->assertEquals(12, $result['tenure_months']);
        $this->assertEquals(1.0, $result['proration_factor']);
        $this->assertEquals(10_000_000, $result['gross_thr_amount']);
        $this->assertSame(0, $result['pph21_amount']);
        $this->assertEquals(10_000_000, $result['net_thr_amount']);
        $this->assertNull($result['tax_calculation_meta']);
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

        $result = $this->service->calculateForEmployee($employee, Carbon::now());

        $this->assertTrue($result['eligible']);
        $this->assertEquals(24, $result['tenure_months']);
        $this->assertEquals(1.0, $result['proration_factor']);
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

}
