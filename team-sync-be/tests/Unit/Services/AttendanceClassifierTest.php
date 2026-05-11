<?php

use App\Models\Attendance;
use App\Models\AttendancePeriod;
use App\Models\AttendancePolicy;
use App\Models\AttendancePolicyMismatch;
use App\Models\HolidayCalendar;
use App\Models\JobInformation;
use App\Models\LeaveRequest;
use App\Models\StaffMemberProfile;
use App\Services\Attendance\AttendanceClassifier;
use App\Services\Attendance\HybridScheduleResolver;
use App\Services\Attendance\LeaveEntitlementValidator;
use App\Services\EmailService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'sanctum']);

    $this->hybridResolver = Mockery::mock(HybridScheduleResolver::class);
    $this->leaveValidator = Mockery::mock(LeaveEntitlementValidator::class);
    $this->emailService = Mockery::mock(EmailService::class);

    $this->classifier = new AttendanceClassifier(
        $this->hybridResolver,
        $this->leaveValidator,
        $this->emailService,
    );
});

afterEach(function () {
    Mockery::close();
});

// ─── Helpers ────────────────────────────────────────────────────────────────

/**
 * Create a full-time office employee with a deterministic attendance policy.
 */
function createFullTimeEmployee(
    string $workLocation = 'office',
    string $employmentType = 'full_time',
    float $monthlySalary = 10000000,
): StaffMemberProfile {
    $profile = StaffMemberProfile::factory()->create();

    JobInformation::factory()->create([
        'staff_member_id' => $profile->id,
        'employment_type' => $employmentType,
        'work_location' => $workLocation,
        'monthly_salary' => $monthlySalary,
    ]);

    return $profile;
}

/**
 * Create or update a deterministic AttendancePolicy for the given employment type.
 */
function createAttendancePolicy(
    string $employmentType = 'full_time',
    string $workStartTime = '09:00:00',
    int $lateGraceMinutes = 30,
    float $halfDayMinHours = 4.0,
    float $warningAbsentPct = 15.0,
): AttendancePolicy {
    $weekdays = match ($employmentType) {
        'part_time' => ['monday', 'wednesday', 'friday'],
        default => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
    };

    return AttendancePolicy::updateOrCreate(
        ['employment_type' => $employmentType],
        [
            'work_start_time' => $workStartTime,
            'work_end_time' => '17:00:00',
            'work_days_per_week' => count($weekdays),
            'default_working_weekdays' => $weekdays,
            'late_grace_minutes' => $lateGraceMinutes,
            'half_day_min_hours' => $halfDayMinHours,
            'warning_absent_pct' => $warningAbsentPct,
        ],
    );
}

/**
 * Create an attendance record for the given employee on a specific date.
 */
function createAttendanceRecord(
    StaffMemberProfile $employee,
    string $date,
    ?string $checkIn = null,
    ?string $checkOut = null,
    ?int $workedMinutes = null,
    string $actualWorkMode = 'office',
    bool $policyMismatchFlag = false,
    ?string $attendancePeriodId = null,
): Attendance {
    $periodId = $attendancePeriodId ?? AttendancePeriod::factory()->create()->id;

    return Attendance::create([
        'staff_member_id' => $employee->id,
        'date' => $date,
        'attendance_period_id' => $periodId,
        'check_in' => $checkIn,
        'check_in_lat' => $checkIn ? '-6.2088' : null,
        'check_in_long' => $checkIn ? '106.8456' : null,
        'check_out' => $checkOut,
        'worked_minutes' => $workedMinutes,
        'actual_work_mode' => $actualWorkMode,
        'policy_mismatch_flag' => $policyMismatchFlag,
        'status' => 'present',
    ]);
}

// ═══════════════════════════════════════════════════════════════════════════════
// 1. Holiday classification
// ═══════════════════════════════════════════════════════════════════════════════

it('returns holiday status for a date that is a national holiday', function () {
    $employee = createFullTimeEmployee();
    createAttendancePolicy();

    HolidayCalendar::create([
        'date' => '2026-05-01',
        'name' => 'Labour Day',
        'type' => 'national_holiday',
        'applies_to' => null,
    ]);

    $result = $this->classifier->classify($employee->id, '2026-05-01');

    expect($result['status'])->toBe('holiday');
    expect($result['source'])->toBe('holiday');
    expect($result['is_paid_leave'])->toBeFalse();
    expect($result['policy_mismatch_flag'])->toBeFalse();
});

it('returns holiday only for employment types that match applies_to filter', function () {
    $employee = createFullTimeEmployee();
    createAttendancePolicy();

    HolidayCalendar::create([
        'date' => '2026-05-01',
        'name' => 'Company Day',
        'type' => 'collective_leave',
        'applies_to' => ['contract'],
    ]);

    $result = $this->classifier->classify($employee->id, '2026-05-01');

    expect($result['status'])->not->toBe('holiday');
});

it('returns holiday for all employment types when applies_to is null', function () {
    $employee = createFullTimeEmployee();
    createAttendancePolicy();

    HolidayCalendar::create([
        'date' => '2026-05-01',
        'name' => 'Independence Day',
        'type' => 'national_holiday',
        'applies_to' => null,
    ]);

    $result = $this->classifier->classify($employee->id, '2026-05-01');

    expect($result['status'])->toBe('holiday');
});

// ═══════════════════════════════════════════════════════════════════════════════
// 2. Valid leave classification
// ═══════════════════════════════════════════════════════════════════════════════

it('returns leave type status for a valid approved leave', function () {
    $employee = createFullTimeEmployee();
    createAttendancePolicy();

    LeaveRequest::create([
        'staff_member_id' => $employee->id,
        'leave_type' => 'annual_leave',
        'start_date' => '2026-05-11',
        'end_date' => '2026-05-11',
        'total_days' => 1,
        'status' => 'approved',
    ]);

    $this->leaveValidator
        ->shouldReceive('validate')
        ->once()
        ->andReturn([
            'valid' => true,
            'is_paid_leave' => true,
        ]);

    $result = $this->classifier->classify($employee->id, '2026-05-11');

    expect($result['status'])->toBe('annual_leave');
    expect($result['source'])->toBe('leave');
    expect($result['is_paid_leave'])->toBeTrue();
});

