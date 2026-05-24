<?php

namespace Tests\Feature\Attendance;

use App\Models\Attendance;
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

class AttendanceFilterTest extends TestCase
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

    public function test_can_filter_attendances_by_status(): void
    {
        $this->actingAsHr();
        $staff = StaffMemberProfile::factory()->create();

        Attendance::factory()->create(['staff_member_id' => $staff->id, 'status' => 'present']);
        Attendance::factory()->create(['staff_member_id' => $staff->id, 'status' => 'late']);
        Attendance::factory()->create(['staff_member_id' => $staff->id, 'status' => 'absent']);

        $response = $this->getJson('/api/v1/attendances/all/paginated?row_per_page=15&status=late');

        $response->assertOk();
        $data = $response->json('data.data');
        $this->assertCount(1, $data);
        $this->assertEquals('late', $data[0]['status']);
    }

    public function test_returns_all_when_no_status_filter(): void
    {
        $this->actingAsHr();
        $staff = StaffMemberProfile::factory()->create();

        Attendance::factory()->count(3)->create(['staff_member_id' => $staff->id]);

        $response = $this->getJson('/api/v1/attendances/all/paginated?row_per_page=15');

        $response->assertOk();
        $data = $response->json('data.data');
        $this->assertCount(3, $data);
    }

    public function test_validates_status_value(): void
    {
        $this->actingAsHr();

        $response = $this->getJson('/api/v1/attendances/all/paginated?row_per_page=15&status=invalid_status');

        $response->assertUnprocessable();
    }
}
