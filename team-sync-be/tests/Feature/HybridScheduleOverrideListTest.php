<?php

namespace Tests\Feature;

use App\Models\HybridScheduleOverride;
use App\Models\HybridWorkSchedule;
use App\Models\StaffMemberProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HybridScheduleOverrideListTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $employee;

    private StaffMemberProfile $employeeProfile;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'attendance-menu', 'guard_name' => 'sanctum']);
        $role = Role::firstOrCreate(['name' => 'HR Admin', 'guard_name' => 'sanctum']);
        $role->givePermissionTo('attendance-menu');
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'sanctum']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('HR Admin');

        $this->employee = User::factory()->create();
        $this->employeeProfile = StaffMemberProfile::factory()->create(['user_id' => $this->employee->id]);
    }

    public function test_admin_can_list_all_overrides_paginated(): void
    {
        Sanctum::actingAs($this->admin);

        HybridScheduleOverride::factory()->count(3)->create([
            'staff_member_id' => $this->employeeProfile->id,
        ]);

        $response = $this->getJson('/api/v1/hybrid-schedule-overrides');

        $response->assertSuccessful()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => ['id', 'staff_member_id', 'date', 'planned_work_mode', 'status'],
                    ],
                    'meta' => ['current_page', 'last_page', 'per_page', 'total'],
                ],
            ])
            ->assertJsonPath('data.meta.total', 3);
    }

    public function test_admin_can_filter_overrides_by_status(): void
    {
        Sanctum::actingAs($this->admin);

        HybridScheduleOverride::factory()->create([
            'staff_member_id' => $this->employeeProfile->id,
            'status'          => 'pending',
        ]);
        HybridScheduleOverride::factory()->create([
            'staff_member_id' => $this->employeeProfile->id,
            'status'          => 'approved',
        ]);

        $response = $this->getJson('/api/v1/hybrid-schedule-overrides?status=pending');

        $response->assertSuccessful()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.data.0.status', 'pending');
    }

    public function test_admin_can_filter_overrides_by_date_range(): void
    {
        Sanctum::actingAs($this->admin);

        HybridScheduleOverride::factory()->create([
            'staff_member_id' => $this->employeeProfile->id,
            'date'            => '2026-05-01',
        ]);
        HybridScheduleOverride::factory()->create([
            'staff_member_id' => $this->employeeProfile->id,
            'date'            => '2026-06-15',
        ]);

        $response = $this->getJson('/api/v1/hybrid-schedule-overrides?date_from=2026-05-01&date_to=2026-05-31');

        $response->assertSuccessful()
            ->assertJsonPath('data.meta.total', 1)
            ->assertJsonPath('data.data.0.date', '2026-05-01');
    }

    public function test_admin_can_search_overrides_by_employee_name(): void
    {
        Sanctum::actingAs($this->admin);

        $otherEmployee = User::factory()->create(['name' => 'Unique Name XYZ']);
        $otherProfile  = StaffMemberProfile::factory()->create(['user_id' => $otherEmployee->id]);

        HybridScheduleOverride::factory()->create(['staff_member_id' => $this->employeeProfile->id]);
        HybridScheduleOverride::factory()->create(['staff_member_id' => $otherProfile->id]);

        $response = $this->getJson('/api/v1/hybrid-schedule-overrides?search=Unique+Name+XYZ');

        $response->assertSuccessful()
            ->assertJsonPath('data.meta.total', 1);
    }

    public function test_non_admin_cannot_list_overrides(): void
    {
        Sanctum::actingAs($this->employee);

        $this->getJson('/api/v1/hybrid-schedule-overrides')
            ->assertForbidden();
    }

    public function test_invalid_status_returns_validation_error(): void
    {
        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/hybrid-schedule-overrides?status=invalid_status')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_invalid_date_format_returns_validation_error(): void
    {
        Sanctum::actingAs($this->admin);

        $this->getJson('/api/v1/hybrid-schedule-overrides?date_from=31-05-2026')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date_from']);
    }

    public function test_current_schedule_mode_is_included_in_response(): void
    {
        Sanctum::actingAs($this->admin);

        HybridWorkSchedule::factory()->create([
            'staff_member_id' => $this->employeeProfile->id,
            'effective_from'  => '2026-01-01',
            'effective_until' => null,
            'monday'          => 'office',
        ]);

        HybridScheduleOverride::factory()->create([
            'staff_member_id'   => $this->employeeProfile->id,
            'date'              => '2026-05-25', // Monday
            'planned_work_mode' => 'remote',
            'status'            => 'pending',
        ]);

        $response = $this->getJson('/api/v1/hybrid-schedule-overrides');

        $response->assertSuccessful()
            ->assertJsonPath('data.data.0.current_schedule_mode', 'office')
            ->assertJsonPath('data.data.0.planned_work_mode', 'remote');
    }
}