it('returns sick_leave status for a valid sick leave', function () {
    $employee = createFullTimeEmployee();
    createAttendancePolicy();

    LeaveRequest::create([
        'staff_member_id' => $employee->id,
        'leave_type' => 'sick_leave',
        'start_date' => '2026-05-11',
        'end_date' => '2026-05-11',
        'total_days' => 1,
        'status' => 'approved',
    ]);

    $this->leaveValidator
        ->shouldReceive('validate')
        ->once()
        ->andReturn([
            'valid' => true,
            'is_paid_leave' => true,
        ]);

    $result = $this->classifier->classify($employee->id, '2026-05-11');

    expect($result['status'])->toBe('sick_leave');
    expect($result['source'])->toBe('leave');
});

it('returns unpaid leave status when is_paid_leave is false', function () {
    $employee = createFullTimeEmployee();
    createAttendancePolicy();

    LeaveRequest::create([
        'staff_member_id' => $employee->id,
        'leave_type' => 'personal_leave',
        'start_date' => '2026-05-11',
        'end_date' => '2026-05-11',
        'total_days' => 1,
        'status' => 'approved',
    ]);

    $this->leaveValidator
        ->shouldReceive('validate')
        ->once()
        ->andReturn([
            'valid' => true,
            'is_paid_leave' => false,
        ]);

    $result = $this->classifier->classify($employee->id, '2026-05-11');

    expect($result['status'])->toBe('personal_leave');
    expect($result['source'])->toBe('leave');
    expect($result['is_paid_leave'])->toBeFalse();
});

it('skips invalid leave and falls through to attendance classification', function () {
    $employee = createFullTimeEmployee();
    createAttendancePolicy();

    LeaveRequest::create([
        'staff_member_id' => $employee->id,
        'leave_type' => 'annual_leave',
        'start_date' => '2026-05-11',
        'end_date' => '2026-05-11',
        'total_days' => 1,
        'status' => 'approved',
    ]);

    $this->leaveValidator
        ->shouldReceive('validate')
        ->once()
        ->andReturn([
            'valid' => false,
            'is_paid_leave' => false,
        ]);

    $result = $this->classifier->classify($employee->id, '2026-05-11');

    expect($result['status'])->toBe('absent');
    expect($result['source'])->toBe('absent');
});

// ═══════════════════════════════════════════════════════════════════════════════
// 3. Attendance record: present (on time)
// ═══════════════════════════════════════════════════════════════════════════════

it('classifies employee as present when check_in is on or before work_start_time', function () {
    $employee = createFullTimeEmployee();
    createAttendancePolicy();

    createAttendanceRecord(
        $employee,
        date: '2026-05-11',
        checkIn: '2026-05-11 09:00:00',
        checkOut: '2026-05-11 17:00:00',
        workedMinutes: 480,
    );

    $result = $this->classifier->classify($employee->id, '2026-05-11');

    expect($result['status'])->toBe('present');
    expect($result['source'])->toBe('attendance');
});

it('classifies employee as present when check_in is before work_start_time', function () {
    $employee = createFullTimeEmployee();
    createAttendancePolicy();

    createAttendanceRecord(
        $employee,
        date: '2026-05-11',
        checkIn: '2026-05-11 08:45:00',
        checkOut: '2026-05-11 17:00:00',
        workedMinutes: 510,
    );

    $result = $this->classifier->classify($employee->id, '2026-05-11');

    expect($result['status'])->toBe('present');
});

// ═══════════════════════════════════════════════════════════════════════════════
// 4. Attendance record: late (within grace period)
// ═══════════════════════════════════════════════════════════════════════════════

it('classifies employee as late when check_in is within grace period', function () {
    $employee = createFullTimeEmployee();
    createAttendancePolicy();

    createAttendanceRecord(
        $employee,
        date: '2026-05-11',
        checkIn: '2026-05-11 09:15:00',
        checkOut: '2026-05-11 17:00:00',
        workedMinutes: 465,
    );

    $result = $this->classifier->classify($employee->id, '2026-05-11');

    expect($result['status'])->toBe('late');
    expect($result['source'])->toBe('attendance');
});

it('classifies employee as late when check_in is exactly at lateThreshold', function () {
    $employee = createFullTimeEmployee();
    createAttendancePolicy();

    createAttendanceRecord(
        $employee,
        date: '2026-05-11',
        checkIn: '2026-05-11 09:30:00',
        checkOut: '2026-05-11 17:00:00',
        workedMinutes: 450,
    );

    $result = $this->classifier->classify($employee->id, '2026-05-11');

    expect($result['status'])->toBe('late');
});

// ═══════════════════════════════════════════════════════════════════════════════
// 5. Attendance record: half_day (after grace, enough hours)
// ═══════════════════════════════════════════════════════════════════════════════

it('classifies employee as half_day when check_in is after grace and worked_minutes >= half_day_min_hours', function () {
    $employee = createFullTimeEmployee();
    createAttendancePolicy();

    createAttendanceRecord(
        $employee,
        date: '2026-05-11',
        checkIn: '2026-05-11 10:00:00',
        checkOut: '2026-05-11 14:30:00',
        workedMinutes: 270,
    );

    $result = $this->classifier->classify($employee->id, '2026-05-11');

    expect($result['status'])->toBe('half_day');
    expect($result['source'])->toBe('attendance');
});

it('classifies as half_day when worked_minutes exactly equals threshold', function () {
    $employee = createFullTimeEmployee();
    createAttendancePolicy();

    createAttendanceRecord(
        $employee,
        date: '2026-05-11',
        checkIn: '2026-05-11 10:00:00',
        checkOut: '2026-05-11 14:00:00',
        workedMinutes: 240,
    );

    $result = $this->classifier->classify($employee->id, '2026-05-11');

    expect($result['status'])->toBe('half_day');
});

// ═══════════════════════════════════════════════════════════════════════════════
// 6. Attendance record: absent (after grace, not enough hours)
// ═══════════════════════════════════════════════════════════════════════════════

it('classifies employee as absent when check_in is after grace and worked_minutes < half_day_min_hours', function () {
    $employee = createFullTimeEmployee();
    createAttendancePolicy();

    createAttendanceRecord(
        $employee,
        date: '2026-05-11',
        checkIn: '2026-05-11 10:30:00',
        checkOut: '2026-05-11 12:30:00',
        workedMinutes: 120,
    );

    $result = $this->classifier->classify($employee->id, '2026-05-11');

    expect($result['status'])->toBe('absent');
    expect($result['source'])->toBe('attendance');
});

