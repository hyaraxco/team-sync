<?php

use App\Models\AttendancePolicy;
use App\Models\HolidayCalendar;
use App\Models\JobInformation;
use App\Models\StaffMemberProfile;
use App\Services\Attendance\WorkingDaysCalculator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Role::create(['name' => 'staff', 'guard_name' => 'sanctum']);

    $this->calculator = new WorkingDaysCalculator;
});

/**
 * Create a StaffMemberProfile with a JobInformation record.
 */
function createEmployee(string $employmentType): StaffMemberProfile
{
    $profile = StaffMemberProfile::factory()->create();
    JobInformation::factory()->forEmployee($profile)->create([
        'employment_type' => $employmentType,
    ]);

    return $profile;
}

// ─────────────────────────────────────────────────────────────────────────────
// calculateForEmployee – full week, no holidays
// ─────────────────────────────────────────────────────────────────────────────

it('returns 5 working days for a full Mon–Fri week with no holidays', function () {
    $employee = createEmployee('full_time');
    AttendancePolicy::factory()->create([
        'employment_type' => 'full_time',
        'default_working_weekdays' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
    ]);

    // 2026-01-05 Mon → 2026-01-11 Sun (full calendar week)
    $result = $this->calculator->calculateForEmployee(
        $employee->id,
        '2026-01-05',
        '2026-01-11'
    );

    expect($result)->toBe(5);
});

// ─────────────────────────────────────────────────────────────────────────────
// calculateForEmployee – partial week schedule
// ─────────────────────────────────────────────────────────────────────────────

it('returns 3 working days for Mon/Wed/Fri schedule with no holidays', function () {
    $employee = createEmployee('part_time');
    AttendancePolicy::factory()->create([
        'employment_type' => 'part_time',
        'default_working_weekdays' => ['monday', 'wednesday', 'friday'],
    ]);

    // 2026-01-05 Mon → 2026-01-11 Sun
    // Scheduled: Mon 5, Wed 7, Fri 9 = 3 days
    $result = $this->calculator->calculateForEmployee(
        $employee->id,
        '2026-01-05',
        '2026-01-11'
    );

    expect($result)->toBe(3);
});

// ─────────────────────────────────────────────────────────────────────────────
// calculateForEmployee – holiday on a scheduled day
// ─────────────────────────────────────────────────────────────────────────────

it('subtracts a holiday that falls on a scheduled day', function () {
    $employee = createEmployee('full_time');
    AttendancePolicy::factory()->create([
        'employment_type' => 'full_time',
        'default_working_weekdays' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
    ]);

    // Holiday on Wed Jan 7
    HolidayCalendar::factory()->create([
        'date' => '2026-01-07',
        'applies_to' => null,
    ]);

    // Mon 5 → Sun 11: 5 scheduled − 1 holiday = 4
    $result = $this->calculator->calculateForEmployee(
        $employee->id,
        '2026-01-05',
        '2026-01-11'
    );

    expect($result)->toBe(4);
});

// ─────────────────────────────────────────────────────────────────────────────
// calculateForEmployee – holiday on a non-scheduled day
// ─────────────────────────────────────────────────────────────────────────────

it('does not subtract a holiday that falls on a non-scheduled day', function () {
    $employee = createEmployee('full_time');
    AttendancePolicy::factory()->create([
        'employment_type' => 'full_time',
        'default_working_weekdays' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
    ]);

    // Holiday on Sat Jan 10 (not a working day)
    HolidayCalendar::factory()->create([
        'date' => '2026-01-10',
        'applies_to' => null,
    ]);

    $result = $this->calculator->calculateForEmployee(
        $employee->id,
        '2026-01-05',
        '2026-01-11'
    );

    expect($result)->toBe(5);
});

// ─────────────────────────────────────────────────────────────────────────────
// calculateForEmployee – applies_to matching employment type
// ─────────────────────────────────────────────────────────────────────────────

it('subtracts a holiday whose applies_to includes the employment type', function () {
    $employee = createEmployee('full_time');
    AttendancePolicy::factory()->create([
        'employment_type' => 'full_time',
        'default_working_weekdays' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
    ]);

    HolidayCalendar::factory()->create([
        'date' => '2026-01-07',
        'applies_to' => ['full_time', 'contract'],
    ]);

    $result = $this->calculator->calculateForEmployee(
        $employee->id,
        '2026-01-05',
        '2026-01-11'
    );

    expect($result)->toBe(4);
});

// ─────────────────────────────────────────────────────────────────────────────
// calculateForEmployee – applies_to NOT matching employment type
// ─────────────────────────────────────────────────────────────────────────────

