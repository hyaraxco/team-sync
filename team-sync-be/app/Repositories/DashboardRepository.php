<?php

namespace App\Repositories;

use App\Constants\CacheConstants;
use App\Enums\TaskStatus;
use App\Interfaces\DashboardRepositoryInterface;
use App\Models\Attendance;
use App\Models\Project;
use App\Models\ProjectTaskStatusLog;
use App\Models\StaffMemberProfile;
use App\Models\Team;
use App\Models\User;
use App\Notifications\TeamPulseNudge;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class DashboardRepository implements DashboardRepositoryInterface
{
    public function getStatistics()
    {
        $cacheKey = CacheConstants::CACHE_KEY_DASHBOARD_STATISTICS.now()->format('Y-m-d-H');

        return cache()->remember($cacheKey, CacheConstants::ONE_HOUR, function () {
            $currentMonth = now()->month;
            $currentYear = now()->year;
            $yesterday = Carbon::yesterday()->toDateString();
            $lastWeekStart = Carbon::now()->subWeek()->startOfWeek();
            $lastWeekEnd = Carbon::now()->subWeek()->endOfWeek();

            // Single query for employee and team stats
            $employeeTeamStats = DB::table('staff_member_profiles')
                ->crossJoin('teams')
                ->selectRaw('
                    COUNT(DISTINCT staff_member_profiles.id) as total_employees,
                    COUNT(DISTINCT CASE
                        WHEN MONTH(staff_member_profiles.created_at) = ?
                        AND YEAR(staff_member_profiles.created_at) = ?
                        THEN staff_member_profiles.id
                    END) as employees_this_month,
                    COUNT(DISTINCT teams.id) as total_teams,
                    COUNT(DISTINCT CASE
                        WHEN MONTH(teams.created_at) = ?
                        AND YEAR(teams.created_at) = ?
                        THEN teams.id
                    END) as new_teams_this_month
                ', [
                    $currentMonth,
                    $currentYear,
                    $currentMonth,
                    $currentYear,
                ])
                ->first();

            // Single query for attendance stats
            $attendanceStats = DB::table('attendances')
                ->selectRaw('
                    COUNT(CASE
                        WHEN MONTH(created_at) = ?
                        AND YEAR(created_at) = ?
                        THEN 1
                    END) as total_this_month,
                    COUNT(CASE
                        WHEN created_at BETWEEN ? AND ?
                        THEN 1
                    END) as total_last_week
                ', [
                    $currentMonth,
                    $currentYear,
                    $lastWeekStart,
                    $lastWeekEnd,
                ])
                ->first();

            // Calculate attendance rates
            $totalWorkDays = now()->day;
            $totalEmployees = (int) $employeeTeamStats->total_employees;
            $totalExpectedAttendances = $totalEmployees * $totalWorkDays;
            $totalActualAttendances = (int) $attendanceStats->total_this_month;

            $attendanceRate = $totalExpectedAttendances > 0
                ? round(($totalActualAttendances / $totalExpectedAttendances) * 100, 1)
                : 0;

            $lastWeekWorkDays = 5;
            $lastWeekExpectedAttendances = $totalEmployees * $lastWeekWorkDays;
            $lastWeekActualAttendances = (int) $attendanceStats->total_last_week;

            $lastWeekAttendanceRate = $lastWeekExpectedAttendances > 0
                ? round(($lastWeekActualAttendances / $lastWeekExpectedAttendances) * 100, 1)
                : 0;

            $attendanceRateChange = round($attendanceRate - $lastWeekAttendanceRate, 1);

            // Single query for task stats
            $taskStats = DB::table('project_tasks')
                ->selectRaw("
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as total_completed,
                    COUNT(CASE
                        WHEN status = 'completed'
                        AND DATE(updated_at) = ?
                        THEN 1
                    END) as completed_yesterday
                ", [$yesterday])
                ->first();

            $tasksCompleted = (int) $taskStats->total_completed;
            $tasksCompletedYesterday = (int) $taskStats->completed_yesterday;
            $tasksChange = $tasksCompleted - $tasksCompletedYesterday;

            // Single query for project stats
            $projectStats = DB::table('projects')
                ->selectRaw("
                    COUNT(CASE WHEN status = 'active' THEN 1 END) as active_projects,
                    COUNT(CASE
                        WHEN status = 'active'
                        AND MONTH(created_at) = ?
                        AND YEAR(created_at) = ?
                        THEN 1
                    END) as new_projects_this_month
                ", [
                    $currentMonth,
                    $currentYear,
                ])
                ->first();

            // Performance outcome stats from completed reviews
            $outcomeStats = DB::table('performance_reviews')
                ->where('status', 'completed')
                ->selectRaw('
                    COUNT(CASE WHEN promotion_eligible = 1 THEN 1 END) as promotion_eligible_count,
                    COUNT(CASE WHEN pip_required = 1 THEN 1 END) as pip_required_count
                ')
                ->first();

            return [
                'employees' => [
                    'total' => (int) $employeeTeamStats->total_employees,
                    'added_this_month' => (int) $employeeTeamStats->employees_this_month,
                ],
                'teams' => [
                    'total' => (int) $employeeTeamStats->total_teams,
                    'new_teams' => (int) $employeeTeamStats->new_teams_this_month,
                ],
                'attendance' => [
                    'rate' => $attendanceRate,
                    'change' => $attendanceRateChange,
                ],
                'tasks' => [
                    'completed' => $tasksCompleted,
                    'change' => $tasksChange,
                ],
                'projects' => [
                    'active' => (int) $projectStats->active_projects,
                    'new_projects' => (int) $projectStats->new_projects_this_month,
                ],
                'performance' => [
                    'promotion_eligible' => (int) ($outcomeStats->promotion_eligible_count ?? 0),
                    'pip_required' => (int) ($outcomeStats->pip_required_count ?? 0),
                ],
            ];
        });
    }

    public function getEmployeeStatistics(int $employeeId)
    {
        $cacheKey = CacheConstants::CACHE_KEY_DASHBOARD_STATISTICS.'employee_'.$employeeId.'_'.now()->format('Y-m-d-H');

        return cache()->remember($cacheKey, CacheConstants::ONE_HOUR, function () use ($employeeId) {
            $startOfMonth = now()->startOfMonth();
            $endOfMonth = now()->endOfMonth();
            $yesterday = Carbon::yesterday()->toDateString();

            $attendanceStats = DB::table('attendances')
                ->where('staff_member_id', $employeeId)
                ->whereBetween('date', [$startOfMonth, $endOfMonth])
                ->selectRaw("
                    COUNT(CASE WHEN status = 'present' THEN 1 END) as present_days,
                    COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_days,
                    COUNT(CASE WHEN status = 'sick_leave' THEN 1 END) as sick_days,
                    COUNT(CASE WHEN status = 'late' THEN 1 END) as late_days,
                    SUM(TIMESTAMPDIFF(MINUTE, check_in, check_out)) as total_minutes
                ")
                ->first();

            $totalDays = now()->daysInMonth;
            $presentDays = (int) ($attendanceStats->present_days ?? 0);
            $attendanceRate = $totalDays > 0 ? round(($presentDays / $totalDays) * 100, 1) : 0;
            $totalHoursWorked = round((int) ($attendanceStats->total_minutes ?? 0) / 60, 1);

            $taskStats = DB::table('project_tasks')
                ->where('assignee_id', $employeeId)
                ->selectRaw("
                    COUNT(CASE WHEN status = 'done' THEN 1 END) as total_done,
                    COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress,
                    COUNT(CASE WHEN status = 'todo' THEN 1 END) as todo,
                    COUNT(CASE WHEN status = 'review' THEN 1 END) as review,
                    COUNT(CASE
                        WHEN status = 'done'
                        AND DATE(updated_at) = ?
                        THEN 1
                    END) as done_yesterday
                ", [$yesterday])
                ->first();

            $assignedProjects = DB::table('project_teams')
                ->join('team_members', 'project_teams.team_id', '=', 'team_members.team_id')
                ->join('projects', 'projects.id', '=', 'project_teams.project_id')
                ->where('team_members.staff_member_id', $employeeId)
                ->whereNull('team_members.left_at')
                ->where('projects.status', 'active')
                ->selectRaw('COUNT(DISTINCT projects.id) as assigned_count')
                ->first();

            $ledProjects = DB::table('projects')
                ->where('project_leader_id', $employeeId)
                ->where('status', 'active')
                ->selectRaw('COUNT(*) as led_count')
                ->first();

            return [
                'attendance' => [
                    'rate' => $attendanceRate,
                    'present_days' => $presentDays,
                    'total_hours' => $totalHoursWorked,
                    'absent_days' => (int) ($attendanceStats->absent_days ?? 0),
                    'sick_days' => (int) ($attendanceStats->sick_days ?? 0),
                    'late_days' => (int) ($attendanceStats->late_days ?? 0),
                ],
                'tasks' => [
                    'done' => (int) ($taskStats->total_done ?? 0),
                    'done_yesterday' => (int) ($taskStats->done_yesterday ?? 0),
                    'in_progress' => (int) ($taskStats->in_progress ?? 0),
                    'todo' => (int) ($taskStats->todo ?? 0),
                    'review' => (int) ($taskStats->review ?? 0),
                ],
                'projects' => [
                    'assigned_active' => (int) ($assignedProjects->assigned_count ?? 0),
                    'led_active' => (int) ($ledProjects->led_count ?? 0),
                ],
            ];
        });
    }

    /**
     * Clear dashboard statistics cache
     */
    public function clearDashboardCache(): void
    {
        cache()->forget(CacheConstants::CACHE_KEY_DASHBOARD_STATISTICS.now()->format('Y-m-d-H'));
    }

    public function getTodayAttendanceOverview(): array
    {
        $today = Carbon::today()->toDateString();

        // Get all active employees with their user info
        $allEmployees = DB::table('staff_member_profiles')
            ->join('users', 'staff_member_profiles.user_id', '=', 'users.id')
            ->leftJoin('job_information', 'job_information.staff_member_id', '=', 'staff_member_profiles.id')
            ->whereNull('staff_member_profiles.deleted_at')
            ->select([
                'staff_member_profiles.id as staff_member_id',
                'users.name',
                'users.profile_photo',
                'job_information.job_title',
                'job_information.work_location',
            ])
            ->get();

        // Get today's attendance records
        $todayAttendances = DB::table('attendances')
            ->whereDate('date', $today)
            ->whereNull('deleted_at')
            ->get()
            ->keyBy('staff_member_id');

        $checkedIn = [];
        $notCheckedIn = [];
        $statusBreakdown = [
            'present' => 0,
            'late' => 0,
            'absent' => 0,
            'on_leave' => 0,
            'remote' => 0,
        ];

        foreach ($allEmployees as $employee) {
            $attendance = $todayAttendances->get($employee->staff_member_id);

            $employeeData = [
                'id' => $employee->staff_member_id,
                'name' => $employee->name,
                'profile_photo' => $employee->profile_photo,
                'position' => $employee->job_title,
            ];

            if ($attendance) {
                $employeeData['check_in'] = $attendance->check_in;
                $employeeData['check_out'] = $attendance->check_out;
                $employeeData['status'] = $attendance->status;
                $checkedIn[] = $employeeData;

                // Tally by status
                $status = $attendance->status ?? 'present';
                if (isset($statusBreakdown[$status])) {
                    $statusBreakdown[$status]++;
                } else {
                    $statusBreakdown['present']++;
                }
            } else {
                // Remote/WFH employees are auto-present
                if ($employee->work_location === 'remote') {
                    $employeeData['status'] = 'remote';
                    $checkedIn[] = $employeeData;
                    $statusBreakdown['remote']++;
                } else {
                    $employeeData['status'] = 'not_checked_in';
                    $notCheckedIn[] = $employeeData;
                }
            }
        }

        return [
            'date' => $today,
            'total_employees' => count($allEmployees),
            'checked_in_count' => count($checkedIn),
            'not_checked_in_count' => count($notCheckedIn),
            'status_breakdown' => $statusBreakdown,
            'checked_in' => array_slice($checkedIn, 0, 10),
            'not_checked_in' => array_slice($notCheckedIn, 0, 10),
        ];
    }

    public function getTeamPulse(): array
    {
        $actor = auth()->user();

        if (! $actor instanceof User) {
            throw new AuthorizationException('Unauthorized.');
        }

        $teamIds = $this->resolveManagedTeamIds($actor);

        if ($teamIds === []) {
            return [
                'updated_at' => now()->toIso8601String(),
                'summary' => [
                    'red' => 0,
                    'yellow' => 0,
                    'green' => 0,
                    'total' => 0,
                ],
                'staff_members' => [],
            ];
        }

        $staffMembers = StaffMemberProfile::query()
            ->with(['user', 'jobInformation.team'])
            ->whereIn('id', function ($query) use ($teamIds) {
                $query->select('staff_member_id')
                    ->from('team_members')
                    ->whereIn('team_id', $teamIds)
                    ->whereNull('left_at');
            })
            ->orderBy('id')
            ->get();

        if ($staffMembers->isEmpty()) {
            return [
                'updated_at' => now()->toIso8601String(),
                'summary' => [
                    'red' => 0,
                    'yellow' => 0,
                    'green' => 0,
                    'total' => 0,
                ],
                'staff_members' => [],
            ];
        }

        $staffIds = $staffMembers->pluck('id')->all();
        $today = Carbon::today();
        $todayString = $today->toDateString();
        $staleThreshold = $today->copy()->subDays(2)->startOfDay();

        $todayAttendances = Attendance::query()
            ->whereIn('staff_member_id', $staffIds)
            ->whereDate('date', $todayString)
            ->get()
            ->keyBy('staff_member_id');

        $taskRows = DB::table('project_tasks')
            ->selectRaw('assignee_id, COUNT(*) as total_tasks')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as done_tasks', [TaskStatus::DONE->value])
            ->selectRaw('SUM(CASE WHEN status IN (?, ?) THEN 1 ELSE 0 END) as active_tasks', [TaskStatus::IN_PROGRESS->value, TaskStatus::REVIEW->value])
            ->selectRaw('SUM(CASE WHEN status = ? AND due_date < ? THEN 1 ELSE 0 END) as overdue_tasks', [TaskStatus::TODO->value, $todayString])
            ->selectRaw('SUM(CASE WHEN status = ? AND due_date <= ? THEN 1 ELSE 0 END) as due_today_tasks', [TaskStatus::TODO->value, $todayString])
            ->whereIn('assignee_id', $staffIds)
            ->whereNull('deleted_at')
            ->groupBy('assignee_id')
            ->get()
            ->keyBy('assignee_id');

        $doneTodayRows = ProjectTaskStatusLog::query()
            ->selectRaw('project_tasks.assignee_id as staff_member_id, COUNT(project_task_status_logs.id) as done_today')
            ->join('project_tasks', 'project_tasks.id', '=', 'project_task_status_logs.project_task_id')
            ->whereIn('project_tasks.assignee_id', $staffIds)
            ->where('project_task_status_logs.to_status', TaskStatus::DONE->value)
            ->whereDate('project_task_status_logs.changed_at', $todayString)
            ->groupBy('project_tasks.assignee_id')
            ->get()
            ->keyBy('staff_member_id');

        $nudgeLookup = DB::table('notifications')
            ->selectRaw('notifiable_id, MAX(created_at) as last_nudged_at')
            ->where('notifiable_type', User::class)
            ->where('type', TeamPulseNudge::class)
            ->whereIn('notifiable_id', $staffMembers->pluck('user_id')->all())
            ->groupBy('notifiable_id')
            ->get()
            ->keyBy('notifiable_id');

        $pulseItems = $staffMembers->map(function (StaffMemberProfile $staffMember) use ($todayAttendances, $taskRows, $doneTodayRows, $nudgeLookup, $staleThreshold) {
            $attendance = $todayAttendances->get($staffMember->id);
            $taskStats = $taskRows->get($staffMember->id);
            $doneToday = (int) ($doneTodayRows->get($staffMember->id)->done_today ?? 0);

            $totalTasks = (int) ($taskStats->total_tasks ?? 0);
            $overdueTasks = (int) ($taskStats->overdue_tasks ?? 0);
            $dueTodayTasks = (int) ($taskStats->due_today_tasks ?? 0);
            $velocityPercent = $dueTodayTasks > 0
                ? (int) round(($doneToday / $dueTodayTasks) * 100)
                : ($doneToday > 0 ? 100 : 0);

            $attendancePresent = $attendance !== null || $staffMember->jobInformation?->work_location === 'remote';
            $attendanceStatus = $attendance?->status ?? ($attendancePresent ? 'remote' : 'not_checked_in');
            $attendanceLabel = $this->normalizeAttendanceLabel($attendanceStatus);
            $attendanceScore = $attendancePresent ? 100 : 0;
            $staleBlocked = $this->hasStaleActiveTasks($staffMember->id, $staleThreshold);

            $riskLevel = 'green';
            $riskReason = 'Task berjalan normal hari ini.';

            if (! $attendancePresent || $overdueTasks > 0 || $staleBlocked) {
                $riskLevel = 'red';
                $riskReason = ! $attendancePresent
                    ? 'Belum terlihat aktif di absensi hari ini.'
                    : ($overdueTasks > 0
                        ? 'Ada task tertunda yang sudah melewati due date.'
                        : 'Ada task aktif yang stagnan lebih dari 2 hari.');
            } elseif ($velocityPercent < 50 && $totalTasks > 0) {
                $riskLevel = 'yellow';
                $riskReason = 'Kecepatan progres hari ini masih di bawah 50%.';
            }

            $lastNudgedAt = $nudgeLookup->get($staffMember->user_id)->last_nudged_at ?? null;

            return [
                'id' => $staffMember->id,
                'name' => $staffMember->user?->name,
                'profile_photo' => $staffMember->user?->profile_photo ? asset('storage/'.$staffMember->user->profile_photo) : null,
                'job_title' => $staffMember->jobInformation?->job_title,
                'team_name' => $staffMember->jobInformation?->team?->name,
                'attendance' => [
                    'status' => $attendanceStatus,
                    'label' => $attendanceLabel,
                    'score' => $attendanceScore,
                ],
                'task_velocity' => [
                    'percent' => max(0, min(100, $velocityPercent)),
                    'done_today' => $doneToday,
                    'due_today' => $dueTodayTasks,
                    'overdue' => $overdueTasks,
                    'active' => (int) ($taskStats->active_tasks ?? 0),
                ],
                'risk' => [
                    'level' => $riskLevel,
                    'reason' => $riskReason,
                ],
                'nudge' => [
                    'last_sent_at' => $lastNudgedAt ? Carbon::parse($lastNudgedAt)->toIso8601String() : null,
                    'status' => $lastNudgedAt ? 'sent' : 'idle',
                ],
                'detail_url' => '/admin/staff-members/'.$staffMember->id,
            ];
        })->sortBy([
            fn (array $item) => $this->riskSortOrder($item['risk']['level']),
            fn (array $item) => $item['task_velocity']['percent'],
            fn (array $item) => $item['name'] ?? '',
        ])->values();

        return [
            'updated_at' => now()->toIso8601String(),
            'summary' => [
                'red' => $pulseItems->where('risk.level', 'red')->count(),
                'yellow' => $pulseItems->where('risk.level', 'yellow')->count(),
                'green' => $pulseItems->where('risk.level', 'green')->count(),
                'total' => $pulseItems->count(),
            ],
            'staff_members' => $pulseItems->all(),
        ];
    }

    public function sendTeamPulseNudge(int $staffMemberId, ?string $message = null): array
    {
        $actor = auth()->user();

        if (! $actor instanceof User) {
            throw new AuthorizationException('Unauthorized.');
        }

        $teamIds = $this->resolveManagedTeamIds($actor);

        if ($teamIds === []) {
            throw new AuthorizationException('You do not manage any team members for Team Pulse.');
        }

        $staffMember = StaffMemberProfile::query()
            ->with(['user'])
            ->where('id', $staffMemberId)
            ->whereIn('id', function ($query) use ($teamIds) {
                $query->select('staff_member_id')
                    ->from('team_members')
                    ->whereIn('team_id', $teamIds)
                    ->whereNull('left_at');
            })
            ->first();

        if (! $staffMember instanceof StaffMemberProfile) {
            throw (new ModelNotFoundException)->setModel(StaffMemberProfile::class, [$staffMemberId]);
        }

        $recipient = $staffMember->user;

        if (! $recipient instanceof User) {
            throw new AuthorizationException('Selected staff member does not have an active user account.');
        }

        $resolvedMessage = trim((string) $message);
        if ($resolvedMessage === '') {
            $resolvedMessage = sprintf(
                'Hi %s, kulihat task hari ini agak melambat. Ada blocker yang bisa kubantu?',
                $recipient->name ?? 'tim'
            );
        }

        $recipient->notify(new TeamPulseNudge(
            $staffMember->id,
            (string) ($recipient->name ?? $staffMember->code),
            (string) ($actor->name ?? 'Manager'),
            $resolvedMessage,
        ));

        return [
            'staff_member_id' => $staffMember->id,
            'staff_member_name' => $recipient->name,
            'message' => $resolvedMessage,
            'sent_at' => now()->toIso8601String(),
        ];
    }

    private function resolveManagedTeamIds(User $actor): array
    {
        $managedTeamIds = Team::query()
            ->where('team_lead_id', $actor->id)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_values(array_unique($managedTeamIds));
    }

    private function hasStaleActiveTasks(int $staffMemberId, Carbon $staleThreshold): bool
    {
        return DB::table('project_tasks')
            ->where('assignee_id', $staffMemberId)
            ->whereNull('deleted_at')
            ->whereIn('status', [TaskStatus::TODO->value, TaskStatus::IN_PROGRESS->value, TaskStatus::REVIEW->value])
            ->where('updated_at', '<', $staleThreshold)
            ->exists();
    }

    private function normalizeAttendanceLabel(string $status): string
    {
        return match ($status) {
            'present' => 'Hadir',
            'late' => 'Terlambat',
            'remote' => 'Remote',
            'half_day' => 'Half Day',
            'annual_leave', 'on_leave' => 'Cuti',
            'sick_leave', 'sick' => 'Sakit',
            default => 'Belum Check-in',
        };
    }

    private function riskSortOrder(string $riskLevel): int
    {
        return match ($riskLevel) {
            'red' => 0,
            'yellow' => 1,
            default => 2,
        };
    }
}
