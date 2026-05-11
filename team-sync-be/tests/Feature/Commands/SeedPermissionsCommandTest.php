<?php

namespace Tests\Feature\Commands;

use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeedPermissionsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    public function test_command_seeds_permissions_successfully(): void
    {
        $this->artisan('seed:permissions')
            ->assertSuccessful()
            ->expectsOutputToContain('Permission seeding completed successfully!');
    }

    public function test_command_calls_permission_and_role_permission_seeders(): void
    {
        $this->artisan('seed:permissions')
            ->assertSuccessful()
            ->expectsOutputToContain('Seeding permissions...')
            ->expectsOutputToContain('Seeding role permissions...');
    }
}
