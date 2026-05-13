<?php

namespace Tests\Feature\Commands;

use App\Models\Payroll;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class PreparePayrollQaCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_qa_payroll_ready_command_seeds_and_checks_successfully(): void
    {
        // Freeze time past the attendance cutoff day (seeder sets cutoff_day=1)
        Carbon::setTestNow('2026-05-02 09:00:00');

        $exitCode = Artisan::call('qa:payroll-ready');

        $this->assertSame(0, $exitCode);

        $this->assertNotNull(User::where('email', 'tasyia@teamsync.com')->first());
        $this->assertNotNull(User::where('email', 'yudhis@teamsync.com')->first());
        $this->assertNotNull(User::where('email', 'agung@teamsync.com')->first());
        $this->assertNotNull(User::where('email', 'dwimeta@teamsync.com')->first());

        $payroll = Payroll::whereDate('salary_month', now()->startOfMonth()->toDateString())->firstOrFail();
        $this->assertSame('paid', $payroll->status->value);
    }
}
