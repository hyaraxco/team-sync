<?php

namespace Database\Seeders;

use App\Models\Attendance;
use App\Models\Payroll;
use App\Models\PayrollDetail;
use App\Models\PayrollSetting;
use App\Models\PerformanceReviewCycle;
use App\Models\ReviewerRule;
use App\Models\StaffMemberProfile;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MinimalPayrollE2ESeeder extends Seeder
{
    /**
     * Seed the minimum data needed for the HR -> Finance payroll flow.
     */
    public function run(): void
    {
        StaffMemberProfile::withoutSyncingToSearch(function () {
            $this->call([
                PermissionSeeder::class,
                RoleSeeder::class,
                RolePermissionSeeder::class,
                AttendancePolicySeeder::class,
                LeaveEntitlementSeeder::class,
                ManagerSeeder::class,
                EmployeeSeeder::class,
                HrSeeder::class,
                FinanceSeeder::class,
            ]);
        });

        DB::transaction(function () {
            $payrollMonth = now()->startOfMonth();
            PayrollSetting::current()->update([
                'attendance_cutoff_day' => 1,
            ]);
            $employee = StaffMemberProfile::with('jobInformation')
                ->where('code', 'EMP001')
                ->firstOrFail();

            $employee->jobInformation()->updateOrCreate(
                ['staff_member_id' => $employee->id],
                [
                    'staff_member_id' => $employee->id,
                    'job_title' => 'Software Engineer',
                    'status' => 'active',
                    'employment_type' => 'full_time',
                    'work_location' => 'remote',
                    'start_date' => '2024-01-01',
                    'monthly_salary' => 10000000,
                ]
            );

            $this->resetPayrollMonth($payrollMonth);
            $this->seedAttendanceForActiveEmployeesForMonth($payrollMonth);

            // Create an empty Performance Review Cycle for E2E testing (ID 1)
            PerformanceReviewCycle::updateOrCreate(
                ['name' => 'E2E Review Cycle P4'],
                [
                    'cycle_type' => 'quarterly',
                    'start_date' => now()->startOfMonth(),
                    'end_date' => now()->addMonths(3)->endOfMonth(),
                    'review_period_start' => now()->startOfMonth(),
                    'review_period_end' => now()->addMonths(3)->endOfMonth(),
                    'status' => 'active',
                    'created_by' => StaffMemberProfile::whereHas('user', fn ($q) => $q->role('hr'))->first()->user_id ?? 1,
                ]
            );

            // Create basic reviewer rules so HR can generate reviews properly
            ReviewerRule::firstOrCreate([
                'reviewee_role' => 'staff',
                'reviewer_role' => 'manager',
            ], [
                'priority' => 1,
                'is_active' => true,
            ]);

            $this->command?->info(sprintf(
                'Minimal payroll E2E data ready for %s. Accounts: HR tasyia@teamsync.com, Manager yudhis@teamsync.com, Employee agung@teamsync.com, Finance dwimeta@teamsync.com (password: teamsync). Use HR to generate the draft, then Finance to review and mark it as paid.',
                $payrollMonth->format('F Y')
            ));
        });
    }

    private function resetPayrollMonth(Carbon $payrollMonth): void
    {
        $payrollIds = Payroll::withTrashed()
            ->whereDate('salary_month', $payrollMonth->toDateString())
            ->pluck('id');

        if ($payrollIds->isEmpty()) {
            return;
        }

        PayrollDetail::withTrashed()->whereIn('payroll_id', $payrollIds)->forceDelete();
        Payroll::withTrashed()->whereIn('id', $payrollIds)->forceDelete();
    }

    private function seedAttendanceForActiveEmployeesForMonth(Carbon $payrollMonth): void
    {
        $activeEmployeeIds = StaffMemberProfile::query()
            ->whereHas('jobInformation', function ($query) {
                $query->where('status', 'active');
            })
            ->pluck('id')
            ->all();

        foreach ($activeEmployeeIds as $employeeId) {
            $this->seedAttendanceForMonth((int) $employeeId, $payrollMonth);
        }
    }

    private function seedAttendanceForMonth(int $employeeId, Carbon $payrollMonth): void
    {
        Attendance::withTrashed()
            ->where('staff_member_id', $employeeId)
            ->whereDate('date', '>=', $payrollMonth->copy()->startOfMonth()->toDateString())
            ->whereDate('date', '<=', $payrollMonth->copy()->endOfMonth()->toDateString())
            ->forceDelete();

        $cursor = $payrollMonth->copy()->startOfMonth();
        $monthEnd = $payrollMonth->copy()->endOfMonth();

        while ($cursor->lte($monthEnd)) {
            if (! $cursor->isWeekend()) {
                Attendance::create([
                    'staff_member_id' => $employeeId,
                    'date' => $cursor->toDateString(),
                    'check_in' => $cursor->format('Y-m-d').' 08:00:00',
                    'check_out' => $cursor->format('Y-m-d').' 17:00:00',
                    'status' => 'present',
                    'notes' => 'Seeded for payroll E2E flow',
                ]);
            }

            $cursor->addDay();
        }
    }
}
