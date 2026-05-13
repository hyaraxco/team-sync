<?php

namespace App\Services\Attendance;

use App\Models\HolidayCalendar;
use App\Models\LeaveEntitlement;
use App\Models\LeaveRequest;
use App\Models\StaffMemberProfile;
use App\Support\AttendanceHelper;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Service for calculating employee leave balances.
 *
 * Known limitation — no carry-forward:
 * Balance calculation is strictly year-scoped. Each call to getEmployeeBalances()
 * resolves the quota and usage for the current year only. There is no mechanism
 * to carry forward unused leave days from one year to the next, and previous
 * year's usage does not influence the current year's quota or remaining balance.
 *
 * When a new calendar year begins, entitlements reset to the full annual quota
 * as defined in LeaveEntitlement, regardless of how many days were left unused
 * in the prior year.
 */
class LeaveBalanceService
{
    /**
     * Get leave balances for an employee, scoped to the current year only.
     *
     * Each entitlement's quota is compared against approved leave requests
     * that overlap the current calendar year (Jan 1 – Dec 31 of the target date's year).
     * No carry-forward from previous years is applied — remaining_days is always
     * calculated as quota_days minus used_days within the current year.
     *
     * @param  int  $employeeId  The staff member's ID
     * @param  string|null  $asOfDate  Optional date to scope the calculation (defaults to now)
     * @return Collection Array of balance records per leave type
     */
    public function getEmployeeBalances(int $employeeId, ?string $asOfDate = null): Collection
    {
        $employee = StaffMemberProfile::with('jobInformation')->find($employeeId);
        if (! $employee) {
            return collect();
        }

        $employmentType = $employee->jobInformation?->employment_type ?? '';
        $employmentType = AttendanceHelper::normalizeEmploymentType($employmentType);

        $entitlements = LeaveEntitlement::where('employment_type', $employmentType)
            ->where('is_eligible', true)
            ->get();

        $targetDate = $asOfDate ? Carbon::parse($asOfDate) : now();
        $yearStart = $targetDate->copy()->startOfYear()->toDateString();
        $yearEnd = $targetDate->copy()->endOfYear()->toDateString();

        $scheduledWeekdays = AttendanceHelper::resolveScheduledWeekdays($employmentType);

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
                    $usedDays += AttendanceHelper::countWorkingLeaveDays(
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

    /**
     * Get upcoming collective leave (cuti bersama) days for an employee.
     */
    public function getUpcomingCollectiveLeave(int $employeeId): Collection
    {
        $employee = StaffMemberProfile::with('jobInformation')->find($employeeId);
        if (! $employee) {
            return collect();
        }

        $employmentType = AttendanceHelper::normalizeEmploymentType($employee->jobInformation?->employment_type ?? 'full_time');

        return HolidayCalendar::query()
            ->where('type', 'collective_leave')
            ->whereDate('date', '>=', now())
            ->whereDate('date', '<=', now()->endOfYear())
            ->orderBy('date')
            ->get()
            ->filter(function (HolidayCalendar $holiday) use ($employmentType) {
                $appliesTo = $holiday->applies_to;

                return $appliesTo === null || in_array($employmentType, $appliesTo, true);
            });
    }
}
