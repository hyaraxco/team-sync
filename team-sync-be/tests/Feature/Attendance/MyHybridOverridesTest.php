<?php

namespace Tests\Feature\Attendance;

use App\Models\HybridScheduleOverride;
use App\Models\StaffMemberProfile;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MyHybridOverridesTest extends TestCase
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

    public function test_unauthenticated_user_cannot_access_my_hybrid_overrides(): void
    {
        $this->getJson('/api/v1/my-hybrid-overrides')
            ->assertUnauthorized();
    }

    public function test_user_without_profile_gets_404(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/my-hybrid-overrides')
            ->assertNotFound()
            ->assertJsonPath('message', 'Profile not found');
    }

    public function test_employee_can_list_own_overrides(): void
    {
        $user = User::factory()->create();
        $profile = StaffMemberProfile::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        HybridScheduleOverride::factory()->count(3)->create([
            'staff_member_id' => $profile->id,
        ]);

        // Create overrides for another employee (should NOT appear)
        $otherProfile = StaffMemberProfile::factory()->create();
        HybridScheduleOverride::factory()->count(2)->create([
            'staff_member_id' => $otherProfile->id,
        ]);

        $this->withoutExceptionHandling();

        $response = $this->getJson('/api/v1/my-hybrid-overrides')
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertCount(3, $response->json('data.data'));
    }

    public function test_overrides_are_ordered_by_date_desc(): void
    {
        $user = User::factory()->create();
        $profile = StaffMemberProfile::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        HybridScheduleOverride::factory()->create([
            'staff_member_id' => $profile->id,
            'date' => '2026-05-01',
        ]);

        HybridScheduleOverride::factory()->create([
            'staff_member_id' => $profile->id,
            'date' => '2026-05-10',
        ]);

        $this->withoutExceptionHandling();

        $response = $this->getJson('/api/v1/my-hybrid-overrides')
            ->assertOk();

        $dates = collect($response->json('data.data'))->pluck('date')->toArray();
        $this->assertEquals('2026-05-10', substr($dates[0], 0, 10));
        $this->assertEquals('2026-05-01', substr($dates[1], 0, 10));
    }

    public function test_overrides_are_paginated(): void
    {
        $user = User::factory()->create();
        $profile = StaffMemberProfile::factory()->create(['user_id' => $user->id]);
        Sanctum::actingAs($user);

        HybridScheduleOverride::factory()->count(20)->create([
            'staff_member_id' => $profile->id,
        ]);

        $this->withoutExceptionHandling();

        $response = $this->getJson('/api/v1/my-hybrid-overrides?per_page=5')
            ->assertOk();

        $this->assertCount(5, $response->json('data.data'));
    }
}