it('classifies as absent when worked_minutes is null and check_in is after grace', function () {
    $employee = createFullTimeEmployee();
    createAttendancePolicy();

    createAttendanceRecord(
        $employee,
        date: '2026-05-11',
        checkIn: '2026-05-11 10:00:00',
        checkOut: null,
        workedMinutes: null,
    );

    $result = $this->classifier->classify($employee->id, '2026-05-11');

    expect($result['status'])->toBe('absent');
});

// ═══════════════════════════════════════════════════════════════════════════════
// 7. Attendance record: absent (no check_in)
// ═══════════════════════════════════════════════════════════════════════════════

it('classifies employee as absent when attendance record has no check_in', function () {
    $employee = createFullTimeEmployee();
    createAttendancePolicy();

    createAttendanceRecord(
        $employee,
        date: '2026-05-11',
        checkIn: null,
        checkOut: null,
        workedMinutes: null,
    );

    $result = $this->classifier->classify($employee->id, '2026-05-11');

    expect($result['status'])->toBe('absent');
    expect($result['source'])->toBe('attendance');
});

// ═══════════════════════════════════════════════════════════════════════════════
// 8. Remote employee auto-present
// ═══════════════════════════════════════════════════════════════════════════════

it('auto-presents remote employee with no attendance record', function () {
    $employee = createFullTimeEmployee(workLocation: 'remote');
    createAttendancePolicy();

    $result = $this->classifier->classify($employee->id, '2026-05-11');

    expect($result['status'])->toBe('present');
    expect($result['source'])->toBe('auto_present');
    expect($result['is_paid_leave'])->toBeFalse();
    expect($result['policy_mismatch_flag'])->toBeFalse();
});

// ═══════════════════════════════════════════════════════════════════════════════
// 9. Hybrid employee on WFH day → auto-present
// ═══════════════════════════════════════════════════════════════════════════════

it('auto-presents hybrid employee on a WFH day with no attendance record', function () {
    $employee = createFullTimeEmployee(workLocation: 'hybrid');
    createAttendancePolicy();

    $this->hybridResolver
        ->shouldReceive('resolve')
        ->once()
        ->andReturn([
            'planned_mode' => 'wfh',
            'source' => 'base_schedule',
        ]);

    $result = $this->classifier->classify($employee->id, '2026-05-11');

    expect($result['status'])->toBe('present');
    expect($result['source'])->toBe('auto_present');
});

it('does not auto-present hybrid employee on a WFO day without attendance', function () {
    $employee = createFullTimeEmployee(workLocation: 'hybrid');
    createAttendancePolicy();

    $this->hybridResolver
        ->shouldReceive('resolve')
        ->once()
        ->andReturn([
            'planned_mode' => 'wfo',
            'source' => 'base_schedule',
        ]);

    $result = $this->classifier->classify($employee->id, '2026-05-11');

    expect($result['status'])->toBe('absent');
    expect($result['source'])->toBe('absent');
});

// ═══════════════════════════════════════════════════════════════════════════════
// 10. Default absent (office employee, no record)
// ═══════════════════════════════════════════════════════════════════════════════

it('returns absent for office employee with no attendance record and no leave', function () {
    $employee = createFullTimeEmployee(workLocation: 'office');
    createAttendancePolicy();

    $result = $this->classifier->classify($employee->id, '2026-05-11');

    expect($result['status'])->toBe('absent');
    expect($result['source'])->toBe('absent');
});

// ═══════════════════════════════════════════════════════════════════════════════
// 11. Non-working day (weekend)
// ═══════════════════════════════════════════════════════════════════════════════

it('returns absent for a non-scheduled day like Saturday', function () {
    $employee = createFullTimeEmployee();
    createAttendancePolicy();

    $result = $this->classifier->classify($employee->id, '2026-05-16');

    expect($result['status'])->toBe('absent');
});

// ═══════════════════════════════════════════════════════════════════════════════
// 12. Employee not found
// ═══════════════════════════════════════════════════════════════════════════════

it('throws ModelNotFoundException for non-existent employee', function () {
    $this->classifier->classify(999999, '2026-05-11');
})->throws(ModelNotFoundException::class);

// ═══════════════════════════════════════════════════════════════════════════════
// 13. summarizePeriod: counts statuses correctly
// ═══════════════════════════════════════════════════════════════════════════════

it('summarizes a week with mixed attendance correctly', function () {
    $employee = createFullTimeEmployee();
    createAttendancePolicy(); // Mon-Fri, late_grace=30, half_day=4h

    // Mon → present
    createAttendanceRecord($employee, '2026-05-11', '2026-05-11 08:50:00', '2026-05-11 17:00:00', 490);
    // Tue → late
    createAttendanceRecord($employee, '2026-05-12', '2026-05-12 09:15:00', '2026-05-12 17:00:00', 465);
    // Wed → half_day
    createAttendanceRecord($employee, '2026-05-13', '2026-05-13 10:00:00', '2026-05-13 14:30:00', 270);
    // Thu → absent (record exists but no check_in — tracked as absent)
    createAttendanceRecord($employee, '2026-05-14', null, null, null);
    // Fri → annual_leave
    LeaveRequest::create([
        'staff_member_id' => $employee->id,
        'leave_type' => 'annual_leave',
        'start_date' => '2026-05-15',
        'end_date' => '2026-05-15',
        'total_days' => 1,
        'status' => 'approved',
    ]);

    $this->leaveValidator
        ->shouldReceive('validate')
        ->atLeast()->once()
        ->andReturn([
            'valid' => true,
            'is_paid_leave' => true,
        ]);

    // Use +1 day on end to avoid date-cast boundary issue with whereBetween
    $result = $this->classifier->summarizePeriod($employee->id, '2026-05-11', '2026-05-16');

    expect($result['present_days'])->toBe(1);
    expect($result['late_days'])->toBe(1);
    expect($result['half_day_count'])->toBe(1);
    expect($result['absent_days'])->toBe(1);
    expect($result['paid_leave_days'])->toBe(1);
    expect($result['unpaid_leave_days'])->toBe(0);
});

