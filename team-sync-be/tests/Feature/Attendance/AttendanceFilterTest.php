<?php

namespace Tests\Feature\Attendance;

use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\AttendancePolicyMismatch;
use App\Models\HybridScheduleOverride;
use App\Models\HybridWorkSchedule;
use App\Models\JobInformation;
use App\Models\StaffMemberProfile;
use App\Models\Team;
use App\Models\TeamMember;
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

        Attendance::factory()->create(['staff_member_id' => $staff->id, 'date' => '2026-11-02', 'status' => 'present']);
        Attendance::factory()->create(['staff_member_id' => $staff->id, 'date' => '2026-11-03', 'status' => 'late']);
        Attendance::factory()->create(['staff_member_id' => $staff->id, 'date' => '2026-11-04', 'status' => 'absent']);

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

        foreach (['2026-11-02', '2026-11-03', '2026-11-04'] as $date) {
            Attendance::factory()->create(['staff_member_id' => $staff->id, 'date' => $date]);
        }

        $response = $this->getJson('/api/v1/attendances/all/paginated?row_per_page=15');

        $response->assertOk();
        $data = $response->json('data.data');
        $this->assertCount(3, $data);
    }

    public function test_manager_employee_lists_only_include_current_reports_from_led_teams(): void
    {
        $manager = User::factory()->create();
        $manager->assignRole('manager');
        $ledTeam = Team::factory()->create(['team_lead_id' => $manager->id]);
        $otherTeam = Team::factory()->create();
        $deletedTeam = Team::factory()->create(['team_lead_id' => $manager->id]);
        $employees = StaffMemberProfile::factory()->count(5)->create();

        TeamMember::factory()->create(['team_id' => $ledTeam->id, 'staff_member_id' => $employees[0]->id]);
        JobInformation::factory()->create(['team_id' => $ledTeam->id, 'staff_member_id' => $employees[1]->id]);
        TeamMember::factory()->create(['team_id' => $otherTeam->id, 'staff_member_id' => $employees[2]->id]);
        TeamMember::factory()->create(['team_id' => $ledTeam->id, 'staff_member_id' => $employees[3]->id, 'left_at' => now()]);
        TeamMember::factory()->create(['team_id' => $deletedTeam->id, 'staff_member_id' => $employees[4]->id]);
        $deletedTeam->delete();

        // Being a member of another leader's team must not grant access to its employees.
        $managerProfile = StaffMemberProfile::factory()->create(['user_id' => $manager->id]);
        TeamMember::factory()->create(['team_id' => $otherTeam->id, 'staff_member_id' => $managerProfile->id]);

        foreach ($employees as $employee) {
            $attendance = Attendance::factory()->create(['staff_member_id' => $employee->id]);
            AttendanceCorrection::factory()->create([
                'attendance_id' => $attendance->id,
                'staff_member_id' => $employee->id,
                'status' => 'pending',
            ]);
            AttendancePolicyMismatch::factory()->create([
                'attendance_id' => $attendance->id,
                'staff_member_id' => $employee->id,
                'status' => AttendancePolicyMismatch::STATUS_PENDING_REVIEW,
            ]);
            HybridWorkSchedule::factory()->create(['staff_member_id' => $employee->id]);
            HybridScheduleOverride::factory()->create(['staff_member_id' => $employee->id]);
        }

        $endpoints = [
            'attendances/all/paginated?row_per_page=100',
            'attendance-corrections/all/paginated?row_per_page=100&status=pending',
            'attendance-policy-mismatches?per_page=100&status=pending_review',
            'hybrid-schedules?per_page=100',
            'hybrid-schedule-overrides?per_page=100',
        ];

        Sanctum::actingAs($manager);
        $expected = [$employees[0]->id, $employees[1]->id];
        foreach ($endpoints as $endpoint) {
            $rows = $this->getJson('/api/v1/'.$endpoint)->assertOk()->json('data.data');
            $this->assertEqualsCanonicalizing($expected, array_column($rows, 'staff_member_id'), $endpoint);
        }
        $rows = $this->getJson('/api/v1/attendances')->assertOk()->json('data');
        $this->assertEqualsCanonicalizing($expected, array_column($rows, 'staff_member_id'));
        foreach ($endpoints as $endpoint) {
            if (! str_starts_with($endpoint, 'attendance-policy-mismatches')) {
                $this->getJson('/api/v1/'.$endpoint.'&search='.urlencode($employees[2]->user->name))
                    ->assertOk()->assertJsonCount(0, 'data.data');
                $this->getJson('/api/v1/'.$endpoint.'&search='.urlencode($employees[0]->user->name))
                    ->assertOk()->assertJsonCount(1, 'data.data')
                    ->assertJsonPath('data.data.0.staff_member_id', $employees[0]->id);
            }
        }

        $outsideAttendance = Attendance::where('staff_member_id', $employees[2]->id)->firstOrFail();
        $outsideCorrection = AttendanceCorrection::where('staff_member_id', $employees[2]->id)->firstOrFail();
        $this->getJson('/api/v1/attendances/'.$outsideAttendance->id)->assertNotFound();
        $this->getJson('/api/v1/attendance-corrections/'.$outsideCorrection->id)->assertForbidden();
        $this->getJson('/api/v1/attendances/'.Attendance::where('staff_member_id', $employees[0]->id)->value('id'))->assertOk();
        $this->getJson('/api/v1/attendance-corrections/'.AttendanceCorrection::where('staff_member_id', $employees[0]->id)->value('id'))->assertOk();

        $ledTeam->update(['team_lead_id' => null]);
        foreach ($endpoints as $endpoint) {
            $this->getJson('/api/v1/'.$endpoint)->assertOk()->assertJsonCount(0, 'data.data');
        }

        foreach (['hr', 'superadmin'] as $role) {
            $manager->syncRoles(['manager', $role]);
            Sanctum::actingAs($manager);
            foreach ($endpoints as $endpoint) {
                $rows = $this->getJson('/api/v1/'.$endpoint)->assertOk()->json('data.data');
                $this->assertEqualsCanonicalizing($employees->pluck('id')->all(), array_column($rows, 'staff_member_id'), $role.': '.$endpoint);
            }
        }
    }

    public function test_validates_status_value(): void
    {
        $this->actingAsHr();

        $response = $this->getJson('/api/v1/attendances/all/paginated?row_per_page=15&status=invalid_status');

        $response->assertUnprocessable();
    }
}
