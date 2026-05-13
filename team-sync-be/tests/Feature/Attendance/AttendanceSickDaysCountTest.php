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

class AttendanceSickDaysCountTest extends TestCase
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
        Carbon::setTestNow('2026-04-15 09:00:00');

        $this->period = AttendancePeriod::factory()->create(['status' => 'open']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_sick_days_are_counted_correctly_in_employee_statistics(): void
    {
        $this->actingAsRole('hr');

        $employee = StaffMemberProfile::factory()->create();

        // Create 2 sick leave records
        $sickDates = [
            Carbon::create(2026, 4, 1), // Wednesday
            Carbon::create(2026, 4, 2), // Thursday
        ];

        foreach ($sickDates as $date) {
            Attendance::create([
                'staff_member_id' => $employee->id,
                'attendance_period_id' => $this->period->id,
                'date' => $date,
                'check_in' => $date->copy()->setTime(8, 0),
                'status' => 'sick_leave',
            ]);
        }

        // Create 1 present record
        Attendance::create([
            'staff_member_id' => $employee->id,
            'attendance_period_id' => $this->period->id,
            'date' => Carbon::create(2026, 4, 3), // Friday
            'check_in' => Carbon::create(2026, 4, 3)->setTime(8, 0),
            'check_out' => Carbon::create(2026, 4, 3)->setTime(17, 0),
            'status' => 'present',
        ]);

        $this->withoutExceptionHandling();

        $response = $this->getJson("/api/v1/attendances/employee/{$employee->id}/statistics?month=2026-04")
            ->assertOk();

        $data = $response->json('data');
        $this->assertEquals(2, $data['sick_days']);
    }

    public function test_sick_days_are_counted_correctly_in_my_statistics(): void
    {
        $user = User::factory()->create();
        $role = Role::findByName('staff', 'sanctum');
        $user->assignRole($role);

        $employee = StaffMemberProfile::withoutSyncingToSearch(function () use ($user) {
            return StaffMemberProfile::factory()->create(['user_id' => $user->id]);
        });

        Sanctum::actingAs($user);

        $employee->jobInformation()->create([
            'staff_member_id' => $employee->id,
            'job_title' => 'Software Engineer',
            'status' => 'active',
            'employment_type' => 'full_time',
            'work_location' => 'office',
            'start_date' => '2024-01-01',
            'monthly_salary' => 10000000,
        ]);

        Sanctum::actingAs($user);

        // Create 3 sick leave records this month
        $sickDates = [
            Carbon::create(2026, 4, 1), // Wednesday
            Carbon::create(2026, 4, 2), // Thursday
            Carbon::create(2026, 4, 3), // Friday
        ];

        foreach ($sickDates as $date) {
            Attendance::create([
                'staff_member_id' => $employee->id,
                'attendance_period_id' => $this->period->id,
                'date' => $date,
                'check_in' => $date->copy()->setTime(8, 0),
                'status' => 'sick_leave',
            ]);
        }

        $this->withoutExceptionHandling();

        $response = $this->getJson('/api/v1/my-attendance-statistics')
            ->assertOk();

        $data = $response->json('data');
        $this->assertEquals(3, $data['sick_days']);
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
