<?php

namespace App\Repositories;

use App\Constants\CacheConstants;
use App\DTOs\AttendanceDto;
use App\Interfaces\AttendanceRepositoryInterface;
use App\Models\Attendance;
use App\Models\AttendancePolicyMismatch;
use App\Models\JobInformation;
use App\Models\Team;
use App\Models\TeamMember;
use App\Services\Attendance\AttendancePeriodService;
use App\Services\EmailService;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AttendanceRepository implements AttendanceRepositoryInterface
{
    public function __construct(
        private readonly AttendancePeriodService $attendancePeriodService,
        private readonly EmailService $emailService
    ) {}

    public function getAll(
        ?string $search,
        ?string $date,
        ?int $limit,
        bool $execute
    ): Builder|QueryBuilder|Collection {
        $query = Attendance::with(['employee.user', 'employee.jobInformation.team'])
            ->where(function ($query) use ($search, $date) {
                if ($search) {
                    $query->search($search);
                }

                if ($date) {
                    // Use direct comparison instead of whereDate for better performance
                    $query->whereBetween('date', [
                        $date.' 00:00:00',
                        $date.' 23:59:59',
                    ]);
                }
            })
            ->orderBy('created_at', 'desc');

        if ($limit) {
            $query->take($limit);
        }

        if ($execute) {
            return $query->get();
        }

        return $query;
    }

    public function getAllPaginated(
        ?string $search,
        int $rowPerPage
    ): LengthAwarePaginator {
        $query = $this->getAll(
            $search,
            null, // date
            null, // limit
            false
        );

        return $query->paginate($rowPerPage);
    }

    public function getById(
        string $id
    ): Attendance {
        return Attendance::with(['employee.user'])
            ->findOrFail($id);
    }

    public function getMyAttendances(): Collection
    {
        return Attendance::with(['employee.user'])
            ->where('employee_id', Auth::user()->employeeProfile->id)
            ->whereDate('date', '>=', now()->subDays(6)->startOfDay())
            ->whereDate('date', '<=', now()->endOfDay())
            ->orderBy('date', 'desc')
            ->get();
    }

    public function getMyAttendanceStatistics()
    {
        $employeeId = Auth::user()->employeeProfile->id;
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();

        $totalDays = now()->day; // Days elapsed in current month

        // Single optimized query instead of 3 separate queries
        $stats = Attendance::where('employee_id', $employeeId)
            ->whereBetween('date', [$startOfMonth, $endOfMonth])
            ->selectRaw("
                COUNT(CASE WHEN status = 'present' THEN 1 END) as present_days,
                COUNT(CASE WHEN status = 'sick' THEN 1 END) as sick_days,
                COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_days
            ")
            ->first();

        return [
            'total_days' => $totalDays,
            'present_days' => (int) $stats->present_days,
            'sick_days' => (int) $stats->sick_days,
            'absent_days' => (int) $stats->absent_days,
        ];
    }

    public function getLastAttendanceByEmployee(): ?Attendance
    {
        return Attendance::with(['employee.user'])
            ->where('employee_id', Auth::user()->employeeProfile->id)
            ->whereBetween('date', [
                now()->startOfDay(),
                now()->endOfDay(),
            ])
            ->first();
    }

    public function checkIn(array $data): Attendance
    {
        return DB::transaction(function () use ($data) {
            if (! $this->attendancePeriodService->canSubmitCorrection(Carbon::now())) {
                throw new \Exception('Attendance period is no longer open for check-in.');
            }

            $existingAttendance = Attendance::where('employee_id', Auth::user()->employeeProfile->id)
                ->whereBetween('date', [
                    now()->startOfDay(),
                    now()->endOfDay(),
                ])
                ->first();

            if ($existingAttendance) {
                throw new \Exception('Employee sudah check in hari ini');
            }

            $attendanceData = array_merge($data, [
                'date' => Carbon::now(),
                'attendance_period_id' => $this->attendancePeriodService
                    ->ensurePeriodForMonth(Carbon::now())
                    ->id,
                'check_in' => Carbon::now(),
                'status' => 'present',
            ]);

            $attendanceDto = AttendanceDto::fromArray($attendanceData);
            $attendance = Attendance::create($attendanceDto->toArray());

            DB::afterCommit(function () use ($attendance) {
                $this->emailService->sendAttendanceCheckedInNotification($attendance);
            });

            return $attendance;
        });
    }

    public function checkOut(array $data): Attendance
    {
        return DB::transaction(function () use ($data) {
            if (! $this->attendancePeriodService->canSubmitCorrection(Carbon::now())) {
                throw new \Exception('Attendance period is no longer open for check-out.');
            }

            $attendance = Attendance::where('employee_id', Auth::user()->employeeProfile->id)
                ->whereBetween('date', [
                    now()->startOfDay(),
                    now()->endOfDay(),
                ])
                ->whereNull('check_out')
                ->first();

            if (! $attendance) {
                throw new \Exception('Tidak ada data check in hari ini');
            }

            $checkOutTime = Carbon::now();
            $workedMinutes = $attendance->check_in
                ? max(0, Carbon::parse($attendance->check_in)->diffInMinutes($checkOutTime, false))
                : null;

            $updateData = array_merge($data, [
                'check_out' => $checkOutTime,
            ]);

            $attendanceDto = AttendanceDto::fromArrayForUpdate($updateData, $attendance);
            $attendance->update(array_merge(
                $attendanceDto->toArray(),
                ['worked_minutes' => $workedMinutes]
            ));

            DB::afterCommit(function () use ($attendance) {
                $this->emailService->sendAttendanceCheckedOutNotification($attendance);
            });

            return $attendance->load(['employee.user']);
        });
    }

    public function acknowledgePolicyMismatch(string $id, array $data): AttendancePolicyMismatch
    {
        return DB::transaction(function () use ($id, $data) {
            $mismatch = AttendancePolicyMismatch::query()->findOrFail($id);

            $this->authorizeManagerScopeForMismatch($mismatch);

            if ($mismatch->status !== AttendancePolicyMismatch::STATUS_PENDING_REVIEW) {
                throw new \Exception('Mismatch can only be acknowledged from pending_review status.');
            }

            $mismatch->update([
                'status' => AttendancePolicyMismatch::STATUS_ACKNOWLEDGED,
                'acknowledged_by' => $this->resolveActorEmployeeProfileId(),
                'acknowledged_at' => now(),
                'resolution_notes' => $data['resolution_notes'] ?? $mismatch->resolution_notes,
            ]);

            DB::afterCommit(function () use ($mismatch) {
                $this->emailService->sendAttendanceMismatchAcknowledgedNotification($mismatch);
            });

            return $mismatch->fresh(['attendance', 'employee.user', 'acknowledgedBy.user', 'resolvedBy.user']);
        });
    }

    public function resolvePolicyMismatch(string $id, array $data): AttendancePolicyMismatch
    {
        return DB::transaction(function () use ($id, $data) {
            $mismatch = AttendancePolicyMismatch::query()->findOrFail($id);

            $this->authorizeHrForMismatchResolution();

            if (! in_array($mismatch->status, AttendancePolicyMismatch::UNRESOLVED_STATUSES, true)) {
                throw new \Exception('Mismatch has already been resolved.');
            }

            $mismatch->update([
                'status' => AttendancePolicyMismatch::STATUS_RESOLVED,
                'resolved_by' => $this->resolveActorEmployeeProfileId(),
                'resolved_at' => now(),
                'resolution_notes' => $data['resolution_notes'] ?? $mismatch->resolution_notes,
            ]);

            DB::afterCommit(function () use ($mismatch) {
                $this->emailService->sendAttendanceMismatchResolvedNotification($mismatch);
            });

            return $mismatch->fresh(['attendance', 'employee.user', 'acknowledgedBy.user', 'resolvedBy.user']);
        });
    }

    public function getStatistics()
    {
        $cacheKey = CacheConstants::CACHE_KEY_ATTENDANCE_STATISTICS.now()->format('Y-m-d-H');

        return cache()->remember($cacheKey, CacheConstants::ONE_HOUR, function () {
            $today = now()->format('Y-m-d');
            $yesterday = now()->subDay()->format('Y-m-d');
            $lastWeekStart = now()->subWeek()->startOfWeek()->format('Y-m-d');
            $lastWeekEnd = now()->subWeek()->endOfWeek()->format('Y-m-d');

            // Single optimized query for attendance stats (today & yesterday)
            $attendanceStats = DB::table('attendances')
                ->selectRaw("
                    COUNT(CASE
                        WHEN DATE(date) = ?
                        AND status = 'present'
                        THEN 1
                    END) as present_today,
                    COUNT(CASE
                        WHEN DATE(date) = ?
                        AND status = 'present'
                        THEN 1
                    END) as present_yesterday,
                    COUNT(CASE
                        WHEN DATE(date) = ?
                        AND status = 'absent'
                        THEN 1
                    END) as absent_today,
                    COUNT(CASE
                        WHEN DATE(date) = ?
                        AND status = 'absent'
                        THEN 1
                    END) as absent_yesterday,
                    COUNT(CASE
                        WHEN DATE(date) = ?
                        AND TIME(check_in) > '09:00:00'
                        THEN 1
                    END) as late_today,
                    COUNT(CASE
                        WHEN DATE(date) BETWEEN ? AND ?
                        AND status = 'present'
                        THEN 1
                    END) as last_week_present
                ", [
                    $today,
                    $yesterday,
                    $today,
                    $yesterday,
                    $today,
                    $lastWeekStart,
                    $lastWeekEnd,
                ])
                ->first();

            // Remote workers today
            $remoteToday = DB::table('attendances')
                ->join('employee_profiles', 'attendances.employee_id', '=', 'employee_profiles.id')
                ->join('job_information', 'employee_profiles.id', '=', 'job_information.employee_id')
                ->where('attendances.date', '>=', $today.' 00:00:00')
                ->where('attendances.date', '<=', $today.' 23:59:59')
                ->where('job_information.work_location', 'remote')
                ->count();

            // Leave requests stats
            $leaveStats = DB::table('leave_requests')
                ->selectRaw("
                    COUNT(CASE
                        WHEN status = 'approved'
                        AND start_date <= ?
                        AND end_date >= ?
                        THEN 1
                    END) as on_leave_today,
                    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_requests
                ", [$today, $today])
                ->first();

            // Get cached employee count
            $totalEmployees = cache()->remember(CacheConstants::CACHE_KEY_EMPLOYEE_TOTAL_COUNT, CacheConstants::ONE_HOUR, function () {
                return DB::table('employee_profiles')->count();
            });

            // Calculate rates
            $presentToday = (int) $attendanceStats->present_today;
            $presentYesterday = (int) $attendanceStats->present_yesterday;
            $absentToday = (int) $attendanceStats->absent_today;
            $absentYesterday = (int) $attendanceStats->absent_yesterday;
            $lastWeekPresent = (int) $attendanceStats->last_week_present;

            $attendanceRate = $totalEmployees > 0
                ? round(($presentToday / $totalEmployees) * 100, 1)
                : 0;

            $lastWeekDays = 5;
            $lastWeekRate = $totalEmployees > 0 && $lastWeekDays > 0
                ? round(($lastWeekPresent / ($totalEmployees * $lastWeekDays)) * 100, 1)
                : 0;

            return [
                'present_today' => $presentToday,
                'present_change' => $presentToday - $presentYesterday,
                'absent_today' => $absentToday,
                'absent_change' => $absentToday - $absentYesterday,
                'late_today' => (int) $attendanceStats->late_today,
                'on_leave_today' => (int) $leaveStats->on_leave_today,
                'remote_today' => $remoteToday,
                'attendance_rate' => $attendanceRate,
                'rate_change' => round($attendanceRate - $lastWeekRate, 1),
                'pending_requests' => (int) $leaveStats->pending_requests,
            ];
        });
    }

    /**
     * Clear attendance statistics cache
     */
    public function clearAttendanceCache(): void
    {
        cache()->forget(CacheConstants::CACHE_KEY_ATTENDANCE_STATISTICS.now()->format('Y-m-d-H'));
    }

    private function authorizeManagerScopeForMismatch(AttendancePolicyMismatch $mismatch): void
    {
        $user = Auth::user();

        if (! $user || ! $user->hasRole('manager')) {
            throw new AuthorizationException('Only manager can acknowledge attendance policy mismatch.');
        }

        $manageableEmployeeIds = $this->getManageableEmployeeIdsForManager();

        if (empty($manageableEmployeeIds) || ! in_array($mismatch->employee_id, $manageableEmployeeIds, true)) {
            throw new AuthorizationException('You can only acknowledge mismatches from your direct reports.');
        }
    }

    private function authorizeHrForMismatchResolution(): void
    {
        $user = Auth::user();

        if (! $user || ! $user->hasRole('hr')) {
            throw new AuthorizationException('Only HR can resolve attendance policy mismatch.');
        }
    }

    private function resolveActorEmployeeProfileId(): int
    {
        $employeeId = Auth::user()?->employeeProfile?->getKey();

        if (! $employeeId) {
            throw new AuthorizationException('Authenticated user does not have an employee profile.');
        }

        return $employeeId;
    }

    private function getManageableEmployeeIdsForManager(): array
    {
        $user = Auth::user();

        if (! $user || ! $user->hasRole('manager')) {
            return [];
        }

        $leadTeamIds = Team::query()
            ->where('team_lead_id', $user->id)
            ->pluck('id')
            ->toArray();

        if (empty($leadTeamIds)) {
            return [];
        }

        $fromTeamMembers = TeamMember::query()
            ->whereIn('team_id', $leadTeamIds)
            ->whereNull('left_at')
            ->pluck('employee_id')
            ->toArray();

        $fromJobInformation = JobInformation::query()
            ->whereIn('team_id', $leadTeamIds)
            ->pluck('employee_id')
            ->toArray();

        return array_values(array_unique(array_merge($fromTeamMembers, $fromJobInformation)));
    }
}
