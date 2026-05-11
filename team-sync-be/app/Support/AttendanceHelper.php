<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\AttendancePolicy;
use App\Models\HolidayCalendar;
use App\Models\LeaveRequest;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Shared attendance/leave utilities extracted from duplicated code
 * across LeaveBalanceService, LeaveEntitlementValidator, AttendanceClassifier,
 * and PayrollRepository.
 */
final class AttendanceHelper
{
    /**
     * Default working weekdays by employment type.
     * Used when no AttendancePolicy is configured for the employment type.
     */
    public const DEFAULT_WORKING_WEEKDAYS_BY_EMPLOYMENT_TYPE = [
        'full_time' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
        'contract' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
        'intern' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
        'part_time' => ['monday', 'wednesday', 'friday'],
    ];

    /**
     * Normalize employment type aliases to canonical values.
     */
    public static function normalizeEmploymentType(string $employmentType): string
    {
        return match ($employmentType) {
            'internship' => 'intern',
            'freelance' => 'contract',
            default => $employmentType,
        };
    }

    /**
     * Check if a date is a holiday applicable to the given employment type.
     *
     * @param  string         $employmentType  Normalized employment type
     * @param  CarbonInterface $date           Date to check
     * @param  Collection     $scheduledWeekdays  Lowercase weekday names the employee works
     */
    public static function isHolidayForEmploymentType(
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

    /**
     * Count working leave days between two dates (inclusive),
     * excluding weekends (per schedule) and holidays.
     *
     * @param  string         $employmentType  Normalized employment type
     * @param  CarbonInterface $startDate
     * @param  CarbonInterface $endDate
     * @param  Collection     $scheduledWeekdays  Lowercase weekday names
     */
    public static function countWorkingLeaveDays(
        string $employmentType,
        CarbonInterface $startDate,
        CarbonInterface $endDate,
        Collection $scheduledWeekdays
    ): int {
        $days = 0;
        $cursor = Carbon::parse($startDate)->startOfDay();

        while ($cursor->lessThanOrEqualTo($endDate)) {
            if ($scheduledWeekdays->contains(strtolower($cursor->englishDayOfWeek))
                && ! self::isHolidayForEmploymentType($employmentType, $cursor, $scheduledWeekdays)) {
                $days++;
            }

            $cursor->addDay();
        }

        return $days;
    }

    /**
     * Resolve scheduled weekdays for an employment type.
     *
     * Queries AttendancePolicy first, falls back to DEFAULT_WORKING_WEEKDAYS_BY_EMPLOYMENT_TYPE.
     *
     * @return Collection<string>  Lowercase weekday names
     */
    public static function resolveScheduledWeekdays(string $employmentType): Collection
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

    /**
     * Extract leave type value from a LeaveRequest, handling both enum and string types.
     */
    public static function leaveTypeValue(LeaveRequest $leaveRequest): string
    {
        $leaveType = $leaveRequest->leave_type;

        if ($leaveType instanceof \BackedEnum) {
            return (string) $leaveType->value;
        }

        return (string) $leaveType;
    }
}
