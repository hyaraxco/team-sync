<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\ActivatesLicense;
use Tests\TestCase;

class PayrollRouteTest extends TestCase
{
    use ActivatesLicense, RefreshDatabase;

    private User $finance;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->activateTestLicense();

        Permission::firstOrCreate(['name' => 'payroll-create', 'guard_name' => 'sanctum']);

        $role = Role::firstOrCreate(['name' => 'Finance', 'guard_name' => 'sanctum']);
        $role->givePermissionTo('payroll-create');

        $this->finance = User::factory()->create();
        $this->finance->assignRole('Finance');

        $this->staff = User::factory()->create();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_generate_readiness_requires_authentication(): void
    {
        $this->getJson('/api/v1/payrolls/generate-readiness?salary_month=2026-01')
            ->assertStatus(401);
    }

    public function test_generate_readiness_requires_payroll_create_permission(): void
    {
        Sanctum::actingAs($this->staff);

        $this->getJson('/api/v1/payrolls/generate-readiness?salary_month=2026-01')
            ->assertStatus(403);
    }

    public function test_generate_readiness_rejects_invalid_salary_month_format(): void
    {
        Sanctum::actingAs($this->finance);

        $this->getJson('/api/v1/payrolls/generate-readiness?salary_month=2026-0')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['salary_month']);
    }

    public function test_generate_readiness_rejects_missing_salary_month(): void
    {
        Sanctum::actingAs($this->finance);

        $this->getJson('/api/v1/payrolls/generate-readiness')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['salary_month']);
    }

    public function test_generate_readiness_rejects_future_salary_month(): void
    {
        Sanctum::actingAs($this->finance);

        $future = now()->addMonths(2)->format('Y-m');

        $this->getJson("/api/v1/payrolls/generate-readiness?salary_month={$future}")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['salary_month']);
    }

    public function test_generate_readiness_returns_200_for_valid_request(): void
    {
        Sanctum::actingAs($this->finance);

        $month = now()->subMonth()->format('Y-m');

        $this->getJson("/api/v1/payrolls/generate-readiness?salary_month={$month}")
            ->assertStatus(200)
            ->assertJsonStructure(['success', 'message']);
    }
}
