<?php

use App\Models\HybridScheduleOverride;
use App\Models\HybridWorkSchedule;
use App\Models\JobInformation;
use App\Models\StaffMemberProfile;
use App\Services\Attendance\HybridScheduleResolver;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'sanctum']);
    $this->resolver = new HybridScheduleResolver;
});

/**
 * Helper: create a hybrid employee (StaffMemberProfile + JobInformation with work_location=hybrid).
 */
function createHybridEmployee(): StaffMemberProfile
{
    $profile = StaffMemberProfile::factory()->create();
    JobInformation::factory()->create([
        'staff_member_id' => $profile->id,
        'work_location' => 'hybrid',
    ]);

    return $profile;
}

/**
 * Helper: create a non-hybrid employee (office).
 */
function createOfficeEmployee(): StaffMemberProfile
{
    $profile = StaffMemberProfile::factory()->create();
    JobInformation::factory()->create([
        'staff_member_id' => $profile->id,
        'work_location' => 'office',
    ]);

    return $profile;
}

// ─────────────────────────────────────────────────────────────────────────────
// 1. Non-hybrid employee returns null/none immediately
// ─────────────────────────────────────────────────────────────────────────────

it('returns null planned_mode and none source for non-hybrid employee', function () {
    $employee = createOfficeEmployee();

    $result = $this->resolver->resolve($employee->id, '2026-05-11');

    expect($result)->toBe([
        'planned_mode' => null,
        'source' => 'none',
    ]);
});

// ─────────────────────────────────────────────────────────────────────────────
// 2. Hybrid employee with approved override returns override mode
// ─────────────────────────────────────────────────────────────────────────────

it('returns override planned_mode when approved HybridScheduleOverride exists for date', function () {
    $employee = createHybridEmployee();

    HybridScheduleOverride::factory()->create([
        'staff_member_id' => $employee->id,
        'date' => '2026-05-11',
        'planned_work_mode' => 'WFH',
        'status' => 'approved',
        'approved_at' => now(),
    ]);

    $result = $this->resolver->resolve($employee->id, '2026-05-11');

    expect($result)->toBe([
        'planned_mode' => 'WFH',
        'source' => 'override',
    ]);
});

// ─────────────────────────────────────────────────────────────────────────────
// 3. Hybrid employee with pending override is NOT matched
// ─────────────────────────────────────────────────────────────────────────────

it('ignores pending override and falls through to base_schedule', function () {
    $employee = createHybridEmployee();

    // Create a pending override — should NOT be matched
    HybridScheduleOverride::factory()->create([
        'staff_member_id' => $employee->id,
        'date' => '2026-05-11',
        'planned_work_mode' => 'WFO',
        'status' => 'pending',
    ]);

    // Create a base schedule for Monday
    HybridWorkSchedule::factory()->create([
        'staff_member_id' => $employee->id,
        'effective_from' => '2026-01-01',
        'effective_until' => null,
        'monday' => 'WFH',
        'tuesday' => 'WFO',
        'wednesday' => 'WFH',
        'thursday' => 'WFO',
        'friday' => 'WFO',
    ]);

    // 2026-05-11 is a Monday
    $result = $this->resolver->resolve($employee->id, '2026-05-11');

    expect($result)->toBe([
        'planned_mode' => 'WFH',
        'source' => 'base_schedule',
    ]);
});

// ─────────────────────────────────────────────────────────────────────────────
// 4. Hybrid employee with base schedule returns weekday-specific mode
// ─────────────────────────────────────────────────────────────────────────────

it('returns base_schedule with correct weekday field', function () {
    $employee = createHybridEmployee();

    HybridWorkSchedule::factory()->create([
        'staff_member_id' => $employee->id,
        'effective_from' => '2026-01-01',
        'effective_until' => null,
        'monday' => 'WFO',
        'tuesday' => 'WFH',
        'wednesday' => 'WFO',
        'thursday' => 'WFH',
        'friday' => 'WFO',
    ]);

    // 2026-05-12 is a Tuesday → WFH
    $result = $this->resolver->resolve($employee->id, '2026-05-12');

    expect($result)->toBe([
        'planned_mode' => 'WFH',
        'source' => 'base_schedule',
    ]);
});

it('returns different modes for different weekdays', function () {
    $employee = createHybridEmployee();

    HybridWorkSchedule::factory()->create([
        'staff_member_id' => $employee->id,
        'effective_from' => '2026-01-01',
        'effective_until' => null,
        'monday' => 'WFO',
        'tuesday' => 'WFH',
        'wednesday' => 'WFO',
        'thursday' => 'WFH',
        'friday' => 'WFO',
    ]);

    // 2026-05-11 is Monday → WFO
    expect($this->resolver->resolve($employee->id, '2026-05-11'))->toBe([
        'planned_mode' => 'WFO',
        'source' => 'base_schedule',
    ]);

    // 2026-05-13 is Wednesday → WFO
    expect($this->resolver->resolve($employee->id, '2026-05-13'))->toBe([
        'planned_mode' => 'WFO',
        'source' => 'base_schedule',
    ]);

    // 2026-05-14 is Thursday → WFH
    expect($this->resolver->resolve($employee->id, '2026-05-14'))->toBe([
        'planned_mode' => 'WFH',
        'source' => 'base_schedule',
    ]);

    // 2026-05-15 is Friday → WFO
    expect($this->resolver->resolve($employee->id, '2026-05-15'))->toBe([
        'planned_mode' => 'WFO',
        'source' => 'base_schedule',
    ]);
});