// ═══════════════════════════════════════════════════════════════════════════════
// 14. summarizePeriod: effective_working_days
// ═══════════════════════════════════════════════════════════════════════════════

it('calculates effective_working_days as scheduled dates minus holidays', function () {
    $employee = createFullTimeEmployee();
    createAttendancePolicy();

    HolidayCalendar::create([
        'date' => '2026-05-13',
        'name' => 'Ascension Day',
        'type' => 'national_holiday',
        'applies_to' => null,
    ]);

    $result = $this->classifier->summarizePeriod($employee->id, '2026-05-11', '2026-05-16');

    // 5 scheduled - 1 holiday = 4 effective working days
    expect($result['effective_working_days'])->toBe(4);
    expect($result['holiday_days'])->toBe(1);
});

it('sets effective_working_days to 0 when all scheduled days are holidays', function () {
    $employee = createFullTimeEmployee();
    createAttendancePolicy();

    foreach (['2026-05-11', '2026-05-12', '2026-05-13', '2026-05-14', '2026-05-15'] as $date) {
        HolidayCalendar::create([
            'date' => $date,
            'name' => 'Holiday',
            'type' => 'national_holiday',
            'applies_to' => null,
        ]);
    }

    $result = $this->classifier->summarizePeriod($employee->id, '2026-05-11', '2026-05-16');

    expect($result['effective_working_days'])->toBe(0);
    expect($result['holiday_days'])->toBe(5);
    expect($result['daily_rate'])->toBe(0.0);
});

// ═══════════════════════════════════════════════════════════════════════════════
// 15. summarizePeriod: deduction_amount
// ═══════════════════════════════════════════════════════════════════════════════

it('calculates deduction_amount from absent, unpaid leave, and half days', function () {
    $employee = createFullTimeEmployee(monthlySalary: 10000000);
    createAttendancePolicy();

    // Mon → present
    createAttendanceRecord($employee, '2026-05-11', '2026-05-11 08:50:00', '2026-05-11 17:00:00', 490);
    // Tue → half_day
    createAttendanceRecord($employee, '2026-05-12', '2026-05-12 10:00:00', '2026-05-12 14:30:00', 270);
    // Wed → absent (record with no check_in)
    createAttendanceRecord($employee, '2026-05-13', null, null, null);
    // Thu → unpaid leave
    LeaveRequest::create([
        'staff_member_id' => $employee->id,
        'leave_type' => 'personal_leave',
        'start_date' => '2026-05-14',
        'end_date' => '2026-05-14',
        'total_days' => 1,
        'status' => 'approved',
    ]);
    $this->leaveValidator
        ->shouldReceive('validate')
        ->atLeast()->once()
        ->andReturn([
            'valid' => true,
            'is_paid_leave' => false,
        ]);
    // Fri → present
    createAttendanceRecord($employee, '2026-05-15', '2026-05-15 08:50:00', '2026-05-15 17:00:00', 490);

    $result = $this->classifier->summarizePeriod($employee->id, '2026-05-11', '2026-05-16');

    // effective_working_days = 5 (no holidays)
    // daily_rate = 10000000 / 5 = 2000000
    // deduction_days = absent(1) + unpaid_leave(1) + half_day(0.5) = 2.5
    // deduction_amount = 2000000 * 2.5 = 5000000
    expect($result['effective_working_days'])->toBe(5);
    expect($result['daily_rate'])->toBe(2000000.0);
    expect($result['deduction_days'])->toBe(2.5);
    expect($result['deduction_amount'])->toBe(5000000.0);
});

it('returns zero deductions when all days are present', function () {
    $employee = createFullTimeEmployee(monthlySalary: 10000000);
    createAttendancePolicy();

    foreach (['2026-05-11', '2026-05-12', '2026-05-13', '2026-05-14', '2026-05-15'] as $date) {
        createAttendanceRecord($employee, $date, "{$date} 08:50:00", "{$date} 17:00:00", 490);
    }

    $result = $this->classifier->summarizePeriod($employee->id, '2026-05-11', '2026-05-16');

    expect($result['deduction_days'])->toBe(0.0);
    expect($result['deduction_amount'])->toBe(0.0);
    expect($result['attended_days'])->toBe(5);
});

// ═══════════════════════════════════════════════════════════════════════════════
// 16. summarizePeriod: attended_days
// ═══════════════════════════════════════════════════════════════════════════════

it('calculates attended_days as present + late', function () {
    $employee = createFullTimeEmployee();
    createAttendancePolicy();

    // Mon → present
    createAttendanceRecord($employee, '2026-05-11', '2026-05-11 08:50:00', '2026-05-11 17:00:00', 490);
    // Tue → late
    createAttendanceRecord($employee, '2026-05-12', '2026-05-12 09:15:00', '2026-05-12 17:00:00', 465);
    // Wed → half_day (not counted as attended)
    createAttendanceRecord($employee, '2026-05-13', '2026-05-13 10:00:00', '2026-05-13 14:30:00', 270);

    $result = $this->classifier->summarizePeriod($employee->id, '2026-05-11', '2026-05-14');

    expect($result['attended_days'])->toBe(2); // present(1) + late(1)
});

// ═══════════════════════════════════════════════════════════════════════════════
// 17. buildWarningFlags: absent_pct_threshold_reached
// ═══════════════════════════════════════════════════════════════════════════════

it('triggers absent_pct_threshold_reached when absent percentage meets warning threshold', function () {
    $employee = createFullTimeEmployee();
    createAttendancePolicy(warningAbsentPct: 15.0);

    // Mon-Wed → absent (record with no check_in)
    createAttendanceRecord($employee, '2026-05-11', null, null, null);
    createAttendanceRecord($employee, '2026-05-12', null, null, null);
    createAttendanceRecord($employee, '2026-05-13', null, null, null);
    // Thu-Fri → present
    createAttendanceRecord($employee, '2026-05-14', '2026-05-14 08:50:00', '2026-05-14 17:00:00', 490);
    createAttendanceRecord($employee, '2026-05-15', '2026-05-15 08:50:00', '2026-05-15 17:00:00', 490);

    $result = $this->classifier->summarizePeriod($employee->id, '2026-05-11', '2026-05-16');

    // 3 absent / 5 effective = 60% ≥ 15% → flag
    expect($result['warning_flags'])->toContain('absent_pct_threshold_reached');
});

