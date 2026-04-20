<?php

namespace Tests\Feature\Performance;

use App\Models\PerformanceReviewCycle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ReviewCycleControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    private function seedRolesAndPermissions(): void
    {
        Permission::create(['name' => 'review-cycle-manage', 'guard_name' => 'sanctum']);

        Role::create(['name' => 'HR', 'guard_name' => 'sanctum'])
            ->givePermissionTo(['review-cycle-manage']);

        Role::create(['name' => 'Manager', 'guard_name' => 'sanctum']);
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

    public function test_hr_can_list_review_cycles(): void
    {
        $hr = $this->actingAsRole('HR');

        PerformanceReviewCycle::factory()->count(3)->create([
            'created_by' => $hr->id,
        ]);

        $response = $this->getJson('/api/v1/performance/cycles');

        $response->assertOk()
            ->assertJsonStructure([
                'success',
                'message',
                'data',
            ])
            ->assertJson([
                'success' => true,
            ]);
    }

    public function test_hr_can_create_review_cycle(): void
    {
        $hr = $this->actingAsRole('HR');

        $payload = [
            'name' => '2026 Annual Review',
            'cycle_type' => 'annual',
            'start_date' => '2026-12-01',
            'end_date' => '2026-12-31',
            'review_period_start' => '2026-01-01',
            'review_period_end' => '2026-11-30',
            'status' => 'draft',
            'created_by' => $hr->id,
        ];

        $response = $this->postJson('/api/v1/performance/cycles', $payload);

        $response->assertCreated()
            ->assertJsonFragment([
                'name' => '2026 Annual Review',
                'status' => 'draft',
            ]);

        $this->assertDatabaseHas('performance_review_cycles', [
            'name' => '2026 Annual Review',
            'status' => 'draft',
        ]);
    }

    public function test_create_review_cycle_validates_required_fields(): void
    {
        $this->actingAsRole('HR');

        $response = $this->postJson('/api/v1/performance/cycles', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'cycle_type', 'start_date', 'end_date']);
    }

    public function test_hr_can_view_single_review_cycle(): void
    {
        $hr = $this->actingAsRole('HR');

        $cycle = PerformanceReviewCycle::factory()->create([
            'name' => 'Q1 Review',
            'created_by' => $hr->id,
        ]);

        $response = $this->getJson("/api/v1/performance/cycles/{$cycle->id}");

        $response->assertOk()
            ->assertJsonFragment([
                'id' => $cycle->id,
                'name' => 'Q1 Review',
            ]);
    }

    public function test_hr_can_update_review_cycle(): void
    {
        $hr = $this->actingAsRole('HR');

        $cycle = PerformanceReviewCycle::factory()->create([
            'name' => 'Original Name',
            'status' => 'draft',
            'created_by' => $hr->id,
        ]);

        $payload = [
            'name' => 'Updated Name',
            'status' => 'active',
        ];

        $response = $this->putJson("/api/v1/performance/cycles/{$cycle->id}", $payload);

        $response->assertOk()
            ->assertJsonFragment([
                'name' => 'Updated Name',
                'status' => 'active',
            ]);

        $this->assertDatabaseHas('performance_review_cycles', [
            'id' => $cycle->id,
            'name' => 'Updated Name',
            'status' => 'active',
        ]);
    }

    public function test_hr_can_delete_review_cycle(): void
    {
        $hr = $this->actingAsRole('HR');

        $cycle = PerformanceReviewCycle::factory()->create([
            'created_by' => $hr->id,
        ]);

        $response = $this->deleteJson("/api/v1/performance/cycles/{$cycle->id}");

        $response->assertOk();

        $this->assertDatabaseMissing('performance_review_cycles', [
            'id' => $cycle->id,
        ]);
    }

    public function test_manager_cannot_create_review_cycle(): void
    {
        $manager = $this->actingAsRole('Manager');

        $payload = [
            'name' => 'Unauthorized Cycle',
            'cycle_type' => 'annual',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'review_period_start' => '2026-11-01',
            'review_period_end' => '2026-12-31',
            'status' => 'draft',
            'created_by' => $manager->id,
        ];

        $response = $this->postJson('/api/v1/performance/cycles', $payload);

        $response->assertForbidden();
    }

    public function test_employee_cannot_create_review_cycle(): void
    {
        $employee = $this->actingAsRole('Employee');

        $payload = [
            'name' => 'Unauthorized Cycle',
            'cycle_type' => 'annual',
            'start_date' => '2026-01-01',
            'end_date' => '2026-12-31',
            'review_period_start' => '2026-11-01',
            'review_period_end' => '2026-12-31',
            'status' => 'draft',
            'created_by' => $employee->id,
        ];

        $response = $this->postJson('/api/v1/performance/cycles', $payload);

        $response->assertForbidden();
    }
}
