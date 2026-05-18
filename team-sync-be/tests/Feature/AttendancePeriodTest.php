<?php

namespace Tests\Feature;

use App\Models\AttendancePeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AttendancePeriodTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure permission exists
        Permission::firstOrCreate(['name' => 'attendance-menu', 'guard_name' => 'sanctum']);
        $role = Role::firstOrCreate(['name' => 'HR Admin', 'guard_name' => 'sanctum']);
        $role->givePermissionTo('attendance-menu');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('HR Admin');
    }

    public function test_can_fetch_all_attendance_periods()
    {
        Sanctum::actingAs($this->admin);

        AttendancePeriod::factory()->create([
            'start_date' => '2026-04-26',
            'end_date' => '2026-05-25',
            'cutoff_date' => '2026-05-20',
            'status' => AttendancePeriod::STATUS_REVIEW,
        ]);

        $response = $this->getJson('/api/v1/attendance-periods');

        $response->assertSuccessful()
            ->assertJsonStructure([
                'data' => [
                    'data' => [
                        '*' => ['id', 'start_date', 'end_date', 'cutoff_date', 'status', 'locked_at'],
                    ],
                ],
            ])
            ->assertJsonPath('data.data.0.start_date', '2026-04-26')
            ->assertJsonPath('data.data.0.end_date', '2026-05-25')
            ->assertJsonPath('data.data.0.cutoff_date', '2026-05-20');
    }

    public function test_can_generate_new_attendance_period()
    {
        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/v1/attendance-periods', [
            'start_date' => '2026-04-26',
            'end_date' => '2026-05-25',
            'cutoff_date' => '2026-05-25',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'open')
            ->assertJsonPath('data.start_date', '2026-04-26')
            ->assertJsonPath('data.end_date', '2026-05-25')
            ->assertJsonPath('data.cutoff_date', '2026-05-25');

        $this->assertDatabaseHas('attendance_periods', [
            'start_date' => '2026-04-26 00:00:00',
            'end_date' => '2026-05-25 00:00:00',
            'status' => 'open',
        ]);
    }
}
