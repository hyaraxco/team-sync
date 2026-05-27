<?php

namespace App\Services;

use App\Interfaces\OvertimeRepositoryInterface;
use App\Models\AttendancePeriod;
use App\Models\OvertimeRecord;
use App\Models\User;
use App\Notifications\OvertimeApprovedNotification;
use App\Notifications\OvertimeRejectedNotification;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class OvertimeService
{
    public function __construct(
        private readonly OvertimeRepositoryInterface $overtimeRepository
    ) {}

    public function getAllPaginated(
        ?string $status,
        ?int $staffMemberId,
        ?string $overtimeType,
        ?string $dateFrom,
        ?string $dateTo,
        int $perPage = 15,
        ?string $search = null
    ): LengthAwarePaginator {
        return $this->overtimeRepository->getAllPaginated(
            $status,
            $staffMemberId,
            $overtimeType,
            $dateFrom,
            $dateTo,
            $perPage,
            $search
        );
    }

    public function getById(int $id): OvertimeRecord
    {
        return $this->overtimeRepository->getById($id);
    }

    public function getByStaffMember(int $staffMemberId, ?string $status, int $perPage = 15): LengthAwarePaginator
    {
        return $this->overtimeRepository->getByStaffMember($staffMemberId, $status, $perPage);
    }

    /**
     * Create a new overtime record.
     *
     * Handles midnight wrap: when start_time > end_time (e.g., 23:00–01:00),
     * the end time is treated as falling on the next calendar day. This
     * correctly calculates 2 hours instead of the 22-hour diff that naive
     * same-day subtraction would produce.
     *
     * @return array{success: bool, message: string, record: ?OvertimeRecord}
     */
    public function create(array $validated): array
    {
        $lockedPeriod = AttendancePeriod::where('status', AttendancePeriod::STATUS_LOCKED)
            ->where('start_date', '<=', $validated['date'])
            ->where('end_date', '>=', $validated['date'])
            ->exists();

        if ($lockedPeriod) {
            return [
                'success' => false,
                'message' => 'Cannot create overtime for a date in a locked attendance period.',
                'record' => null,
            ];
        }

        $start = Carbon::createFromFormat('H:i', $validated['start_time']);
        $end = Carbon::createFromFormat('H:i', $validated['end_time']);

        // Handle midnight wrap: if end time is before start time (e.g., 23:00–01:00),
        // the overtime crosses midnight. Carbon::createFromFormat sets both times on
        // the same date, so diffInMinutes would return ~22 hours instead of the
        // correct 2 hours. Adding 1 day to end corrects this.
        if ($end->lessThan($start)) {
            $end->addDay();
        }

        $hours = round($start->diffInMinutes($end) / 60, 2);

        if ($hours > OvertimeRecord::MAX_HOURS_PER_DAY) {
            return [
                'success' => false,
                'message' => 'Overtime hours exceed maximum allowed per day ('.OvertimeRecord::MAX_HOURS_PER_DAY.' hours)',
                'record' => null,
            ];
        }

        return DB::transaction(function () use ($validated, $hours) {
            // Lock weekly overtime rows to prevent concurrent requests from
            // both passing the weekly limit check (race condition fix).
            $existingWeeklyHours = $this->overtimeRepository->getWeeklyHoursForStaffMemberLocked(
                $validated['staff_member_id'],
                $validated['date']
            );

            if (($existingWeeklyHours + $hours) > OvertimeRecord::MAX_HOURS_PER_WEEK) {
                $remaining = round(OvertimeRecord::MAX_HOURS_PER_WEEK - $existingWeeklyHours, 2);

                return [
                    'success' => false,
                    'message' => 'Weekly overtime limit exceeded. Maximum '.OvertimeRecord::MAX_HOURS_PER_WEEK." hours/week. Remaining capacity: {$remaining} hours.",
                    'record' => null,
                ];
            }

            $record = $this->overtimeRepository->create([
                'staff_member_id' => $validated['staff_member_id'],
                'attendance_id' => $validated['attendance_id'] ?? null,
                'date' => $validated['date'],
                'start_time' => $validated['start_time'],
                'end_time' => $validated['end_time'],
                'hours' => $hours,
                'overtime_type' => $validated['overtime_type'],
                'status' => OvertimeRecord::STATUS_PENDING,
                'notes' => $validated['notes'] ?? null,
            ]);

            return [
                'success' => true,
                'message' => 'Overtime Record Created Successfully',
                'record' => $record,
            ];
        });
    }

    /**
     * @return array{success: bool, message: string, record: ?OvertimeRecord}
     */
    public function approve(int $id, User $approver): array
    {
        $record = $this->overtimeRepository->getById($id);

        if ($record->status !== OvertimeRecord::STATUS_PENDING) {
            return [
                'success' => false,
                'message' => 'Only pending overtime records can be approved',
                'record' => null,
            ];
        }

        $record = $this->overtimeRepository->approve($record, $approver->id);

        $employeeUser = $record->staffMember?->user;
        if ($employeeUser) {
            $employeeUser->notify(new OvertimeApprovedNotification($record, $approver));
        }

        return [
            'success' => true,
            'message' => 'Overtime Record Approved Successfully',
            'record' => $record,
        ];
    }

    /**
     * @return array{success: bool, message: string, record: ?OvertimeRecord}
     */
    public function reject(int $id, string $reason, User $rejector): array
    {
        $record = $this->overtimeRepository->getById($id);

        if ($record->status !== OvertimeRecord::STATUS_PENDING) {
            return [
                'success' => false,
                'message' => 'Only pending overtime records can be rejected',
                'record' => null,
            ];
        }

        $record = $this->overtimeRepository->reject($record, $reason);

        $employeeUser = $record->staffMember?->user;
        if ($employeeUser) {
            $employeeUser->notify(new OvertimeRejectedNotification($record, $rejector));
        }

        return [
            'success' => true,
            'message' => 'Overtime Record Rejected Successfully',
            'record' => $record,
        ];
    }

    public function getSummary(): array
    {
        return $this->overtimeRepository->getSummary();
    }
}
