<?php

namespace Tests\Feature\Overtime;

use App\Interfaces\PayrollRepositoryInterface;
use App\Models\Attendance;
use App\Models\JobInformation;
use App\Models\OvertimeRecord;
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
use Tests\TestCase;

class OvertimePayrollIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

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

    // ─────────────────────────────────────────────────────────────────────────
    // Integration: Overtime amount added to final_salary during generation
    // ─────────────────────────────────────────────────────────────────────────

    public function test_overtime_amount_added_to_final_salary(): void
    {
        Carbon::setTestNow('2026-04-28 09:00:00');
        PayrollSetting::current()->update(['attendance_cutoff_day' => 25]);

        $salary = 10_000_000;
        $employee = $this->createEmployeeWithAttendance($salary);

        // Create approved overtime records for this month
        OvertimeRecord::factory()->forEmployee($employee)->approved()->create([
            'date' => '2026-04-10',
            'start_time' => '17:00',
            'end_time' => '19:00',
            'hours' => 2.0,
            'overtime_type' => 'workday',
        ]);

        $payroll = app(PayrollRepositoryInterface::class)->generatePayroll('2026-04', null);
        $detail = $payroll->payrollDetails->firstWhere('staff_member_id', $employee->id);

        $this->assertNotNull($detail);
        $this->assertGreaterThan(0, (float) $detail->overtime_amount);
        $this->assertGreaterThan(0, (float) $detail->overtime_hours);
        $this->assertEquals(1, $detail->overtime_records_count);

        // Final salary should be greater than original salary minus deductions
        // because overtime is ADDED
        $hourlyRate = $salary / 173;
        $expectedOvertimeAmount = round((1.5 * $hourlyRate) + (1.0 * 2.0 * $hourlyRate), 2);
        $this->assertEqualsWithDelta($expectedOvertimeAmount, (float) $detail->overtime_amount, 1.0);
    }

    public function test_only_approved_overtime_is_included_in_payroll(): void
    {
        Carbon::setTestNow('2026-04-28 09:00:00');
        PayrollSetting::current()->update(['attendance_cutoff_day' => 25]);

        $salary = 10_000_000;
        $employee = $this->createEmployeeWithAttendance($salary);

        // Create approved overtime
        OvertimeRecord::factory()->forEmployee($employee)->approved()->create([
            'date' => '2026-04-10',
            'start_time' => '17:00',
            'end_time' => '19:00',
            'hours' => 2.0,
            'overtime_type' => 'workday',
        ]);

        // Create pending overtime (should NOT be included)
        OvertimeRecord::factory()->forEmployee($employee)->create([
            'date' => '2026-04-11',
            'start_time' => '17:00',
            'end_time' => '20:00',
            'hours' => 3.0,
            'overtime_type' => 'workday',
            'status' => OvertimeRecord::STATUS_PENDING,
        ]);

        $payroll = app(PayrollRepositoryInterface::class)->generatePayroll('2026-04', null);
        $detail = $payroll->payrollDetails->firstWhere('staff_member_id', $employee->id);

        $this->assertNotNull($detail);
        // Only 1 approved record should be counted
        $this->assertEquals(1, $detail->overtime_records_count);
        $this->assertEquals(2.0, (float) $detail->overtime_hours);
    }

    public function test_pending_and_rejected_overtime_excluded_from_payroll(): void
    {
        Carbon::setTestNow('2026-04-28 09:00:00');
        PayrollSetting::current()->update(['attendance_cutoff_day' => 25]);

        $salary = 10_000_000;
        $employee = $this->createEmployeeWithAttendance($salary);

        // Create only pending overtime
        OvertimeRecord::factory()->forEmployee($employee)->create([
            'date' => '2026-04-10',
            'start_time' => '17:00',
            'end_time' => '19:00',
            'hours' => 2.0,
            'overtime_type' => 'workday',
            'status' => OvertimeRecord::STATUS_PENDING,
        ]);

        // Create rejected overtime
        OvertimeRecord::factory()->forEmployee($employee)->rejected()->create([
            'date' => '2026-04-11',
            'start_time' => '17:00',
            'end_time' => '20:00',
            'hours' => 3.0,
            'overtime_type' => 'workday',
        ]);

        $payroll = app(PayrollRepositoryInterface::class)->generatePayroll('2026-04', null);
        $detail = $payroll->payrollDetails->firstWhere('staff_member_id', $employee->id);

        $this->assertNotNull($detail);
        $this->assertEquals(0, $detail->overtime_records_count);
        $this->assertEquals(0.0, (float) $detail->overtime_hours);
        $this->assertEquals(0.0, (float) $detail->overtime_amount);
    }

    public function test_payroll_without_overtime_has_zero_overtime_fields(): void
    {
        Carbon::setTestNow('2026-04-28 09:00:00');
        PayrollSetting::current()->update(['attendance_cutoff_day' => 25]);

        $employee = $this->createEmployeeWithAttendance(8_000_000);

        $payroll = app(PayrollRepositoryInterface::class)->generatePayroll('2026-04', null);
        $detail = $payroll->payrollDetails->firstWhere('staff_member_id', $employee->id);

        $this->assertNotNull($detail);
        $this->assertEquals(0, $detail->overtime_records_count);
        $this->assertEquals(0.0, (float) $detail->overtime_hours);
        $this->assertEquals(0.0, (float) $detail->overtime_amount);
    }

    public function test_multiple_approved_overtime_records_summed_correctly(): void
    {
        Carbon::setTestNow('2026-04-28 09:00:00');
        PayrollSetting::current()->update(['attendance_cutoff_day' => 25]);

        $salary = 10_000_000;
        $employee = $this->createEmployeeWithAttendance($salary);

        // Create multiple approved overtime records
        OvertimeRecord::factory()->forEmployee($employee)->approved()->create([
            'date' => '2026-04-07',
            'start_time' => '17:00',
            'end_time' => '18:00',
            'hours' => 1.0,
            'overtime_type' => 'workday',
        ]);

        OvertimeRecord::factory()->forEmployee($employee)->approved()->create([
            'date' => '2026-04-14',
            'start_time' => '17:00',
            'end_time' => '19:00',
            'hours' => 2.0,
            'overtime_type' => 'workday',
        ]);

        OvertimeRecord::factory()->forEmployee($employee)->approved()->create([
            'date' => '2026-04-12',
            'start_time' => '08:00',
            'end_time' => '16:00',
            'hours' => 8.0,
            'overtime_type' => 'weekend',
        ]);

        $payroll = app(PayrollRepositoryInterface::class)->generatePayroll('2026-04', null);
        $detail = $payroll->payrollDetails->firstWhere('staff_member_id', $employee->id);

        $this->assertNotNull($detail);
        $this->assertEquals(3, $detail->overtime_records_count);
        $this->assertEquals(11.0, (float) $detail->overtime_hours);
        $this->assertGreaterThan(0, (float) $detail->overtime_amount);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helper
    // ─────────────────────────────────────────────────────────────────────────

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
                        'check_in' => $cursor->format('Y-m-d') . ' 08:00:00',
                        'check_out' => $cursor->format('Y-m-d') . ' 17:00:00',
                        'status' => 'present',
                        'notes' => 'Overtime integration test',
                    ]);
                }
                $cursor->addDay();
            }

            return $employee;
        });
    }
}
