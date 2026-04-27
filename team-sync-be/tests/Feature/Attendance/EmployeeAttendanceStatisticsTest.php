<?php

namespace Tests\Feature\Attendance;

use App\Models\Attendance;
use App\Models\AttendancePeriod;
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

class EmployeeAttendanceStatisticsTest extends TestCase
{
    use RefreshDatabase;

    private AttendancePeriod $period;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->period = AttendancePeriod::factory()->create(['status' => 'open']);
    }

    public function test_unauthenticated_user_cannot_access_employee_statistics(): void
    {
        $this->getJson('/api/v1/attendances/employee/1/statistics')
            ->assertUnauthorized();
    }

    public function test_user_without_permission_cannot_access_employee_statistics(): void
    {
        $this->actingAsRole('staff');

        $employee = StaffMemberProfile::factory()->create();

        $this->getJson("/api/v1/attendances/employee/{$employee->id}/statistics")
            ->assertForbidden();
    }

    public function test_authorized_user_can_get_employee_statistics(): void
    {
        $this->actingAsRole('hr');

        $employee = StaffMemberProfile::factory()->create();

        // Create some attendance records for this month
        $baseDate = now()->startOfMonth();
        for ($i = 0; $i < 5; $i++) {
            $date = $baseDate->copy()->addDays($i);
            if ($date->isWeekday()) {
                Attendance::create([
                    'staff_member_id' => $employee->id,
                    'attendance_period_id' => $this->period->id,
                    'date' => $date,
                    'check_in' => $date->copy()->setTime(8, 0),
                    'check_out' => $date->copy()->setTime(17, 0),
                    'status' => 'present',
                ]);
            }
        }

        Attendance::create([
            'staff_member_id' => $employee->id,
            'attendance_period_id' => $this->period->id,
            'date' => $baseDate->copy()->addDays(16),
            'check_in' => $baseDate->copy()->addDays(16)->setTime(8, 0),
            'status' => 'absent',
        ]);

        $this->withoutExceptionHandling();

        $response = $this->getJson("/api/v1/attendances/employee/{$employee->id}/statistics")
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'total_days',
                    'present_days',
                    'late_days',
                    'half_day_count',
                    'sick_days',
                    'absent_days',
                    'avg_hours',
                ]
            ]);

        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(0, $data['total_days']);
        $this->assertGreaterThanOrEqual(0, $data['present_days']);
        $this->assertGreaterThanOrEqual(0, $data['absent_days']);
    }

    public function test_statistics_returns_correct_counts(): void
    {
        $this->actingAsRole('hr');

        $employee = StaffMemberProfile::factory()->create();

        $baseDate = now()->startOfMonth();

        // 3 present
        for ($i = 0; $i < 3; $i++) {
            Attendance::create([
                'staff_member_id' => $employee->id,
                'attendance_period_id' => $this->period->id,
                'date' => $baseDate->copy()->addDays($i),
                'check_in' => $baseDate->copy()->addDays($i)->setTime(8, 0),
                'check_out' => $baseDate->copy()->addDays($i)->setTime(17, 0),
                'status' => 'present',
            ]);
        }

        // 1 late
        Attendance::create([
            'staff_member_id' => $employee->id,
            'attendance_period_id' => $this->period->id,
            'date' => $baseDate->copy()->addDays(6),
            'check_in' => $baseDate->copy()->addDays(6)->setTime(10, 0),
            'check_out' => $baseDate->copy()->addDays(6)->setTime(17, 0),
            'status' => 'late',
        ]);

        // 1 sick
        Attendance::create([
            'staff_member_id' => $employee->id,
            'attendance_period_id' => $this->period->id,
            'date' => $baseDate->copy()->addDays(7),
            'check_in' => $baseDate->copy()->addDays(7)->setTime(8, 0),
            'status' => 'sick',
        ]);

        $this->withoutExceptionHandling();

        $response = $this->getJson("/api/v1/attendances/employee/{$employee->id}/statistics")
            ->assertOk();

        $data = $response->json('data');
        // present_days includes present + late + half_day
        $this->assertGreaterThanOrEqual(3, $data['present_days']);
        $this->assertEquals(1, $data['sick_days']);
        $this->assertEquals(1, $data['late_days']);
    }

    public function test_can_filter_statistics_by_month(): void
    {
        $this->actingAsRole('hr');

        $employee = StaffMemberProfile::factory()->create();

        // Create attendance in March 2026
        for ($i = 0; $i < 3; $i++) {
            Attendance::create([
                'staff_member_id' => $employee->id,
                'attendance_period_id' => $this->period->id,
                'date' => "2026-03-" . str_pad($i + 10, 2, '0', STR_PAD_LEFT),
                'check_in' => "2026-03-" . str_pad($i + 10, 2, '0', STR_PAD_LEFT) . " 08:00:00",
                'check_out' => "2026-03-" . str_pad($i + 10, 2, '0', STR_PAD_LEFT) . " 17:00:00",
                'status' => 'present',
            ]);
        }

        $this->withoutExceptionHandling();

        $response = $this->getJson("/api/v1/attendances/employee/{$employee->id}/statistics?month=2026-03")
            ->assertOk();

        $data = $response->json('data');
        $this->assertEquals(3, $data['present_days']);
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
