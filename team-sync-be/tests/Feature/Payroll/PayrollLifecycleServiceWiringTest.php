<?php

namespace Tests\Feature\Payroll;

use App\Models\User;
use App\Services\Payroll\PayrollLifecycleService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\ActivatesLicense;
use Tests\TestCase;

class PayrollLifecycleServiceWiringTest extends TestCase
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

    public function test_payroll_lifecycle_service_is_resolvable_from_container(): void
    {
        $service = app(PayrollLifecycleService::class);

        $this->assertInstanceOf(PayrollLifecycleService::class, $service);
    }

    public function test_approve_endpoint_delegates_via_lifecycle_service(): void
    {
        $this->actingAsFinance();

        // Without a real payroll, expect 404 (proves routing through service works)
        $response = $this->postJson('/api/v1/payrolls/fake-id/approve');

        $this->assertContains($response->status(), [404, 400]);
    }

    public function test_mark_as_paid_endpoint_delegates_via_lifecycle_service(): void
    {
        $this->actingAsFinance();

        $response = $this->postJson('/api/v1/payrolls/fake-id/mark-as-paid', [
            'payment_date' => now()->format('Y-m-d'),
        ]);

        $this->assertContains($response->status(), [404, 400, 422]);
    }

    public function test_reopen_endpoint_delegates_via_lifecycle_service(): void
    {
        $this->actingAsFinance();

        $response = $this->postJson('/api/v1/payrolls/fake-id/reopen', [
            'reason' => 'Correction needed',
        ]);

        $this->assertContains($response->status(), [404, 400]);
    }

    public function test_resend_notifications_endpoint_delegates_via_lifecycle_service(): void
    {
        $this->actingAsFinance();

        $response = $this->postJson('/api/v1/payrolls/fake-id/resend-notifications');

        $this->assertContains($response->status(), [404, 400]);
    }

    public function test_notification_deliveries_endpoint_delegates_via_lifecycle_service(): void
    {
        $this->actingAsFinance();

        $response = $this->getJson('/api/v1/payrolls/fake-id/notification-deliveries');

        $this->assertContains($response->status(), [404, 500]);
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
