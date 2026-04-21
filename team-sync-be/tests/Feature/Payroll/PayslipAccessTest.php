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

class PayslipAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MinimalPayrollE2ESeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_employee_can_list_view_and_download_only_their_paid_payslip(): void
    {
        $employee = User::where('email', 'agung@teamsync.com')->firstOrFail();
        $otherStaffMemberProfile = $this->createSecondEmployeeWithAttendance();
        $payroll = $this->createPaidPayrollForCurrentMonth();

        $employeePayslip = $payroll->payrollDetails()->where('staff_member_id', $employee->staffMemberProfile->id)->firstOrFail();
        $otherPayslip = $payroll->payrollDetails()->where('staff_member_id', $otherStaffMemberProfile->id)->firstOrFail();

        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/my-payslips?year=' . now()->year)
            ->assertOk()
            ->assertJsonFragment(['id' => $employeePayslip->id])
            ->assertJsonMissing(['id' => $otherPayslip->id]);

        $this->getJson("/api/v1/my-payslips/{$employeePayslip->id}")
            ->assertOk()
            ->assertJsonFragment(['employee_email' => 'agung@teamsync.com'])
            ->assertJsonPath('data.adjustments', []);

        $this->get("/api/v1/payslips/{$employeePayslip->id}/download")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->getJson("/api/v1/my-payslips/{$otherPayslip->id}")
            ->assertNotFound();

        $this->get("/api/v1/payslips/{$otherPayslip->id}/download")
            ->assertNotFound();
    }

    public function test_non_payslip_roles_cannot_access_payslip_endpoints(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/my-payslips')->assertForbidden();
        $this->getJson('/api/v1/my-payslips/1')->assertForbidden();
        $this->get('/api/v1/payslips/1/download')->assertForbidden();
    }

    public function test_internal_admin_roles_can_access_only_their_own_paid_payslips(): void
    {
        foreach (['tasyia@teamsync.com', 'dwimeta@teamsync.com', 'yudhis@teamsync.com'] as $email) {
            $this->createAttendanceForEmployee(User::where('email', $email)->firstOrFail()->staffMemberProfile);
        }

        $payroll = $this->createPaidPayrollForCurrentMonth();
        $otherPayslip = $payroll->payrollDetails()
            ->whereHas('staffMember.user', fn ($query) => $query->where('email', 'agung@teamsync.com'))
            ->firstOrFail();

        $cases = [
            'tasyia@teamsync.com',
            'dwimeta@teamsync.com',
            'yudhis@teamsync.com',
        ];

        foreach ($cases as $email) {
            $user = User::where('email', $email)->firstOrFail();
            $ownPayslip = $payroll->payrollDetails()
                ->where('staff_member_id', $user->staffMemberProfile->id)
                ->firstOrFail();

            Sanctum::actingAs($user);

            $this->getJson('/api/v1/my-payslips?year=' . now()->year)
                ->assertOk()
                ->assertJsonFragment(['id' => $ownPayslip->id])
                ->assertJsonMissing(['id' => $otherPayslip->id]);

            $this->getJson("/api/v1/my-payslips/{$ownPayslip->id}")
                ->assertOk()
                ->assertJsonFragment(['employee_email' => $email]);

            $this->getJson("/api/v1/my-payslips/{$otherPayslip->id}")
                ->assertNotFound();
        }
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
                        'check_in' => $cursor->format('Y-m-d') . ' 08:00:00',
                        'check_out' => $cursor->format('Y-m-d') . ' 17:00:00',
                        'status' => 'present',
                        'notes' => 'Admin role payslip test attendance',
                    ]);
                } else {
                    Attendance::create([
                        'staff_member_id' => $staffMemberProfile->id,
                        'date' => $cursor->toDateString(),
                        'check_in' => $cursor->format('Y-m-d') . ' 08:00:00',
                        'check_out' => $cursor->format('Y-m-d') . ' 17:00:00',
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
