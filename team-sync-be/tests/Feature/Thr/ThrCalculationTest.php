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
use Tests\Concerns\ActivatesLicense;
use Tests\TestCase;

class ThrCalculationTest extends TestCase
{
    use ActivatesLicense, RefreshDatabase;

    /**
     * Reference payment date for tenure-based scenarios.
     * Using a mid-month date keeps tenure arithmetic free of end-of-month edge cases.
     */
    private const PAYMENT_DATE = '2026-04-15';

    private ThrCalculationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(self::PAYMENT_DATE);
        $this->activateTestLicense();
        $this->seedTaxInfrastructure();
        $this->service = app(ThrCalculationService::class);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_full_thr_for_employee_with_12_months_tenure(): void
    {
        // Hired Apr 15 2024 → payment Apr 15 2026 = 24 months tenure (≥12 → full THR)
        $employee = $this->createEmployee(salary: 10_000_000, startDate: Carbon::parse('2024-04-15'));
        $paymentDate = Carbon::parse(self::PAYMENT_DATE);

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
        // Hired Oct 15 2025 → payment Apr 15 2026 = 6 months
        $employee = $this->createEmployee(salary: 12_000_000, startDate: Carbon::parse('2025-10-15'));
        $paymentDate = Carbon::parse(self::PAYMENT_DATE);

        $result = $this->service->calculateForEmployee($employee, $paymentDate);

        $this->assertTrue($result['eligible']);
        $this->assertEquals(6, $result['tenure_months']);
        $this->assertEquals(0.5, $result['proration_factor']);
        $this->assertEquals(6_000_000, $result['gross_thr_amount']);
    }

    public function test_prorated_thr_for_employee_with_3_months_tenure(): void
    {
        // Hired Jan 15 2026 → payment Apr 15 2026 = 3 months
        $employee = $this->createEmployee(salary: 8_000_000, startDate: Carbon::parse('2026-01-15'));
        $paymentDate = Carbon::parse(self::PAYMENT_DATE);

        $result = $this->service->calculateForEmployee($employee, $paymentDate);

        $this->assertTrue($result['eligible']);
        $this->assertEquals(3, $result['tenure_months']);
        $this->assertEquals(0.25, $result['proration_factor']);
        $this->assertEquals(2_000_000, $result['gross_thr_amount']);
    }

    public function test_ineligible_if_tenure_less_than_1_month(): void
    {
        // Hired 15 days before payment date — under 1 month tenure
        $employee = $this->createEmployee(
            salary: 10_000_000,
            startDate: Carbon::parse(self::PAYMENT_DATE)->subDays(15)
        );
        $paymentDate = Carbon::parse(self::PAYMENT_DATE);

        $result = $this->service->calculateForEmployee($employee, $paymentDate);

        $this->assertFalse($result['eligible']);
        $this->assertNotNull($result['ineligibility_reason']);
    }

    public function test_ineligible_if_employee_not_active(): void
    {
        $employee = $this->createEmployee(
            salary: 10_000_000,
            startDate: Carbon::parse('2025-04-15'),
            status: 'resigned'
        );
        $paymentDate = Carbon::parse(self::PAYMENT_DATE);

        $result = $this->service->calculateForEmployee($employee, $paymentDate);

        $this->assertFalse($result['eligible']);
        $this->assertStringContainsString('not active', $result['ineligibility_reason']);
    }

    public function test_ineligible_if_salary_is_zero(): void
    {
        $employee = $this->createEmployee(salary: 0, startDate: Carbon::parse('2025-04-15'));
        $paymentDate = Carbon::parse(self::PAYMENT_DATE);

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
        $this->createEmployee(salary: 10_000_000, startDate: Carbon::parse('2025-04-15'), religion: 'islam');
        $this->createEmployee(salary: 10_000_000, startDate: Carbon::parse('2025-04-15'), religion: 'hindu');

        $islamEmployees = $this->service->getEligibleEmployees(ThrPayroll::EVENT_IDUL_FITRI);
        $hinduEmployees = $this->service->getEligibleEmployees(ThrPayroll::EVENT_NYEPI);

        $this->assertEquals(1, $islamEmployees->count());
        $this->assertEquals(1, $hinduEmployees->count());
        $this->assertEquals('islam', $islamEmployees->first()->religion);
        $this->assertEquals('hindu', $hinduEmployees->first()->religion);
    }

