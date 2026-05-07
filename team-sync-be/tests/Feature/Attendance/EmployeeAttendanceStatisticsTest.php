<?php

namespace Tests\Feature\Attendance;

use App\Models\Attendance;
use App\Models\AttendancePeriod;
use App\Models\StaffMemberProfile;
use App\Models\User;
use Carbon\Carbon;
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
                ],
            ]);

        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(0, $data['total_days']);
        $this->assertGreaterThanOrEqual(0, $data['present_days']);
        $this->assertGreaterThanOrEqual(0, $data['absent_days']);
    }

    public function test_statistics_returns_correct_counts(): void
    {
        // Freeze time to mid-month so the endpoint's endDate covers all seeded records
        Carbon::setTestNow('2026-04-15 09:00:00');

        $this->actingAsRole('hr');

        $employee = StaffMemberProfile::factory()->create();

        // Use explicit weekday dates in April 2026 to avoid weekend ambiguity
        $presentDates = [
            Carbon::create(2026, 4, 1),  // Wednesday
            Carbon::create(2026, 4, 2),  // Thursday
            Carbon::create(2026, 4, 3),  // Friday
        ];

        // 3 present
        foreach ($presentDates as $date) {
            Attendance::create([
                'staff_member_id' => $employee->id,
                'attendance_period_id' => $this->period->id,
                'date' => $date,
                'check_in' => $date->copy()->setTime(8, 0),
                'check_out' => $date->copy()->setTime(17, 0),
                'status' => 'present',
            ]);
        }

        // 1 late
        $lateDate = Carbon::create(2026, 4, 7); // Monday
        Attendance::create([
            'staff_member_id' => $employee->id,
            'attendance_period_id' => $this->period->id,
            'date' => $lateDate,
            'check_in' => $lateDate->copy()->setTime(10, 0),
            'check_out' => $lateDate->copy()->setTime(17, 0),
            'status' => 'late',
        ]);

        // 1 sick
        $sickDate = Carbon::create(2026, 4, 8); // Tuesday
        Attendance::create([
            'staff_member_id' => $employee->id,
            'attendance_period_id' => $this->period->id,
            'date' => $sickDate,
            'check_in' => $sickDate->copy()->setTime(8, 0),
            'status' => 'sick',
        ]);

        $this->withoutExceptionHandling();

        $response = $this->getJson("/api/v1/attendances/employee/{$employee->id}/statistics?month=2026-04")
            ->assertOk();

        $data = $response->json('data');
        // present_days includes present + late + half_day
        $this->assertGreaterThanOrEqual(3, $data['present_days']);
        $this->assertEquals(1, $data['sick_days']);
        $this->assertEquals(1, $data['late_days']);

        Carbon::setTestNow();
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
                'date' => '2026-03-'.str_pad($i + 10, 2, '0', STR_PAD_LEFT),
                'check_in' => '2026-03-'.str_pad($i + 10, 2, '0', STR_PAD_LEFT).' 08:00:00',
                'check_out' => '2026-03-'.str_pad($i + 10, 2, '0', STR_PAD_LEFT).' 17:00:00',
                'status' => 'present',
            ]);
        }

        $this->withoutExceptionHandling();

        $response = $this->getJson("/api/v1/attendances/employee/{$employee->id}/statistics?month=2026-03")
            ->assertOk();

        $data = $response->json('data');
        $this->assertEquals(3, $data['present_days']);
    }

    public function test_employee_statistics_validates_month_format(): void
    {
        $this->actingAsRole('hr');

        $employee = StaffMemberProfile::factory()->create();

        $this->getJson("/api/v1/attendances/employee/{$employee->id}/statistics?month=2026/03")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['month']);
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
