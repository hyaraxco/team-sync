<?php

namespace Tests\Feature\Overtime;

use App\Models\Attendance;
use App\Models\AttendancePeriod;
use App\Models\OvertimeRecord;
use App\Models\StaffMemberProfile;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\ActivatesLicense;
use Tests\TestCase;

class OvertimeControllerTest extends TestCase
{
    use ActivatesLicense, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,
        ]);

        $this->activateTestLicense();
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
        // Use a user with no overtime-create permission (staff now has it)
        $user = User::factory()->create();
        Sanctum::actingAs($user);

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

    public function test_create_overtime_validates_attendance_belongs_to_same_staff_member_and_date(): void
    {
        $this->actingAsRole('hr');

        $employee = StaffMemberProfile::withoutSyncingToSearch(function () {
            return StaffMemberProfile::factory()->create();
        });
        $otherEmployee = StaffMemberProfile::withoutSyncingToSearch(function () {
            return StaffMemberProfile::factory()->create();
        });
        $attendance = Attendance::factory()->create([
            'staff_member_id' => $otherEmployee->id,
            'date' => now()->subDay()->format('Y-m-d'),
        ]);

        $payload = [
            'staff_member_id' => $employee->id,
            'attendance_id' => $attendance->id,
            'date' => now()->subDay()->format('Y-m-d'),
            'start_time' => '17:00',
            'end_time' => '19:00',
            'overtime_type' => 'workday',
        ];

        $this->postJson('/api/v1/overtime', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['attendance_id']);
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

    public function test_staff_without_overtime_approve_permission_cannot_reject_overtime(): void
    {
        $this->actingAsRole('staff');

        $record = StaffMemberProfile::withoutSyncingToSearch(function () {
            $employee = StaffMemberProfile::factory()->create();

            return OvertimeRecord::factory()->forEmployee($employee)->create([
                'status' => OvertimeRecord::STATUS_PENDING,
            ]);
        });

        $this->postJson("/api/v1/overtime/{$record->id}/reject", [
            'rejection_reason' => 'Valid rejection reason from staff',
        ])->assertForbidden();

        $this->assertDatabaseHas('overtime_records', [
            'id' => $record->id,
            'status' => OvertimeRecord::STATUS_PENDING,
            'rejection_reason' => null,
        ]);
    }

    public function test_finance_without_overtime_approve_permission_cannot_reject_overtime(): void
    {
        $this->actingAsRole('finance');

        $record = StaffMemberProfile::withoutSyncingToSearch(function () {
            $employee = StaffMemberProfile::factory()->create();

            return OvertimeRecord::factory()->forEmployee($employee)->create([
                'status' => OvertimeRecord::STATUS_PENDING,
            ]);
        });

        $this->postJson("/api/v1/overtime/{$record->id}/reject", [
            'rejection_reason' => 'Valid rejection reason from finance',
        ])->assertForbidden();

        $this->assertDatabaseHas('overtime_records', [
            'id' => $record->id,
            'status' => OvertimeRecord::STATUS_PENDING,
            'rejection_reason' => null,
        ]);
    }

    public function test_manager_with_overtime_approve_permission_can_reject_same_team_overtime(): void
    {
        $manager = $this->actingAsRole('manager');
        $team = Team::factory()->active()->create(['team_lead_id' => $manager->id]);

        $record = StaffMemberProfile::withoutSyncingToSearch(function () use ($team) {
            $employee = StaffMemberProfile::factory()->create();
            TeamMember::factory()->forTeam($team)->forEmployee($employee)->create();

            return OvertimeRecord::factory()->forEmployee($employee)->create([
                'status' => OvertimeRecord::STATUS_PENDING,
            ]);
        });

        $this->postJson("/api/v1/overtime/{$record->id}/reject", [
            'rejection_reason' => 'Overtime rejected by manager with valid reason',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'rejected');

        $this->assertDatabaseHas('overtime_records', [
            'id' => $record->id,
            'status' => 'rejected',
            'rejection_reason' => 'Overtime rejected by manager with valid reason',
        ]);
    }

    public function test_manager_list_and_summary_are_scoped_to_managed_teams(): void
    {
        // Two managers and two teams
        $managerA = $this->actingAsRole('manager');
        $teamA = Team::factory()->active()->create(['team_lead_id' => $managerA->id]);

        $managerB = User::factory()->create();
        $managerB->assignRole(Role::findByName('manager', 'sanctum'));
        $teamB = Team::factory()->active()->create(['team_lead_id' => $managerB->id]);

        $employeeA = StaffMemberProfile::withoutSyncingToSearch(fn () => StaffMemberProfile::factory()->create());
        TeamMember::factory()->forTeam($teamA)->forEmployee($employeeA)->create();

        $employeeB = StaffMemberProfile::withoutSyncingToSearch(fn () => StaffMemberProfile::factory()->create());
        TeamMember::factory()->forTeam($teamB)->forEmployee($employeeB)->create();

        $recordA = OvertimeRecord::factory()->forEmployee($employeeA)->create([
            'status' => OvertimeRecord::STATUS_PENDING,
            'date' => now()->toDateString(),
            'hours' => 2.0,
        ]);

        $recordB = OvertimeRecord::factory()->forEmployee($employeeB)->create([
            'status' => OvertimeRecord::STATUS_PENDING,
            'date' => now()->toDateString(),
            'hours' => 3.0,
        ]);

        // Manager A list only includes Record A, excludes Record B
        Sanctum::actingAs($managerA);
        $responseA = $this->getJson('/api/v1/overtime')
            ->assertOk();
        $idsA = collect($responseA->json('data.data'))->pluck('id')->all();
        $this->assertContains($recordA->id, $idsA);
        $this->assertNotContains($recordB->id, $idsA);

        // Manager A querying with employeeB's id gets empty results
        $this->getJson("/api/v1/overtime?staff_member_id={$employeeB->id}")
            ->assertOk()
            ->assertJsonCount(0, 'data.data');

        // Manager A summary only includes Record A
        $summaryA = $this->getJson('/api/v1/overtime/summary')->assertOk()->json('data');
        $this->assertSame(1, $summaryA['total_pending']);

        // Manager B list only includes Record B, excludes Record A
        Sanctum::actingAs($managerB);
        $responseB = $this->getJson('/api/v1/overtime')
            ->assertOk();
        $idsB = collect($responseB->json('data.data'))->pluck('id')->all();
        $this->assertContains($recordB->id, $idsB);
        $this->assertNotContains($recordA->id, $idsB);

        // Manager B summary only includes Record B
        $summaryB = $this->getJson('/api/v1/overtime/summary')->assertOk()->json('data');
        $this->assertSame(1, $summaryB['total_pending']);

        // HR sees both records in list and summary
        $this->actingAsRole('hr');
        $responseHr = $this->getJson('/api/v1/overtime')->assertOk();
        $idsHr = collect($responseHr->json('data.data'))->pluck('id')->all();
        $this->assertContains($recordA->id, $idsHr);
        $this->assertContains($recordB->id, $idsHr);

        $summaryHr = $this->getJson('/api/v1/overtime/summary')->assertOk()->json('data');
        $this->assertGreaterThanOrEqual(2, $summaryHr['total_pending']);
    }

    public function test_manager_cross_team_show_approve_reject_denied_with_403_and_record_unmutated(): void
    {
        $managerA = $this->actingAsRole('manager');
        $teamA = Team::factory()->active()->create(['team_lead_id' => $managerA->id]);

        $managerB = User::factory()->create();
        $managerB->assignRole(Role::findByName('manager', 'sanctum'));
        $teamB = Team::factory()->active()->create(['team_lead_id' => $managerB->id]);

        $employeeA = StaffMemberProfile::withoutSyncingToSearch(fn () => StaffMemberProfile::factory()->create());
        TeamMember::factory()->forTeam($teamA)->forEmployee($employeeA)->create();

        $employeeB = StaffMemberProfile::withoutSyncingToSearch(fn () => StaffMemberProfile::factory()->create());
        TeamMember::factory()->forTeam($teamB)->forEmployee($employeeB)->create();

        $recordA = OvertimeRecord::factory()->forEmployee($employeeA)->create([
            'status' => OvertimeRecord::STATUS_PENDING,
        ]);
        $recordB = OvertimeRecord::factory()->forEmployee($employeeB)->create([
            'status' => OvertimeRecord::STATUS_PENDING,
        ]);

        Sanctum::actingAs($managerA);

        // Direct show cross-team ID returns 403
        $this->getJson("/api/v1/overtime/{$recordB->id}")
            ->assertForbidden();

        // Direct approve cross-team ID returns 403 and leaves status unchanged
        $this->postJson("/api/v1/overtime/{$recordB->id}/approve")
            ->assertForbidden();

        $this->assertDatabaseHas('overtime_records', [
            'id' => $recordB->id,
            'status' => OvertimeRecord::STATUS_PENDING,
            'approved_by' => null,
        ]);

        // Direct reject cross-team ID returns 403 and leaves rejection fields unchanged
        $this->postJson("/api/v1/overtime/{$recordB->id}/reject", [
            'rejection_reason' => 'Cross team rejection attempt with valid length',
        ])->assertForbidden();

        $this->assertDatabaseHas('overtime_records', [
            'id' => $recordB->id,
            'status' => OvertimeRecord::STATUS_PENDING,
            'rejection_reason' => null,
        ]);

        // Same-team show, approve, reject work for Manager A
        $this->getJson("/api/v1/overtime/{$recordA->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $recordA->id);

        $this->postJson("/api/v1/overtime/{$recordA->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->assertDatabaseHas('overtime_records', [
            'id' => $recordA->id,
            'status' => 'approved',
            'approved_by' => $managerA->id,
        ]);

        // Same-team reject also works on another pending record in team A
        $recordA2 = OvertimeRecord::factory()->forEmployee($employeeA)->create([
            'status' => OvertimeRecord::STATUS_PENDING,
        ]);

        $this->postJson("/api/v1/overtime/{$recordA2->id}/reject", [
            'rejection_reason' => 'Legitimate same team rejection by manager',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        $this->assertDatabaseHas('overtime_records', [
            'id' => $recordA2->id,
            'status' => 'rejected',
            'rejection_reason' => 'Legitimate same team rejection by manager',
        ]);
    }

    public function test_superadmin_can_reject_overtime(): void
    {
        $this->actingAsRole('superadmin');

        $record = StaffMemberProfile::withoutSyncingToSearch(function () {
            $employee = StaffMemberProfile::factory()->create();

            return OvertimeRecord::factory()->forEmployee($employee)->create([
                'status' => OvertimeRecord::STATUS_PENDING,
            ]);
        });

        $this->postJson("/api/v1/overtime/{$record->id}/reject", [
            'rejection_reason' => 'Overtime rejected by superadmin with valid reason',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'rejected');

        $this->assertDatabaseHas('overtime_records', [
            'id' => $record->id,
            'status' => 'rejected',
            'rejection_reason' => 'Overtime rejected by superadmin with valid reason',
        ]);
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
    // Negative Path: Business Rule Enforcement
    // ─────────────────────────────────────────────────────────────────────────

    public function test_cannot_create_overtime_exceeding_4_hours_per_day(): void
    {
        $this->actingAsRole('hr');

        $employee = StaffMemberProfile::withoutSyncingToSearch(function () {
            return StaffMemberProfile::factory()->create();
        });

        $payload = [
            'staff_member_id' => $employee->id,
            'date' => now()->subDay()->format('Y-m-d'),
            'start_time' => '17:00',
            'end_time' => '21:30', // 4.5 hours — exceeds max 4
            'overtime_type' => 'workday',
        ];

        $this->postJson('/api/v1/overtime', $payload)
            ->assertStatus(422)
            ->assertJsonFragment(['success' => false]);
    }

    public function test_cannot_create_overtime_for_locked_attendance_period(): void
    {
        $this->actingAsRole('hr');

        $employee = StaffMemberProfile::withoutSyncingToSearch(function () {
            return StaffMemberProfile::factory()->create();
        });

        $lockedDate = now()->subDays(10)->format('Y-m-d');

        AttendancePeriod::factory()->create([
            'start_date' => now()->subDays(15)->format('Y-m-d'),
            'end_date' => now()->subDays(5)->format('Y-m-d'),
            'status' => AttendancePeriod::STATUS_LOCKED,
            'locked_at' => now()->subDays(3),
        ]);

        $payload = [
            'staff_member_id' => $employee->id,
            'date' => $lockedDate,
            'start_time' => '17:00',
            'end_time' => '19:00',
            'overtime_type' => 'workday',
        ];

        $this->postJson('/api/v1/overtime', $payload)
            ->assertStatus(422)
            ->assertJsonFragment(['success' => false]);
    }

    public function test_cannot_create_duplicate_overtime_for_same_date_and_employee(): void
    {
        $this->actingAsRole('hr');

        $date = now()->subDay()->format('Y-m-d');

        $employee = StaffMemberProfile::withoutSyncingToSearch(function () use ($date) {
            $emp = StaffMemberProfile::factory()->create();
            OvertimeRecord::factory()->forEmployee($emp)->create([
                'date' => $date,
            ]);

            return $emp;
        });

        $payload = [
            'staff_member_id' => $employee->id,
            'date' => $date,
            'start_time' => '17:00',
            'end_time' => '19:00',
            'overtime_type' => 'workday',
        ];

        $this->postJson('/api/v1/overtime', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['date']);
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
