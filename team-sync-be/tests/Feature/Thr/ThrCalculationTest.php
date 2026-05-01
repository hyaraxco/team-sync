<?php

namespace Tests\Feature\Thr;

use App\Models\BpjsRate;
use App\Models\PtkpAmount;
use App\Models\StaffMemberProfile;
use App\Models\TaxBracket;
use App\Models\ThrPayroll;
use App\Models\User;
use App\Services\Payroll\ThrCalculationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThrCalculationTest extends TestCase
{
    use RefreshDatabase;

    private ThrCalculationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedTaxInfrastructure();
        $this->service = app(ThrCalculationService::class);
    }

    public function test_full_thr_for_employee_with_12_months_tenure(): void
    {
        $employee = $this->createEmployee(salary: 10_000_000, startDate: now()->subMonths(24));
        $paymentDate = now();

        $result = $this->service->calculateForEmployee($employee, $paymentDate);

        $this->assertTrue($result['eligible']);
        $this->assertEquals(24, $result['tenure_months']);
        $this->assertEquals(1.0, $result['proration_factor']);
        $this->assertEquals(10_000_000, $result['gross_thr_amount']);
        $this->assertGreaterThanOrEqual(0, $result['pph21_amount']);
        $this->assertEquals(
            round(10_000_000 - $result['pph21_amount'], 2),
            $result['net_thr_amount']
        );
    }

    public function test_prorated_thr_for_employee_with_6_months_tenure(): void
    {
        $employee = $this->createEmployee(salary: 12_000_000, startDate: now()->subMonths(6));
        $paymentDate = now();

        $result = $this->service->calculateForEmployee($employee, $paymentDate);

        $this->assertTrue($result['eligible']);
        $this->assertEquals(6, $result['tenure_months']);
        $this->assertEquals(0.5, $result['proration_factor']);
        $this->assertEquals(6_000_000, $result['gross_thr_amount']);
    }

    public function test_prorated_thr_for_employee_with_3_months_tenure(): void
    {
        $employee = $this->createEmployee(salary: 8_000_000, startDate: now()->subMonths(3));
        $paymentDate = now();

        $result = $this->service->calculateForEmployee($employee, $paymentDate);

        $this->assertTrue($result['eligible']);
        $this->assertEquals(3, $result['tenure_months']);
        $this->assertEquals(0.25, $result['proration_factor']);
        $this->assertEquals(2_000_000, $result['gross_thr_amount']);
    }

    public function test_ineligible_if_tenure_less_than_1_month(): void
    {
        $employee = $this->createEmployee(salary: 10_000_000, startDate: now()->subDays(15));
        $paymentDate = now();

        $result = $this->service->calculateForEmployee($employee, $paymentDate);

        $this->assertFalse($result['eligible']);
        $this->assertNotNull($result['ineligibility_reason']);
    }

    public function test_ineligible_if_employee_not_active(): void
    {
        $employee = $this->createEmployee(salary: 10_000_000, startDate: now()->subMonths(12), status: 'resigned');
        $paymentDate = now();

        $result = $this->service->calculateForEmployee($employee, $paymentDate);

        $this->assertFalse($result['eligible']);
        $this->assertStringContainsString('not active', $result['ineligibility_reason']);
    }

    public function test_ineligible_if_salary_is_zero(): void
    {
        $employee = $this->createEmployee(salary: 0, startDate: now()->subMonths(12));
        $paymentDate = now();

        $result = $this->service->calculateForEmployee($employee, $paymentDate);

        $this->assertFalse($result['eligible']);
        $this->assertStringContainsString('salary', $result['ineligibility_reason']);
    }

    public function test_payment_deadline_is_7_days_before_holiday(): void
    {
        $holiday = Carbon::parse('2026-04-01');
        $deadline = $this->service->calculatePaymentDeadline($holiday);

        $this->assertEquals('2026-03-25', $deadline->format('Y-m-d'));
    }

    public function test_thr_tax_uses_annualization_difference_method(): void
    {
        $result = $this->service->calculateThrTax(
            monthlySalary: 10_000_000,
            thrAmount: 10_000_000,
            ptkpStatus: 'TK/0',
            hasNpwp: true
        );

        $this->assertArrayHasKey('pph21_on_thr', $result);
        $this->assertArrayHasKey('meta', $result);
        $this->assertEquals('annualization_difference', $result['meta']['method']);
        $this->assertGreaterThanOrEqual(0, $result['pph21_on_thr']);
    }

    public function test_thr_tax_higher_without_npwp(): void
    {
        $withNpwp = $this->service->calculateThrTax(10_000_000, 10_000_000, 'TK/0', true);
        $withoutNpwp = $this->service->calculateThrTax(10_000_000, 10_000_000, 'TK/0', false);

        $this->assertGreaterThanOrEqual($withNpwp['pph21_on_thr'], $withoutNpwp['pph21_on_thr']);
    }

    public function test_get_eligible_employees_filters_by_religion(): void
    {
        $this->createEmployee(salary: 10_000_000, startDate: now()->subMonths(12), religion: 'islam');
        $this->createEmployee(salary: 10_000_000, startDate: now()->subMonths(12), religion: 'hindu');

        $islamEmployees = $this->service->getEligibleEmployees(ThrPayroll::EVENT_IDUL_FITRI);
        $hinduEmployees = $this->service->getEligibleEmployees(ThrPayroll::EVENT_NYEPI);

        $this->assertEquals(1, $islamEmployees->count());
        $this->assertEquals(1, $hinduEmployees->count());
        $this->assertEquals('islam', $islamEmployees->first()->religion);
        $this->assertEquals('hindu', $hinduEmployees->first()->religion);
    }

    public function test_natal_event_includes_both_kristen_and_katolik(): void
    {
        $this->createEmployee(salary: 10_000_000, startDate: now()->subMonths(12), religion: 'kristen');
        $this->createEmployee(salary: 10_000_000, startDate: now()->subMonths(12), religion: 'katolik');

        $employees = $this->service->getEligibleEmployees(ThrPayroll::EVENT_NATAL);

        $this->assertEquals(2, $employees->count());
    }

    public function test_exactly_12_months_tenure_gets_full_thr(): void
    {
        $employee = $this->createEmployee(salary: 15_000_000, startDate: now()->subMonths(12));
        $paymentDate = now();

        $result = $this->service->calculateForEmployee($employee, $paymentDate);

        $this->assertTrue($result['eligible']);
        $this->assertEquals(12, $result['tenure_months']);
        $this->assertEquals(1.0, $result['proration_factor']);
        $this->assertEquals(15_000_000, $result['gross_thr_amount']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function createEmployee(
        float $salary,
        Carbon $startDate,
        string $status = 'active',
        string $religion = 'islam'
    ): StaffMemberProfile {
        $user = User::factory()->create();

        $profile = StaffMemberProfile::factory()->create([
            'user_id' => $user->id,
            'religion' => $religion,
            'ptkp_status' => 'TK/0',
            'npwp' => '12.345.678.9-012.000',
        ]);

        $profile->jobInformation()->create([
            'monthly_salary' => $salary,
            'start_date' => $startDate,
            'status' => $status,
            'employment_type' => 'permanent',
            'job_title' => 'Staff',
            'team_id' => null,
            'work_location' => 'office',
        ]);

        $profile->load('jobInformation');

        return $profile;
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
        PtkpAmount::create(['status' => 'K/1', 'annual_amount' => 63_000_000]);

        BpjsRate::create(['component' => 'jht', 'employee_rate' => 2, 'employer_rate' => 3.7, 'max_salary_base' => null]);
        BpjsRate::create(['component' => 'jp', 'employee_rate' => 1, 'employer_rate' => 2, 'max_salary_base' => 9_559_600]);
        BpjsRate::create(['component' => 'jkk', 'employee_rate' => 0, 'employer_rate' => 0.24, 'max_salary_base' => null]);
        BpjsRate::create(['component' => 'jkm', 'employee_rate' => 0, 'employer_rate' => 0.3, 'max_salary_base' => null]);
        BpjsRate::create(['component' => 'bpjs_kesehatan', 'employee_rate' => 1, 'employer_rate' => 4, 'max_salary_base' => 12_000_000]);
    }
}