it('does not trigger absent_pct_threshold_reached when below threshold', function () {
    $employee = createFullTimeEmployee();
    createAttendancePolicy(warningAbsentPct: 25.0);

    // Mon → absent (no check_in record)
    createAttendanceRecord($employee, '2026-05-11', null, null, null);
    // Tue-Fri → present
    createAttendanceRecord($employee, '2026-05-12', '2026-05-12 08:50:00', '2026-05-12 17:00:00', 490);
    createAttendanceRecord($employee, '2026-05-13', '2026-05-13 08:50:00', '2026-05-13 17:00:00', 490);
    createAttendanceRecord($employee, '2026-05-14', '2026-05-14 08:50:00', '2026-05-14 17:00:00', 490);
    createAttendanceRecord($employee, '2026-05-15', '2026-05-15 08:50:00', '2026-05-15 17:00:00', 490);

    $result = $this->classifier->summarizePeriod($employee->id, '2026-05-11', '2026-05-16');

    // 1 absent / 5 = 20% < 25% → no flag
    expect($result['warning_flags'])->not->toContain('absent_pct_threshold_reached');
});

// ═══════════════════════════════════════════════════════════════════════════════
// 18. buildWarningFlags: unresolved_policy_mismatch
// ═══════════════════════════════════════════════════════════════════════════════

it('includes unresolved_policy_mismatch flag when unresolved mismatches exist', function () {
    $employee = createFullTimeEmployee();
    createAttendancePolicy();

    // Create an attendance record for the date (needed for tracking)
    $attendance = createAttendanceRecord($employee, '2026-05-11', '2026-05-11 08:50:00', '2026-05-11 17:00:00', 490);

    AttendancePolicyMismatch::create([
        'attendance_id' => $attendance->id,
        'staff_member_id' => $employee->id,
        'mismatch_date' => '2026-05-11',
        'planned_work_mode' => 'wfo',
        'actual_work_mode' => 'wfh',
        'status' => AttendancePolicyMismatch::STATUS_PENDING_REVIEW,
    ]);

    $result = $this->classifier->summarizePeriod($employee->id, '2026-05-11', '2026-05-12');

    expect($result['warning_flags'])->toContain('unresolved_policy_mismatch');
});

it('does not include unresolved_policy_mismatch flag when all mismatches are resolved', function () {
    $employee = createFullTimeEmployee();
    createAttendancePolicy();

    $attendance = createAttendanceRecord($employee, '2026-05-11', '2026-05-11 08:50:00', '2026-05-11 17:00:00', 490);

    AttendancePolicyMismatch::create([
        'attendance_id' => $attendance->id,
        'staff_member_id' => $employee->id,
        'mismatch_date' => '2026-05-11',
        'planned_work_mode' => 'wfo',
        'actual_work_mode' => 'wfh',
        'status' => AttendancePolicyMismatch::STATUS_RESOLVED,
    ]);

    $result = $this->classifier->summarizePeriod($employee->id, '2026-05-11', '2026-05-12');

    expect($result['warning_flags'])->not->toContain('unresolved_policy_mismatch');
});

// ═══════════════════════════════════════════════════════════════════════════════
// 19. summarizePeriod: sick_days counted within leave days
// ═══════════════════════════════════════════════════════════════════════════════

it('counts sick_days separately from paid_leave_days', function () {
    $employee = createFullTimeEmployee();
    createAttendancePolicy();

    LeaveRequest::create([
        'staff_member_id' => $employee->id,
        'leave_type' => 'sick_leave',
        'start_date' => '2026-05-11',
        'end_date' => '2026-05-11',
        'total_days' => 1,
        'status' => 'approved',
    ]);

    $this->leaveValidator
        ->shouldReceive('validate')
        ->atLeast()->once()
        ->andReturn([
            'valid' => true,
            'is_paid_leave' => true,
        ]);

    $result = $this->classifier->summarizePeriod($employee->id, '2026-05-11', '2026-05-12');

    expect($result['paid_leave_days'])->toBe(1);
    expect($result['sick_days'])->toBe(1);
});

// ═══════════════════════════════════════════════════════════════════════════════
// 20. Policy mismatch detection in classifyAttendanceRecord
// ═══════════════════════════════════════════════════════════════════════════════

it('sets policy_mismatch_flag true for hybrid employee with planned/actual mode mismatch', function () {
    $employee = createFullTimeEmployee(workLocation: 'hybrid');
    createAttendancePolicy();

    $attendance = createAttendanceRecord(
        $employee,
        date: '2026-05-11',
        checkIn: '2026-05-11 08:50:00',
        checkOut: '2026-05-11 17:00:00',
        workedMinutes: 490,
        actualWorkMode: 'wfh',
    );

    $this->hybridResolver
        ->shouldReceive('resolve')
        ->once()
        ->andReturn([
            'planned_mode' => 'wfo',
            'source' => 'base_schedule',
        ]);

    $this->emailService
        ->shouldReceive('sendAttendanceMismatchCreatedNotification')
        ->once();

    $result = $this->classifier->classify($employee->id, '2026-05-11');

    expect($result['policy_mismatch_flag'])->toBeTrue();
    expect($result['status'])->toBe('present');

    $mismatch = AttendancePolicyMismatch::where('attendance_id', $attendance->id)->first();
    expect($mismatch)->not->toBeNull();
    expect($mismatch->planned_work_mode)->toBe('wfo');
    expect($mismatch->actual_work_mode)->toBe('wfh');
    expect($mismatch->status)->toBe(AttendancePolicyMismatch::STATUS_PENDING_REVIEW);
});

it('clears policy_mismatch_flag for non-hybrid employee', function () {
    $employee = createFullTimeEmployee(workLocation: 'office');
    createAttendancePolicy();

    $attendance = createAttendanceRecord(
        $employee,
        date: '2026-05-11',
        checkIn: '2026-05-11 08:50:00',
        checkOut: '2026-05-11 17:00:00',
        workedMinutes: 490,
        policyMismatchFlag: true,
    );

    $result = $this->classifier->classify($employee->id, '2026-05-11');

    expect($result['policy_mismatch_flag'])->toBeFalse();

    $attendance->refresh();
    expect($attendance->policy_mismatch_flag)->toBeFalse();
});

