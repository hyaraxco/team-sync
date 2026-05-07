<?php

namespace Tests\Feature\Payroll;

use App\Interfaces\PayrollRepositoryInterface;
use App\Jobs\GeneratePayrollJob;
use App\Models\Attendance;
use App\Models\AttendancePeriod;
use App\Models\Payroll;
use App\Models\PayrollAdjustment;
use App\Models\PayrollDetail;
use App\Models\PayrollSetting;
use App\Models\StaffMemberProfile;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\ActivatesLicense;
use Tests\TestCase;

class PayrollAdjustmentApplicationTest extends TestCase
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
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_generate_payroll_applies_approved_adjustments_and_marks_them_applied(): void
    {
        Queue::fake();
        Carbon::setTestNow('2026-05-28 09:00:00');

        PayrollSetting::current()->update([
            'attendance_cutoff_day' => 25,
            'rounding_mode' => 'none',
            'rounding_unit' => 1,
            'absent_deduction_rate' => 1,
        ]);

        $employee = $this->createActiveEmployeeWithAttendance(Carbon::parse('2026-05-01'));

        $sourcePeriod = AttendancePeriod::create([
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'cutoff_date' => '2026-04-25',
            'status' => AttendancePeriod::STATUS_LOCKED,
            'locked_at' => now(),
        ]);

        $targetPeriod = AttendancePeriod::create([
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-31',
            'cutoff_date' => '2026-05-25',
            'status' => AttendancePeriod::STATUS_REVIEW,
        ]);

        $adjustment = PayrollAdjustment::create([
            'staff_member_id' => $employee->id,
            'source_period_id' => $sourcePeriod->id,
            'target_period_id' => $targetPeriod->id,
            'source_reference_type' => 'leave_request',
            'source_reference_id' => 999,
            'adjustment_kind' => PayrollAdjustment::KIND_ABSENCE_CORRECTION_CREDIT,
            'days_delta' => 2,
            'amount_delta' => 250000,
            'reason' => 'Post-lock sick proof approved',
            'status' => PayrollAdjustment::STATUS_APPROVED,
        ]);

        $this->actingAsRole('finance');

        $this->postJson('/api/v1/payrolls/generate', [
            'salary_month' => '2026-05',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'processing');

        Queue::assertPushed(GeneratePayrollJob::class, function (GeneratePayrollJob $job) {
            $job->handle(app(PayrollRepositoryInterface::class));

            return $job->salaryMonth === '2026-05';
        });

        $payroll = Payroll::query()
            ->whereDate('salary_month', '2026-05-01')
            ->firstOrFail();

        $detail = PayrollDetail::query()
            ->where('payroll_id', $payroll->id)
            ->where('staff_member_id', $employee->id)
            ->firstOrFail();

        $baseFinalSalary = max(
            0,
            round((float) $detail->original_salary - (float) $detail->deduction_amount, 2)
        );

        $this->assertEquals(
            250000.0,
            round((float) $detail->final_salary - $baseFinalSalary, 2)
        );

        $this->assertSame(
            PayrollAdjustment::STATUS_APPLIED,
            $adjustment->fresh()->status
        );

        $this->assertSame(
            0,
            PayrollAdjustment::query()
                ->where('target_period_id', $targetPeriod->id)
                ->where('status', PayrollAdjustment::STATUS_APPROVED)
                ->count()
        );

        $this->getJson("/api/v1/payrolls/{$payroll->id}/details")
            ->assertOk()
            ->assertJsonFragment([
                'adjustment_kind' => PayrollAdjustment::KIND_ABSENCE_CORRECTION_CREDIT,
            ])
            ->assertJsonFragment([
                'adjustment_total_amount' => 250000,
            ]);
    }

    private function actingAsRole(string $roleName): User
    {
        $user = User::factory()->create();
        $role = Role::findByName($roleName, 'sanctum');
        $user->assignRole($role);

        Sanctum::actingAs($user);

        return $user;
    }

    private function createActiveEmployeeWithAttendance(Carbon $attendanceDate): StaffMemberProfile
    {
        return StaffMemberProfile::withoutSyncingToSearch(function () use ($attendanceDate) {
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

            $cursor = $attendanceDate->copy()->startOfMonth();
            $monthEnd = $attendanceDate->copy()->endOfMonth();

            while ($cursor->lte($monthEnd)) {
                if (! $cursor->isWeekend()) {
                    Attendance::create([
                        'staff_member_id' => $employee->id,
                        'date' => $cursor->toDateString(),
                        'check_in' => $cursor->copy()->format('Y-m-d').' 08:00:00',
                        'check_out' => $cursor->copy()->format('Y-m-d').' 17:00:00',
                        'status' => 'present',
                        'notes' => 'Adjustment application coverage',
                    ]);
                }

                $cursor->addDay();
            }

            return $employee;
        });
    }
}
