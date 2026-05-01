<?php

namespace Tests\Feature\Payroll;

use App\Interfaces\PayrollRepositoryInterface;
use App\Models\Attendance;
use App\Models\StaffMemberProfile;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\MinimalPayrollE2ESeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PayslipEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Freeze time past the attendance cutoff day (seeder sets cutoff_day=1)
        Carbon::setTestNow('2026-05-02 09:00:00');

        $this->seed(MinimalPayrollE2ESeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_unauthenticated_user_cannot_email_payslip(): void
    {
        $this->postJson('/api/v1/payslips/1/email')
            ->assertStatus(401);
    }

    public function test_employee_can_email_own_payslip(): void
    {
        $employee = User::where('email', 'agung@teamsync.com')->firstOrFail();
        $payroll = $this->createPaidPayrollForCurrentMonth();

        $employeePayslip = $payroll->payrollDetails()
            ->where('staff_member_id', $employee->staffMemberProfile->id)
            ->firstOrFail();

        Sanctum::actingAs($employee);

        $this->postJson("/api/v1/payslips/{$employeePayslip->id}/email")
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Payslip emailed successfully',
            ]);
    }

    public function test_employee_cannot_email_other_payslip(): void
    {
        $employee = User::where('email', 'agung@teamsync.com')->firstOrFail();
        $otherStaffMemberProfile = $this->createSecondEmployeeWithAttendance();
        $payroll = $this->createPaidPayrollForCurrentMonth();

        $otherPayslip = $payroll->payrollDetails()
            ->where('staff_member_id', $otherStaffMemberProfile->id)
            ->firstOrFail();

        Sanctum::actingAs($employee);

        $this->postJson("/api/v1/payslips/{$otherPayslip->id}/email")
            ->assertStatus(422);
    }

    private function createAttendanceForEmployee(StaffMemberProfile $staffMemberProfile): void
    {
        $month = now()->startOfMonth();
        $monthEnd = $month->copy()->endOfMonth();
        $cursor = $month->copy();

        while ($cursor->lte($monthEnd)) {
            if (! $cursor->isWeekend()) {
                $attendance = Attendance::query()
                    ->where('staff_member_id', $staffMemberProfile->id)
                    ->whereDate('date', $cursor->toDateString())
                    ->first();

                if ($attendance) {
                    $attendance->update([
                        'check_in' => $cursor->format('Y-m-d').' 08:00:00',
                        'check_out' => $cursor->format('Y-m-d').' 17:00:00',
                        'status' => 'present',
                        'notes' => 'Admin role payslip test attendance',
                    ]);
                } else {
                    Attendance::create([
                        'staff_member_id' => $staffMemberProfile->id,
                        'date' => $cursor->toDateString(),
                        'check_in' => $cursor->format('Y-m-d').' 08:00:00',
                        'check_out' => $cursor->format('Y-m-d').' 17:00:00',
                        'status' => 'present',
                        'notes' => 'Admin role payslip test attendance',
                    ]);
                }
            }

            $cursor->addDay();
        }
    }

    private function createSecondEmployeeWithAttendance(): StaffMemberProfile
    {
        return StaffMemberProfile::withoutSyncingToSearch(function () {
            $staffMemberProfile = StaffMemberProfile::factory()->create();

            $staffMemberProfile->jobInformation()->create([
                'staff_member_id' => $staffMemberProfile->id,
                'job_title' => 'QA Engineer',
                'years_experience' => 3,
                'status' => 'active',
                'employment_type' => 'full_time',
                'work_location' => 'remote',
                'start_date' => '2024-01-01',
                'monthly_salary' => 9000000,
                'skill_level' => 'intermediate',
            ]);

            $staffMemberProfile->bankInformation()->create([
                'staff_member_id' => $staffMemberProfile->id,
                'bank_name' => 'Mandiri',
                'account_number' => '4455667788',
                'account_holder_name' => 'Second Payslip Employee',
                'account_type' => 'saving',
            ]);

            $this->createAttendanceForEmployee($staffMemberProfile);

            return $staffMemberProfile;
        });
    }

    private function createPaidPayrollForCurrentMonth()
    {
        StaffMemberProfile::query()
            ->whereHas('jobInformation', function ($query) {
                $query->where('status', 'active');
            })
            ->get()
            ->each(function (StaffMemberProfile $staffMemberProfile) {
                $this->createAttendanceForEmployee($staffMemberProfile);
            });

        $repository = app(PayrollRepositoryInterface::class);
        $salaryMonth = now()->startOfMonth()->format('Y-m');
        $payroll = $repository->generatePayroll($salaryMonth);
        $repository->approvePayroll($payroll->id);

        return $repository->markAsPaid($payroll->id, now()->endOfMonth()->toDateString());
    }
}
