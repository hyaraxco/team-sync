<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendancePeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AttendancePeriodUpdateTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(['name' => 'attendance-menu', 'guard_name' => 'sanctum']);
        $role = Role::firstOrCreate(['name' => 'HR Admin', 'guard_name' => 'sanctum']);
        $role->givePermissionTo('attendance-menu');

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->admin = User::factory()->create();
        $this->admin->assignRole('HR Admin');
    }

    public function test_unauthenticated_user_cannot_update_period(): void
    {
        $period = AttendancePeriod::factory()->create(['status' => AttendancePeriod::STATUS_OPEN]);

        $this->putJson("/api/v1/attendance-periods/{$period->id}", [
            'status' => AttendancePeriod::STATUS_REVIEW,
        ])->assertUnauthorized();
    }

    public function test_can_transition_from_open_to_review(): void
    {
        Sanctum::actingAs($this->admin);

        $period = AttendancePeriod::factory()->create([
            'status' => AttendancePeriod::STATUS_OPEN,
        ]);

        $this->withoutExceptionHandling();

        $response = $this->putJson("/api/v1/attendance-periods/{$period->id}", [
            'status' => AttendancePeriod::STATUS_REVIEW,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', AttendancePeriod::STATUS_REVIEW);

        $this->assertDatabaseHas('attendance_periods', [
            'id' => $period->id,
            'status' => AttendancePeriod::STATUS_REVIEW,
        ]);
    }

    public function test_can_transition_from_review_to_locked(): void
    {
        Sanctum::actingAs($this->admin);

        $period = AttendancePeriod::factory()->create([
            'status' => AttendancePeriod::STATUS_REVIEW,
        ]);

        $this->withoutExceptionHandling();

        $response = $this->putJson("/api/v1/attendance-periods/{$period->id}", [
            'status' => AttendancePeriod::STATUS_LOCKED,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', AttendancePeriod::STATUS_LOCKED);

        $period->refresh();
        $this->assertNotNull($period->locked_at);
    }

    public function test_cannot_skip_review_and_go_directly_to_locked(): void
    {
        Sanctum::actingAs($this->admin);

        $period = AttendancePeriod::factory()->create([
            'status' => AttendancePeriod::STATUS_OPEN,
        ]);

        $response = $this->putJson("/api/v1/attendance-periods/{$period->id}", [
            'status' => AttendancePeriod::STATUS_LOCKED,
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Must move to review before locking.');

        $this->assertDatabaseHas('attendance_periods', [
            'id' => $period->id,
            'status' => AttendancePeriod::STATUS_OPEN,
        ]);
    }

    public function test_cannot_change_status_of_locked_period(): void
    {
        Sanctum::actingAs($this->admin);

        $period = AttendancePeriod::factory()->create([
            'status' => AttendancePeriod::STATUS_LOCKED,
            'locked_at' => now(),
        ]);

        $response = $this->putJson("/api/v1/attendance-periods/{$period->id}", [
            'status' => AttendancePeriod::STATUS_OPEN,
        ]);

        $response->assertUnprocessable()
            ->assertJsonPath('message', 'Cannot change status of a locked period.');

        $this->assertDatabaseHas('attendance_periods', [
            'id' => $period->id,
            'status' => AttendancePeriod::STATUS_LOCKED,
        ]);
    }

    public function test_locked_at_is_set_when_transitioning_to_locked(): void
    {
        Sanctum::actingAs($this->admin);

        $period = AttendancePeriod::factory()->create([
            'status' => AttendancePeriod::STATUS_REVIEW,
        ]);

        $this->assertNull($period->locked_at);

        $this->withoutExceptionHandling();

        $this->putJson("/api/v1/attendance-periods/{$period->id}", [
            'status' => AttendancePeriod::STATUS_LOCKED,
        ])->assertOk();

        $period->refresh();
        $this->assertNotNull($period->locked_at);
        $this->assertEqualsWithDelta(now()->timestamp, $period->locked_at->timestamp, 5);
    }

    public function test_update_validates_status_field(): void
    {
        Sanctum::actingAs($this->admin);

        $period = AttendancePeriod::factory()->create([
            'status' => AttendancePeriod::STATUS_OPEN,
        ]);

        $this->putJson("/api/v1/attendance-periods/{$period->id}", [
            'status' => 'invalid_status',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    public function test_update_requires_status_field(): void
    {
        Sanctum::actingAs($this->admin);

        $period = AttendancePeriod::factory()->create([
            'status' => AttendancePeriod::STATUS_OPEN,
        ]);

        $this->putJson("/api/v1/attendance-periods/{$period->id}", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }
}