// ─────────────────────────────────────────────────────────────────────────────
// 5. Override takes precedence over base schedule
// ─────────────────────────────────────────────────────────────────────────────

it('returns override instead of base_schedule when both exist for the same date', function () {
    $employee = createHybridEmployee();

    HybridWorkSchedule::factory()->create([
        'staff_member_id' => $employee->id,
        'effective_from' => '2026-01-01',
        'effective_until' => null,
        'monday' => 'WFO',
        'tuesday' => 'WFH',
        'wednesday' => 'WFO',
        'thursday' => 'WFH',
        'friday' => 'WFO',
    ]);

    HybridScheduleOverride::factory()->create([
        'staff_member_id' => $employee->id,
        'date' => '2026-05-11', // Monday — base_schedule says WFO
        'planned_work_mode' => 'WFH',
        'status' => 'approved',
        'approved_at' => now(),
    ]);

    $result = $this->resolver->resolve($employee->id, '2026-05-11');

    expect($result)->toBe([
        'planned_mode' => 'WFH',
        'source' => 'override',
    ]);
});

// ─────────────────────────────────────────────────────────────────────────────
// 6. Weekend returns null/none regardless of schedule
// ─────────────────────────────────────────────────────────────────────────────

it('returns null planned_mode and none source on Saturday', function () {
    $employee = createHybridEmployee();

    HybridWorkSchedule::factory()->create([
        'staff_member_id' => $employee->id,
        'effective_from' => '2026-01-01',
        'effective_until' => null,
        'monday' => 'WFO',
        'tuesday' => 'WFH',
        'wednesday' => 'WFO',
        'thursday' => 'WFH',
        'friday' => 'WFO',
    ]);

    // 2026-05-16 is a Saturday
    $result = $this->resolver->resolve($employee->id, '2026-05-16');

    expect($result)->toBe([
        'planned_mode' => null,
        'source' => 'none',
    ]);
});

it('returns null planned_mode and none source on Sunday', function () {
    $employee = createHybridEmployee();

    HybridWorkSchedule::factory()->create([
        'staff_member_id' => $employee->id,
        'effective_from' => '2026-01-01',
        'effective_until' => null,
        'monday' => 'WFO',
        'tuesday' => 'WFH',
        'wednesday' => 'WFO',
        'thursday' => 'WFH',
        'friday' => 'WFO',
    ]);

    // 2026-05-17 is a Sunday
    $result = $this->resolver->resolve($employee->id, '2026-05-17');

    expect($result)->toBe([
        'planned_mode' => null,
        'source' => 'none',
    ]);
});

// ─────────────────────────────────────────────────────────────────────────────
// 7. Schedule with effective_until: null is open-ended
// ─────────────────────────────────────────────────────────────────────────────

it('matches schedule with null effective_until as open-ended', function () {
    $employee = createHybridEmployee();

    HybridWorkSchedule::factory()->create([
        'staff_member_id' => $employee->id,
        'effective_from' => '2026-01-01',
        'effective_until' => null,
        'monday' => 'WFO',
        'tuesday' => 'WFH',
        'wednesday' => 'WFO',
        'thursday' => 'WFH',
        'friday' => 'WFO',
    ]);

    // 2026-05-11 is Monday — should match the open-ended schedule
    $result = $this->resolver->resolve($employee->id, '2026-05-11');

    expect($result)->toBe([
        'planned_mode' => 'WFO',
        'source' => 'base_schedule',
    ]);
});

// ─────────────────────────────────────────────────────────────────────────────
// 8. Schedule with effective_until in the past is not matched
// ─────────────────────────────────────────────────────────────────────────────

it('does not match schedule whose effective_until is in the past', function () {
    $employee = createHybridEmployee();

    HybridWorkSchedule::factory()->create([
        'staff_member_id' => $employee->id,
        'effective_from' => '2025-01-01',
        'effective_until' => '2025-12-31',
        'monday' => 'WFH',
        'tuesday' => 'WFH',
        'wednesday' => 'WFH',
        'thursday' => 'WFH',
        'friday' => 'WFH',
    ]);

    // 2026-05-11 is after effective_until — schedule should not match
    $result = $this->resolver->resolve($employee->id, '2026-05-11');

    expect($result)->toBe([
        'planned_mode' => null,
        'source' => 'none',
    ]);
});

// ─────────────────────────────────────────────────────────────────────────────
// 9. Employee not found → throws ModelNotFoundException
// ─────────────────────────────────────────────────────────────────────────────

