<?php

namespace Tests\Feature\Overtime;

use App\Models\OvertimeRecord;
use App\Models\StaffMemberProfile;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class OvertimeControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Permission Guards
    // ─────────────────────────────────────────────────────────────────────────

    public function test_unauthenticated_user_cannot_access_overtime(): void
    {
        $this->getJson('/api/v1/overtime')
            ->assertUnauthorized();
    }

    public function test_user_without_permission_cannot_list_overtime(): void
    {
        $this->actingAsRole('staff');

        $this->getJson('/api/v1/overtime')
            ->assertForbidden();
    }

    public function test_user_without_permission_cannot_create_overtime(): void
    {
        $this->actingAsRole('staff');

        $this->postJson('/api/v1/overtime', [])
            ->assertForbidden();
    }

    public function test_user_without_permission_cannot_approve_overtime(): void
    {
        $this->actingAsRole('staff');

        $record = OvertimeRecord::factory()->create();

        $this->postJson("/api/v1/overtime/{$record->id}/approve")
            ->assertForbidden();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // CRUD Operations
    // ─────────────────────────────────────────────────────────────────────────

    public function test_authorized_user_can_list_overtime_records(): void
    {
        $this->actingAsRole('hr');

        StaffMemberProfile::withoutSyncingToSearch(function () {
            $employee = StaffMemberProfile::factory()->create();
            OvertimeRecord::factory()->count(3)->forEmployee($employee)->create();
        });

        $response = $this->getJson('/api/v1/overtime')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertCount(3, $response->json('data.data'));
    }

    public function test_authorized_user_can_create_overtime_record(): void
    {
        $this->actingAsRole('hr');

        $employee = StaffMemberProfile::withoutSyncingToSearch(function () {
            return StaffMemberProfile::factory()->create();
        });

        $payload = [
            'staff_member_id' => $employee->id,
            'date' => now()->subDay()->format('Y-m-d'),
            'start_time' => '17:00',
            'end_time' => '19:00',
            'overtime_type' => 'workday',
            'notes' => 'Project deadline',
        ];

        $response = $this->postJson('/api/v1/overtime', $payload)
            ->assertCreated()
            ->assertJsonPath('success', true);

        $this->assertDatabaseHas('overtime_records', [
            'staff_member_id' => $employee->id,
            'overtime_type' => 'workday',
            'status' => 'pending',
            'hours' => 2.00,
        ]);
    }

    public function test_create_overtime_validates_max_hours_per_day(): void
    {
        $this->actingAsRole('hr');

        $employee = StaffMemberProfile::withoutSyncingToSearch(function () {
            return StaffMemberProfile::factory()->create();
        });

        $payload = [
            'staff_member_id' => $employee->id,
            'date' => now()->subDay()->format('Y-m-d'),
            'start_time' => '17:00',
            'end_time' => '22:00', // 5 hours - exceeds max 4
            'overtime_type' => 'workday',
        ];

        $this->postJson('/api/v1/overtime', $payload)
            ->assertStatus(422);
    }

    public function test_authorized_user_can_show_overtime_record(): void
    {
        $this->actingAsRole('hr');

        $record = StaffMemberProfile::withoutSyncingToSearch(function () {
            $employee = StaffMemberProfile::factory()->create();

            return OvertimeRecord::factory()->forEmployee($employee)->create();
        });

        $this->getJson("/api/v1/overtime/{$record->id}")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', $record->id);
    }

    public function test_create_overtime_validates_required_fields(): void
    {
        $this->actingAsRole('hr');

        $this->postJson('/api/v1/overtime', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['staff_member_id', 'date', 'start_time', 'end_time', 'overtime_type']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Approval Flow
    // ─────────────────────────────────────────────────────────────────────────

    public function test_authorized_user_can_approve_pending_overtime(): void
    {
        $this->actingAsRole('hr');

        $record = StaffMemberProfile::withoutSyncingToSearch(function () {
            $employee = StaffMemberProfile::factory()->create();

            return OvertimeRecord::factory()->forEmployee($employee)->create([
                'status' => OvertimeRecord::STATUS_PENDING,
            ]);
        });

        $this->postJson("/api/v1/overtime/{$record->id}/approve")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('overtime_records', [
            'id' => $record->id,
            'status' => 'approved',
        ]);
    }

    public function test_cannot_approve_already_approved_overtime(): void
    {
        $this->actingAsRole('hr');

        $record = StaffMemberProfile::withoutSyncingToSearch(function () {
            $employee = StaffMemberProfile::factory()->create();

            return OvertimeRecord::factory()->forEmployee($employee)->approved()->create();
        });

        $this->postJson("/api/v1/overtime/{$record->id}/approve")
            ->assertBadRequest()
            ->assertJsonPath('message', 'Only pending overtime records can be approved');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Rejection Flow
    // ─────────────────────────────────────────────────────────────────────────

    public function test_authorized_user_can_reject_pending_overtime_with_reason(): void
    {
        $this->actingAsRole('hr');

        $record = StaffMemberProfile::withoutSyncingToSearch(function () {
            $employee = StaffMemberProfile::factory()->create();

            return OvertimeRecord::factory()->forEmployee($employee)->create([
                'status' => OvertimeRecord::STATUS_PENDING,
            ]);
        });

        $this->postJson("/api/v1/overtime/{$record->id}/reject", [
            'rejection_reason' => 'Overtime was not pre-approved by manager',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'rejected');

        $this->assertDatabaseHas('overtime_records', [
            'id' => $record->id,
            'status' => 'rejected',
            'rejection_reason' => 'Overtime was not pre-approved by manager',
        ]);
    }

    public function test_reject_requires_minimum_reason_length(): void
    {
        $this->actingAsRole('hr');

        $record = StaffMemberProfile::withoutSyncingToSearch(function () {
            $employee = StaffMemberProfile::factory()->create();

            return OvertimeRecord::factory()->forEmployee($employee)->create([
                'status' => OvertimeRecord::STATUS_PENDING,
            ]);
        });

        $this->postJson("/api/v1/overtime/{$record->id}/reject", [
            'rejection_reason' => 'short', // Less than 10 chars
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['rejection_reason']);
    }

    public function test_cannot_reject_already_rejected_overtime(): void
    {
        $this->actingAsRole('hr');

        $record = StaffMemberProfile::withoutSyncingToSearch(function () {
            $employee = StaffMemberProfile::factory()->create();

            return OvertimeRecord::factory()->forEmployee($employee)->rejected()->create();
        });

        $this->postJson("/api/v1/overtime/{$record->id}/reject", [
            'rejection_reason' => 'This is a valid rejection reason',
        ])
            ->assertBadRequest();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Filtering
    // ─────────────────────────────────────────────────────────────────────────

    public function test_can_filter_overtime_by_status(): void
    {
        $this->actingAsRole('hr');

        StaffMemberProfile::withoutSyncingToSearch(function () {
            $employee = StaffMemberProfile::factory()->create();
            OvertimeRecord::factory()->forEmployee($employee)->count(2)->create(['status' => 'pending']);
            OvertimeRecord::factory()->forEmployee($employee)->count(1)->approved()->create();
        });

        $response = $this->getJson('/api/v1/overtime?status=pending')
            ->assertOk();

        $this->assertCount(2, $response->json('data.data'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Summary
    // ─────────────────────────────────────────────────────────────────────────

    public function test_can_get_overtime_summary(): void
    {
        $this->actingAsRole('hr');

        StaffMemberProfile::withoutSyncingToSearch(function () {
            $employee = StaffMemberProfile::factory()->create();
            OvertimeRecord::factory()->forEmployee($employee)->count(2)->create([
                'status' => 'pending',
                'date' => now()->format('Y-m-d'),
            ]);
            OvertimeRecord::factory()->forEmployee($employee)->approved()->create([
                'date' => now()->format('Y-m-d'),
                'hours' => 2.5,
            ]);
        });

        $response = $this->getJson('/api/v1/overtime/summary')
            ->assertOk()
            ->assertJsonPath('success', true);

        $data = $response->json('data');
        $this->assertArrayHasKey('total_pending', $data);
        $this->assertArrayHasKey('approved_this_month', $data);
        $this->assertArrayHasKey('total_hours_this_month', $data);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helper
    // ─────────────────────────────────────────────────────────────────────────

    private function actingAsRole(string $roleName): User
    {
        $user = User::factory()->create();
        $role = Role::findByName($roleName, 'sanctum');
        $user->assignRole($role);

        Sanctum::actingAs($user);

        return $user;
    }
}
