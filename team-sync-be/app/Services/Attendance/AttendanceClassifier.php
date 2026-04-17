<?php

namespace App\Services\Attendance;

use App\Models\Attendance;
use App\Models\AttendancePolicy;
use App\Models\AttendancePolicyMismatch;
use App\Models\EmployeeProfile;
use App\Models\HolidayCalendar;
use App\Models\LeaveRequest;
use App\Services\EmailService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Collection;

class AttendanceClassifier
{
    private const DEFAULT_POLICIES = [
        'full_time' => [
            'work_start_time' => '09:00:00',
            'late_grace_minutes' => 30,
            'half_day_min_hours' => 4.00,
            'warning_absent_pct' => 15.00,
            'default_working_weekdays' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
        ],
        'contract' => [
            'work_start_time' => '09:00:00',
            'late_grace_minutes' => 30,
            'half_day_min_hours' => 4.00,
            'warning_absent_pct' => 15.00,
            'default_working_weekdays' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
        ],
        'intern' => [
            'work_start_time' => '09:00:00',
            'late_grace_minutes' => 30,
            'half_day_min_hours' => 3.00,
            'warning_absent_pct' => 20.00,
            'default_working_weekdays' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
        ],
        'part_time' => [
            'work_start_time' => '09:00:00',
            'late_grace_minutes' => 20,
            'half_day_min_hours' => 2.00,
            'warning_absent_pct' => 20.00,
            'default_working_weekdays' => ['monday', 'wednesday', 'friday'],
        ],
    ];

    private array $holidayCache = [];

    public function __construct(
        private readonly HybridScheduleResolver $hybridScheduleResolver,
        private readonly LeaveEntitlementValidator $leaveEntitlementValidator,
        private readonly EmailService $emailService,
    ) {}

    public function classify(int $employeeId, CarbonInterface|string $date): array
    {
        $context = $this->buildContext($employeeId);

        return $this->classifyWithContext($context, Carbon::parse($date)->startOfDay());
    }

    public function summarizePeriod(int $employeeId, CarbonInterface|string $startDate, CarbonInterface|string $endDate): array
    {
        $context = $this->buildContext($employeeId);
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        $scheduledDates = $this->scheduledDatesWithinPeriod(
            $context['scheduled_weekdays'],
            $start,
            $end
        );

        $summary = [
            'present_days' => 0,
            'late_days' => 0,
            'half_day_count' => 0,
            'paid_leave_days' => 0,
            'unpaid_leave_days' => 0,
            'holiday_days' => $this->countHolidayDays($context, $scheduledDates),
            'absent_days' => 0,
            'sick_days' => 0,
            'policy_mismatch_days' => 0,
        ];

        $trackedDates = $this->trackedDatesWithinPeriod($context, $start, $end, $scheduledDates);

        foreach ($trackedDates as $dateKey) {
            $classification = $this->classifyWithContext($context, Carbon::parse($dateKey)->startOfDay());
            $status = $classification['status'];

            if ($status === 'present') {
                $summary['present_days']++;
            } elseif ($status === 'late') {
                $summary['late_days']++;
            } elseif ($status === 'half_day') {
                $summary['half_day_count']++;
            } elseif ($status === 'absent') {
                $summary['absent_days']++;
            } elseif ($classification['source'] === 'leave') {
                if ($classification['is_paid_leave']) {
                    $summary['paid_leave_days']++;
                } else {
                    $summary['unpaid_leave_days']++;
                }

                if ($status === 'sick_leave') {
                    $summary['sick_days']++;
                }
            }

            if ($classification['policy_mismatch_flag']) {
                $summary['policy_mismatch_days']++;
            }
        }

        $effectiveWorkingDays = max(0, count($scheduledDates) - $summary['holiday_days']);
        $monthlySalary = (float) ($context['employee']->jobInformation?->monthly_salary ?? 0);
        $dailyRate = $effectiveWorkingDays > 0 ? $monthlySalary / $effectiveWorkingDays : 0;
        $deductionDays = $summary['absent_days'] + $summary['unpaid_leave_days'] + ($summary['half_day_count'] * 0.5);

        return [
            ...$summary,
            'effective_working_days' => $effectiveWorkingDays,
            'daily_rate' => round($dailyRate, 2),
            'deduction_days' => round($deductionDays, 2),
            'deduction_amount' => round($dailyRate * $deductionDays, 2),
            'attended_days' => $summary['present_days'] + $summary['late_days'],
            'warning_flags' => $this->buildWarningFlags($context, $summary, $effectiveWorkingDays, $start, $end),
        ];
    }

