<?php

namespace Tests\Feature\Commands;

use App\Models\Company;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActivatesLicense;
use Tests\TestCase;

class AppDoctorExtendedTest extends TestCase
{
    use ActivatesLicense, RefreshDatabase;

    private function seedCompany(): void
    {
        Company::query()->create([
            'name' => 'PT Team Sync Nusantara',
            'slug' => 'team-sync',
            'domain' => 'teamsync.local',
            'timezone' => 'Asia/Jakarta',
            'locale' => 'id',
            'currency' => 'IDR',
            'is_active' => true,
            'settings' => [],
        ]);
    }

    public function test_queue_sync_driver_produces_warning(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seedCompany();
        $this->activateTestLicense();

        // sync is already the default in phpunit.xml, but set explicitly for clarity
        config()->set('queue.default', 'sync');

        $this->artisan('app:doctor')
            ->expectsOutputToContain('[WARN] Queue configuration: Queue uses sync driver. Background notifications will run inline.')
            ->assertExitCode(0);
    }

    public function test_mail_array_driver_produces_warning(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seedCompany();
        $this->activateTestLicense();

        // array is already the default in phpunit.xml, but set explicitly for clarity
        config()->set('mail.default', 'array');

        $this->artisan('app:doctor')
            ->expectsOutputToContain("[WARN] Mail configuration: Mail driver 'array' is configured. Real email delivery is disabled.")
            ->assertExitCode(0);
    }

    public function test_mail_log_driver_produces_warning(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seedCompany();
        $this->activateTestLicense();

        config()->set('mail.default', 'log');

        $this->artisan('app:doctor')
            ->expectsOutputToContain("[WARN] Mail configuration: Mail driver 'log' is configured. Real email delivery is disabled.")
            ->assertExitCode(0);
    }

    public function test_warnings_do_not_cause_failure_when_company_and_license_exist(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seedCompany();
        $this->activateTestLicense();

        // Ensure warning-producing drivers are active
        config()->set('queue.default', 'sync');
        config()->set('mail.default', 'array');

        $this->artisan('app:doctor')
            ->expectsOutputToContain('[WARN] Queue configuration')
            ->expectsOutputToContain('[WARN] Mail configuration')
            ->expectsOutputToContain('Application doctor completed without blocking issues.')
            ->assertExitCode(0);
    }

    public function test_multiple_failures_counted_when_company_and_license_missing(): void
    {
        $this->seed(RoleSeeder::class);

        // No company seeded, no license activated
        $this->artisan('app:doctor')
            ->expectsOutputToContain('[FAIL] Company seeded')
            ->expectsOutputToContain('[FAIL] License configured')
            ->expectsOutputToContain('Application doctor found 2 blocking issue(s).')
            ->assertExitCode(1);
    }
}
