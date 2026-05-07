<?php

namespace Tests\Feature;

use App\Models\HolidayCalendar;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HolidayCalendarTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure permission exists
        Permission::firstOrCreate(['name' => 'attendance-menu', 'guard_name' => 'sanctum']);
        $role = Role::firstOrCreate(['name' => 'HR Admin', 'guard_name' => 'sanctum']);
        $role->givePermissionTo('attendance-menu');

        $this->admin = User::factory()->create();
        $this->admin->assignRole('HR Admin');

        $this->user = User::factory()->create();
    }

    public function test_requires_authentication_to_access_holidays()
    {
        $response = $this->getJson('/api/v1/holiday-calendars');
        $response->assertUnauthorized();
    }

    public function test_allows_user_with_permission_to_view_holidays()
    {
        Sanctum::actingAs($this->admin);

        HolidayCalendar::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/holiday-calendars');

        $response->assertSuccessful()
            ->assertJsonStructure([
                'data' => [
                    'data' => [
                        '*' => ['id', 'date', 'name', 'type'],
                    ],
                ],
            ]);
    }

    public function test_forbids_normal_user_from_managing_holidays()
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/v1/holiday-calendars', [
            'date' => '2026-01-01',
            'name' => 'Tahun Baru',
            'type' => 'national_holiday',
        ]);

        $response->assertForbidden();
    }

    public function test_allows_admin_to_create_a_holiday()
    {
        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/v1/holiday-calendars', [
            'date' => '2026-01-01',
            'name' => 'Tahun Baru',
            'type' => 'national_holiday',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Tahun Baru');

        $this->assertDatabaseHas('holiday_calendars', [
            'date' => '2026-01-01 00:00:00',
            'name' => 'Tahun Baru',
            'type' => 'national_holiday',
        ]);
    }

    public function test_validates_holiday_creation_data()
    {
        Sanctum::actingAs($this->admin);

        $response = $this->postJson('/api/v1/holiday-calendars', []);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['date', 'name', 'type']);
    }

    public function test_allows_admin_to_update_a_holiday()
    {
        Sanctum::actingAs($this->admin);

        $holiday = HolidayCalendar::factory()->create([
            'name' => 'Old Name',
        ]);

        $response = $this->putJson("/api/v1/holiday-calendars/{$holiday->id}", [
            'date' => '2026-01-02',
            'name' => 'New Name',
            'type' => 'collective_leave',
        ]);

        $response->assertSuccessful()
            ->assertJsonPath('data.name', 'New Name');

        $this->assertDatabaseHas('holiday_calendars', [
            'id' => $holiday->id,
            'name' => 'New Name',
        ]);
    }

    public function test_allows_admin_to_delete_a_holiday()
    {
        Sanctum::actingAs($this->admin);

        $holiday = HolidayCalendar::factory()->create();

        $response = $this->deleteJson("/api/v1/holiday-calendars/{$holiday->id}");

        $response->assertSuccessful();

        $this->assertDatabaseMissing('holiday_calendars', [
            'id' => $holiday->id,
        ]);
    }
}
