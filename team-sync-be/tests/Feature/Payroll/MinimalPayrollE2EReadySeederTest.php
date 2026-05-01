<?php

namespace Tests\Feature\Payroll;

use App\Models\Payroll;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\MinimalPayrollE2EReadySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MinimalPayrollE2EReadySeederTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_ready_seeder_creates_paid_payroll_and_employee_can_see_it(): void
    {
        // Freeze time past the attendance cutoff day (seeder sets cutoff_day=1)
        Carbon::setTestNow('2026-05-02 09:00:00');

        $this->seed(MinimalPayrollE2EReadySeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $payroll = Payroll::whereDate('salary_month', now()->startOfMonth()->toDateString())
            ->firstOrFail();

        $this->assertSame('paid', $payroll->status);
        $this->assertGreaterThan(0, $payroll->payrollDetails()->count());

        $employee = User::where('email', 'agung@teamsync.com')->firstOrFail();
        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/my-payslips?year='.now()->year)
            ->assertOk()
            ->assertJsonFragment(['employee_email' => 'agung@teamsync.com']);
    }
}