    private function trackedDatesWithinPeriod(
        array $context,
        CarbonInterface $startDate,
        CarbonInterface $endDate,
        array $scheduledDates
    ): array {
        $scheduledLookup = array_fill_keys($scheduledDates, true);
        $trackedDates = [];

        $attendanceDates = Attendance::query()
            ->where('employee_id', $context['employee']->id)
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->pluck('date');

        foreach ($attendanceDates as $attendanceDate) {
            $dateKey = Carbon::parse($attendanceDate)->toDateString();
            if (isset($scheduledLookup[$dateKey])) {
                $trackedDates[$dateKey] = true;
            }
        }

        $approvedLeaves = LeaveRequest::query()
            ->where('employee_id', $context['employee']->id)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $endDate->toDateString())
            ->whereDate('end_date', '>=', $startDate->toDateString())
            ->get(['start_date', 'end_date']);

        foreach ($approvedLeaves as $approvedLeave) {
            $cursor = Carbon::parse($approvedLeave->start_date)->startOfDay();
            if ($cursor->lessThan($startDate)) {
                $cursor = Carbon::parse($startDate)->startOfDay();
            }

            $leaveEnd = Carbon::parse($approvedLeave->end_date)->endOfDay();
            if ($leaveEnd->greaterThan($endDate)) {
                $leaveEnd = Carbon::parse($endDate)->endOfDay();
            }

            while ($cursor->lessThanOrEqualTo($leaveEnd)) {
                $dateKey = $cursor->toDateString();
                if (isset($scheduledLookup[$dateKey])) {
                    $trackedDates[$dateKey] = true;
                }
                $cursor->addDay();
            }
        }

        ksort($trackedDates);

