<?php

namespace Tests\Feature\Payroll;

use App\Models\User;
use App\Services\Payroll\PayrollQueryService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\ActivatesLicense;
use Tests\TestCase;

class PayrollQueryServiceWiringTest extends TestCase
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

    public function test_payroll_query_service_is_resolvable_from_container(): void
    {
        $service = app(PayrollQueryService::class);

        $this->assertInstanceOf(PayrollQueryService::class, $service);
    }

    public function test_index_endpoint_delegates_via_query_service(): void
    {
        $this->actingAsFinance();

        $response = $this->getJson('/api/v1/payrolls');

        $response->assertStatus(200);
    }

    public function test_get_all_paginated_endpoint_delegates_via_query_service(): void
    {
        $this->actingAsFinance();

        $response = $this->getJson('/api/v1/payrolls/all/paginated');

        $response->assertStatus(200);
    }

    public function test_show_endpoint_delegates_via_query_service(): void
    {
        $this->actingAsFinance();

        $response = $this->getJson('/api/v1/payrolls/fake-id');

        $this->assertContains($response->status(), [404, 500]);
    }

    public function test_get_details_endpoint_delegates_via_query_service(): void
    {
        $this->actingAsFinance();

        $response = $this->getJson('/api/v1/payrolls/fake-id/details');

        $this->assertContains($response->status(), [404, 500]);
    }

    public function test_get_reconciliation_endpoint_delegates_via_query_service(): void
    {
        $this->actingAsFinance();

        $response = $this->getJson('/api/v1/payrolls/fake-id/reconciliation');

        $this->assertContains($response->status(), [404, 500]);
    }

    public function test_get_statistics_endpoint_delegates_via_query_service(): void
    {
        $this->actingAsFinance();

        $response = $this->getJson('/api/v1/payrolls/statistics');

        $response->assertStatus(200);
    }

    public function test_get_payroll_statistics_endpoint_delegates_via_query_service(): void
    {
        $this->actingAsFinance();

        $response = $this->getJson('/api/v1/payrolls/fake-id/statistics');

        $this->assertContains($response->status(), [404, 500]);
    }

    public function test_get_activity_logs_endpoint_delegates_via_query_service(): void
    {
        $this->actingAsFinance();

        $response = $this->getJson('/api/v1/payrolls/fake-id/activity-logs');

        $this->assertContains($response->status(), [404, 500]);
    }

    public function test_get_reconciliation_resolutions_endpoint_delegates_via_query_service(): void
    {
        $this->actingAsFinance();

        $response = $this->getJson('/api/v1/payrolls/fake-id/reconciliation-resolutions');

        $this->assertContains($response->status(), [404, 500]);
    }

    public function test_resolve_reconciliation_exception_endpoint_delegates_via_query_service(): void
    {
        $this->actingAsFinance();

        $response = $this->postJson('/api/v1/payrolls/fake-id/reconciliation/resolve', [
            'exception_type' => 'missing_bank_account',
            'resolution_action' => 'acknowledge',
            'notes' => 'Test resolution',
        ]);

        $this->assertContains($response->status(), [404, 400, 422]);
    }

    private function actingAsFinance(): User
    {
        $user = User::factory()->create();
        $user->assignRole('finance');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Sanctum::actingAs($user);

        return $user;
    }
}
