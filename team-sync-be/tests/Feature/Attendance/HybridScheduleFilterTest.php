<?php

namespace Tests\Feature\Attendance;

use App\Models\HybridWorkSchedule;
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

class HybridScheduleFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function actingAsHr(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName('superadmin', 'sanctum'));
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_can_search_hybrid_schedules_by_employee_name(): void
    {
        $this->actingAsHr();

        $user1 = User::factory()->create(['name' => 'Alice Johnson']);
        $staff1 = StaffMemberProfile::factory()->create(['user_id' => $user1->id]);
        HybridWorkSchedule::factory()->create(['staff_member_id' => $staff1->id]);

        $user2 = User::factory()->create(['name' => 'Bob Smith']);
        $staff2 = StaffMemberProfile::factory()->create(['user_id' => $user2->id]);
        HybridWorkSchedule::factory()->create(['staff_member_id' => $staff2->id]);

        $response = $this->getJson('/api/v1/hybrid-schedules?per_page=15&search=Alice');

        $response->assertOk();
        $data = $response->json('data.data');
        $this->assertCount(1, $data);
    }

    public function test_returns_all_without_search(): void
    {
        $this->actingAsHr();

        $staff1 = StaffMemberProfile::factory()->create();
        HybridWorkSchedule::factory()->create(['staff_member_id' => $staff1->id]);

        $staff2 = StaffMemberProfile::factory()->create();
        HybridWorkSchedule::factory()->create(['staff_member_id' => $staff2->id]);

        $response = $this->getJson('/api/v1/hybrid-schedules?per_page=15');

        $response->assertOk();
        $data = $response->json('data.data');
        $this->assertCount(2, $data);
    }

    public function test_validates_per_page_is_integer(): void
    {
        $this->actingAsHr();

        $response = $this->getJson('/api/v1/hybrid-schedules?per_page=abc');

        $response->assertUnprocessable();
    }
}
