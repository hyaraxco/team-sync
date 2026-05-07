<?php

namespace Tests\Feature\StaffMember;

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

class StaffMemberProfileControllerValidationTest extends TestCase
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

    public function test_paginated_list_validates_filters(): void
    {
        $this->actingAsRole('hr');

        $this->getJson('/api/v1/staff-members/all/paginated?row_per_page=0&work_location=spaceship')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['row_per_page', 'work_location']);
    }

    public function test_check_availability_reports_taken_values(): void
    {
        $hr = $this->actingAsRole('hr');

        $existingProfile = StaffMemberProfile::withoutSyncingToSearch(function () {
            return StaffMemberProfile::factory()->create();
        });

        $this->postJson('/api/v1/staff-members/check-availability', [
            'email' => $existingProfile->user->email,
            'identity_number' => $existingProfile->identity_number,
        ])
            ->assertUnprocessable()
            ->assertJsonPath('success', false)
            ->assertJsonValidationErrors(['email', 'identity_number']);
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