it('updates existing mismatch record instead of creating a new one', function () {
    $employee = createFullTimeEmployee(workLocation: 'hybrid');
    createAttendancePolicy();

    $attendance = createAttendanceRecord(
        $employee,
        date: '2026-05-11',
        checkIn: '2026-05-11 08:50:00',
        checkOut: '2026-05-11 17:00:00',
        workedMinutes: 490,
        actualWorkMode: 'wfh',
        policyMismatchFlag: true,
    );

    $existingMismatch = AttendancePolicyMismatch::create([
        'attendance_id' => $attendance->id,
        'staff_member_id' => $employee->id,
        'mismatch_date' => '2026-05-10',
        'planned_work_mode' => 'wfo',
        'actual_work_mode' => 'wfo',
        'status' => AttendancePolicyMismatch::STATUS_PENDING_REVIEW,
    ]);

    $this->hybridResolver
        ->shouldReceive('resolve')
        ->once()
        ->andReturn([
            'planned_mode' => 'wfo',
            'source' => 'base_schedule',
        ]);

    $this->emailService
        ->shouldNotReceive('sendAttendanceMismatchCreatedNotification');

    $result = $this->classifier->classify($employee->id, '2026-05-11');

    expect($result['policy_mismatch_flag'])->toBeTrue();

    $existingMismatch->refresh();
    expect($existingMismatch->planned_work_mode)->toBe('wfo');
    expect($existingMismatch->actual_work_mode)->toBe('wfh');
});

it('returns false policy_mismatch_flag for hybrid with no mismatch', function () {
    $employee = createFullTimeEmployee(workLocation: 'hybrid');
    createAttendancePolicy();

    createAttendanceRecord(
        $employee,
        date: '2026-05-11',
        checkIn: '2026-05-11 08:50:00',
        checkOut: '2026-05-11 17:00:00',
        workedMinutes: 490,
        actualWorkMode: 'wfo',
    );

    $this->hybridResolver
        ->shouldReceive('resolve')
        ->once()
        ->andReturn([
            'planned_mode' => 'wfo',
            'source' => 'base_schedule',
        ]);

    $result = $this->classifier->classify($employee->id, '2026-05-11');

    expect($result['policy_mismatch_flag'])->toBeFalse();
});

// ═══════════════════════════════════════════════════════════════════════════════
// 21. Priority: holiday takes precedence over leave
// ═══════════════════════════════════════════════════════════════════════════════

it('returns holiday even when a leave request exists for the same date', function () {
    $employee = createFullTimeEmployee();
    createAttendancePolicy();

    HolidayCalendar::create([
        'date' => '2026-05-11',
        'name' => 'Labour Day',
        'type' => 'national_holiday',
        'applies_to' => null,
    ]);

    LeaveRequest::create([
        'staff_member_id' => $employee->id,
        'leave_type' => 'annual_leave',
        'start_date' => '2026-05-11',
        'end_date' => '2026-05-11',
        'total_days' => 1,
        'status' => 'approved',
    ]);

    $result = $this->classifier->classify($employee->id, '2026-05-11');

    expect($result['status'])->toBe('holiday');
    expect($result['source'])->toBe('holiday');
});

// ═══════════════════════════════════════════════════════════════════════════════
// 22. Priority: leave takes precedence over attendance record
// ═══════════════════════════════════════════════════════════════════════════════

it('returns leave status even when an attendance record exists', function () {
    $employee = createFullTimeEmployee();
    createAttendancePolicy();

    LeaveRequest::create([
        'staff_member_id' => $employee->id,
        'leave_type' => 'sick_leave',
        'start_date' => '2026-05-11',
        'end_date' => '2026-05-11',
        'total_days' => 1,
        'status' => 'approved',
    ]);

    $this->leaveValidator
        ->shouldReceive('validate')
        ->once()
        ->andReturn([
            'valid' => true,
            'is_paid_leave' => true,
        ]);

    createAttendanceRecord(
        $employee,
        date: '2026-05-11',
        checkIn: '2026-05-11 08:50:00',
        checkOut: '2026-05-11 17:00:00',
        workedMinutes: 490,
    );

    $result = $this->classifier->classify($employee->id, '2026-05-11');

    expect($result['status'])->toBe('sick_leave');
    expect($result['source'])->toBe('leave');
});

// ═══════════════════════════════════════════════════════════════════════════════
// 23. summarizePeriod: policy_mismatch_days counted correctly
// ═══════════════════════════════════════════════════════════════════════════════

it('counts policy_mismatch_days for hybrid employees with mismatches', function () {
    $employee = createFullTimeEmployee(workLocation: 'hybrid');
    createAttendancePolicy();

    // Mon → attendance with mismatch (actual=wfh, planned=wfo)
    createAttendanceRecord(
        $employee,
        date: '2026-05-11',
        checkIn: '2026-05-11 08:50:00',
        checkOut: '2026-05-11 17:00:00',
        workedMinutes: 490,
        actualWorkMode: 'wfh',
    );

    // Tue → attendance without mismatch (actual=wfo, planned=wfo)
    createAttendanceRecord(
        $employee,
        date: '2026-05-12',
        checkIn: '2026-05-12 08:50:00',
        checkOut: '2026-05-12 17:00:00',
        workedMinutes: 490,
        actualWorkMode: 'wfo',
    );

    $this->hybridResolver
        ->shouldReceive('resolve')
        ->andReturnUsing(function ($empId, $date) {
            return [
                'planned_mode' => 'wfo',
                'source' => 'base_schedule',
            ];
        });

    $this->emailService
        ->shouldReceive('sendAttendanceMismatchCreatedNotification')
        ->once();

    $result = $this->classifier->summarizePeriod($employee->id, '2026-05-11', '2026-05-13');

    expect($result['policy_mismatch_days'])->toBe(1);
});

// ═══════════════════════════════════════════════════════════════════════════════
// 24. summarizePeriod: unpaid leave days
// ═══════════════════════════════════════════════════════════════════════════════

it('counts unpaid_leave_days for personal leave with is_paid_leave false', function () {
    $employee = createFullTimeEmployee();
    createAttendancePolicy();

    LeaveRequest::create([
        'staff_member_id' => $employee->id,
        'leave_type' => 'personal_leave',
        'start_date' => '2026-05-11',
        'end_date' => '2026-05-11',
        'total_days' => 1,
        'status' => 'approved',
    ]);

    $this->leaveValidator
        ->shouldReceive('validate')
        ->atLeast()->once()
        ->andReturn([
            'valid' => true,
            'is_paid_leave' => false,
        ]);

    $result = $this->classifier->summarizePeriod($employee->id, '2026-05-11', '2026-05-12');

    expect($result['unpaid_leave_days'])->toBe(1);
    expect($result['paid_leave_days'])->toBe(0);
});