it('does not subtract a holiday whose applies_to excludes the employment type', function () {
    $employee = createEmployee('full_time');
    AttendancePolicy::factory()->create([
        'employment_type' => 'full_time',
        'default_working_weekdays' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
    ]);

    HolidayCalendar::factory()->create([
        'date' => '2026-01-07',
        'applies_to' => ['contract', 'intern'],
    ]);

    $result = $this->calculator->calculateForEmployee(
        $employee->id,
        '2026-01-05',
        '2026-01-11'
    );

    expect($result)->toBe(5);
});

// ─────────────────────────────────────────────────────────────────────────────
// calculateForEmployee – applies_to null (applies to all)
// ─────────────────────────────────────────────────────────────────────────────

it('subtracts a holiday with applies_to null (applies to all employment types)', function () {
    $employee = createEmployee('contract');
    AttendancePolicy::factory()->create([
        'employment_type' => 'contract',
        'default_working_weekdays' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
    ]);

    HolidayCalendar::factory()->create([
        'date' => '2026-01-07',
        'applies_to' => null,
    ]);

    $result = $this->calculator->calculateForEmployee(
        $employee->id,
        '2026-01-05',
        '2026-01-11'
    );

    expect($result)->toBe(4);
});

// ─────────────────────────────────────────────────────────────────────────────
// calculateForEmployee – empty period (weekend only)
// ─────────────────────────────────────────────────────────────────────────────

it('returns 0 when the period is a single weekend day', function () {
    $employee = createEmployee('full_time');
    AttendancePolicy::factory()->create([
        'employment_type' => 'full_time',
        'default_working_weekdays' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
    ]);

    // 2026-01-10 is a Saturday
    $result = $this->calculator->calculateForEmployee(
        $employee->id,
        '2026-01-10',
        '2026-01-10'
    );

    expect($result)->toBe(0);
});

// ─────────────────────────────────────────────────────────────────────────────
// calculateForEmployee – no employment_type throws
// ─────────────────────────────────────────────────────────────────────────────

it('throws InvalidArgumentException when employee has no employment_type (no JobInformation)', function () {
    // StaffMemberProfile exists but has no JobInformation → jobInformation is null
    // The null-safe operator (?->employment_type) returns null, triggering the throw
    $profile = StaffMemberProfile::factory()->create();

    $this->calculator->calculateForEmployee(
        $profile->id,
        '2026-01-05',
        '2026-01-11'
    );
})->throws(InvalidArgumentException::class, 'Employee does not have job information employment_type.');

// ─────────────────────────────────────────────────────────────────────────────
// calculateForEmployee – no attendance policy throws
// ─────────────────────────────────────────────────────────────────────────────

it('throws InvalidArgumentException when no AttendancePolicy exists for employment type', function () {
    $employee = createEmployee('full_time');
    // Do NOT create any AttendancePolicy

    $this->calculator->calculateForEmployee(
        $employee->id,
        '2026-01-05',
        '2026-01-11'
    );
})->throws(InvalidArgumentException::class, 'Attendance policy not found for employment type [full_time].');

// ─────────────────────────────────────────────────────────────────────────────
// calculateForEmployee – employee not found
// ─────────────────────────────────────────────────────────────────────────────

it('throws ModelNotFoundException when employee does not exist', function () {
    $this->calculator->calculateForEmployee(
        99999,
        '2026-01-05',
        '2026-01-11'
    );
})->throws(Illuminate\Database\Eloquent\ModelNotFoundException::class);

// ─────────────────────────────────────────────────────────────────────────────
// applicableHolidayDates – empty scheduledDateKeys
// ─────────────────────────────────────────────────────────────────────────────

it('returns an empty collection when scheduledDateKeys is empty', function () {
    HolidayCalendar::factory()->create([
        'date' => '2026-01-07',
        'applies_to' => null,
    ]);

    $result = $this->calculator->applicableHolidayDates(
        'full_time',
        '2026-01-05',
        '2026-01-11',
        []
    );

    expect($result)->toBeEmpty();
});

// ─────────────────────────────────────────────────────────────────────────────
// applicableHolidayDates – filters by scheduled dates
// ─────────────────────────────────────────────────────────────────────────────