    public function test_natal_event_includes_both_kristen_and_katolik(): void
    {
        $this->createEmployee(salary: 10_000_000, startDate: Carbon::parse('2025-04-15'), religion: 'kristen');
        $this->createEmployee(salary: 10_000_000, startDate: Carbon::parse('2025-04-15'), religion: 'katolik');

        $employees = $this->service->getEligibleEmployees(ThrPayroll::EVENT_NATAL);

        $this->assertEquals(2, $employees->count());
    }

    public function test_exactly_12_months_tenure_gets_full_thr(): void
    {
        // Hired Apr 15 2025 → payment Apr 15 2026 = 12 months exactly
        $employee = $this->createEmployee(salary: 15_000_000, startDate: Carbon::parse('2025-04-15'));
        $paymentDate = Carbon::parse(self::PAYMENT_DATE);

        $result = $this->service->calculateForEmployee($employee, $paymentDate);

        $this->assertTrue($result['eligible']);
        $this->assertEquals(12, $result['tenure_months']);
        $this->assertEquals(1.0, $result['proration_factor']);
        $this->assertEquals(15_000_000, $result['gross_thr_amount']);
    }

    // ─── Boundary cases — calendar-month / end-of-short-month tenure ─────

    public function test_end_of_february_hire_is_3_months_at_late_may(): void
    {
        // Bug regression: hired Feb 28 2026, payment May 30 2026.
        // Carbon::diffInMonths returns 2 (because day 28 < day 30 in last partial month),
        // but Indonesian payroll treats this as 3 completed calendar months because
        // Feb 28 is the last day of February — end-of-month hires are not penalized
        // when later months have more days.
        $employee = $this->createEmployee(salary: 8_000_000, startDate: Carbon::parse('2026-02-28'));
        $paymentDate = Carbon::parse('2026-05-30');

        $result = $this->service->calculateForEmployee($employee, $paymentDate);

        $this->assertTrue($result['eligible']);
        $this->assertEquals(3, $result['tenure_months']);
        $this->assertEquals(0.25, $result['proration_factor']);
        $this->assertEquals(2_000_000, $result['gross_thr_amount']);
    }

    public function test_end_of_january_hire_is_3_months_at_end_of_april(): void
    {
        // Hired Jan 31 2026, payment Apr 30 2026.
        // Apr has only 30 days, but Jan 31 is last-of-month so this counts as 3 months.
        $employee = $this->createEmployee(salary: 8_000_000, startDate: Carbon::parse('2026-01-31'));
        $paymentDate = Carbon::parse('2026-04-30');

        $result = $this->service->calculateForEmployee($employee, $paymentDate);

        $this->assertTrue($result['eligible']);
        $this->assertEquals(3, $result['tenure_months']);
    }

    public function test_mid_month_hire_not_yet_at_anniversary_day_floors(): void
    {
        // Hired Jan 20 2026, payment Apr 15 2026.
        // Day 15 < day 20, and Jan 20 is NOT last-of-month → only 2 completed months.
        $employee = $this->createEmployee(salary: 8_000_000, startDate: Carbon::parse('2026-01-20'));
        $paymentDate = Carbon::parse('2026-04-15');

        $result = $this->service->calculateForEmployee($employee, $paymentDate);

        $this->assertTrue($result['eligible']);
        $this->assertEquals(2, $result['tenure_months']);
    }

    public function test_leap_day_hire_returns_eleven_months_just_before_anniversary(): void
    {
        // Hired Feb 29 2024 (leap day), payment Feb 28 2025.
        // Day 28 < day 29 in non-leap year, but Feb 29 was last-of-month → 12 months.
        // Then evaluated one day earlier should still yield 11 vs 12 boundary check.
        $employee = $this->createEmployee(salary: 10_000_000, startDate: Carbon::parse('2024-02-29'));
        $paymentDate = Carbon::parse('2025-02-27');

        $result = $this->service->calculateForEmployee($employee, $paymentDate);

        $this->assertTrue($result['eligible']);
        $this->assertEquals(11, $result['tenure_months']);
    }

    public function test_leap_day_hire_returns_twelve_months_at_end_of_february_following_year(): void
    {
        // Hired Feb 29 2024 → payment Feb 28 2025. Feb 29 is last-of-month, so the
        // anniversary lands on Feb 28 (last day of Feb in 2025) → 12 months.
        $employee = $this->createEmployee(salary: 10_000_000, startDate: Carbon::parse('2024-02-29'));
        $paymentDate = Carbon::parse('2025-02-28');

        $result = $this->service->calculateForEmployee($employee, $paymentDate);

        $this->assertTrue($result['eligible']);
        $this->assertEquals(12, $result['tenure_months']);
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
