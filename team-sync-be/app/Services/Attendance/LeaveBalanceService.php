<?php

namespace App\Services\Attendance;

use App\Models\AttendancePolicy;
use App\Models\HolidayCalendar;
use App\Models\LeaveEntitlement;
use App\Models\LeaveRequest;
use App\Models\StaffMemberProfile;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class LeaveBalanceService
{
    private const DEFAULT_WORKING_WEEKDAYS_BY_EMPLOYMENT_TYPE = [
        'full_time' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
        'contract' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
        'intern' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
        'part_time' => ['monday', 'wednesday', 'friday'],
    ];

    public function getEmployeeBalances(int $employeeId, ?string $asOfDate = null): Collection
    {
        $employee = StaffMemberProfile::with('jobInformation')->find($employeeId);
        if (! $employee) {
            return collect();
        }

        $employmentType = $employee->jobInformation?->employment_type ?? '';
        $employmentType = $this->normalizeEmploymentType($employmentType);

        $entitlements = LeaveEntitlement::where('employment_type', $employmentType)
            ->where('is_eligible', true)
            ->get();

        $targetDate = $asOfDate ? Carbon::parse($asOfDate) : now();
        $yearStart = $targetDate->copy()->startOfYear()->toDateString();
        $yearEnd = $targetDate->copy()->endOfYear()->toDateString();

        $scheduledWeekdays = $this->resolveScheduledWeekdays($employmentType);

        return $entitlements->map(function (LeaveEntitlement $entitlement) use ($employeeId, $employmentType, $yearStart, $yearEnd, $scheduledWeekdays) {
            $usedDays = 0;
            if ($entitlement->quota_scope === 'annual') {
                $approvedLeaves = LeaveRequest::where('staff_member_id', $employeeId)
                    ->where('status', 'approved')
                    ->where('leave_type', $entitlement->leave_type)
                    ->whereDate('start_date', '<=', $yearEnd)
                    ->whereDate('end_date', '>=', $yearStart)
                    ->get();

                foreach ($approvedLeaves as $leave) {
                    $usedDays += $this->countWorkingLeaveDays(
                        $employmentType,
                        Carbon::parse($leave->start_date)->startOfDay(),
                        Carbon::parse($leave->end_date)->endOfDay(),
                        $scheduledWeekdays
                    );
                }
            }

            return [
                'leave_type' => $entitlement->leave_type,
                'quota_scope' => $entitlement->quota_scope,
                'quota_days' => $entitlement->quota_days ? (float) $entitlement->quota_days : null,
                'used_days' => $usedDays,
                'remaining_days' => $entitlement->quota_days !== null ? max(0, (float) $entitlement->quota_days - $usedDays) : null,
                'is_paid' => $entitlement->is_paid,
                'requires_attachment' => $entitlement->requires_attachment,
            ];
        });
    }

    private function countWorkingLeaveDays(
        string $employmentType,
        CarbonInterface $startDate,
        CarbonInterface $endDate,
        Collection $scheduledWeekdays
    ): int {
        $days = 0;
        $cursor = Carbon::parse($startDate)->startOfDay();

        while ($cursor->lessThanOrEqualTo($endDate)) {
            if ($scheduledWeekdays->contains(strtolower($cursor->englishDayOfWeek))
                && ! $this->isHolidayForEmploymentType($employmentType, $cursor, $scheduledWeekdays)) {
                $days++;
            }

            $cursor->addDay();
        }

        return $days;
    }

    private function isHolidayForEmploymentType(
        string $employmentType,
        CarbonInterface $date,
        Collection $scheduledWeekdays
    ): bool {
        if (! $scheduledWeekdays->contains(strtolower($date->englishDayOfWeek))) {
            return false;
        }

        return HolidayCalendar::query()
            ->whereDate('date', $date->toDateString())
            ->get()
            ->contains(function (HolidayCalendar $holiday) use ($employmentType) {
                $appliesTo = $holiday->applies_to;

                return $appliesTo === null || in_array($employmentType, $appliesTo, true);
            });
    }

    private function resolveScheduledWeekdays(string $employmentType): Collection
    {
        $policy = AttendancePolicy::query()
            ->where('employment_type', $employmentType)
            ->first();

        $weekdays = $policy?->default_working_weekdays
            ?? self::DEFAULT_WORKING_WEEKDAYS_BY_EMPLOYMENT_TYPE[$employmentType]
            ?? self::DEFAULT_WORKING_WEEKDAYS_BY_EMPLOYMENT_TYPE['full_time'];

        return collect($weekdays)
            ->map(fn ($weekday) => strtolower((string) $weekday))
            ->values();
    }

    private function normalizeEmploymentType(string $employmentType): string
    {
        return match ($employmentType) {
            'internship' => 'intern',
            'freelance' => 'contract',
            default => $employmentType,
        };
    }
}
