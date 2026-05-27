<?php

namespace Tests\Feature\Attendance;

use App\Models\LeaveRequest;
use App\Models\StaffMemberProfile;
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

class LeaveRequestFilterTest extends TestCase
{
    use ActivatesLicense, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->activateTestLicense();
    }

    private function actingAsHr(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findByName('superadmin', 'sanctum'));
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_can_filter_leave_requests_by_status(): void
    {
        $this->actingAsHr();
        $staff = StaffMemberProfile::factory()->create();

        LeaveRequest::factory()->create(['staff_member_id' => $staff->id, 'status' => 'pending']);
        LeaveRequest::factory()->create(['staff_member_id' => $staff->id, 'status' => 'approved']);

        $response = $this->getJson('/api/v1/leave-requests/all/paginated?row_per_page=15&status=pending');

        $response->assertOk();
        $data = $response->json('data.data');
        $this->assertCount(1, $data);
        $this->assertEquals('pending', $data[0]['status']);
    }

    public function test_can_filter_leave_requests_by_date_range(): void
    {
        $this->actingAsHr();
        $staff = StaffMemberProfile::factory()->create();

        LeaveRequest::factory()->create([
            'staff_member_id' => $staff->id,
            'start_date' => '2026-03-15',
            'end_date' => '2026-03-17',
        ]);
        LeaveRequest::factory()->create([
            'staff_member_id' => $staff->id,
            'start_date' => '2026-04-10',
            'end_date' => '2026-04-12',
        ]);

        $response = $this->getJson('/api/v1/leave-requests/all/paginated?row_per_page=15&date_from=2026-03-01&date_to=2026-03-31');

        $response->assertOk();
        $data = $response->json('data.data');
        $this->assertCount(1, $data);
    }

    public function test_validates_status_value(): void
    {
        $this->actingAsHr();

        $response = $this->getJson('/api/v1/leave-requests/all/paginated?row_per_page=15&status=invalid');

        $response->assertUnprocessable();
    }

    public function test_validates_date_format(): void
    {
        $this->actingAsHr();

        $response = $this->getJson('/api/v1/leave-requests/all/paginated?row_per_page=15&date_from=not-a-date');

        $response->assertUnprocessable();
    }
}
