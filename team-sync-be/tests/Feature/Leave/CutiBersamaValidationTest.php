<?php

namespace Tests\Feature\Leave;

use App\Models\HolidayCalendar;
use App\Models\StaffMemberProfile;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\ActivatesLicense;
use Tests\TestCase;

class CutiBersamaValidationTest extends TestCase
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

    public function test_leave_request_on_cuti_bersama_day_shows_warning(): void
    {
        $user = User::factory()->create();
        $user->assignRole('staff');

        $profile = StaffMemberProfile::factory()->create([
            'user_id' => $user->id,
        ]);

        // Create cuti bersama day
        HolidayCalendar::create([
            'date' => now()->addDays(5)->format('Y-m-d'),
            'name' => 'Cuti Bersama Test',
            'type' => 'collective_leave',
            'applies_to' => ['full_time', 'contract', 'intern', 'part_time'],
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/leave-requests', [
            'staff_member_id' => $profile->id,
            'leave_type' => 'annual_leave',
            'start_date' => now()->addDays(5)->format('Y-m-d'),
            'end_date' => now()->addDays(5)->format('Y-m-d'),
            'reason' => 'Test leave request',
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('start_date');
        $this->assertStringContainsString('collective leave', $response->json('errors.start_date.0'));
    }

    public function test_leave_request_not_on_cuti_bersama_day_passes_validation(): void
    {
        $user = User::factory()->create();
        $user->assignRole('staff');

        $profile = StaffMemberProfile::factory()->create([
            'user_id' => $user->id,
        ]);

        // Create cuti bersama day (different date)
        HolidayCalendar::create([
            'date' => now()->addDays(10)->format('Y-m-d'),
            'name' => 'Cuti Bersama Test',
            'type' => 'collective_leave',
            'applies_to' => ['full_time', 'contract', 'intern', 'part_time'],
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/leave-requests', [
            'staff_member_id' => $profile->id,
            'leave_type' => 'annual_leave',
            'start_date' => now()->addDays(5)->format('Y-m-d'),
            'end_date' => now()->addDays(5)->format('Y-m-d'),
            'reason' => 'Test leave request',
        ]);

        // Should pass validation (may fail for other reasons like entitlement)
        $this->assertNotEquals(422, $response->status());
    }

    public function test_national_holiday_does_not_trigger_cuti_bersama_warning(): void
    {
        $user = User::factory()->create();
        $user->assignRole('staff');

        $profile = StaffMemberProfile::factory()->create([
            'user_id' => $user->id,
        ]);

        HolidayCalendar::create([
            'date' => now()->addDays(5)->format('Y-m-d'),
            'name' => 'National Holiday Test',
            'type' => 'national_holiday',
            'applies_to' => ['full_time', 'contract', 'intern', 'part_time'],
        ]);

        Sanctum::actingAs($user);

        $response = $this->postJson('/api/v1/leave-requests', [
            'staff_member_id' => $profile->id,
            'leave_type' => 'annual_leave',
            'start_date' => now()->addDays(5)->format('Y-m-d'),
            'end_date' => now()->addDays(5)->format('Y-m-d'),
            'reason' => 'Test leave request',
        ]);

        if ($response->status() === 422) {
            $errors = $response->json('errors.start_date', []);
            foreach ($errors as $error) {
                $this->assertStringNotContainsString('collective leave', $error);
            }
        } else {
            // Request passed validation — national holiday did not trigger cuti bersama rejection
            $this->assertNotEquals(422, $response->status());
        }
    }

    public function test_upcoming_cuti_bersama_endpoint_returns_only_collective_leave(): void
    {
        $user = User::factory()->create();
        $user->assignRole('staff');

        StaffMemberProfile::factory()->create([
            'user_id' => $user->id,
        ]);

        // Create mix of holidays
        HolidayCalendar::create([
            'date' => now()->addDays(5)->format('Y-m-d'),
            'name' => 'Cuti Bersama 1',
            'type' => 'collective_leave',
            'applies_to' => ['full_time', 'contract', 'intern', 'part_time'],
        ]);

        HolidayCalendar::create([
            'date' => now()->addDays(10)->format('Y-m-d'),
            'name' => 'National Holiday',
            'type' => 'national_holiday',
            'applies_to' => ['full_time', 'contract', 'intern', 'part_time'],
        ]);

        HolidayCalendar::create([
            'date' => now()->addDays(15)->format('Y-m-d'),
            'name' => 'Cuti Bersama 2',
            'type' => 'collective_leave',
            'applies_to' => ['full_time', 'contract', 'intern', 'part_time'],
        ]);

        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/my-upcoming-cuti-bersama');

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertCount(2, $data);
        foreach ($data as $holiday) {
            $this->assertEquals('collective_leave', $holiday['type']);
        }
    }
}
