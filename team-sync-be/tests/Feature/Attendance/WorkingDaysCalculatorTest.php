<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendancePolicy;
use App\Models\EmployeeProfile;
use App\Models\HolidayCalendar;
use App\Models\JobInformation;
use App\Services\Attendance\WorkingDaysCalculator;
use Database\Seeders\AttendancePolicySeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkingDaysCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private WorkingDaysCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RoleSeeder::class,
            AttendancePolicySeeder::class,
        ]);
        $this->calculator = app(WorkingDaysCalculator::class);
    }

    public function test_part_time_effective_working_days_is_not_reduced_by_tuesday_holiday(): void
    {
        $employee = $this->createEmployeeWithEmploymentType('part_time');

        HolidayCalendar::create([
            'date' => '2026-04-07',
            'name' => 'Tuesday Company Holiday',
            'type' => 'company',
            'applies_to' => null,
        ]);

        $workingDays = $this->calculator->calculateForEmployee($employee->id, '2026-04-01', '2026-04-30');

        $this->assertSame(13, $workingDays);
    }

    public function test_full_time_effective_working_days_is_reduced_by_same_tuesday_holiday(): void
    {
        $employee = $this->createEmployeeWithEmploymentType('full_time');

        HolidayCalendar::create([
            'date' => '2026-04-07',
            'name' => 'Tuesday Company Holiday',
            'type' => 'company',
            'applies_to' => null,
        ]);

        $workingDays = $this->calculator->calculateForEmployee($employee->id, '2026-04-01', '2026-04-30');

        $this->assertSame(21, $workingDays);
    }

    public function test_holiday_applies_to_full_time_only_does_not_affect_part_time(): void
    {
        $partTimeEmployee = $this->createEmployeeWithEmploymentType('part_time');
        $fullTimeEmployee = $this->createEmployeeWithEmploymentType('full_time');

        HolidayCalendar::create([
            'date' => '2026-04-06',
            'name' => 'Full Time Only Holiday',
            'type' => 'company',
            'applies_to' => ['full_time'],
        ]);

        $partTimeWorkingDays = $this->calculator->calculateForEmployee($partTimeEmployee->id, '2026-04-01', '2026-04-30');
        $fullTimeWorkingDays = $this->calculator->calculateForEmployee($fullTimeEmployee->id, '2026-04-01', '2026-04-30');

        $this->assertSame(13, $partTimeWorkingDays);
        $this->assertSame(21, $fullTimeWorkingDays);
    }

    public function test_scheduled_holiday_on_part_time_monday_reduces_effective_working_days(): void
    {
        $employee = $this->createEmployeeWithEmploymentType('part_time');

        HolidayCalendar::create([
            'date' => '2026-04-06',
            'name' => 'Monday Holiday',
            'type' => 'national',
            'applies_to' => null,
        ]);

        $workingDays = $this->calculator->calculateForEmployee($employee->id, '2026-04-01', '2026-04-30');

        $this->assertSame(12, $workingDays);
    }

    private function createEmployeeWithEmploymentType(string $employmentType): EmployeeProfile
    {
        $policy = AttendancePolicy::query()
            ->where('employment_type', $employmentType)
            ->firstOrFail();

        $employee = EmployeeProfile::withoutSyncingToSearch(function () {
            return EmployeeProfile::factory()->create();
        });

        JobInformation::factory()
            ->forEmployee($employee)
            ->active()
            ->state([
                'employment_type' => $employmentType,
                'work_location' => 'office',
                'status' => 'active',
                'monthly_salary' => 10000000,
            ])
            ->create();

        $this->assertSame($employmentType, $policy->employment_type);

        return $employee;
    }
}