        return array_keys($trackedDates);
    }

    private function countHolidayDays(array $context, array $scheduledDates): int
    {
        $holidayDays = 0;

        foreach ($scheduledDates as $scheduledDate) {
            if ($this->isHolidayForDate($context, Carbon::parse($scheduledDate)->startOfDay())) {
                $holidayDays++;
            }
        }

        return $holidayDays;
    }

    private function classifyWithContext(array $context, CarbonInterface $date): array
    {
        if ($this->isHolidayForDate($context, $date)) {
            return [
                'status' => 'holiday',
                'source' => 'holiday',
                'is_paid_leave' => false,
                'policy_mismatch_flag' => false,
            ];
        }

        $leaveClassification = $this->resolvePayrollValidLeaveForDate($context, $date);
        if ($leaveClassification !== null) {
            return [
                'status' => $leaveClassification['leave_type'],
                'source' => 'leave',
                'is_paid_leave' => $leaveClassification['is_paid'],
                'policy_mismatch_flag' => false,
            ];
        }

        $attendance = Attendance::query()
            ->where('employee_id', $context['employee']->id)
            ->whereDate('date', $date->toDateString())
            ->first();

        if ($attendance) {
            $status = $this->classifyAttendanceRecord($attendance, $context, $date);
            $policyMismatchFlag = $this->syncPolicyMismatchState($attendance, $context, $date);

            return [
                'status' => $status,
                'source' => 'attendance',
                'is_paid_leave' => false,
                'policy_mismatch_flag' => $policyMismatchFlag,
            ];
        }

        return [
            'status' => 'absent',
            'source' => 'absent',
            'is_paid_leave' => false,
            'policy_mismatch_flag' => false,
        ];
    }

    private function classifyAttendanceRecord(Attendance $attendance, array $context, CarbonInterface $date): string
    {
        if (! $attendance->check_in) {
            return 'absent';
        }

        $workStart = Carbon::parse($date->toDateString().' '.$context['policy']['work_start_time']);
        $lateThreshold = $workStart->copy()->addMinutes((int) $context['policy']['late_grace_minutes']);
        $checkIn = Carbon::parse($attendance->check_in);

        if ($checkIn->lessThanOrEqualTo($workStart)) {
            return 'present';
        }

        if ($checkIn->greaterThan($workStart) && $checkIn->lessThanOrEqualTo($lateThreshold)) {
            return 'late';
        }

        $workedMinutes = $attendance->worked_minutes;
        if ($workedMinutes === null) {
            return 'absent';
        }

        $halfDayThresholdMinutes = (int) round(((float) $context['policy']['half_day_min_hours']) * 60);

        return (int) $workedMinutes >= $halfDayThresholdMinutes ? 'half_day' : 'absent';
    }

    private function syncPolicyMismatchState(Attendance $attendance, array $context, CarbonInterface $date): bool
    {
        if ($context['work_location'] !== 'hybrid') {
            if ($attendance->policy_mismatch_flag) {
                $attendance->update(['policy_mismatch_flag' => false]);
            }

            return false;
        }

        $resolved = $this->hybridScheduleResolver->resolve($attendance->employee_id, $date);
        $plannedMode = $resolved['planned_mode'] ?? null;
        $actualMode = $attendance->actual_work_mode;

        $mismatch = $plannedMode !== null
            && $actualMode !== null
            && $plannedMode !== $actualMode;

        if ((bool) $attendance->policy_mismatch_flag !== $mismatch) {
            $attendance->update(['policy_mismatch_flag' => $mismatch]);
        }

        if (! $mismatch) {
            return false;
        }

        $existing = AttendancePolicyMismatch::query()
            ->where('attendance_id', $attendance->id)
            ->first();

        if ($existing) {
            $existing->update([
                'mismatch_date' => $date->toDateString(),
                'planned_work_mode' => $plannedMode,
                'actual_work_mode' => $actualMode,
            ]);

            return true;
        }

        $createdMismatch = AttendancePolicyMismatch::query()->create([
            'attendance_id' => $attendance->id,
            'employee_id' => $attendance->employee_id,
            'mismatch_date' => $date->toDateString(),
            'planned_work_mode' => $plannedMode,
            'actual_work_mode' => $actualMode,
            'status' => AttendancePolicyMismatch::STATUS_PENDING_REVIEW,
        ]);

        $this->emailService->sendAttendanceMismatchCreatedNotification(
            $createdMismatch->fresh(['employee.user', 'employee.jobInformation'])
        );

        return true;
    }

    private function resolvePayrollValidLeaveForDate(array $context, CarbonInterface $date): ?array
    {
        $leaveRequests = LeaveRequest::query()
            ->where('employee_id', $context['employee']->id)
            ->where('status', 'approved')
            ->whereDate('start_date', '<=', $date->toDateString())
            ->whereDate('end_date', '>=', $date->toDateString())
            ->orderBy('start_date')
            ->get();

        /** @var LeaveRequest $leaveRequest */
        foreach ($leaveRequests as $leaveRequest) {
            $validation = $this->leaveEntitlementValidator->validate($leaveRequest, $date);
            if (! ($validation['valid'] ?? false)) {
                continue;
            }

            $leaveType = $this->leaveTypeValue($leaveRequest);

            return [
                'leave_type' => $leaveType,
                'is_paid' => (bool) ($validation['is_paid_leave'] ?? false),
            ];
        }

        return null;
    }

    private function isHolidayForDate(array $context, CarbonInterface $date): bool
    {
        if (! $this->isScheduledWorkingDay($context, $date)) {
            return false;
        }

        $dateKey = $date->toDateString();
        $cacheKey = $context['employment_type'].'|'.$dateKey;

        if (array_key_exists($cacheKey, $this->holidayCache)) {
            return $this->holidayCache[$cacheKey];
        }

        $isHoliday = HolidayCalendar::query()
            ->whereDate('date', $dateKey)
            ->get()
            ->contains(function (HolidayCalendar $holiday) use ($context) {
                $appliesTo = $holiday->applies_to;

                return $appliesTo === null || in_array($context['employment_type'], $appliesTo, true);
            });

        $this->holidayCache[$cacheKey] = $isHoliday;

        return $isHoliday;
    }

    private function isScheduledWorkingDay(array $context, CarbonInterface $date): bool
    {
        return $context['scheduled_weekdays']->contains(strtolower($date->englishDayOfWeek));
    }

    private function scheduledDatesWithinPeriod(Collection $scheduledWeekdays, CarbonInterface $startDate, CarbonInterface $endDate): array
    {
        $scheduledDates = [];
        $cursor = Carbon::parse($startDate)->startOfDay();

        while ($cursor->lessThanOrEqualTo($endDate)) {
            if ($scheduledWeekdays->contains(strtolower($cursor->englishDayOfWeek))) {
                $scheduledDates[] = $cursor->toDateString();
            }

            $cursor->addDay();
        }

        return $scheduledDates;
    }

    private function buildContext(int $employeeId): array
    {
        $employee = EmployeeProfile::query()
            ->with('jobInformation')
            ->find($employeeId);

        if (! $employee) {
            throw (new ModelNotFoundException())->setModel(EmployeeProfile::class, [$employeeId]);
        }

        $employmentType = (string) ($employee->jobInformation?->employment_type ?? 'full_time');
        $employmentType = $this->normalizeEmploymentType($employmentType);
        $policy = $this->resolvePolicy($employmentType);

        return [
            'employee' => $employee,
            'employment_type' => $employmentType,
            'work_location' => (string) ($employee->jobInformation?->work_location ?? 'office'),
            'policy' => $policy,
            'scheduled_weekdays' => collect($policy['default_working_weekdays'])
                ->map(fn ($day) => strtolower((string) $day))
                ->values(),
        ];
    }

    private function resolvePolicy(string $employmentType): array
    {
        $policy = AttendancePolicy::query()
            ->where('employment_type', $employmentType)
            ->first();

        if ($policy) {
            return [
                'work_start_time' => $policy->work_start_time,
                'late_grace_minutes' => (int) $policy->late_grace_minutes,
                'half_day_min_hours' => (float) $policy->half_day_min_hours,
                'warning_absent_pct' => (float) $policy->warning_absent_pct,
                'default_working_weekdays' => $policy->default_working_weekdays ?? [],
            ];
        }

        return self::DEFAULT_POLICIES[$employmentType] ?? self::DEFAULT_POLICIES['full_time'];
    }

    private function leaveTypeValue(LeaveRequest $leaveRequest): string
    {
        $leaveType = $leaveRequest->leave_type;

        if ($leaveType instanceof \BackedEnum) {
            return (string) $leaveType->value;
        }

        return (string) $leaveType;
    }

    private function normalizeEmploymentType(string $employmentType): string
    {
        return match ($employmentType) {
            'internship' => 'intern',
            'freelance' => 'contract',
            default => $employmentType,
        };
    }

    private function buildWarningFlags(
        array $context,
        array $summary,
        int $effectiveWorkingDays,
        CarbonInterface $startDate,
        CarbonInterface $endDate
    ): array {
        $flags = [];

        if ($effectiveWorkingDays > 0) {
            $absentPct = ($summary['absent_days'] / $effectiveWorkingDays) * 100;
            if ($absentPct >= (float) $context['policy']['warning_absent_pct']) {
                $flags[] = 'absent_pct_threshold_reached';
            }
        }

        $hasUnresolvedMismatch = AttendancePolicyMismatch::query()
            ->where('employee_id', $context['employee']->id)
            ->whereBetween('mismatch_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->whereIn('status', AttendancePolicyMismatch::UNRESOLVED_STATUSES)
            ->exists();

        if ($hasUnresolvedMismatch) {
            $flags[] = 'unresolved_policy_mismatch';
        }

        return $flags;
    }
}