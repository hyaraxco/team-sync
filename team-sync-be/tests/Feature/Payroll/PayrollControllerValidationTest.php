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

class PayrollControllerValidationTest extends TestCase
{
    use ActivatesLicense, RefreshDatabase;

    private User $finance;

    protected function setUp(): void
    {
        parent::setUp();
        $this->activateTestLicense();

        Permission::firstOrCreate(['name' => 'payroll-list', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'payroll-create', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'payroll-edit', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'payroll-process', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'payroll-statistics', 'guard_name' => 'sanctum']);
        Permission::firstOrCreate(['name' => 'payroll-readiness-view', 'guard_name' => 'sanctum']);

        $role = Role::firstOrCreate(['name' => 'Finance', 'guard_name' => 'sanctum']);
        $role->givePermissionTo([
            'payroll-list', 'payroll-create', 'payroll-edit',
            'payroll-process', 'payroll-statistics', 'payroll-readiness-view',
        ]);

        $this->finance = User::factory()->create();
        $this->finance->assignRole('Finance');
    }

    public function test_get_all_paginated_rejects_non_integer_row_per_page(): void
    {
        Sanctum::actingAs($this->finance);

        $this->getJson('/api/v1/payrolls/all/paginated?row_per_page=abc')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['row_per_page']);
    }

    public function test_get_details_rejects_per_page_below_10(): void
    {
        Sanctum::actingAs($this->finance);

        $payrollId = 1; // Validation runs before route lookup; 404 only after pass
        $this->getJson("/api/v1/payrolls/{$payrollId}/details?per_page=5")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_get_details_rejects_per_page_above_100(): void
    {
        Sanctum::actingAs($this->finance);

        $this->getJson('/api/v1/payrolls/1/details?per_page=200')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['per_page']);
    }

    public function test_get_reconciliation_rejects_invalid_severity(): void
    {
        Sanctum::actingAs($this->finance);

        $this->getJson('/api/v1/payrolls/1/reconciliation?severity=high')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['severity']);
    }

    public function test_get_reconciliation_rejects_type_exceeding_100_chars(): void
    {
        Sanctum::actingAs($this->finance);

        $longType = str_repeat('a', 101);
        $this->getJson("/api/v1/payrolls/1/reconciliation?type={$longType}")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    public function test_readiness_dashboard_requires_salary_month(): void
    {
        Sanctum::actingAs($this->finance);

        $this->getJson('/api/v1/payrolls/readiness-dashboard')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['salary_month']);
    }

    public function test_readiness_dashboard_rejects_invalid_format(): void
    {
        Sanctum::actingAs($this->finance);

        $this->getJson('/api/v1/payrolls/readiness-dashboard?salary_month=2026/04')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['salary_month']);
    }

    public function test_readiness_team_summary_requires_salary_month(): void
    {
        Sanctum::actingAs($this->finance);

        $this->getJson('/api/v1/payrolls/readiness-dashboard/team-summary')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['salary_month']);
    }

    public function test_generate_requires_salary_month(): void
    {
        \Queue::fake();
        Sanctum::actingAs($this->finance);

        $this->postJson('/api/v1/payrolls/generate', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['salary_month']);

        \Queue::assertNothingPushed();
    }

    public function test_generate_rejects_invalid_date_format(): void
    {
        \Queue::fake();
        Sanctum::actingAs($this->finance);

        $this->postJson('/api/v1/payrolls/generate', ['salary_month' => '04-2026'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['salary_month']);

        \Queue::assertNothingPushed();
    }

    public function test_resolve_reconciliation_exception_requires_resolution_action(): void
    {
        Sanctum::actingAs($this->finance);

        $this->postJson('/api/v1/payrolls/1/reconciliation/resolve', [
            'staff_member_id' => 1,
            'exception_type' => 'missing_bank',
            'reason' => 'verified manually',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['resolution_action']);
    }

    public function test_resolve_reconciliation_exception_rejects_invalid_action(): void
    {
        Sanctum::actingAs($this->finance);

        $this->postJson('/api/v1/payrolls/1/reconciliation/resolve', [
            'staff_member_id' => 1,
            'exception_type' => 'missing_bank',
            'resolution_action' => 'ignore',
            'reason' => 'verified manually',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['resolution_action']);
    }

    public function test_generate_readiness_requires_salary_month(): void
    {
        Sanctum::actingAs($this->finance);

        $this->getJson('/api/v1/payrolls/generate-readiness')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['salary_month']);
    }

    public function test_generate_readiness_rejects_invalid_format(): void
    {
        Sanctum::actingAs($this->finance);

        $this->getJson('/api/v1/payrolls/generate-readiness?salary_month=2026-1')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['salary_month']);
    }

    public function test_generate_readiness_rejects_future_month(): void
    {
        Sanctum::actingAs($this->finance);

        $futureMonth = now()->addMonths(2)->format('Y-m');
        $this->getJson("/api/v1/payrolls/generate-readiness?salary_month={$futureMonth}")
            ->assertStatus(422)
            ->assertJsonValidationErrors(['salary_month']);
    }

    public function test_generate_readiness_accepts_current_month(): void
    {
        Sanctum::actingAs($this->finance);

        $currentMonth = now()->format('Y-m');
        // Validation must pass (closure uses `>`, not `>=`).
        // Endpoint may still 422 on domain logic — assert validation field NOT in errors.
        $response = $this->getJson("/api/v1/payrolls/generate-readiness?salary_month={$currentMonth}");

        // Validation passed if we didn't get a validation error for salary_month
        $response->assertJsonMissingValidationErrors(['salary_month']);
    }

    public function test_mark_as_paid_requires_payment_date(): void
    {
        Sanctum::actingAs($this->finance);

        // Need an approved payroll for state to matter
        $payroll = Payroll::factory()->create(['status' => 'approved']);

        $this->postJson("/api/v1/payrolls/{$payroll->id}/mark-as-paid", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['payment_date']);

        $this->assertDatabaseHas('payrolls', [
            'id' => $payroll->id,
            'status' => 'approved',
        ]);
    }

    public function test_mark_as_paid_rejects_invalid_date(): void
    {
        Sanctum::actingAs($this->finance);

        $payroll = Payroll::factory()->create(['status' => 'approved']);

        $this->postJson("/api/v1/payrolls/{$payroll->id}/mark-as-paid", [
            'payment_date' => 'not-a-date',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['payment_date']);
    }

    public function test_mark_as_paid_rejects_future_payment_date(): void
    {
        Sanctum::actingAs($this->finance);

        $payroll = Payroll::factory()->create(['status' => 'approved']);

        $futureDate = now()->addDays(7)->format('Y-m-d');
        $this->postJson("/api/v1/payrolls/{$payroll->id}/mark-as-paid", [
            'payment_date' => $futureDate,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['payment_date']);

        $this->assertDatabaseHas('payrolls', [
            'id' => $payroll->id,
            'status' => 'approved',
        ]);
    }

    public function test_get_analytics_rejects_months_below_1(): void
    {
        Sanctum::actingAs($this->finance);

        $this->getJson('/api/v1/payrolls/analytics?months=0')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['months']);
    }

    public function test_get_analytics_rejects_months_above_24(): void
    {
        Sanctum::actingAs($this->finance);

        $this->getJson('/api/v1/payrolls/analytics?months=25')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['months']);
    }

    public function test_get_analytics_rejects_non_integer_months(): void
    {
        Sanctum::actingAs($this->finance);

        $this->getJson('/api/v1/payrolls/analytics?months=six')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['months']);
    }

    public function test_get_analytics_rejects_float_months(): void
    {
        Sanctum::actingAs($this->finance);

        $this->getJson('/api/v1/payrolls/analytics?months=3.5')
            ->assertStatus(422)
            ->assertJsonValidationErrors(['months']);
    }

    public function test_update_detail_rejects_negative_final_salary(): void
    {
        Sanctum::actingAs($this->finance);

        $this->putJson('/api/v1/payroll-details/1', [
            'final_salary' => -1,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['final_salary']);
    }

    public function test_update_detail_rejects_float_final_salary(): void
    {
        Sanctum::actingAs($this->finance);

        $this->putJson('/api/v1/payroll-details/1', [
            'final_salary' => 9200000.50,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['final_salary']);
    }

    public function test_update_detail_rejects_non_integer_final_salary(): void
    {
        Sanctum::actingAs($this->finance);

        $this->putJson('/api/v1/payroll-details/1', [
            'final_salary' => '10juta',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['final_salary']);
    }

    public function test_reopen_payroll_requires_reason(): void
    {
        Sanctum::actingAs($this->finance);

        $payroll = Payroll::factory()->create(['status' => 'approved']);

        $this->postJson("/api/v1/payrolls/{$payroll->id}/reopen", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }

    public function test_reopen_payroll_rejects_reason_under_10_chars(): void
    {
        Sanctum::actingAs($this->finance);

        $payroll = Payroll::factory()->create(['status' => 'approved']);

        $this->postJson("/api/v1/payrolls/{$payroll->id}/reopen", [
            'reason' => 'short',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);
    }

    public function test_reopen_payroll_rejects_reason_over_500_chars(): void
    {
        Sanctum::actingAs($this->finance);

        $payroll = Payroll::factory()->create(['status' => 'approved']);

        $this->postJson("/api/v1/payrolls/{$payroll->id}/reopen", [
            'reason' => str_repeat('a', 501),
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reason']);

        $this->assertDatabaseHas('payrolls', [
            'id' => $payroll->id,
            'status' => 'approved',
        ]);
    }
}
