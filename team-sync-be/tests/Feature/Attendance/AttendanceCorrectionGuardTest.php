<?php

namespace Tests\Feature\Attendance;

use App\Models\Attendance;
use App\Models\AttendancePeriod;
use App\Models\EmployeeProfile;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AttendanceCorrectionGuardTest extends TestCase
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

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_employee_cannot_check_out_when_period_is_review(): void
    {
        Carbon::setTestNow('2026-04-10 17:10:00');

        $employee = $this->actingAsEmployee();

        $period = AttendancePeriod::create([
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'cutoff_date' => '2026-04-05',
            'status' => AttendancePeriod::STATUS_REVIEW,
        ]);

        $attendance = Attendance::create([
            'employee_id' => $employee->id,
            'date' => '2026-04-10',
            'attendance_period_id' => $period->id,
            'check_in' => '2026-04-10 08:55:00',
            'status' => 'present',
            'notes' => 'Guard check-out test',
        ]);

        $response = $this->postJson('/api/v1/attendances/check-out', [
            'check_out_lat' => -6.2,
            'check_out_long' => 106.8,
            'notes' => 'Attempt check-out in review period',
        ]);

        $response->assertStatus(400);
        $this->assertStringContainsString('no longer open', (string) $response->json('message'));

        $this->assertTrue(
            Attendance::query()
                ->whereKey($attendance->id)
                ->whereNull('check_out')
                ->exists()
        );
    }

    public function test_employee_cannot_check_in_when_period_is_review(): void
    {
        Carbon::setTestNow('2026-04-10 08:10:00');

        $employee = $this->actingAsEmployee();

        AttendancePeriod::create([
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'cutoff_date' => '2026-04-05',
            'status' => AttendancePeriod::STATUS_REVIEW,
        ]);

        $response = $this->postJson('/api/v1/attendances/check-in', [
            'check_in_lat' => -6.2,
            'check_in_long' => 106.8,
            'notes' => 'Attempt check-in in review period',
        ]);

        $response->assertStatus(400);
        $this->assertStringContainsString('no longer open', (string) $response->json('message'));

        $this->assertFalse(
            Attendance::query()
                ->where('employee_id', $employee->id)
                ->whereDate('date', '2026-04-10')
                ->exists()
        );
    }

    public function test_employee_cannot_check_out_when_period_is_locked(): void
    {
        Carbon::setTestNow('2026-04-10 17:10:00');

        $employee = $this->actingAsEmployee();

        $period = AttendancePeriod::create([
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'cutoff_date' => '2026-04-05',
            'status' => AttendancePeriod::STATUS_LOCKED,
            'locked_at' => now(),
        ]);

        Attendance::create([
            'employee_id' => $employee->id,
            'date' => '2026-04-10',
            'attendance_period_id' => $period->id,
            'check_in' => '2026-04-10 08:55:00',
            'status' => 'present',
            'notes' => 'Guard check-out test',
        ]);

        $response = $this->postJson('/api/v1/attendances/check-out', [
            'check_out_lat' => -6.2,
            'check_out_long' => 106.8,
            'notes' => 'Attempt check-out in locked period',
        ]);

        $response->assertStatus(400);
        $this->assertStringContainsString('no longer open', (string) $response->json('message'));
    }

    public function test_employee_cannot_check_in_when_period_is_locked(): void
    {
        Carbon::setTestNow('2026-04-10 08:10:00');

        $employee = $this->actingAsEmployee();

        AttendancePeriod::create([
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'cutoff_date' => '2026-04-05',
            'status' => AttendancePeriod::STATUS_LOCKED,
            'locked_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/attendances/check-in', [
            'check_in_lat' => -6.2,
            'check_in_long' => 106.8,
            'notes' => 'Attempt check-in in locked period',
        ]);

        $response->assertStatus(400);
        $this->assertStringContainsString('no longer open', (string) $response->json('message'));

        $this->assertFalse(
            Attendance::query()
                ->where('employee_id', $employee->id)
                ->whereDate('date', '2026-04-10')
                ->exists()
        );
    }

    private function actingAsEmployee(): EmployeeProfile
    {
        $employee = EmployeeProfile::withoutSyncingToSearch(function () {
            return EmployeeProfile::factory()->create();
        });

        $employee->jobInformation()->create([
            'employee_id' => $employee->id,
            'job_title' => 'Software Engineer',
            'status' => 'active',
            'employment_type' => 'full_time',
            'work_location' => 'remote',
            'start_date' => '2024-01-01',
            'monthly_salary' => 10000000,
        ]);

        $user = User::query()->findOrFail($employee->user_id);
        $user->assignRole(Role::findByName('staff', 'sanctum'));

        Sanctum::actingAs($user);

        return $employee;
    }
}
