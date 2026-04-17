<?php

namespace App\Repositories;

use App\Constants\CacheConstants;
use App\Interfaces\AnalyticsRepositoryInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AnalyticsRepository implements AnalyticsRepositoryInterface
{
    public function getExecutiveSummary(string $period, ?string $department, ?int $teamId): array
    {
        $parsed = $this->parsePeriod($period);
        $cacheKey = 'analytics_executive_summary_' . md5(json_encode([$period, $department, $teamId])) . '_' . now()->format('Y-m-d-H');

        return cache()->remember($cacheKey, CacheConstants::ONE_HOUR, function () use ($parsed, $department, $teamId) {
            $start = $parsed['start'];
            $end = $parsed['end'];
            $months = $parsed['months'];

            // Calculate previous period for comparison
            $prevStart = $start->copy()->subMonths($months);
            $prevEnd = $start->copy()->subDay();

            // ── KPI: Total Employees (active, not soft-deleted) ─────────────
            $employeeQuery = DB::table('employee_profiles')
                ->whereNull('employee_profiles.deleted_at')
                ->when($department || $teamId, function ($q) use ($department, $teamId) {
                    $q->join('job_information', 'job_information.employee_id', '=', 'employee_profiles.id');
                    if ($department) {
                        $q->join('teams', 'teams.id', '=', 'job_information.team_id')
                            ->where('teams.department', $department);
                    }
                    if ($teamId) {
                        $q->join('team_members', 'team_members.employee_id', '=', 'employee_profiles.id')
                            ->where('team_members.team_id', $teamId)
                            ->whereNull('team_members.left_at');
                    }
                });

            $totalEmployees = (int) $employeeQuery->count(DB::raw('DISTINCT employee_profiles.id'));

            // Employees at start of previous period (created before prevStart and not deleted before prevStart)
            $prevEmployeeQuery = DB::table('employee_profiles')
                ->where('employee_profiles.created_at', '<', $start)
                ->where(function ($q) use ($start) {
                    $q->whereNull('employee_profiles.deleted_at')
                        ->orWhere('employee_profiles.deleted_at', '>=', $start);
                })
                ->when($department || $teamId, function ($q) use ($department, $teamId) {
                    $q->join('job_information', 'job_information.employee_id', '=', 'employee_profiles.id');
                    if ($department) {
                        $q->join('teams', 'teams.id', '=', 'job_information.team_id')
                            ->where('teams.department', $department);
                    }
                    if ($teamId) {
                        $q->join('team_members', 'team_members.employee_id', '=', 'employee_profiles.id')
                            ->where('team_members.team_id', $teamId)
                            ->whereNull('team_members.left_at');
                    }
                });

            $prevTotalEmployees = (int) $prevEmployeeQuery->count(DB::raw('DISTINCT employee_profiles.id'));
            $employeeGrowth = $prevTotalEmployees > 0
                ? round((($totalEmployees - $prevTotalEmployees) / $prevTotalEmployees) * 100, 1)
                : 0;

            // ── Build filtered employee IDs subquery for reuse ───────────────
            $filteredEmployeeIds = DB::table('employee_profiles')
                ->select('employee_profiles.id')
                ->whereNull('employee_profiles.deleted_at')
                ->when($department, function ($q) use ($department) {
                    $q->join('job_information as ji_filter', 'ji_filter.employee_id', '=', 'employee_profiles.id')
                        ->join('teams as t_filter', 't_filter.id', '=', 'ji_filter.team_id')
                        ->where('t_filter.department', $department);
                })
                ->when($teamId, function ($q) use ($teamId) {
                    $q->join('team_members as tm_filter', 'tm_filter.employee_id', '=', 'employee_profiles.id')
                        ->where('tm_filter.team_id', $teamId)
                        ->whereNull('tm_filter.left_at');
                })
                ->pluck('id');

            // ── KPI: Attendance Rate ────────────────────────────────────────
            $attendanceStats = DB::table('attendances')
                ->whereIn('employee_id', $filteredEmployeeIds)
                ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                ->selectRaw("
                    COUNT(*) as total_records,
                    COUNT(CASE WHEN status IN ('present', 'late', 'half_day') THEN 1 END) as attended_records
                ")
                ->first();

            $attendanceRate = $attendanceStats->total_records > 0
                ? round(($attendanceStats->attended_records / $attendanceStats->total_records) * 100, 1)
                : 0;

            // Previous period attendance rate
            $prevAttendanceStats = DB::table('attendances')
                ->whereIn('employee_id', $filteredEmployeeIds)
                ->whereBetween('date', [$prevStart->toDateString(), $prevEnd->toDateString()])
                ->selectRaw("
                    COUNT(*) as total_records,
                    COUNT(CASE WHEN status IN ('present', 'late', 'half_day') THEN 1 END) as attended_records
                ")
                ->first();

            $prevAttendanceRate = $prevAttendanceStats->total_records > 0
                ? round(($prevAttendanceStats->attended_records / $prevAttendanceStats->total_records) * 100, 1)
                : 0;

            $attendanceRateChange = round($attendanceRate - $prevAttendanceRate, 1);

            // ── KPI: Average Salary ─────────────────────────────────────────
            $salaryStats = DB::table('job_information')
                ->whereIn('employee_id', $filteredEmployeeIds)
                ->where('status', 'active')
                ->selectRaw('AVG(monthly_salary) as avg_salary')
                ->first();

            $averageSalary = round((float) ($salaryStats->avg_salary ?? 0), 2);

            // Previous period average salary (from payroll data)
            $prevSalaryStats = DB::table('payroll_details')
                ->join('payrolls', 'payrolls.id', '=', 'payroll_details.payroll_id')
                ->whereIn('payroll_details.employee_id', $filteredEmployeeIds)
                ->whereIn('payrolls.status', ['approved', 'paid'])
                ->whereBetween('payrolls.salary_month', [$prevStart->toDateString(), $prevEnd->toDateString()])
                ->selectRaw('AVG(payroll_details.final_salary) as avg_salary')
                ->first();

            $prevAverageSalary = (float) ($prevSalaryStats->avg_salary ?? 0);
            $salaryChange = $prevAverageSalary > 0
                ? round((($averageSalary - $prevAverageSalary) / $prevAverageSalary) * 100, 1)
                : 0;

            // ── KPI: Active Projects ────────────────────────────────────────
            $activeProjects = (int) DB::table('projects')
                ->where('status', 'active')
                ->count();

            // ── KPI: Task Completion Rate ───────────────────────────────────
            $taskStats = DB::table('project_tasks')
                ->whereIn('assignee_id', $filteredEmployeeIds)
                ->whereIn('status', ['todo', 'in_progress', 'review', 'done'])
                ->selectRaw("
                    COUNT(*) as total_tasks,
                    COUNT(CASE WHEN status = 'done' THEN 1 END) as completed_tasks
                ")
                ->first();

            $taskCompletionRate = $taskStats->total_tasks > 0
                ? round(($taskStats->completed_tasks / $taskStats->total_tasks) * 100, 1)
                : 0;

            // ── KPI: Leave Utilization ──────────────────────────────────────
            $leaveUsed = DB::table('leave_requests')
                ->whereIn('employee_id', $filteredEmployeeIds)
                ->where('status', 'approved')
                ->whereBetween('start_date', [$start->toDateString(), $end->toDateString()])
                ->selectRaw('COALESCE(SUM(total_days), 0) as total_used')
                ->first();

            $totalLeaveQuota = DB::table('leave_entitlements')
                ->where('is_eligible', true)
                ->selectRaw('COALESCE(SUM(quota_days), 0) as total_quota')
                ->first();

            // Approximate utilization: total used / (quota per type * employee count)
            $totalQuotaDays = (float) ($totalLeaveQuota->total_quota ?? 0);
            $leaveUtilization = ($totalQuotaDays > 0 && $totalEmployees > 0)
                ? round(((float) $leaveUsed->total_used / ($totalQuotaDays * $totalEmployees)) * 100, 1)
                : 0;

            // ── Attendance vs Deduction Trend (monthly) ─────────────────────
            $attendanceTrend = DB::table('attendances')
                ->whereIn('employee_id', $filteredEmployeeIds)
                ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
                ->selectRaw("
                    DATE_FORMAT(date, '%Y-%m') as month_key,
                    COUNT(*) as total_records,
                    COUNT(CASE WHEN status IN ('present', 'late', 'half_day') THEN 1 END) as attended_records
                ")
                ->groupBy('month_key')
                ->orderBy('month_key')
                ->get()
                ->keyBy('month_key');

            $deductionTrend = DB::table('payroll_details')
                ->join('payrolls', 'payrolls.id', '=', 'payroll_details.payroll_id')
                ->whereIn('payroll_details.employee_id', $filteredEmployeeIds)
                ->whereIn('payrolls.status', ['approved', 'paid'])
                ->whereBetween('payrolls.salary_month', [$start->toDateString(), $end->toDateString()])
                ->selectRaw("
                    DATE_FORMAT(payrolls.salary_month, '%Y-%m') as month_key,
                    COALESCE(SUM(payroll_details.deduction_amount), 0) as total_deductions
                ")
                ->groupBy('month_key')
                ->orderBy('month_key')
                ->get()
                ->keyBy('month_key');

            $attendanceVsDeductionTrend = [];
            $cursor = $start->copy()->startOfMonth();
            while ($cursor->lte($end)) {
                $key = $cursor->format('Y-m');
                $label = $cursor->format('M Y');

                $att = $attendanceTrend->get($key);
                $ded = $deductionTrend->get($key);

                $monthAttendanceRate = ($att && $att->total_records > 0)
                    ? round(($att->attended_records / $att->total_records) * 100, 1)
                    : 0;

                $attendanceVsDeductionTrend[] = [
                    'month' => $label,
                    'attendance_rate' => $monthAttendanceRate,
                    'total_deductions' => round((float) ($ded->total_deductions ?? 0), 2),
                ];

                $cursor->addMonth();
            }

            // ── Monthly HR Cost (stacked area chart) ────────────────────────
            $monthlyCostData = DB::table('payroll_details')
                ->join('payrolls', 'payrolls.id', '=', 'payroll_details.payroll_id')
                ->whereIn('payroll_details.employee_id', $filteredEmployeeIds)
                ->whereIn('payrolls.status', ['approved', 'paid'])
                ->whereBetween('payrolls.salary_month', [$start->toDateString(), $end->toDateString()])
                ->selectRaw("
                    DATE_FORMAT(payrolls.salary_month, '%Y-%m') as month_key,
                    COALESCE(SUM(payroll_details.final_salary), 0) as salary,
                    COALESCE(SUM(payroll_details.ph21_amount), 0) as tax,
                    COALESCE(SUM(
                        payroll_details.bpjs_tk_employee + payroll_details.bpjs_tk_employer +
                        payroll_details.bpjs_kes_employee + payroll_details.bpjs_kes_employer
                    ), 0) as bpjs,
                    COALESCE(SUM(payroll_details.deduction_amount), 0) as deductions
                ")
                ->groupBy('month_key')
                ->orderBy('month_key')
                ->get()
                ->keyBy('month_key');

            $monthlyHrCost = [];
            $cursor = $start->copy()->startOfMonth();
            while ($cursor->lte($end)) {
                $key = $cursor->format('Y-m');
                $label = $cursor->format('M Y');
                $row = $monthlyCostData->get($key);

                $monthlyHrCost[] = [
                    'month' => $label,
                    'salary' => round((float) ($row->salary ?? 0), 2),
                    'tax' => round((float) ($row->tax ?? 0), 2),
                    'bpjs' => round((float) ($row->bpjs ?? 0), 2),
                    'deductions' => round((float) ($row->deductions ?? 0), 2),
                ];

                $cursor->addMonth();
            }

            // ── Team Performance ────────────────────────────────────────────
            $teamPerformance = DB::table('teams')
                ->where('teams.status', 'active')
                ->when($department, function ($q) use ($department) {
                    $q->where('teams.department', $department);
                })
                ->when($teamId, function ($q) use ($teamId) {
                    $q->where('teams.id', $teamId);
                })
                ->leftJoin('team_members', function ($join) {
                    $join->on('team_members.team_id', '=', 'teams.id')
                        ->whereNull('team_members.left_at');
                })
                ->leftJoin('attendances', function ($join) use ($start, $end) {
                    $join->on('attendances.employee_id', '=', 'team_members.employee_id')
                        ->whereBetween('attendances.date', [$start->toDateString(), $end->toDateString()]);
                })
                ->leftJoin('project_tasks', function ($join) {
                    $join->on('project_tasks.assignee_id', '=', 'team_members.employee_id')
                        ->whereIn('project_tasks.status', ['todo', 'in_progress', 'review', 'done']);
                })
                ->groupBy('teams.id', 'teams.name')
                ->selectRaw("
                    teams.name as team_name,
                    COUNT(DISTINCT team_members.employee_id) as member_count,
                    CASE
                        WHEN COUNT(attendances.id) > 0
                        THEN ROUND(
                            COUNT(CASE WHEN attendances.status IN ('present', 'late', 'half_day') THEN 1 END)
                            / COUNT(attendances.id) * 100, 1
                        )
                        ELSE 0
                    END as attendance_rate,
                    CASE
                        WHEN COUNT(project_tasks.id) > 0
                        THEN ROUND(
                            COUNT(CASE WHEN project_tasks.status = 'done' THEN 1 END)
                            / COUNT(DISTINCT project_tasks.id) * 100, 1
                        )
                        ELSE 0
                    END as task_completion
                ")
                ->havingRaw('member_count > 0')
                ->orderBy('teams.name')
                ->get()
                ->map(function ($row) {
                    return [
                        'team_name' => $row->team_name,
                        'attendance_rate' => (float) $row->attendance_rate,
                        'task_completion' => (float) $row->task_completion,
                        'member_count' => (int) $row->member_count,
                    ];
                })
                ->all();

            return [
                'period' => [
                    'start' => $start->toDateString(),
                    'end' => $end->toDateString(),
                    'label' => $parsed['label'],
                ],
                'kpis' => [
                    'total_employees' => $totalEmployees,
                    'employee_growth' => $employeeGrowth,
                    'attendance_rate' => $attendanceRate,
                    'attendance_rate_change' => $attendanceRateChange,
                    'average_salary' => $averageSalary,
                    'salary_change' => $salaryChange,
                    'active_projects' => $activeProjects,
                    'task_completion_rate' => $taskCompletionRate,
                    'leave_utilization' => $leaveUtilization,
                ],
                'attendance_vs_deduction_trend' => $attendanceVsDeductionTrend,
                'monthly_hr_cost' => $monthlyHrCost,
                'team_performance' => $teamPerformance,
            ];
        });
    }

    public function getWorkforceAnalytics(string $period, ?string $department): array
    {
        // TODO: Implement workforce analytics (headcount trends, turnover, hiring pipeline)
        return [];
    }

    public function getAttendanceAnalytics(string $period, ?string $department, ?int $teamId): array
    {
        // TODO: Implement attendance analytics (daily patterns, late trends, absence reasons)
        return [];
    }

    public function getLeaveAnalytics(string $period, ?string $department): array
    {
        // TODO: Implement leave analytics (utilization by type, seasonal patterns, balance overview)
        return [];
    }

    public function getPayrollAnalytics(string $period, ?string $department): array
    {
        // TODO: Implement payroll analytics (cost distribution, salary bands, tax/BPJS breakdown)
        return [];
    }

    public function getProjectAnalytics(string $period, ?int $projectId): array
    {
        // TODO: Implement project analytics (velocity, burndown, resource allocation)
        return [];
    }

    /**
     * Parse a period string into start/end Carbon dates, a human-readable label, and month count.
     *
     * @return array{start: Carbon, end: Carbon, label: string, months: int}
     */
    private function parsePeriod(string $period): array
    {
        $end = Carbon::today();

        return match (true) {
            $period === 'ytd' => [
                'start' => Carbon::create($end->year, 1, 1)->startOfDay(),
                'end' => $end,
                'label' => 'Year to Date (' . $end->year . ')',
                'months' => $end->month,
            ],
            str_ends_with($period, 'm') => (function () use ($period, $end) {
                $months = (int) rtrim($period, 'm');
                $months = max(1, $months);
                $start = $end->copy()->subMonths($months)->startOfMonth();

                return [
                    'start' => $start,
                    'end' => $end,
                    'label' => "Last {$months} Months",
                    'months' => $months,
                ];
            })(),
            default => [
                'start' => $end->copy()->subMonths(6)->startOfMonth(),
                'end' => $end,
                'label' => 'Last 6 Months',
                'months' => 6,
            ],
        };
    }
}
