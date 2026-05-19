<?php

namespace Tests\Feature\Payroll;

use App\Models\User;
use App\Services\Payroll\PayrollAnalyticsService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\ActivatesLicense;
use Tests\TestCase;

class PayrollAnalyticsServiceWiringTest extends TestCase
{
    use ActivatesLicense;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->activateTestLicense();
        $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_payroll_analytics_service_is_resolvable_from_container(): void
    {
        $service = app(PayrollAnalyticsService::class);

        $this->assertInstanceOf(PayrollAnalyticsService::class, $service);
    }

    public function test_analytics_endpoint_returns_200_via_service(): void
    {
        $finance = User::factory()->create();
        $finance->assignRole('finance');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Sanctum::actingAs($finance);

        $this->getJson('/api/v1/payrolls/analytics')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'message', 'data' => ['periods_requested', 'trends']]);
    }

    public function test_comparison_endpoint_returns_200_via_service(): void
    {
        $finance = User::factory()->create();
        $finance->assignRole('finance');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Sanctum::actingAs($finance);

        $this->getJson('/api/v1/payrolls/compare?month1=2026-01&month2=2026-02')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'message', 'data' => ['month1', 'month2', 'variances']]);
    }
}
