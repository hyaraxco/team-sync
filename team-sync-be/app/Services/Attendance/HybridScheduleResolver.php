<?php

namespace App\Services\Attendance;

use App\Models\StaffMemberProfile;
use App\Models\HybridScheduleOverride;
use App\Models\HybridWorkSchedule;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class HybridScheduleResolver
{
    public function resolve(int $employeeId, CarbonInterface|string $date): array
    {
        $employee = StaffMemberProfile::query()
            ->with('jobInformation')
            ->findOrFail($employeeId);

        if ($employee->jobInformation?->work_location !== 'hybrid') {
            return [
                'planned_mode' => null,
                'source' => 'none',
            ];
        }

        $targetDate = Carbon::parse($date)->toDateString();

        $override = HybridScheduleOverride::query()
            ->where('staff_member_id', $employeeId)
            ->whereDate('date', $targetDate)
            ->where('status', 'approved')
            ->latest('approved_at')
            ->first();

        if ($override) {
            return [
                'planned_mode' => $override->planned_work_mode,
                'source' => 'override',
            ];
        }

        $schedule = HybridWorkSchedule::query()
            ->where('staff_member_id', $employeeId)
            ->whereDate('effective_from', '<=', $targetDate)
            ->where(function ($query) use ($targetDate) {
                $query->whereNull('effective_until')
                    ->orWhereDate('effective_until', '>=', $targetDate);
            })
            ->orderByDesc('effective_from')
            ->first();

        if (! $schedule) {
            return [
                'planned_mode' => null,
                'source' => 'none',
            ];
        }

        $weekdayField = strtolower(Carbon::parse($targetDate)->englishDayOfWeek);
        if (! in_array($weekdayField, ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'], true)) {
            return [
                'planned_mode' => null,
                'source' => 'none',
            ];
        }

        return [
            'planned_mode' => $schedule->{$weekdayField},
            'source' => 'base_schedule',
        ];
    }
}
