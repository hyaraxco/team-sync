<?php

namespace App\Console\Commands;

use App\Models\Payroll;
use App\Models\PayrollDetail;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use RuntimeException;
use Spatie\Permission\PermissionRegistrar;

class PreparePayrollQaCommand extends Command
{
    protected $signature = 'qa:payroll-ready {--fresh : Run migrate:fresh before seeding}';

    protected $description = 'Prepare payroll QA data and run payroll readiness checks for HR, Finance, Employee, and Manager roles';

    public function handle(): int
    {
        $originalMailer = config('mail.default');
        $originalQueue = config('queue.default');

        config([
            'mail.default' => 'array',
            'queue.default' => 'sync',
        ]);

        if ($this->option('fresh')) {
            $this->warn('Running migrate:fresh...');
            $this->call('migrate:fresh');
        }

        $this->info('Seeding payroll QA ready dataset...');
        $this->call('db:seed', ['--class' => 'MinimalPayrollE2EReadySeeder']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->newLine();
        $this->info('Running payroll readiness checks...');

        try {
            $this->assertCredentialValid('HR login', 'tasyia@teamsync.com', 'teamsync');
            $this->assertCredentialValid('Finance login', 'dwimeta@teamsync.com', 'teamsync');
            $this->assertCredentialValid('Employee login', 'agung@teamsync.com', 'teamsync');
            $this->assertCredentialValid('Manager login', 'yudhis@teamsync.com', 'teamsync');

            $this->assertRouteExists('Login endpoint', 'POST', 'api/v1/login');
            $this->assertRouteExists('Payroll list endpoint', 'GET', 'api/v1/payrolls/all/paginated');
            $this->assertRouteExists('Payroll generate endpoint', 'POST', 'api/v1/payrolls/generate');
            $this->assertRouteExists('Payroll statistics endpoint', 'GET', 'api/v1/payrolls/{id}/statistics');
            $this->assertRouteExists('Payroll mark-as-paid endpoint', 'POST', 'api/v1/payrolls/{id}/mark-as-paid');
            $this->assertRouteExists('Employee payslip endpoint', 'GET', 'api/v1/my-payslips');

            $this->assertPermissionMatrix();
            $this->assertEmployeePayslipReadiness();
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            config([
                'mail.default' => $originalMailer,
                'queue.default' => $originalQueue,
            ]);

            return self::FAILURE;
        }

        config([
            'mail.default' => $originalMailer,
            'queue.default' => $originalQueue,
        ]);

        $this->newLine();
        $this->info('QA payroll dataset is ready.');
        $this->line('Accounts:');
        $this->line('- HR: tasyia@teamsync.com / teamsync');
        $this->line('- Manager: yudhis@teamsync.com / teamsync');
        $this->line('- Employee: agung@teamsync.com / teamsync');
        $this->line('- Finance: dwimeta@teamsync.com / teamsync');

        return self::SUCCESS;
    }

    private function assertCredentialValid(string $label, string $email, string $password): void
    {
        $isValid = Auth::guard('web')->validate([
            'email' => $email,
            'password' => $password,
        ]);

        if (! $isValid) {
            throw new RuntimeException(sprintf('%s failed. Invalid credentials.', $label));
        }

        $this->line(sprintf('- %s: OK', $label));
    }

    private function assertRouteExists(string $label, string $method, string $uri): void
    {
        $route = collect(app('router')->getRoutes()->getRoutes())->first(
            fn ($candidate) => $candidate->uri() === $uri
                && in_array(strtoupper($method), $candidate->methods(), true)
        );

        if (! $route) {
            throw new RuntimeException(sprintf('%s failed. Route %s %s is not registered.', $label, strtoupper($method), $uri));
        }

        $this->line(sprintf('- %s: OK (%s %s)', $label, strtoupper($method), $uri));
    }

    private function assertPermissionMatrix(): void
    {
        $hr = User::query()->where('email', 'tasyia@teamsync.com')->firstOrFail();
        $finance = User::query()->where('email', 'dwimeta@teamsync.com')->firstOrFail();
        $manager = User::query()->where('email', 'yudhis@teamsync.com')->firstOrFail();
        $employee = User::query()->where('email', 'agung@teamsync.com')->firstOrFail();

        if (! $hr->hasPermissionTo('payroll-create', 'sanctum') || $hr->hasPermissionTo('payroll-process', 'sanctum')) {
            throw new RuntimeException('Permission matrix check failed for HR.');
        }

        if ($finance->hasPermissionTo('payroll-create', 'sanctum')
            || ! $finance->hasPermissionTo('payroll-process', 'sanctum')
            || ! $finance->hasPermissionTo('payroll-statistics', 'sanctum')) {
            throw new RuntimeException('Permission matrix check failed for Finance.');
        }

        if ($manager->hasPermissionTo('payroll-list', 'sanctum') || $manager->hasPermissionTo('payroll-menu', 'sanctum')) {
            throw new RuntimeException('Permission matrix check failed for Manager.');
        }

        if ($employee->hasPermissionTo('payroll-list', 'sanctum') || ! $employee->hasPermissionTo('payslip-view', 'sanctum')) {
            throw new RuntimeException('Permission matrix check failed for Employee.');
        }

        $this->line('- Permission matrix: OK');
    }

    private function assertEmployeePayslipReadiness(): void
    {
        $employee = User::query()->where('email', 'agung@teamsync.com')->firstOrFail();

        $hasPaidPayslip = PayrollDetail::query()
            ->where('employee_id', $employee->employeeProfile?->id)
            ->whereHas('payroll', function ($query) {
                $query->where('status', 'paid');
            })
            ->exists();

        if (! $hasPaidPayslip) {
            throw new RuntimeException('Employee payslip readiness check failed. No paid payroll detail found.');
        }

        $payroll = Payroll::query()
            ->whereDate('salary_month', now()->startOfMonth()->toDateString())
            ->first();

        if (! $payroll || $payroll->status !== 'paid') {
            throw new RuntimeException('Payroll readiness check failed. Current month payroll is not paid.');
        }

        $this->line('- Employee payroll readiness: OK (permission + paid payslip)');
    }
}