// ═══════════════════════════════════════════════════════════════════════════════
// 25. Edge case: multiple approved leaves for same date
// ═══════════════════════════════════════════════════════════════════════════════

it('uses the first valid leave when multiple approved leaves exist for the same date', function () {
    $employee = createFullTimeEmployee();
    createAttendancePolicy();

    $leave1 = LeaveRequest::create([
        'staff_member_id' => $employee->id,
        'leave_type' => 'sick_leave',
        'start_date' => '2026-05-11',
        'end_date' => '2026-05-13',
        'total_days' => 3,
        'status' => 'approved',
    ]);

    $leave2 = LeaveRequest::create([
        'staff_member_id' => $employee->id,
        'leave_type' => 'annual_leave',
        'start_date' => '2026-05-11',
        'end_date' => '2026-05-11',
        'total_days' => 1,
        'status' => 'approved',
    ]);

    // First leave invalid, second valid — use flexible mock matching
    $callCount = 0;
    $this->leaveValidator
        ->shouldReceive('validate')
        ->twice()
        ->andReturnUsing(function () use (&$callCount) {
            $callCount++;

            return $callCount === 1
                ? ['valid' => false, 'is_paid_leave' => false]
                : ['valid' => true, 'is_paid_leave' => true];
        });

    $result = $this->classifier->classify($employee->id, '2026-05-11');

    expect($result['status'])->toBe('annual_leave');
    expect($result['source'])->toBe('leave');
});

// ═══════════════════════════════════════════════════════════════════════════════
// 26. Edge case: part_time employee with shorter grace period
// ═══════════════════════════════════════════════════════════════════════════════

it('applies part_time grace period of 20 minutes', function () {
    $employee = createFullTimeEmployee(employmentType: 'part_time');
    createAttendancePolicy('part_time', '09:00:00', 20, 2.0, 20.0);

    // check_in at 09:19 → within 20-min grace → late
    createAttendanceRecord($employee, '2026-05-11', '2026-05-11 09:19:00', '2026-05-11 12:00:00', 161);

    $result = $this->classifier->classify($employee->id, '2026-05-11');

    expect($result['status'])->toBe('late');
});

it('classifies part_time employee as half_day with 2-hour minimum', function () {
    $employee = createFullTimeEmployee(employmentType: 'part_time');
    createAttendancePolicy('part_time', '09:00:00', 20, 2.0, 20.0);

    // check_in at 09:25 (after grace), worked 130 min (≥ 120 = 2h)
    createAttendanceRecord($employee, '2026-05-11', '2026-05-11 09:25:00', '2026-05-11 11:35:00', 130);

    $result = $this->classifier->classify($employee->id, '2026-05-11');

    expect($result['status'])->toBe('half_day');
});

// ═══════════════════════════════════════════════════════════════════════════════
// 27. Edge case: intern with 3-hour half-day threshold
// ═══════════════════════════════════════════════════════════════════════════════

it('applies intern half_day_min_hours of 3.0', function () {
    $employee = createFullTimeEmployee(employmentType: 'intern');
    createAttendancePolicy('intern', '09:00:00', 30, 3.0, 20.0);

    // check_in at 10:00 (after grace), worked 190 min (≥ 180 = 3h)
    createAttendanceRecord($employee, '2026-05-11', '2026-05-11 10:00:00', '2026-05-11 13:10:00', 190);

    $result = $this->classifier->classify($employee->id, '2026-05-11');

    expect($result['status'])->toBe('half_day');
});

it('classifies intern as absent when worked minutes below 3-hour threshold', function () {
    $employee = createFullTimeEmployee(employmentType: 'intern');
    createAttendancePolicy('intern', '09:00:00', 30, 3.0, 20.0);

    // check_in at 10:00, worked 150 min (< 180 = 3h)
    createAttendanceRecord($employee, '2026-05-11', '2026-05-11 10:00:00', '2026-05-11 12:30:00', 150);

    $result = $this->classifier->classify($employee->id, '2026-05-11');

    expect($result['status'])->toBe('absent');
});

// ═══════════════════════════════════════════════════════════════════════════════
// 28. Default policy fallback when no AttendancePolicy record exists
// ═══════════════════════════════════════════════════════════════════════════════

it('uses default policy constants when no AttendancePolicy record exists', function () {
    $employee = createFullTimeEmployee();
    // No createAttendancePolicy() → falls back to DEFAULT_POLICIES['full_time']

    createAttendanceRecord($employee, '2026-05-11', '2026-05-11 09:00:00', '2026-05-11 17:00:00', 480);

    $result = $this->classifier->classify($employee->id, '2026-05-11');

    expect($result['status'])->toBe('present');
});

// ═══════════════════════════════════════════════════════════════════════════════
// 29. summarizePeriod: handles period with no scheduled dates
// ═══════════════════════════════════════════════════════════════════════════════

it('returns all zeros for a period with no scheduled working days', function () {
    $employee = createFullTimeEmployee();
    createAttendancePolicy(); // Mon-Fri only

    $result = $this->classifier->summarizePeriod($employee->id, '2026-05-16', '2026-05-17');

    expect($result['present_days'])->toBe(0);
    expect($result['late_days'])->toBe(0);
    expect($result['half_day_count'])->toBe(0);
    expect($result['absent_days'])->toBe(0);
    expect($result['paid_leave_days'])->toBe(0);
    expect($result['unpaid_leave_days'])->toBe(0);
    expect($result['effective_working_days'])->toBe(0);
    expect($result['deduction_days'])->toBe(0.0);
    expect($result['deduction_amount'])->toBe(0.0);
});

// ═══════════════════════════════════════════════════════════════════════════════
// 30. summarizePeriod: handles single non-working day
// ═══════════════════════════════════════════════════════════════════════════════

