<?php

namespace Tests\Feature\Commands;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CheckPermissionSyncCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function test_exits_successfully_when_all_route_permissions_exist_in_database(): void
    {
        // Seed the permissions that ARE in the routes file
        $routePermissions = [
            'meeting-create',
            'attendance-menu',
            'payroll-menu',
            'review-manager-submit',
            'review-cycle-manage',
            'review-calibrate',
            'review-assign-reviewer',
        ];

        foreach ($routePermissions as $permName) {
            Permission::firstOrCreate(['name' => $permName, 'guard_name' => 'sanctum']);
        }

        $this->artisan('permissions:sync-check')
            ->assertExitCode(0)
            ->expectsOutputToContain('All route permissions exist in database');
    }

    public function test_reports_missing_permissions_when_they_do_not_exist_in_database(): void
    {
        // Don't seed any permissions — all route permissions will be "missing"
        $this->artisan('permissions:sync-check')
            ->assertExitCode(0) // without --fail-on-missing flag, still exits 0
            ->expectsOutputToContain('missing permission');
    }

    public function test_exits_with_code_1_when_using_fail_on_missing_flag_and_permissions_are_missing(): void
    {
        // Don't seed any permissions
        $this->artisan('permissions:sync-check --fail-on-missing')
            ->assertExitCode(1);
    }

    public function test_outputs_count_of_permissions_found_in_routes(): void
    {
        Permission::firstOrCreate(['name' => 'meeting-create', 'guard_name' => 'sanctum']);

        $this->artisan('permissions:sync-check')
            ->expectsOutputToContain('Found');
    }
}
