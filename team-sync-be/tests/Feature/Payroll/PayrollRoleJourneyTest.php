<?php

namespace Tests\Feature\Payroll;

use App\Interfaces\PayrollRepositoryInterface;
use App\Jobs\GeneratePayrollJob;
use App\Models\Attendance;
use App\Models\Payroll;
use App\Models\StaffMemberProfile;
use App\Models\User;
use App\Notifications\PayrollPaid;
use Carbon\Carbon;
use Database\Seeders\MinimalPayrollE2ESeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\ActivatesLicense;
use Tests\TestCase;

class PayrollRoleJourneyTest extends TestCase
{
    use ActivatesLicense, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Freeze time past the attendance cutoff day (seeder sets cutoff_day=1)
        Carbon::setTestNow('2026-05-02 09:00:00');

        $this->seed(MinimalPayrollE2ESeeder::class);
        $this->activateTestLicense();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Notification::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_seeded_hr_finance_manager_and_employee_follow_the_expected_payroll_journey(): void
    {
        Queue::fake();

        $salaryMonth = now()->startOfMonth()->format('Y-m');
        $payrollDate = now()->startOfMonth()->toDateString();
        $this->seedFullMonthAttendanceForActiveEmployees($salaryMonth);

        // Finance generates payroll (Finance owns payroll-create)
        $this->actingAsEmail('dwimeta@teamsync.com');
        $financeMe = $this
            ->getJson('/api/v1/me')
            ->assertOk();

        $this->assertSame('dwimeta@teamsync.com', $financeMe->json('data.email'));
        $this->assertContains('payroll-create', $financeMe->json('data.permissions'));
        $this->assertContains('payroll-process', $financeMe->json('data.permissions'));

        $this
            ->postJson('/api/v1/payrolls/generate', [
                'salary_month' => $salaryMonth,
            ])
            ->assertOk();

        Queue::assertPushed(GeneratePayrollJob::class, function (GeneratePayrollJob $job) use ($salaryMonth) {
            $job->handle(app(PayrollRepositoryInterface::class));

            return $job->salaryMonth === $salaryMonth;
        });

        $payroll = Payroll::whereDate('salary_month', $payrollDate)->firstOrFail();

        $this->assertSame('pending', $payroll->status->value);
        $this->assertGreaterThan(0, $payroll->payrollDetails()->count());

        // HR should NOT have payroll-create (read-only readiness only)
        $this->actingAsEmail('tasyia@teamsync.com');
        $hrMe = $this
            ->getJson('/api/v1/me')
            ->assertOk();

        $this->assertSame('tasyia@teamsync.com', $hrMe->json('data.email'));
        $this->assertNotContains('payroll-create', $hrMe->json('data.permissions'));
        $this->assertContains('payroll-readiness-view', $hrMe->json('data.permissions'));

        // Switch back to Finance for approval/payment flow
        $this->actingAsEmail('dwimeta@teamsync.com');

        $this
            ->getJson('/api/v1/payrolls/all/paginated')
            ->assertOk()
            ->assertJsonFragment(['id' => $payroll->id])
            ->assertJsonFragment(['status' => 'pending']);

        $this
            ->getJson("/api/v1/payrolls/{$payroll->id}/details")
            ->assertOk()
            ->assertJsonFragment(['code' => 'EMP001']);

        $this
            ->getJson("/api/v1/payrolls/{$payroll->id}/statistics")
            ->assertOk();

        $this
            ->postJson("/api/v1/payrolls/{$payroll->id}/approve")
            ->assertOk();

        $this->assertSame('approved', $payroll->fresh()->status->value);

        $this
            ->postJson("/api/v1/payrolls/{$payroll->id}/mark-as-paid", [
                'payment_date' => now()->toDateString(),
            ])
            ->assertOk();

        $this->assertSame('paid', $payroll->fresh()->status->value);

        $employeeUser = User::where('email', 'agung@teamsync.com')->firstOrFail();
        $employeePayslip = $payroll->fresh()->payrollDetails()->where('staff_member_id', $employeeUser->staffMemberProfile?->id)->firstOrFail();
        Notification::assertSentTo($employeeUser, PayrollPaid::class, function (PayrollPaid $notification) use ($employeeUser, $employeePayslip) {
            $mailMessage = $notification->toMail($employeeUser);

            return $mailMessage->actionText === 'Lihat Payroll Saya'
            && $mailMessage->actionUrl === url('/admin/my-payroll/'.$employeePayslip->id);
        });

        $this
            ->postJson("/api/v1/payrolls/{$payroll->id}/resend-notifications")
            ->assertOk();

        Notification::assertSentToTimes($employeeUser, PayrollPaid::class, 2);

        $this->actingAsEmail('yudhis@teamsync.com');
        $this
            ->getJson('/api/v1/payrolls/all/paginated')
            ->assertForbidden();

        $this->actingAsEmail('agung@teamsync.com');
        $this
            ->getJson('/api/v1/payrolls/all/paginated')
            ->assertForbidden();
    }

    private function actingAsEmail(string $email): User
    {
        $user = User::where('email', $email)->firstOrFail();
        Sanctum::actingAs($user);

        return $user;
    }

    private function seedFullMonthAttendanceForActiveEmployees(string $salaryMonth): void
    {
        $month = Carbon::createFromFormat('Y-m', $salaryMonth)->startOfMonth();
        $monthEnd = $month->copy()->endOfMonth();

        $employees = StaffMemberProfile::query()
            ->whereHas('jobInformation', function ($query) {
                $query->where('status', 'active');
            })
            ->get();

        foreach ($employees as $employee) {
            $cursor = $month->copy();

            while ($cursor->lte($monthEnd)) {
                if (! $cursor->isWeekend()) {
                    $attendance = Attendance::query()
                        ->where('staff_member_id', $employee->id)
                        ->whereDate('date', $cursor->toDateString())
                        ->first();

                    if ($attendance) {
                        $attendance->update([
                            'check_in' => $cursor->format('Y-m-d').' 08:00:00',
                            'check_out' => $cursor->format('Y-m-d').' 17:00:00',
                            'status' => 'present',
                            'notes' => 'Role journey readiness coverage',
                        ]);
                    } else {
                        Attendance::create([
                            'staff_member_id' => $employee->id,
                            'date' => $cursor->toDateString(),
                            'check_in' => $cursor->format('Y-m-d').' 08:00:00',
                            'check_out' => $cursor->format('Y-m-d').' 17:00:00',
                            'status' => 'present',
                            'notes' => 'Role journey readiness coverage',
                        ]);
                    }
                }

                $cursor->addDay();
            }
        }
    }
}
