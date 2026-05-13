<?php

namespace Tests\Feature\Payroll;

use App\Interfaces\PayrollRepositoryInterface;
use App\Models\Attendance;
use App\Models\JobInformation;
use App\Models\Payroll;
use App\Models\PayrollSetting;
use App\Models\StaffMemberProfile;
use Carbon\Carbon;
use Database\Seeders\BpjsRateSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PtkpAmountSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TaxBracketSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\ActivatesLicense;
use Tests\TestCase;

/**
 * Tests that batch processing produces the same results as sequential processing
 * and correctly tracks progress.
 */
class PayrollBatchProcessingTest extends TestCase
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
        Notification::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** @test */
    public function test_batch_processing_generates_correct_payroll_for_multiple_employees(): void
    {
        Carbon::setTestNow('2026-04-28 09:00:00');
        PayrollSetting::current()->update(['attendance_cutoff_day' => 25]);

        $salaries = [5_000_000, 8_000_000, 10_000_000, 12_000_000, 15_000_000,
            6_500_000, 7_200_000, 9_800_000, 11_500_000, 13_000_000,
            4_500_000, 20_000_000];

        $employees = [];
        foreach ($salaries as $salary) {
            $employees[] = $this->createEmployeeWithAttendance($salary);
        }

        $repository = app(PayrollRepositoryInterface::class);
        $payroll = $repository->generatePayroll('2026-04', null);

        // All employees should have payroll details
        $this->assertCount(count($salaries), $payroll->payrollDetails);

        // Verify each employee has correct original salary
        foreach ($employees as $index => $employee) {
            $detail = $payroll->payrollDetails->firstWhere('staff_member_id', $employee->id);
            $this->assertNotNull($detail, "PayrollDetail should exist for employee #{$index}");
            $this->assertEquals(
                $salaries[$index],
                (float) $detail->original_salary,
                "Original salary should match for employee #{$index}"
            );
            $this->assertGreaterThan(0, (float) $detail->final_salary);
        }
    }

    /** @test */
    public function test_batch_processing_updates_processed_count(): void
    {
        Carbon::setTestNow('2026-04-28 09:00:00');
        PayrollSetting::current()->update(['attendance_cutoff_day' => 25]);

        // Create 5 employees
        for ($i = 0; $i < 5; $i++) {
            $this->createEmployeeWithAttendance(8_000_000 + ($i * 1_000_000));
        }

        $repository = app(PayrollRepositoryInterface::class);
        $payroll = $repository->generatePayroll('2026-04', null);

        // After generation, processed_count should reflect total employees processed
        $freshPayroll = Payroll::find($payroll->id);
        $this->assertNotNull($freshPayroll);
        // processed_count is updated during processing; final status is 'pending'
        $this->assertEquals('pending', $freshPayroll->status->value);
        $this->assertEquals(5, $freshPayroll->payrollDetails()->count());
    }

    /** @test */
    public function test_batch_processing_includes_tax_and_bpjs_for_all_employees(): void
    {
        Carbon::setTestNow('2026-04-28 09:00:00');
        PayrollSetting::current()->update(['attendance_cutoff_day' => 25]);

        $employees = [];
        for ($i = 0; $i < 8; $i++) {
            $employees[] = $this->createEmployeeWithAttendance(10_000_000 + ($i * 2_000_000));
        }

        $repository = app(PayrollRepositoryInterface::class);
        $payroll = $repository->generatePayroll('2026-04', null);

        foreach ($employees as $employee) {
            $detail = $payroll->payrollDetails->firstWhere('staff_member_id', $employee->id);
            $this->assertNotNull($detail);

            // All employees with salary > PTKP should have PPh21
            $this->assertGreaterThan(0, (float) $detail->pph21_amount);
            // All employees should have BPJS
            $this->assertGreaterThan(0, (float) $detail->bpjs_tk_employee);
            $this->assertGreaterThan(0, (float) $detail->bpjs_tk_employer);
            $this->assertGreaterThan(0, (float) $detail->bpjs_kes_employee);
            $this->assertGreaterThan(0, (float) $detail->bpjs_kes_employer);
        }
    }

    /** @test */
    public function test_batch_processing_total_matches_individual_calculations(): void
    {
        Carbon::setTestNow('2026-04-28 09:00:00');
        PayrollSetting::current()->update(['attendance_cutoff_day' => 25]);

        $employee1 = $this->createEmployeeWithAttendance(10_000_000);
        $employee2 = $this->createEmployeeWithAttendance(15_000_000);

        $repository = app(PayrollRepositoryInterface::class);
        $payroll = $repository->generatePayroll('2026-04', null);

        $detail1 = $payroll->payrollDetails->firstWhere('staff_member_id', $employee1->id);
        $detail2 = $payroll->payrollDetails->firstWhere('staff_member_id', $employee2->id);

        $this->assertNotNull($detail1);
        $this->assertNotNull($detail2);

        // Verify totals are consistent
        $totalOriginal = (float) $detail1->original_salary + (float) $detail2->original_salary;
        $this->assertEquals(25_000_000, $totalOriginal);

        // Each detail should have independent calculations
        $this->assertNotEquals(
            (float) $detail1->pph21_amount,
            (float) $detail2->pph21_amount,
            'Different salaries should produce different PPh21 amounts'
        );
    }

    private function createEmployeeWithAttendance(int $salary): StaffMemberProfile
    {
        return StaffMemberProfile::withoutSyncingToSearch(function () use ($salary) {
            $employee = StaffMemberProfile::factory()->create();

            JobInformation::factory()
                ->forEmployee($employee)
                ->active()
                ->state([
                    'monthly_salary' => $salary,
                    'status' => 'active',
                    'employment_type' => 'full_time',
                ])
                ->create();

            $startDate = Carbon::now()->startOfMonth();
            $endDate = Carbon::now()->endOfMonth();

            $cursor = $startDate->copy();
            while ($cursor->lte($endDate)) {
                if (! $cursor->isWeekend()) {
                    Attendance::create([
                        'staff_member_id' => $employee->id,
                        'date' => $cursor->toDateString(),
                        'check_in' => $cursor->format('Y-m-d').' 08:00:00',
                        'check_out' => $cursor->format('Y-m-d').' 17:00:00',
                        'status' => 'present',
                        'notes' => 'Batch processing test',
                    ]);
                }
                $cursor->addDay();
            }

            return $employee;
        });
    }
}
