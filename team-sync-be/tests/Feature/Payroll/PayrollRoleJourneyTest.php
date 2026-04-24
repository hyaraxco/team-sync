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
use Tests\TestCase;

class PayrollRoleJourneyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MinimalPayrollE2ESeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Notification::fake();
    }

    public function test_seeded_hr_finance_manager_and_employee_follow_the_expected_payroll_journey(): void
    {
        Queue::fake();

        $salaryMonth = now()->startOfMonth()->format('Y-m');
        $payrollDate = now()->startOfMonth()->toDateString();
        $this->seedFullMonthAttendanceForActiveEmployees($salaryMonth);

        $this->actingAsEmail('tasyia@teamsync.com');
        $hrMe = $this
            ->getJson('/api/v1/me')
            ->assertOk();

        $this->assertSame('tasyia@teamsync.com', $hrMe->json('data.email'));
        $this->assertContains('payroll-create', $hrMe->json('data.permissions'));
        $this->assertNotContains('payroll-statistics', $hrMe->json('data.permissions'));

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

        $this->assertSame('pending', $payroll->status);
        $this->assertGreaterThan(0, $payroll->payrollDetails()->count());

        $this->actingAsEmail('dwimeta@teamsync.com');
        $financeMe = $this
            ->getJson('/api/v1/me')
            ->assertOk();

        $this->assertSame('dwimeta@teamsync.com', $financeMe->json('data.email'));
        $this->assertContains('payroll-process', $financeMe->json('data.permissions'));
        $this->assertNotContains('payroll-create', $financeMe->json('data.permissions'));

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

        $this->assertSame('approved', $payroll->fresh()->status);

        $this
            ->postJson("/api/v1/payrolls/{$payroll->id}/mark-as-paid", [
                'payment_date' => now()->endOfMonth()->toDateString(),
            ])
            ->assertOk();

        $this->assertSame('paid', $payroll->fresh()->status);

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
