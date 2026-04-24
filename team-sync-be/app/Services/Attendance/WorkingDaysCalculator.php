<?php

namespace App\Services\Attendance;

use App\Models\AttendancePolicy;
use App\Models\HolidayCalendar;
use App\Models\StaffMemberProfile;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class WorkingDaysCalculator
{
    public function calculateForEmployee(int $employeeId, CarbonInterface|string $startDate, CarbonInterface|string $endDate): int
    {
        $employee = StaffMemberProfile::query()
            ->with('jobInformation')
            ->findOrFail($employeeId);

        $employmentType = $employee->jobInformation?->employment_type;
        if (! $employmentType) {
            throw new \InvalidArgumentException('Employee does not have job information employment_type.');
        }

        $policy = AttendancePolicy::query()
            ->where('employment_type', $employmentType)
            ->first();

        if (! $policy) {
            throw new \InvalidArgumentException("Attendance policy not found for employment type [{$employmentType}].");
        }

        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        $scheduledWeekdays = collect($policy->default_working_weekdays ?? [])
            ->map(fn ($day) => strtolower((string) $day))
            ->values();

        $scheduledDates = $this->scheduledDatesWithinPeriod($start, $end, $scheduledWeekdays);
        if ($scheduledDates->isEmpty()) {
            return 0;
        }

        $holidayDates = $this->applicableHolidayDates(
            $employmentType,
            $start,
            $end,
            $scheduledDates->keys()->all()
        );

        return $scheduledDates->count() - $holidayDates->count();
    }

    public function applicableHolidayDates(
        string $employmentType,
        CarbonInterface|string $startDate,
        CarbonInterface|string $endDate,
        array $scheduledDateKeys
    ): Collection {
        if (empty($scheduledDateKeys)) {
            return collect();
        }

        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        return HolidayCalendar::query()
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->get()
            ->filter(function (HolidayCalendar $holiday) use ($employmentType, $scheduledDateKeys) {
                $dateKey = Carbon::parse($holiday->date)->toDateString();
                if (! in_array($dateKey, $scheduledDateKeys, true)) {
                    return false;
                }

                $appliesTo = $holiday->applies_to;
                if ($appliesTo === null) {
                    return true;
                }

                return in_array($employmentType, $appliesTo, true);
            })
            ->pluck('date')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->unique()
            ->values();
    }

    private function scheduledDatesWithinPeriod(CarbonInterface $start, CarbonInterface $end, Collection $scheduledWeekdays): Collection
    {
        return collect(CarbonPeriod::create($start, $end))
            ->filter(function (CarbonInterface $date) use ($scheduledWeekdays) {
                return $scheduledWeekdays->contains(strtolower($date->englishDayOfWeek));
            })
            ->mapWithKeys(fn (CarbonInterface $date) => [$date->toDateString() => $date->toDateString()]);
    }
}
