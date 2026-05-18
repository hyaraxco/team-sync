<?php

namespace Tests\Feature\Payroll;

use App\Models\Payroll;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\ActivatesLicense;
use Tests\TestCase;

class PayrollComparisonTest extends TestCase
{
    use ActivatesLicense, RefreshDatabase;

    private User $finance;

    protected function setUp(): void
    {
        parent::setUp();
        $this->activateTestLicense();

        Permission::firstOrCreate(['name' => 'payroll-statistics', 'guard_name' => 'sanctum']);
        $role = Role::firstOrCreate(['name' => 'Finance', 'guard_name' => 'sanctum']);
        $role->givePermissionTo('payroll-statistics');

        $this->finance = User::factory()->create();
        $this->finance->assignRole('Finance');
    }

    public function test_comparison_returns_valid_structure_for_two_months(): void
    {
        Sanctum::actingAs($this->finance);

        Payroll::factory()->create(['salary_month' => '2026-03-01', 'status' => 'paid']);
        Payroll::factory()->create(['salary_month' => '2026-04-01', 'status' => 'paid']);

        $this->getJson('/api/v1/payrolls/compare?month1=2026-03&month2=2026-04')
            ->assertSuccessful()
            ->assertJsonStructure(['data']);
    }

    public function test_comparison_requires_month1(): void
    {
        Sanctum::actingAs($this->finance);

        $this->getJson('/api/v1/payrolls/compare?month2=2026-04')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['month1']);
    }

    public function test_comparison_requires_month2(): void
    {
        Sanctum::actingAs($this->finance);

        $this->getJson('/api/v1/payrolls/compare?month1=2026-03')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['month2']);
    }

    public function test_comparison_rejects_invalid_month_format(): void
    {
        Sanctum::actingAs($this->finance);

        $this->getJson('/api/v1/payrolls/compare?month1=April-2026&month2=2026-04')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['month1']);
    }

    public function test_comparison_handles_months_with_no_data_gracefully(): void
    {
        Sanctum::actingAs($this->finance);

        $this->getJson('/api/v1/payrolls/compare?month1=2099-01&month2=2099-02')
            ->assertSuccessful();
    }
}
