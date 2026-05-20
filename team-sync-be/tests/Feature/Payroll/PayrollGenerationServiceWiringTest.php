<?php

namespace Tests\Feature\Payroll;

use App\Models\User;
use App\Services\Payroll\PayrollGenerationService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\ActivatesLicense;
use Tests\TestCase;

class PayrollGenerationServiceWiringTest extends TestCase
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

    public function test_payroll_generation_service_is_resolvable_from_container(): void
    {
        $service = app(PayrollGenerationService::class);

        $this->assertInstanceOf(PayrollGenerationService::class, $service);
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
