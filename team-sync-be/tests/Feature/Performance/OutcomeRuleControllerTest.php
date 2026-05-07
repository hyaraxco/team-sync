<?php

namespace Tests\Feature\Performance;

use App\Models\PerformanceOutcomeRule;
use App\Models\User;
use Database\Seeders\PerformanceOutcomeRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\ActivatesLicense;
use Tests\TestCase;

class OutcomeRuleControllerTest extends TestCase
{
    use ActivatesLicense, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->activateTestLicense();
        $this->seedRolesAndPermissions();
    }

    private function seedRolesAndPermissions(): void
    {
        Permission::create(['name' => 'review-cycle-manage', 'guard_name' => 'sanctum']);

        Role::create(['name' => 'HR', 'guard_name' => 'sanctum'])
            ->givePermissionTo(['review-cycle-manage']);

        Role::create(['name' => 'Employee', 'guard_name' => 'sanctum']);
    }

    private function actingAsRole(string $roleName): User
    {
        $user = User::factory()->create();
        $role = Role::findByName($roleName, 'sanctum');
        $user->assignRole($role);
        Sanctum::actingAs($user);

        return $user;
    }

    private function createRule(array $overrides = []): PerformanceOutcomeRule
    {
        return PerformanceOutcomeRule::create(array_merge([
            'label' => 'Test Rule',
            'min_rating' => 3.50,
            'max_rating' => 4.49,
            'bonus_months' => 2.00,
            'salary_increase_pct' => 7.00,
            'promotion_eligible' => false,
            'pip_required' => false,
        ], $overrides));
    }

    public function test_hr_can_list_outcome_rules(): void
    {
        $this->actingAsRole('HR');
        $this->seed(PerformanceOutcomeRuleSeeder::class);

        $response = $this->getJson('/api/v1/performance/outcome-rules');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data',
            ])
            ->assertJsonCount(5, 'data');
    }

    public function test_hr_can_create_outcome_rule(): void
    {
        $this->actingAsRole('HR');

        $payload = [
            'label' => 'Outstanding',
            'min_rating' => 4.50,
            'max_rating' => 5.00,
            'bonus_months' => 3.00,
            'salary_increase_pct' => 10.00,
            'promotion_eligible' => true,
            'pip_required' => false,
            'description' => 'Top performer',
        ];

        $response = $this->postJson('/api/v1/performance/outcome-rules', $payload);

        $response->assertCreated()
            ->assertJsonFragment(['label' => 'Outstanding']);

        $this->assertDatabaseHas('performance_outcome_rules', [
            'label' => 'Outstanding',
            'bonus_months' => 3.00,
        ]);
    }

    public function test_create_validates_required_fields(): void
    {
        $this->actingAsRole('HR');

        $response = $this->postJson('/api/v1/performance/outcome-rules', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'label', 'min_rating', 'max_rating',
                'bonus_months', 'salary_increase_pct',
                'promotion_eligible', 'pip_required',
            ]);
    }

    public function test_create_validates_rating_range(): void
    {
        $this->actingAsRole('HR');

        $response = $this->postJson('/api/v1/performance/outcome-rules', [
            'label' => 'Bad Range',
            'min_rating' => 4.00,
            'max_rating' => 3.00,
            'bonus_months' => 0,
            'salary_increase_pct' => 0,
            'promotion_eligible' => false,
            'pip_required' => false,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['max_rating']);
    }

    public function test_create_rejects_overlapping_range(): void
    {
        $this->actingAsRole('HR');

        $this->createRule(['label' => 'Existing', 'min_rating' => 3.00, 'max_rating' => 4.00]);

        $response = $this->postJson('/api/v1/performance/outcome-rules', [
            'label' => 'Overlapping',
            'min_rating' => 3.50,
            'max_rating' => 4.50,
            'bonus_months' => 1,
            'salary_increase_pct' => 5,
            'promotion_eligible' => false,
            'pip_required' => false,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['min_rating']);
    }

    public function test_hr_can_update_outcome_rule(): void
    {
        $this->actingAsRole('HR');
        $rule = $this->createRule();

        $response = $this->putJson("/api/v1/performance/outcome-rules/{$rule->id}", [
            'label' => 'Updated Label',
            'bonus_months' => 5.00,
        ]);

        $response->assertOk()
            ->assertJsonFragment(['label' => 'Updated Label']);

        $this->assertEquals(5.00, $rule->fresh()->bonus_months);
    }

    public function test_hr_can_delete_outcome_rule(): void
    {
        $this->actingAsRole('HR');
        $rule = $this->createRule();

        $response = $this->deleteJson("/api/v1/performance/outcome-rules/{$rule->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('performance_outcome_rules', ['id' => $rule->id]);
    }

    public function test_employee_cannot_access_outcome_rules(): void
    {
        $this->actingAsRole('Employee');

        $response = $this->getJson('/api/v1/performance/outcome-rules');

        $response->assertForbidden();
    }

    public function test_unauthenticated_cannot_access_outcome_rules(): void
    {
        $response = $this->getJson('/api/v1/performance/outcome-rules');

        $response->assertUnauthorized();
    }
}
