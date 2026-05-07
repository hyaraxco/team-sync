<?php

namespace Tests\Feature\Payroll;

use App\Interfaces\PayrollRepositoryInterface;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\ActivatesLicense;
use Tests\TestCase;

/**
 * Regression tests: Payroll endpoints must not leak raw exception messages on 500.
 */
class ErrorHandlingSafetyTest extends TestCase
{
    use ActivatesLicense, RefreshDatabase;

    private const INTERNAL_SECRET = 'SQLSTATE[HY000]: MySQL server has gone away';

    protected function setUp(): void
    {
        parent::setUp();

        $this->activateTestLicense();

        $this->seed([
            RoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function actingAsFinance(): User
    {
        $user = User::factory()->create();
        $role = Role::findByName('finance', 'sanctum');
        $user->assignRole($role);
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_analytics_endpoint_does_not_leak_throwable_message(): void
    {
        $this->actingAsFinance();

        $mock = $this->mock(PayrollRepositoryInterface::class);
        $mock->shouldReceive('getAnalytics')
            ->once()
            ->andThrow(new \RuntimeException(self::INTERNAL_SECRET));

        Log::shouldReceive('error')
            ->once()
            ->withArgs(fn (string $msg) => str_contains($msg, 'PayrollController::getAnalytics error'));

        $response = $this->getJson('/api/v1/payrolls/analytics?months=6');

        $response->assertStatus(500);
        $response->assertJsonFragment(['message' => 'Internal Server Error']);
        $this->assertStringNotContainsString(self::INTERNAL_SECRET, $response->getContent());
        $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
    }

    public function test_domain_exception_returns_business_message_not_internals(): void
    {
        // generate-readiness requires payroll-create permission → Finance role
        $user = User::factory()->create();
        $role = Role::findByName('finance', 'sanctum');
        $user->assignRole($role);
        Sanctum::actingAs($user);

        $mock = $this->mock(PayrollRepositoryInterface::class);
        $mock->shouldReceive('getGenerateReadiness')
            ->once()
            ->andThrow(new \Exception('Payroll for this month already exists'));

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(fn (string $msg) => str_contains($msg, 'PayrollController domain exception'));

        $response = $this->getJson('/api/v1/payrolls/generate-readiness?salary_month=2026-04');

        // Domain exception → 400 with business message (controlled)
        $response->assertStatus(400);
        $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
        $this->assertStringNotContainsString('Stack trace', $response->getContent());
    }
}
