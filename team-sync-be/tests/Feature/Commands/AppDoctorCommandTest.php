<?php

namespace Tests\Feature\Commands;

use App\Models\Company;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActivatesLicense;
use Tests\TestCase;

class AppDoctorCommandTest extends TestCase
{
    use ActivatesLicense, RefreshDatabase;

    public function test_app_doctor_fails_when_company_and_license_are_missing(): void
    {
        $this->seed(RoleSeeder::class);

        $this->artisan('app:doctor')
            ->expectsOutputToContain('[FAIL] Company seeded: No company record found.')
            ->expectsOutputToContain('[FAIL] License configured: No active valid license found.')
            ->expectsOutputToContain('Application doctor found 2 blocking issue(s).')
            ->assertExitCode(1);
    }

    public function test_app_doctor_passes_with_company_and_active_license(): void
    {
        $this->seed(RoleSeeder::class);

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

        $this->activateTestLicense();

        $this->artisan('app:doctor')
            ->expectsOutputToContain('[PASS] Database connection')
            ->expectsOutputToContain('[PASS] Company seeded')
            ->expectsOutputToContain('[PASS] License configured')
            ->expectsOutputToContain('Application doctor completed without blocking issues.')
            ->assertExitCode(0);
    }
}
