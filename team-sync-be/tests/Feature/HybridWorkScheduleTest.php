<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\HybridWorkSchedule;
use App\Models\HybridScheduleOverride;
use App\Models\StaffMemberProfile;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Laravel\Sanctum\Sanctum;

class HybridWorkScheduleTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $employee;
    private StaffMemberProfile $employeeProfile;
    private StaffMemberProfile $adminProfile;

    protected function setUp(): void
    {
        parent::setUp();

        // Admin setup
        Permission::firstOrCreate(['name' => 'attendance-menu', 'guard_name' => 'sanctum']);
        $role = Role::firstOrCreate(['name' => 'HR Admin', 'guard_name' => 'sanctum']);
        $role->givePermissionTo('attendance-menu');
        Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'sanctum']);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('HR Admin');
        $this->adminProfile = StaffMemberProfile::factory()->create(['user_id' => $this->admin->id]);

        // Employee setup
        $this->employee = User::factory()->create();
        $this->employeeProfile = StaffMemberProfile::factory()->create(['user_id' => $this->employee->id]);
    }

    public function test_can_fetch_all_hybrid_schedules_as_admin()
    {
        Sanctum::actingAs($this->admin);

        HybridWorkSchedule::factory()->count(2)->create();

        $response = $this->getJson('/api/v1/hybrid-schedules');

        $response->assertSuccessful()
            ->assertJsonStructure([
                'data' => [
                    'data' => [
                        '*' => ['id', 'staff_member_id', 'effective_from', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday']
                    ]
                ]
            ]);
    }

    public function test_employee_can_fetch_their_own_schedule()
    {
        Sanctum::actingAs($this->employee);

        $schedule = HybridWorkSchedule::factory()->create([
            'staff_member_id' => $this->employeeProfile->id,
            'effective_from' => '2026-01-01',
        ]);

        $response = $this->getJson('/api/v1/my-hybrid-schedule');

        $response->assertSuccessful()
            ->assertJsonPath('data.id', $schedule->id)
            ->assertJsonPath('data.staff_member_id', $this->employeeProfile->id);
    }

    public function test_can_request_schedule_override()
    {
        Sanctum::actingAs($this->employee);

        $response = $this->postJson('/api/v1/hybrid-schedule-overrides', [
            'date' => '2026-05-01',
            'planned_work_mode' => 'WFH',
            'reason' => 'Need focus time',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'pending');

        $this->assertDatabaseHas('hybrid_schedule_overrides', [
            'staff_member_id' => $this->employeeProfile->id,
            'status' => 'pending',
            'date' => '2026-05-01 00:00:00',
            'planned_work_mode' => 'WFH',
        ]);
    }

    public function test_admin_can_approve_override()
    {
        Sanctum::actingAs($this->admin);

        $override = HybridScheduleOverride::factory()->create([
            'staff_member_id' => $this->employeeProfile->id,
            'status' => 'pending',
        ]);

        $response = $this->postJson("/api/v1/hybrid-schedule-overrides/{$override->id}/approve");

        $response->assertSuccessful()
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('hybrid_schedule_overrides', [
            'id' => $override->id,
            'status' => 'approved',
            'approved_by' => $this->adminProfile->id,
        ]);
    }

    public function test_admin_can_reject_override()
    {
        Sanctum::actingAs($this->admin);

        $override = HybridScheduleOverride::factory()->create([
            'staff_member_id' => $this->employeeProfile->id,
            'status' => 'pending',
        ]);

        $response = $this->postJson("/api/v1/hybrid-schedule-overrides/{$override->id}/reject", [
            'review_notes' => 'Tidak sesuai kuota WFH.',
        ]);

        $response->assertSuccessful()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.review_notes', 'Tidak sesuai kuota WFH.');

        $this->assertDatabaseHas('hybrid_schedule_overrides', [
            'id' => $override->id,
            'status' => 'rejected',
            'review_notes' => 'Tidak sesuai kuota WFH.',
        ]);
    }
}
