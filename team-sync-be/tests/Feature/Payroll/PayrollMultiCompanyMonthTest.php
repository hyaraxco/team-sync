<?php

namespace Tests\Feature\Payroll;

use App\Interfaces\PayrollRepositoryInterface;
use App\Jobs\GeneratePayrollJob;
use App\Models\Attendance;
use App\Models\Company;
use App\Models\Payroll;
use App\Models\PayrollSetting;
use App\Models\StaffMemberProfile;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\BpjsRateSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PtkpAmountSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TaxBracketSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\ActivatesLicense;
use Tests\TestCase;

/**
 * payrolls used to be unique on salary_month alone, so one company's payroll
 * blocked every other company for the same month. The key is now
 * (company_id, salary_month).
 */
class PayrollMultiCompanyMonthTest extends TestCase
{
    use ActivatesLicense, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->activateTestLicense();

        $this->seed([
            RoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,
            TaxBracketSeeder::class,
            BpjsRateSeeder::class,
            PtkpAmountSeeder::class,
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_two_companies_can_hold_payroll_for_the_same_month(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        $first = Payroll::create(['company_id' => $companyA->id, 'salary_month' => '2026-04-01', 'status' => 'pending']);
        $second = Payroll::create(['company_id' => $companyB->id, 'salary_month' => '2026-04-01', 'status' => 'pending']);

        $this->assertNotSame($first->id, $second->id);
        $this->assertSame(2, Payroll::whereDate('salary_month', '2026-04-01')->count());
    }

    public function test_a_second_payroll_for_the_same_company_and_month_is_rejected(): void
    {
        $company = Company::factory()->create();
        Payroll::create(['company_id' => $company->id, 'salary_month' => '2026-04-01', 'status' => 'pending']);

        $this->expectException(QueryException::class);

        Payroll::create(['company_id' => $company->id, 'salary_month' => '2026-04-01', 'status' => 'pending']);
    }

    public function test_an_existing_payroll_only_blocks_generation_for_its_own_company(): void
    {
        Carbon::setTestNow('2026-04-28 09:00:00');
        PayrollSetting::current()->update(['attendance_cutoff_day' => 25]);

        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();
        $financeA = User::factory()->create(['company_id' => $companyA->id]);
        $financeB = User::factory()->create(['company_id' => $companyB->id]);

        $this->createActiveEmployeeWithAttendance(now()->startOfMonth());
        $this->seedBpjsPayroll('2026-04');

        $payroll = app(PayrollRepositoryInterface::class)->generatePayroll('2026-04', $financeA->id);
        $this->assertSame($companyA->id, $payroll->company_id);

        Sanctum::actingAs($financeA);
        $this->assertSame('duplicate_period', app(PayrollRepositoryInterface::class)
            ->getGenerateReadiness('2026-04')['reason_code']);

        Sanctum::actingAs($financeB);
        $this->assertNotSame('duplicate_period', app(PayrollRepositoryInterface::class)
            ->getGenerateReadiness('2026-04')['reason_code']);
    }

    public function test_generation_without_a_company_context_is_rejected_when_several_companies_exist(): void
    {
        Carbon::setTestNow('2026-04-28 09:00:00');
        PayrollSetting::current()->update(['attendance_cutoff_day' => 25]);
        Company::factory()->count(2)->create();

        $this->expectExceptionMessage('Cannot tell which company this payroll belongs to');

        app(PayrollRepositoryInterface::class)->generatePayroll('2026-04');
    }

    public function test_job_unique_key_is_per_company_and_month(): void
    {
        $companyA = Company::factory()->create();
        $companyB = Company::factory()->create();

        $jobA = new GeneratePayrollJob('2026-04', null, $companyA->id);
        $jobB = new GeneratePayrollJob('2026-04', null, $companyB->id);
        $sameCompany = new GeneratePayrollJob('2026-04', null, $companyA->id);

        $this->assertNotSame($jobA->uniqueId(), $jobB->uniqueId());
        $this->assertSame($jobA->uniqueId(), $sameCompany->uniqueId());
    }

    private function createActiveEmployeeWithAttendance(Carbon $month): StaffMemberProfile
    {
        return StaffMemberProfile::withoutSyncingToSearch(function () use ($month) {
            $employee = StaffMemberProfile::factory()->create();
            $employee->jobInformation()->create([
                'staff_member_id' => $employee->id,
                'job_title' => 'Software Engineer',
                'years_experience' => 5,
                'status' => 'active',
                'employment_type' => 'full_time',
                'work_location' => 'remote',
                'start_date' => '2024-01-01',
                'monthly_salary' => 10000000,
                'skill_level' => 'expert',
            ]);

            $cursor = $month->copy()->startOfMonth();
            $endDate = $month->copy()->endOfMonth();
            while ($cursor->lte($endDate)) {
                if (! $cursor->isWeekend()) {
                    Attendance::create([
                        'staff_member_id' => $employee->id,
                        'date' => $cursor->toDateString(),
                        'check_in' => $this->localAttendanceTime($cursor->format('Y-m-d').' 08:00:00'),
                        'check_out' => $this->localAttendanceTime($cursor->format('Y-m-d').' 17:00:00'),
                        'status' => 'present',
                        'notes' => 'Multi-company payroll coverage',
                    ]);
                }
                $cursor->addDay();
            }

            return $employee;
        });
    }
}