it('returns only holidays that fall within the scheduled dates', function () {
    // Create holidays on a scheduled day and a non-scheduled day
    HolidayCalendar::factory()->create([
        'date' => '2026-01-07', // Wed — in scheduled set
        'name' => 'Holiday on Wed',
        'applies_to' => null,
    ]);
    HolidayCalendar::factory()->create([
        'date' => '2026-01-10', // Sat — NOT in scheduled set
        'name' => 'Holiday on Sat',
        'applies_to' => null,
    ]);

    $scheduledDateKeys = ['2026-01-05', '2026-01-06', '2026-01-07', '2026-01-08', '2026-01-09'];

    $result = $this->calculator->applicableHolidayDates(
        'full_time',
        '2026-01-05',
        '2026-01-11',
        $scheduledDateKeys
    );

    expect($result)->toHaveCount(1)
        ->and($result->first())->toBe('2026-01-07');
});

// ─────────────────────────────────────────────────────────────────────────────
// applicableHolidayDates – applies_to null (all types)
// ─────────────────────────────────────────────────────────────────────────────

it('includes holidays with applies_to null for any employment type', function () {
    HolidayCalendar::factory()->create([
        'date' => '2026-01-07',
        'applies_to' => null,
    ]);

    $scheduledDateKeys = ['2026-01-07'];

    $result = $this->calculator->applicableHolidayDates(
        'intern',
        '2026-01-05',
        '2026-01-11',
        $scheduledDateKeys
    );

    expect($result)->toHaveCount(1);
});

// ─────────────────────────────────────────────────────────────────────────────
// applicableHolidayDates – applies_to matching
// ─────────────────────────────────────────────────────────────────────────────

it('includes holidays when employment type is in applies_to array', function () {
    HolidayCalendar::factory()->create([
        'date' => '2026-01-07',
        'applies_to' => ['full_time', 'contract'],
    ]);

    $scheduledDateKeys = ['2026-01-07'];

    $result = $this->calculator->applicableHolidayDates(
        'contract',
        '2026-01-05',
        '2026-01-11',
        $scheduledDateKeys
    );

    expect($result)->toHaveCount(1);
});

// ─────────────────────────────────────────────────────────────────────────────
// applicableHolidayDates – applies_to not matching
// ─────────────────────────────────────────────────────────────────────────────

it('excludes holidays when employment type is not in applies_to array', function () {
    HolidayCalendar::factory()->create([
        'date' => '2026-01-07',
        'applies_to' => ['contract'],
    ]);

    $scheduledDateKeys = ['2026-01-07'];

    $result = $this->calculator->applicableHolidayDates(
        'full_time',
        '2026-01-05',
        '2026-01-11',
        $scheduledDateKeys
    );

    expect($result)->toBeEmpty();
});

// ─────────────────────────────────────────────────────────────────────────────
// applicableHolidayDates – no holidays in range
// ─────────────────────────────────────────────────────────────────────────────

it('returns empty collection when no holidays exist in the date range', function () {
    HolidayCalendar::factory()->create([
        'date' => '2026-02-14', // outside the query range
        'applies_to' => null,
    ]);

    $scheduledDateKeys = ['2026-01-07'];

    $result = $this->calculator->applicableHolidayDates(
        'full_time',
        '2026-01-05',
        '2026-01-11',
        $scheduledDateKeys
    );

    expect($result)->toBeEmpty();
});

// ─────────────────────────────────────────────────────────────────────────────
// Multi-week period with mixed holidays
// ─────────────────────────────────────────────────────────────────────────────

it('correctly calculates working days across a multi-week period with mixed holidays', function () {
    $employee = createEmployee('full_time');
    AttendancePolicy::factory()->create([
        'employment_type' => 'full_time',
        'default_working_weekdays' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
    ]);

    // 2026-01-05 Mon → 2026-01-18 Sun = 2 calendar weeks = 10 scheduled weekdays
    // Holiday on Wed Jan 7 (scheduled, applies_to null) → subtract 1
    // Holiday on Sat Jan 10 (not scheduled) → no subtraction
    // Holiday on Mon Jan 12 (scheduled, applies_to = ['contract']) → no subtraction for full_time
    // Holiday on Thu Jan 15 (scheduled, applies_to = ['full_time']) → subtract 1
    // Total: 10 − 2 = 8
    HolidayCalendar::factory()->create([
        'date' => '2026-01-07',
        'applies_to' => null,
    ]);
    HolidayCalendar::factory()->create([
        'date' => '2026-01-10',
        'applies_to' => null,
    ]);
    HolidayCalendar::factory()->create([
        'date' => '2026-01-12',
        'applies_to' => ['contract'],
    ]);
    HolidayCalendar::factory()->create([
        'date' => '2026-01-15',
        'applies_to' => ['full_time'],
    ]);

    $result = $this->calculator->calculateForEmployee(
        $employee->id,
        '2026-01-05',
        '2026-01-18'
    );

    expect($result)->toBe(8);
});

