<?php

namespace Tests\Feature\Commands;

use App\Models\Payroll;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class PreparePayrollQaCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_qa_payroll_ready_command_seeds_and_checks_successfully(): void
    {
        $exitCode = Artisan::call('qa:payroll-ready');

        $this->assertSame(0, $exitCode);

        $this->assertNotNull(User::where('email', 'tasyia@teamsync.com')->first());
        $this->assertNotNull(User::where('email', 'yudhis@teamsync.com')->first());
        $this->assertNotNull(User::where('email', 'agung@teamsync.com')->first());
        $this->assertNotNull(User::where('email', 'dwimeta@teamsync.com')->first());

        $payroll = Payroll::whereDate('salary_month', now()->startOfMonth()->toDateString())->firstOrFail();
        $this->assertSame('paid', $payroll->status);
    }
}
