<?php

namespace Tests\Feature\Attendance;

use App\Models\EmployeeProfile;
use App\Models\HybridScheduleOverride;
use App\Models\HybridWorkSchedule;
use App\Models\JobInformation;
use App\Services\Attendance\HybridScheduleResolver;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HybridScheduleResolverTest extends TestCase
{
    use RefreshDatabase;

    private HybridScheduleResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->resolver = app(HybridScheduleResolver::class);
    }

    public function test_override_takes_priority_over_base_schedule(): void
    {
        $employee = $this->createEmployeeWithWorkLocation('hybrid');

        HybridWorkSchedule::create([
            'employee_id' => $employee->id,
            'effective_from' => '2026-04-01',
            'effective_until' => null,
            'monday' => 'office',
            'tuesday' => 'remote',
            'wednesday' => 'office',
            'thursday' => 'remote',
            'friday' => 'office',
        ]);

        HybridScheduleOverride::create([
            'employee_id' => $employee->id,
            'date' => '2026-04-06',
            'planned_work_mode' => 'remote',
            'reason' => 'Approved swap',
            'status' => 'approved',
            'requested_by' => $employee->id,
            'approved_by' => $employee->id,
            'approved_at' => '2026-04-05 09:00:00',
        ]);

        $resolved = $this->resolver->resolve($employee->id, '2026-04-06');

        $this->assertSame([
            'planned_mode' => 'remote',
            'source' => 'override',
        ], $resolved);
    }

    public function test_base_schedule_is_used_when_no_override_exists(): void
    {
        $employee = $this->createEmployeeWithWorkLocation('hybrid');

        HybridWorkSchedule::create([
            'employee_id' => $employee->id,
            'effective_from' => '2026-04-01',
            'effective_until' => null,
            'monday' => 'office',
            'tuesday' => 'remote',
            'wednesday' => 'office',
            'thursday' => 'remote',
            'friday' => 'office',
        ]);

        $resolved = $this->resolver->resolve($employee->id, '2026-04-07');

        $this->assertSame([
            'planned_mode' => 'remote',
            'source' => 'base_schedule',
        ], $resolved);
    }

    public function test_non_hybrid_employee_returns_null_planned_mode(): void
    {
        $employee = $this->createEmployeeWithWorkLocation('office');

        $resolved = $this->resolver->resolve($employee->id, '2026-04-07');

        $this->assertSame([
            'planned_mode' => null,
            'source' => 'none',
        ], $resolved);
    }

    public function test_hybrid_employee_without_schedule_returns_none_source(): void
    {
        $employee = $this->createEmployeeWithWorkLocation('hybrid');

        $resolved = $this->resolver->resolve($employee->id, '2026-04-07');

        $this->assertSame([
            'planned_mode' => null,
            'source' => 'none',
        ], $resolved);
    }

    private function createEmployeeWithWorkLocation(string $workLocation): EmployeeProfile
    {
        $employee = EmployeeProfile::withoutSyncingToSearch(function () {
            return EmployeeProfile::factory()->create();
        });

        JobInformation::factory()
            ->forEmployee($employee)
            ->active()
            ->state([
                'employment_type' => 'full_time',
                'work_location' => $workLocation,
                'status' => 'active',
            ])
            ->create();

        return $employee;
    }
}