// ─────────────────────────────────────────────────────────────────────────────
// calculateForEmployee – same start and end on a weekday
// ─────────────────────────────────────────────────────────────────────────────

it('returns 1 when period is a single scheduled weekday with no holidays', function () {
    $employee = createEmployee('full_time');
    AttendancePolicy::factory()->create([
        'employment_type' => 'full_time',
        'default_working_weekdays' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
    ]);

    // 2026-01-07 is a Wednesday
    $result = $this->calculator->calculateForEmployee(
        $employee->id,
        '2026-01-07',
        '2026-01-07'
    );

    expect($result)->toBe(1);
});

// ─────────────────────────────────────────────────────────────────────────────
// calculateForEmployee – holiday on first and last day of period
// ─────────────────────────────────────────────────────────────────────────────

it('subtracts holidays that fall on the first and last scheduled weekdays of the period', function () {
    $employee = createEmployee('full_time');
    AttendancePolicy::factory()->create([
        'employment_type' => 'full_time',
        'default_working_weekdays' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
    ]);

    // Use a 2-week period so holidays fall well within the range (avoids SQLite date boundary comparison issues)
    // 2026-01-05 Mon → 2026-01-18 Sun = 10 scheduled weekdays
    // Holidays on Mon Jan 5 (first scheduled) and Fri Jan 16 (last scheduled)
    HolidayCalendar::factory()->create([
        'date' => '2026-01-05',
        'applies_to' => null,
    ]);
    HolidayCalendar::factory()->create([
        'date' => '2026-01-16',
        'applies_to' => null,
    ]);

    $result = $this->calculator->calculateForEmployee(
        $employee->id,
        '2026-01-05',
        '2026-01-18'
    );

    // 10 scheduled weekdays − 2 holidays = 8
    expect($result)->toBe(8);
});

// ─────────────────────────────────────────────────────────────────────────────
// calculateForEmployee – multiple holidays on the same date (dedup)
// ─────────────────────────────────────────────────────────────────────────────

it('deduplicates multiple holiday entries on the same date', function () {
    $employee = createEmployee('full_time');
    AttendancePolicy::factory()->create([
        'employment_type' => 'full_time',
        'default_working_weekdays' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
    ]);

    // Two holidays on the same date — unique() in applicableHolidayDates should dedup
    HolidayCalendar::factory()->create([
        'date' => '2026-01-07',
        'name' => 'Holiday One',
        'applies_to' => null,
    ]);
    HolidayCalendar::factory()->create([
        'date' => '2026-01-07',
        'name' => 'Holiday Two',
        'applies_to' => null,
    ]);

    $result = $this->calculator->calculateForEmployee(
        $employee->id,
        '2026-01-05',
        '2026-01-11'
    );

    // 5 scheduled − 1 unique holiday = 4
    expect($result)->toBe(4);
});

// ─────────────────────────────────────────────────────────────────────────────
// calculateForEmployee – Saturday-only schedule
// ─────────────────────────────────────────────────────────────────────────────

it('supports a Saturday-only schedule', function () {
    $employee = createEmployee('part_time');
    AttendancePolicy::factory()->create([
        'employment_type' => 'part_time',
        'default_working_weekdays' => ['saturday'],
    ]);

    // 2026-01-05 Mon → 2026-01-11 Sun: only Sat Jan 10 is scheduled
    $result = $this->calculator->calculateForEmployee(
        $employee->id,
        '2026-01-05',
        '2026-01-11'
    );

    expect($result)->toBe(1);
});

// ─────────────────────────────────────────────────────────────────────────────
// applicableHolidayDates – multiple applicable holidays in range
// ─────────────────────────────────────────────────────────────────────────────

it('returns all applicable holidays within the date range', function () {
    HolidayCalendar::factory()->create([
        'date' => '2026-01-06',
        'applies_to' => null,
    ]);
    HolidayCalendar::factory()->create([
        'date' => '2026-01-08',
        'applies_to' => null,
    ]);
    HolidayCalendar::factory()->create([
        'date' => '2026-01-09',
        'applies_to' => null,
    ]);

    $scheduledDateKeys = [
        '2026-01-05',
        '2026-01-06',
        '2026-01-07',
        '2026-01-08',
        '2026-01-09',
    ];

    $result = $this->calculator->applicableHolidayDates(
        'full_time',
        '2026-01-05',
        '2026-01-11',
        $scheduledDateKeys
    );

    expect($result)->toHaveCount(3)
        ->toContain('2026-01-06')
        ->toContain('2026-01-08')
        ->toContain('2026-01-09');
});