it('throws ModelNotFoundException when employee id does not exist', function () {
    $this->resolver->resolve(99999, '2026-05-11');
})->throws(ModelNotFoundException::class);

// ─────────────────────────────────────────────────────────────────────────────
// 10. No schedule or override found → returns null/none
// ─────────────────────────────────────────────────────────────────────────────

it('returns null planned_mode and none source when no schedule or override exists', function () {
    $employee = createHybridEmployee();

    $result = $this->resolver->resolve($employee->id, '2026-05-11');

    expect($result)->toBe([
        'planned_mode' => null,
        'source' => 'none',
    ]);
});

// ─────────────────────────────────────────────────────────────────────────────
// Edge cases
// ─────────────────────────────────────────────────────────────────────────────

it('accepts a CarbonInterface date argument', function () {
    $employee = createHybridEmployee();

    HybridWorkSchedule::factory()->create([
        'staff_member_id' => $employee->id,
        'effective_from' => '2026-01-01',
        'effective_until' => null,
        'monday' => 'WFO',
    ]);

    $result = $this->resolver->resolve($employee->id, Carbon::parse('2026-05-11'));

    expect($result)->toBe([
        'planned_mode' => 'WFO',
        'source' => 'base_schedule',
    ]);
});

it('matches approved override even with late approved_at timestamp', function () {
    $employee = createHybridEmployee();

    HybridScheduleOverride::factory()->create([
        'staff_member_id' => $employee->id,
        'date' => '2026-05-11',
        'planned_work_mode' => 'REMOTE',
        'status' => 'approved',
        'approved_at' => now()->subDays(5),
    ]);

    $result = $this->resolver->resolve($employee->id, '2026-05-11');

    expect($result)->toBe([
        'planned_mode' => 'REMOTE',
        'source' => 'override',
    ]);
});

it('picks the schedule with the most recent effective_from when multiple overlap', function () {
    $employee = createHybridEmployee();

    HybridWorkSchedule::factory()->create([
        'staff_member_id' => $employee->id,
        'effective_from' => '2025-01-01',
        'effective_until' => null,
        'monday' => 'WFH',
        'tuesday' => 'WFH',
        'wednesday' => 'WFH',
        'thursday' => 'WFH',
        'friday' => 'WFH',
    ]);

    HybridWorkSchedule::factory()->create([
        'staff_member_id' => $employee->id,
        'effective_from' => '2026-04-01',
        'effective_until' => null,
        'monday' => 'WFO',
        'tuesday' => 'WFO',
        'wednesday' => 'WFO',
        'thursday' => 'WFO',
        'friday' => 'WFO',
    ]);

    // 2026-05-11 is Monday — should pick the April schedule (WFO), not the older one (WFH)
    $result = $this->resolver->resolve($employee->id, '2026-05-11');

    expect($result)->toBe([
        'planned_mode' => 'WFO',
        'source' => 'base_schedule',
    ]);
});

it('returns the scheduled mode value directly (WFH/WFO/REMOTE)', function () {
    $employee = createHybridEmployee();

    HybridWorkSchedule::factory()->create([
        'staff_member_id' => $employee->id,
        'effective_from' => '2026-01-01',
        'effective_until' => null,
        'monday' => 'REMOTE',
        'tuesday' => 'REMOTE',
        'wednesday' => 'REMOTE',
        'thursday' => 'REMOTE',
        'friday' => 'REMOTE',
    ]);

    // 2026-05-11 is Monday
    $result = $this->resolver->resolve($employee->id, '2026-05-11');

    expect($result['planned_mode'])->toBe('REMOTE')
        ->and($result['source'])->toBe('base_schedule');
});

it('treats rejected override same as no override', function () {
    $employee = createHybridEmployee();

    HybridScheduleOverride::factory()->create([
        'staff_member_id' => $employee->id,
        'date' => '2026-05-11',
        'planned_work_mode' => 'WFH',
        'status' => 'rejected',
    ]);

    HybridWorkSchedule::factory()->create([
        'staff_member_id' => $employee->id,
        'effective_from' => '2026-01-01',
        'effective_until' => null,
        'monday' => 'WFO',
    ]);

    $result = $this->resolver->resolve($employee->id, '2026-05-11');

    expect($result)->toBe([
        'planned_mode' => 'WFO',
        'source' => 'base_schedule',
    ]);
});

it('does not match override for a different date', function () {
    $employee = createHybridEmployee();

    HybridScheduleOverride::factory()->create([
        'staff_member_id' => $employee->id,
        'date' => '2026-05-12',
        'planned_work_mode' => 'WFH',
        'status' => 'approved',
        'approved_at' => now(),
    ]);

    HybridWorkSchedule::factory()->create([
        'staff_member_id' => $employee->id,
        'effective_from' => '2026-01-01',
        'effective_until' => null,
        'monday' => 'WFO',
    ]);

    // 2026-05-11 (Monday) has no override — should fall through to base_schedule
    $result = $this->resolver->resolve($employee->id, '2026-05-11');

    expect($result)->toBe([
        'planned_mode' => 'WFO',
        'source' => 'base_schedule',
    ]);
});
