<?php

namespace Tests\Feature\Payroll;

use App\Models\AttendancePeriod;
use App\Models\PayrollAdjustment;
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

class PayrollAdjustmentControllerTest extends TestCase
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

    public function test_unauthenticated_user_cannot_access_adjustments(): void
    {
        $this->getJson('/api/v1/payroll-adjustments')
            ->assertUnauthorized();
    }

    public function test_user_without_permission_cannot_access_adjustments(): void
    {
        $this->actingAsRole('staff');

        $this->getJson('/api/v1/payroll-adjustments')
            ->assertForbidden();
    }

    public function test_authorized_user_can_list_adjustments(): void
    {
        $this->actingAsRole('hr');

        $employee = StaffMemberProfile::factory()->create();
        
        $sourcePeriod = AttendancePeriod::create([
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'cutoff_date' => '2026-04-25',
            'status' => AttendancePeriod::STATUS_LOCKED,
            'locked_at' => now(),
        ]);

        PayrollAdjustment::create([
            'staff_member_id' => $employee->id,
            'source_period_id' => $sourcePeriod->id,
            'target_period_id' => $sourcePeriod->id,
            'source_reference_type' => 'manual',
            'source_reference_id' => 1,
            'adjustment_kind' => PayrollAdjustment::KIND_ABSENCE_CORRECTION_DEDUCTION,
            'amount_delta' => -50000,
            'reason' => 'Test deduction',
            'status' => PayrollAdjustment::STATUS_PENDING,
        ]);

        $this->withoutExceptionHandling();

        $response = $this->getJson('/api/v1/payroll-adjustments')
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'data' => [
                    'data' => [
                        '*' => [
                            'id',
                            'staff_member',
                            'source_period',
                            'adjustment_kind',
                            'amount_delta',
                            'status',
                        ]
                    ]
                ]
            ]);

        $this->assertCount(1, $response->json('data.data'));
    }

    public function test_authorized_user_can_approve_pending_adjustment(): void
    {
        $user = $this->actingAsRole('hr');

        $employee = StaffMemberProfile::factory()->create();
        $sourcePeriod = AttendancePeriod::create([
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'cutoff_date' => '2026-04-25',
            'status' => AttendancePeriod::STATUS_LOCKED,
            'locked_at' => now(),
        ]);

        $adjustment = PayrollAdjustment::create([
            'staff_member_id' => $employee->id,
            'source_period_id' => $sourcePeriod->id,
            'target_period_id' => $sourcePeriod->id,
            'source_reference_type' => 'manual',
            'source_reference_id' => 1,
            'adjustment_kind' => PayrollAdjustment::KIND_ABSENCE_CORRECTION_DEDUCTION,
            'amount_delta' => -50000,
            'reason' => 'Test deduction',
            'status' => PayrollAdjustment::STATUS_PENDING,
        ]);

        $this->withoutExceptionHandling();

        $this->postJson("/api/v1/payroll-adjustments/{$adjustment->id}/approve", [
            'notes' => 'Approved by HR',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', PayrollAdjustment::STATUS_APPROVED);

        $this->assertDatabaseHas('payroll_adjustments', [
            'id' => $adjustment->id,
            'status' => PayrollAdjustment::STATUS_APPROVED,
        ]);
    }

    public function test_cannot_approve_already_approved_adjustment(): void
    {
        $this->actingAsRole('hr');

        $employee = StaffMemberProfile::factory()->create();
        $sourcePeriod = AttendancePeriod::create([
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'cutoff_date' => '2026-04-25',
            'status' => AttendancePeriod::STATUS_LOCKED,
            'locked_at' => now(),
        ]);

        $adjustment = PayrollAdjustment::create([
            'staff_member_id' => $employee->id,
            'source_period_id' => $sourcePeriod->id,
            'target_period_id' => $sourcePeriod->id,
            'source_reference_type' => 'manual',
            'source_reference_id' => 1,
            'adjustment_kind' => PayrollAdjustment::KIND_ABSENCE_CORRECTION_DEDUCTION,
            'amount_delta' => -50000,
            'reason' => 'Test deduction',
            'status' => PayrollAdjustment::STATUS_APPROVED, // Already approved
        ]);

        $this->postJson("/api/v1/payroll-adjustments/{$adjustment->id}/approve")
            ->assertBadRequest()
            ->assertJsonPath('message', 'Only pending adjustments can be approved');
    }

    private function actingAsRole(string $roleName): User
    {
        $user = User::factory()->create();
        $role = Role::findByName($roleName, 'sanctum');
        $user->assignRole($role);

        Sanctum::actingAs($user);

        return $user;
    }
}