it('returns all zeros for a single-day non-working period', function () {
    $employee = createFullTimeEmployee();
    createAttendancePolicy();

    // Saturday to Sunday — both non-working days for full_time
    $result = $this->classifier->summarizePeriod($employee->id, '2026-05-16', '2026-05-17');

    expect($result['effective_working_days'])->toBe(0);
    expect($result['present_days'])->toBe(0);
    expect($result['absent_days'])->toBe(0);
});

// ═══════════════════════════════════════════════════════════════════════════════
// 31. classify() accepts Carbon date objects
// ═══════════════════════════════════════════════════════════════════════════════

it('accepts Carbon date object as date parameter', function () {
    $employee = createFullTimeEmployee();
    createAttendancePolicy();

    createAttendanceRecord($employee, '2026-05-11', '2026-05-11 08:50:00', '2026-05-11 17:00:00', 490);

    $result = $this->classifier->classify($employee->id, Carbon::parse('2026-05-11'));

    expect($result['status'])->toBe('present');
});

it('accepts Carbon date objects for summarizePeriod', function () {
    $employee = createFullTimeEmployee();
    createAttendancePolicy();

    $result = $this->classifier->summarizePeriod(
        $employee->id,
        Carbon::parse('2026-05-11'),
        Carbon::parse('2026-05-15'),
    );

    expect($result)->toHaveKeys([
        'present_days', 'late_days', 'half_day_count',
        'paid_leave_days', 'unpaid_leave_days', 'holiday_days',
        'absent_days', 'sick_days', 'policy_mismatch_days',
        'effective_working_days', 'daily_rate', 'deduction_days',
        'deduction_amount', 'attended_days', 'warning_flags',
    ]);
});

// ═══════════════════════════════════════════════════════════════════════════════
// 32. Holiday caching
// ═══════════════════════════════════════════════════════════════════════════════

it('caches holiday lookups within a single classify call', function () {
    $employee = createFullTimeEmployee();
    createAttendancePolicy();

    HolidayCalendar::create([
        'date' => '2026-05-11',
        'name' => 'May Day',
        'type' => 'national_holiday',
        'applies_to' => null,
    ]);

    $result1 = $this->classifier->classify($employee->id, '2026-05-11');
    $result2 = $this->classifier->classify($employee->id, '2026-05-11');

    expect($result1['status'])->toBe('holiday');
    expect($result2['status'])->toBe('holiday');
});

// ═══════════════════════════════════════════════════════════════════════════════
// 33. Edge case: late employee not counted in attended_days in summary
// ═══════════════════════════════════════════════════════════════════════════════

it('does not include half_day in attended_days count', function () {
    $employee = createFullTimeEmployee();
    createAttendancePolicy();

    // Half day
    createAttendanceRecord($employee, '2026-05-11', '2026-05-11 10:00:00', '2026-05-11 14:30:00', 270);
    // Present
    createAttendanceRecord($employee, '2026-05-12', '2026-05-12 08:50:00', '2026-05-12 17:00:00', 490);

    $result = $this->classifier->summarizePeriod($employee->id, '2026-05-11', '2026-05-13');

    // attended = present(1) + late(0) = 1, half_day is NOT included
    expect($result['attended_days'])->toBe(1);
    expect($result['half_day_count'])->toBe(1);
});

// ═══════════════════════════════════════════════════════════════════════════════
// 34. Edge case: multi-day leave spanning across the summary period
// ═══════════════════════════════════════════════════════════════════════════════

it('handles multi-day leave spanning across the summary period', function () {
    $employee = createFullTimeEmployee();
    createAttendancePolicy();

    LeaveRequest::create([
        'staff_member_id' => $employee->id,
        'leave_type' => 'annual_leave',
        'start_date' => '2026-05-11',
        'end_date' => '2026-05-15',
        'total_days' => 5,
        'status' => 'approved',
    ]);

    $this->leaveValidator
        ->shouldReceive('validate')
        ->atLeast()->once()
        ->andReturn([
            'valid' => true,
            'is_paid_leave' => true,
        ]);

    $result = $this->classifier->summarizePeriod($employee->id, '2026-05-11', '2026-05-16');

    expect($result['paid_leave_days'])->toBe(5);
    expect($result['absent_days'])->toBe(0);
});

// ═══════════════════════════════════════════════════════════════════════════════
// 35. summarizePeriod: deduction_days rounding
// ═══════════════════════════════════════════════════════════════════════════════

it('rounds deduction_days to 2 decimal places', function () {
    $employee = createFullTimeEmployee(monthlySalary: 10000000);
    createAttendancePolicy();

    // Half day only
    createAttendanceRecord($employee, '2026-05-11', '2026-05-11 10:00:00', '2026-05-11 14:30:00', 270);

    $result = $this->classifier->summarizePeriod($employee->id, '2026-05-11', '2026-05-12');

    expect($result['deduction_days'])->toBe(0.5);
});

// ═══════════════════════════════════════════════════════════════════════════════
// 36. classify(): remote employee with attendance record → uses record
// ═══════════════════════════════════════════════════════════════════════════════

it('uses attendance record for remote employee when one exists', function () {
    $employee = createFullTimeEmployee(workLocation: 'remote');
    createAttendancePolicy();

    createAttendanceRecord($employee, '2026-05-11', '2026-05-11 08:50:00', '2026-05-11 17:00:00', 490);

    $result = $this->classifier->classify($employee->id, '2026-05-11');

    expect($result['status'])->toBe('present');
    expect($result['source'])->toBe('attendance');
});

// ═══════════════════════════════════════════════════════════════════════════════
// 37. summarizePeriod: sick leave also counted in paid_leave_days
// ═══════════════════════════════════════════════════════════════════════════════

it('counts sick leave in both paid_leave_days and sick_days', function () {
    $employee = createFullTimeEmployee();
    createAttendancePolicy();

    LeaveRequest::create([
        'staff_member_id' => $employee->id,
        'leave_type' => 'sick_leave',
        'start_date' => '2026-05-11',
        'end_date' => '2026-05-12',
        'total_days' => 2,
        'status' => 'approved',
    ]);

    $this->leaveValidator
        ->shouldReceive('validate')
        ->atLeast()->once()
        ->andReturn([
            'valid' => true,
            'is_paid_leave' => true,
        ]);

    $result = $this->classifier->summarizePeriod($employee->id, '2026-05-11', '2026-05-13');

    expect($result['paid_leave_days'])->toBe(2);
    expect($result['sick_days'])->toBe(2);
});
