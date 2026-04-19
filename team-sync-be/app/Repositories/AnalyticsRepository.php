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
                    COALESCE(SUM(payroll_details.pph21_amount), 0) as tax,
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
        $parsed = $this->parsePeriod($period);
        $cacheKey = CacheConstants::CACHE_KEY_ANALYTICS_PREFIX . 'workforce_' . md5(json_encode([$period, $department])) . '_' . now()->format('Y-m-d-H');

        return cache()->remember($cacheKey, CacheConstants::ONE_HOUR, function () use ($parsed, $department) {
            return $this->computeWorkforceAnalytics($parsed['start'], $parsed['end'], $parsed['label'], $department);
        });
    }

    /**
     * @param Carbon $start
     * @param Carbon $end
     * @param string $label
     * @param string|null $department
     * @return array<string, mixed>
     */
    private function computeWorkforceAnalytics(Carbon $start, Carbon $end, string $label, ?string $department): array
    {
            /** @var array<int, int> $filteredEmployeeIds */
            $filteredEmployeeIds = $this->getFilteredEmployeeIds($department, null);

            return [
                'period' => ['start' => $start->toDateString(), 'end' => $end->toDateString(), 'label' => $label],
                'headcount_trend' => $this->getHeadcountTrend($start, $end, $department),
                ...$this->getWorkforceDemographics($filteredEmployeeIds),
                ...$this->getWorkforceDistributions($filteredEmployeeIds),
            ];
    }

    /**
     * @param array<int, int> $filteredEmployeeIds
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function getWorkforceDemographics(array $filteredEmployeeIds): array
    {
            // ── Gender Distribution ─────────────────────────────────────────
            $genderDistribution = DB::table('employee_profiles')
                ->whereIn('id', $filteredEmployeeIds)
                ->whereNotNull('gender')
                ->selectRaw("gender, COUNT(*) as count")
                ->groupBy('gender')
                ->get()
                ->map(fn ($r) => ['gender' => $r->gender, 'count' => (int) $r->count])
                ->values()->all();

            // ── Employment Type Breakdown ────────────────────────────────────
            $employmentTypes = DB::table('job_information')
                ->whereIn('employee_id', $filteredEmployeeIds)
                ->whereNotNull('employment_type')
                ->selectRaw("employment_type, COUNT(*) as count")
                ->groupBy('employment_type')
                ->orderByDesc('count')
                ->get()
                ->map(fn ($r) => ['type' => $r->employment_type, 'count' => (int) $r->count])
                ->values()->all();

            // ── Work Location Distribution ──────────────────────────────────
            $workLocations = DB::table('job_information')
                ->whereIn('employee_id', $filteredEmployeeIds)
                ->whereNotNull('work_location')
                ->selectRaw("work_location, COUNT(*) as count")
                ->groupBy('work_location')
                ->orderByDesc('count')
                ->get()
                ->map(fn ($r) => ['location' => $r->work_location, 'count' => (int) $r->count])
                ->values()->all();

            // ── Department Headcount ─────────────────────────────────────────
            $departmentHeadcount = DB::table('teams')
                ->join('team_members', function ($join) {
                    $join->on('team_members.team_id', '=', 'teams.id')->whereNull('team_members.left_at');
                })
                ->whereIn('team_members.employee_id', $filteredEmployeeIds)
                ->whereNotNull('teams.department')
                ->selectRaw("teams.department, COUNT(DISTINCT team_members.employee_id) as count")
                ->groupBy('teams.department')
                ->orderByDesc('count')
                ->get()
                ->map(fn ($r) => ['department' => $r->department, 'count' => (int) $r->count])
                ->values()->all();

            return [
                'gender_distribution' => $genderDistribution,
                'employment_types' => $employmentTypes,
                'work_locations' => $workLocations,
                'department_headcount' => $departmentHeadcount,
            ];
    }

    /**
     * @param array<int, int> $filteredEmployeeIds
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function getWorkforceDistributions(array $filteredEmployeeIds): array
    {
            // ── PTKP Status Distribution (replaces dropped skill_level column) ─
            // skill_level was removed from job_information in the April 2026 refactor.
            // ptkp_status is the relevant tax-identity classification stored in employee_profiles.
            $skillLevels = DB::table('employee_profiles')
                ->whereIn('id', $filteredEmployeeIds)
                ->whereNotNull('ptkp_status')
                ->selectRaw("ptkp_status as skill_level, COUNT(*) as count")
                ->groupBy('ptkp_status')
                ->orderByDesc('count')
                ->get()
                ->map(fn ($r) => ['level' => $r->skill_level, 'count' => (int) $r->count])
                ->values()->all();

            // ── Age Distribution ────────────────────────────────────────────
            $ageDistribution = DB::table('employee_profiles')
                ->whereIn('id', $filteredEmployeeIds)
                ->whereNotNull('date_of_birth')
                ->selectRaw("
                    CASE
                        WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) < 25 THEN '<25'
                        WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 25 AND 30 THEN '25-30'
                        WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 31 AND 35 THEN '31-35'
                        WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 36 AND 40 THEN '36-40'
                        WHEN TIMESTAMPDIFF(YEAR, date_of_birth, CURDATE()) BETWEEN 41 AND 50 THEN '41-50'
                        ELSE '50+'
                    END as age_range,
                    COUNT(*) as count
                ")
                ->groupBy('age_range')
                ->orderByRaw("FIELD(age_range, '<25', '25-30', '31-35', '36-40', '41-50', '50+')")
                ->get()
                ->map(fn ($r) => ['range' => $r->age_range, 'count' => (int) $r->count])
                ->values()->all();

            // ── Tenure Distribution ─────────────────────────────────────────
            $tenureDistribution = DB::table('job_information')
                ->whereIn('employee_id', $filteredEmployeeIds)
                ->whereNotNull('start_date')
                ->selectRaw("
                    CASE
                        WHEN TIMESTAMPDIFF(MONTH, start_date, CURDATE()) < 6 THEN '<6 months'
                        WHEN TIMESTAMPDIFF(MONTH, start_date, CURDATE()) BETWEEN 6 AND 12 THEN '6-12 months'
                        WHEN TIMESTAMPDIFF(YEAR, start_date, CURDATE()) BETWEEN 1 AND 2 THEN '1-2 years'
                        WHEN TIMESTAMPDIFF(YEAR, start_date, CURDATE()) BETWEEN 3 AND 5 THEN '3-5 years'
                        ELSE '5+ years'
                    END as tenure_range,
                    COUNT(*) as count
                ")
                ->groupBy('tenure_range')
                ->orderByRaw("FIELD(tenure_range, '<6 months', '6-12 months', '1-2 years', '3-5 years', '5+ years')")
                ->get()
                ->map(fn ($r) => ['range' => $r->tenure_range, 'count' => (int) $r->count])
                ->values()->all();

            return [
                'skill_levels' => $skillLevels,
                'age_distribution' => $ageDistribution,
                'tenure_distribution' => $tenureDistribution,
            ];
    }

    /**
     * @return array<int, array{month: string, count: int}>
     */
    private function getHeadcountTrend(Carbon $start, Carbon $end, ?string $department): array
    {
            $headcountTrend = [];
            $cursor = $start->copy()->startOfMonth();
            while ($cursor->lte($end)) {
                $monthEnd = $cursor->copy()->endOfMonth();
                $count = DB::table('employee_profiles')
                    ->where('created_at', '<=', $monthEnd)
                    ->where(function ($q) use ($monthEnd) {
                        $q->whereNull('deleted_at')->orWhere('deleted_at', '>', $monthEnd);
                    })
                    ->when($department, function ($q) use ($department) {
                        $q->join('job_information as ji', 'ji.employee_id', '=', 'employee_profiles.id')
                            ->join('teams as t', 't.id', '=', 'ji.team_id')
                            ->where('t.department', $department);
                    })
                    ->count(DB::raw('DISTINCT employee_profiles.id'));

                $headcountTrend[] = [
                    'month' => $cursor->format('M Y'),
                    'count' => $count,
                ];
                $cursor->addMonth();
            }

            return $headcountTrend;
    }

    public function getAttendanceAnalytics(string $period, ?string $department, ?int $teamId): array
    {
        $parsed = $this->parsePeriod($period);
        $cacheKey = CacheConstants::CACHE_KEY_ANALYTICS_PREFIX . 'attendance_' . md5(json_encode([$period, $department, $teamId])) . '_' . now()->format('Y-m-d-H');

        return cache()->remember($cacheKey, CacheConstants::ONE_HOUR, function () use ($parsed, $department, $teamId) {
            return $this->computeAttendanceAnalytics($parsed['start'], $parsed['end'], $parsed['label'], $department, $teamId);
        });
    }

    /**
     * @param Carbon $start
     * @param Carbon $end
     * @param string $label
     * @param string|null $department
     * @param int|null $teamId
     * @return array<string, mixed>
     */
    private function computeAttendanceAnalytics(Carbon $start, Carbon $end, string $label, ?string $department, ?int $teamId): array
    {
            /** @var array<int, int> $filteredEmployeeIds */
            $filteredEmployeeIds = $this->getFilteredEmployeeIds($department, $teamId);
            $startDate = $start->toDateString();
            $endDate = $end->toDateString();

            return [
                'period' => [
                    'start' => $startDate,
                    'end' => $endDate,
                    'label' => $label,
                ],
                ...$this->getAttendanceRateAndStatus($filteredEmployeeIds, $startDate, $endDate),
                ...$this->getAttendanceComplianceMetrics($filteredEmployeeIds, $startDate, $endDate),
            ];
    }

    /**
     * @param array<int, int> $filteredEmployeeIds
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function getAttendanceRateAndStatus(array $filteredEmployeeIds, string $startDate, string $endDate): array
    {
            // ── Monthly Attendance Rate Trend ────────────────────────────────
            $monthlyTrend = DB::table('attendances')
                ->whereIn('employee_id', $filteredEmployeeIds)
                ->whereBetween('date', [$startDate, $endDate])
                ->selectRaw("
                    DATE_FORMAT(date, '%Y-%m') as month_key,
                    COUNT(*) as total_records,
                    COUNT(CASE WHEN status = 'present' THEN 1 END) as present_count,
                    COUNT(CASE WHEN status = 'late' THEN 1 END) as late_count,
                    COUNT(CASE WHEN status = 'absent' THEN 1 END) as absent_count,
                    COUNT(CASE WHEN status = 'half_day' THEN 1 END) as half_day_count,
                    COUNT(CASE WHEN status = 'sick_leave' THEN 1 END) as sick_leave_count,
                    COUNT(CASE WHEN status = 'annual_leave' THEN 1 END) as annual_leave_count,
                    COALESCE(AVG(worked_minutes), 0) as avg_worked_minutes
                ")
                ->groupBy('month_key')
                ->orderBy('month_key')
                ->get();

            $monthlyAttendanceRate = $monthlyTrend->map(function ($row) {
                $attended = $row->present_count + $row->late_count + $row->half_day_count;
                $rate = $row->total_records > 0 ? round(($attended / $row->total_records) * 100, 1) : 0;

                return [
                    'month' => Carbon::createFromFormat('Y-m', $row->month_key)->format('M Y'),
                    'attendance_rate' => $rate,
                    'present' => (int) $row->present_count,
                    'late' => (int) $row->late_count,
                    'absent' => (int) $row->absent_count,
                    'half_day' => (int) $row->half_day_count,
                    'sick_leave' => (int) $row->sick_leave_count,
                    'annual_leave' => (int) $row->annual_leave_count,
                    'avg_hours' => round((float) $row->avg_worked_minutes / 60, 1),
                ];
            })->values()->all();

            // ── Status Distribution (current period) ────────────────────────
            $statusDistribution = DB::table('attendances')
                ->whereIn('employee_id', $filteredEmployeeIds)
                ->whereBetween('date', [$startDate, $endDate])
                ->selectRaw("
                    status,
                    COUNT(*) as count
                ")
                ->groupBy('status')
                ->orderByDesc('count')
                ->get()
                ->map(fn ($row) => [
                    'status' => $row->status,
                    'count' => (int) $row->count,
                ])
                ->values()
                ->all();

            // ── Weekly Lateness Trend ────────────────────────────────────────
            $latenessTrend = DB::table('attendances')
                ->whereIn('employee_id', $filteredEmployeeIds)
                ->whereBetween('date', [$startDate, $endDate])
                ->where('status', 'late')
                ->selectRaw("
                    YEARWEEK(date, 1) as week_key,
                    MIN(date) as week_start,
                    COUNT(*) as late_count
                ")
                ->groupBy('week_key')
                ->orderBy('week_key')
                ->get()
                ->map(fn ($row) => [
                    'week' => Carbon::parse($row->week_start)->format('d M'),
                    'late_count' => (int) $row->late_count,
                ])
                ->values()
                ->all();

            // ── Average Hours Worked per Day ────────────────────────────────
            $avgHoursTrend = DB::table('attendances')
                ->whereIn('employee_id', $filteredEmployeeIds)
                ->whereBetween('date', [$startDate, $endDate])
                ->whereNotNull('worked_minutes')
                ->where('worked_minutes', '>', 0)
                ->selectRaw("
                    DATE_FORMAT(date, '%Y-%m') as month_key,
                    ROUND(AVG(worked_minutes) / 60, 1) as avg_hours
                ")
                ->groupBy('month_key')
                ->orderBy('month_key')
                ->get()
                ->map(fn ($row) => [
                    'month' => Carbon::createFromFormat('Y-m', $row->month_key)->format('M Y'),
                    'avg_hours' => (float) $row->avg_hours,
                ])
                ->values()
                ->all();

            return [
                'monthly_attendance_rate' => $monthlyAttendanceRate,
                'status_distribution' => $statusDistribution,
                'lateness_trend' => $latenessTrend,
                'avg_hours_trend' => $avgHoursTrend,
            ];
    }

    /**
     * @param array<int, int> $filteredEmployeeIds
     * @return array<string, array<int, array<string, mixed>>>
     */
    private function getAttendanceComplianceMetrics(array $filteredEmployeeIds, string $startDate, string $endDate): array
    {
            // ── Work Mode Distribution (monthly stacked) ────────────────────
            $workModeTrend = DB::table('attendances')
                ->whereIn('employee_id', $filteredEmployeeIds)
                ->whereBetween('date', [$startDate, $endDate])
                ->whereNotNull('actual_work_mode')
                ->selectRaw("
                    DATE_FORMAT(date, '%Y-%m') as month_key,
                    actual_work_mode,
                    COUNT(*) as count
                ")
                ->groupBy('month_key', 'actual_work_mode')
                ->orderBy('month_key')
                ->get();

            // Pivot work mode data by month
            $workModeByMonth = [];
            foreach ($workModeTrend as $row) {
                $monthLabel = Carbon::createFromFormat('Y-m', $row->month_key)->format('M Y');
                if (! isset($workModeByMonth[$monthLabel])) {
                    $workModeByMonth[$monthLabel] = ['month' => $monthLabel, 'office' => 0, 'remote' => 0, 'hybrid' => 0];
                }
                $mode = strtolower($row->actual_work_mode);
                if (isset($workModeByMonth[$monthLabel][$mode])) {
                    $workModeByMonth[$monthLabel][$mode] = (int) $row->count;
                }
            }
            $workModeDistribution = array_values($workModeByMonth);

            // ── Top Late Employees ──────────────────────────────────────────
            $topLateEmployees = DB::table('attendances')
                ->join('employee_profiles', 'employee_profiles.id', '=', 'attendances.employee_id')
                ->join('users', 'users.id', '=', 'employee_profiles.user_id')
                ->whereIn('attendances.employee_id', $filteredEmployeeIds)
                ->whereBetween('attendances.date', [$startDate, $endDate])
                ->where('attendances.status', 'late')
                ->groupBy('attendances.employee_id', 'users.name', 'employee_profiles.code')
                ->selectRaw("
                    attendances.employee_id,
                    users.name as employee_name,
                    employee_profiles.code as employee_code,
                    COUNT(*) as late_count
                ")
                ->orderByDesc('late_count')
                ->limit(10)
                ->get()
                ->map(fn ($row) => [
                    'employee_id' => (int) $row->employee_id,
                    'employee_name' => $row->employee_name,
                    'employee_code' => $row->employee_code,
                    'late_count' => (int) $row->late_count,
                ])
                ->values()
                ->all();

            // ── Policy Mismatch Trend ───────────────────────────────────────
            $mismatchTrend = DB::table('attendance_policy_mismatches')
                ->whereIn('employee_id', $filteredEmployeeIds)
                ->whereBetween('mismatch_date', [$startDate, $endDate])
                ->selectRaw("
                    DATE_FORMAT(mismatch_date, '%Y-%m') as month_key,
                    COUNT(*) as total,
                    COUNT(CASE WHEN status = 'resolved' THEN 1 END) as resolved
                ")
                ->groupBy('month_key')
                ->orderBy('month_key')
                ->get()
                ->map(fn ($row) => [
                    'month' => Carbon::createFromFormat('Y-m', $row->month_key)->format('M Y'),
                    'total' => (int) $row->total,
                    'resolved' => (int) $row->resolved,
                ])
                ->values()
                ->all();

            // ── Correction Request Rate ─────────────────────────────────────
            $correctionTrend = DB::table('attendance_corrections')
                ->whereIn('employee_id', $filteredEmployeeIds)
                ->whereBetween('created_at', [$startDate, $endDate])
                ->selectRaw("
                    DATE_FORMAT(created_at, '%Y-%m') as month_key,
                    COUNT(*) as total,
                    COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved,
                    COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected,
                    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending
                ")
                ->groupBy('month_key')
                ->orderBy('month_key')
                ->get()
                ->map(function ($row) {
                    $approvalRate = $row->total > 0
                        ? round((($row->approved) / $row->total) * 100, 1)
                        : 0;

                    return [
                        'month' => Carbon::createFromFormat('Y-m', $row->month_key)->format('M Y'),
                        'total' => (int) $row->total,
                        'approved' => (int) $row->approved,
                        'rejected' => (int) $row->rejected,
                        'pending' => (int) $row->pending,
                        'approval_rate' => $approvalRate,
                    ];
                })
                ->values()
                ->all();

            return [
                'work_mode_distribution' => $workModeDistribution,
                'top_late_employees' => $topLateEmployees,
                'policy_mismatch_trend' => $mismatchTrend,
                'correction_trend' => $correctionTrend,
            ];
    }

    public function getLeaveAnalytics(string $period, ?string $department): array
    {
        $parsed = $this->parsePeriod($period);
        $cacheKey = CacheConstants::CACHE_KEY_ANALYTICS_PREFIX . 'leave_' . md5(json_encode([$period, $department])) . '_' . now()->format('Y-m-d-H');

        return cache()->remember($cacheKey, CacheConstants::ONE_HOUR, function () use ($parsed, $department) {
            $start = $parsed['start'];
            $end = $parsed['end'];
            $filteredEmployeeIds = $this->getFilteredEmployeeIds($department, null);

            // ── Leave Requests by Month ──────────────────────────────────────
            $monthlyTrend = DB::table('leave_requests')
                ->whereIn('employee_id', $filteredEmployeeIds)
                ->whereBetween('start_date', [$start->toDateString(), $end->toDateString()])
                ->selectRaw("
                    DATE_FORMAT(start_date, '%Y-%m') as month_key,
                    COUNT(*) as total,
                    COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved,
                    COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected,
                    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending
                ")
                ->groupBy('month_key')
                ->orderBy('month_key')
                ->get()
                ->map(fn ($r) => [
                    'month' => Carbon::createFromFormat('Y-m', $r->month_key)->format('M Y'),
                    'total' => (int) $r->total,
                    'approved' => (int) $r->approved,
                    'rejected' => (int) $r->rejected,
                    'pending' => (int) $r->pending,
                ])
                ->values()->all();

            // ── Leave Type Distribution ──────────────────────────────────────
            $typeDistribution = DB::table('leave_requests')
                ->whereIn('employee_id', $filteredEmployeeIds)
                ->whereBetween('start_date', [$start->toDateString(), $end->toDateString()])
                ->selectRaw("leave_type, COUNT(*) as count, COALESCE(SUM(total_days), 0) as total_days")
                ->groupBy('leave_type')
                ->orderByDesc('count')
                ->get()
                ->map(fn ($r) => [
                    'type' => $r->leave_type,
                    'count' => (int) $r->count,
                    'total_days' => (int) $r->total_days,
                ])
                ->values()->all();

            // ── Approval Rate ───────────────────────────────────────────────
            $approvalStats = DB::table('leave_requests')
                ->whereIn('employee_id', $filteredEmployeeIds)
                ->whereBetween('start_date', [$start->toDateString(), $end->toDateString()])
                ->selectRaw("
                    COUNT(*) as total,
                    COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved,
                    COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected,
                    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending
                ")
                ->first();

            $approvalRate = [
                'total' => (int) $approvalStats->total,
                'approved' => (int) $approvalStats->approved,
                'rejected' => (int) $approvalStats->rejected,
                'pending' => (int) $approvalStats->pending,
                'approval_percentage' => $approvalStats->total > 0
                    ? round(($approvalStats->approved / $approvalStats->total) * 100, 1)
                    : 0,
            ];

            // ── Average Leave Duration by Type ──────────────────────────────
            $avgDuration = DB::table('leave_requests')
                ->whereIn('employee_id', $filteredEmployeeIds)
                ->whereBetween('start_date', [$start->toDateString(), $end->toDateString()])
                ->where('status', 'approved')
                ->selectRaw("leave_type, ROUND(AVG(total_days), 1) as avg_days")
                ->groupBy('leave_type')
                ->orderByDesc('avg_days')
                ->get()
                ->map(fn ($r) => ['type' => $r->leave_type, 'avg_days' => (float) $r->avg_days])
                ->values()->all();

            // ── Leave by Department ──────────────────────────────────────────
            $leaveByDepartment = DB::table('leave_requests')
                ->join('employee_profiles', 'employee_profiles.id', '=', 'leave_requests.employee_id')
                ->join('job_information', 'job_information.employee_id', '=', 'employee_profiles.id')
                ->join('teams', 'teams.id', '=', 'job_information.team_id')
                ->whereIn('leave_requests.employee_id', $filteredEmployeeIds)
                ->whereBetween('leave_requests.start_date', [$start->toDateString(), $end->toDateString()])
                ->where('leave_requests.status', 'approved')
                ->whereNotNull('teams.department')
                ->selectRaw("teams.department, leave_requests.leave_type, COALESCE(SUM(leave_requests.total_days), 0) as total_days")
                ->groupBy('teams.department', 'leave_requests.leave_type')
                ->orderBy('teams.department')
                ->get();

            // Pivot by department
            $deptLeaveMap = [];
            foreach ($leaveByDepartment as $row) {
                if (! isset($deptLeaveMap[$row->department])) {
                    $deptLeaveMap[$row->department] = ['department' => $row->department, 'total_days' => 0, 'types' => []];
                }
                $deptLeaveMap[$row->department]['total_days'] += (int) $row->total_days;
                $deptLeaveMap[$row->department]['types'][] = ['type' => $row->leave_type, 'days' => (int) $row->total_days];
            }
            $leaveByDept = array_values($deptLeaveMap);

            // ── Top Leave Takers ────────────────────────────────────────────
            $topLeaveTakers = DB::table('leave_requests')
                ->join('employee_profiles', 'employee_profiles.id', '=', 'leave_requests.employee_id')
                ->join('users', 'users.id', '=', 'employee_profiles.user_id')
                ->whereIn('leave_requests.employee_id', $filteredEmployeeIds)
                ->whereBetween('leave_requests.start_date', [$start->toDateString(), $end->toDateString()])
                ->where('leave_requests.status', 'approved')
                ->groupBy('leave_requests.employee_id', 'users.name', 'employee_profiles.code')
                ->selectRaw("leave_requests.employee_id, users.name, employee_profiles.code, SUM(leave_requests.total_days) as total_days, COUNT(*) as request_count")
                ->orderByDesc('total_days')
                ->limit(10)
                ->get()
                ->map(fn ($r) => [
                    'employee_name' => $r->name,
                    'employee_code' => $r->code,
                    'total_days' => (int) $r->total_days,
                    'request_count' => (int) $r->request_count,
                ])
                ->values()->all();

            // ── Sick Leave Proof Compliance ──────────────────────────────────
            $proofCompliance = DB::table('leave_requests')
                ->whereIn('employee_id', $filteredEmployeeIds)
                ->whereBetween('start_date', [$start->toDateString(), $end->toDateString()])
                ->where('leave_type', 'sick_leave')
                ->where('status', 'approved')
                ->selectRaw("
                    COUNT(*) as total,
                    COUNT(CASE WHEN proof_file_path IS NOT NULL THEN 1 END) as with_proof,
                    COUNT(CASE WHEN proof_review_status = 'approved' THEN 1 END) as proof_approved
                ")
                ->first();

            $proofComplianceData = [
                'total_sick_leaves' => (int) $proofCompliance->total,
                'with_proof' => (int) $proofCompliance->with_proof,
                'proof_approved' => (int) $proofCompliance->proof_approved,
                'compliance_rate' => $proofCompliance->total > 0
                    ? round(($proofCompliance->with_proof / $proofCompliance->total) * 100, 1)
                    : 0,
            ];

            return [
                'period' => ['start' => $start->toDateString(), 'end' => $end->toDateString(), 'label' => $parsed['label']],
                'monthly_trend' => $monthlyTrend,
                'type_distribution' => $typeDistribution,
                'approval_rate' => $approvalRate,
                'avg_duration_by_type' => $avgDuration,
                'leave_by_department' => $leaveByDept,
                'top_leave_takers' => $topLeaveTakers,
                'proof_compliance' => $proofComplianceData,
            ];
        });
    }

    public function getPayrollAnalytics(string $period, ?string $department): array
    {
        $parsed = $this->parsePeriod($period);
        $cacheKey = CacheConstants::CACHE_KEY_ANALYTICS_PREFIX . 'payroll_' . md5(json_encode([$period, $department])) . '_' . now()->format('Y-m-d-H');

        return cache()->remember($cacheKey, CacheConstants::ONE_HOUR, function () use ($parsed, $department) {
            $start = $parsed['start'];
            $end = $parsed['end'];
            $filteredEmployeeIds = $this->getFilteredEmployeeIds($department, null);

            // ── Total Payroll Cost Trend ─────────────────────────────────────
            $costTrend = DB::table('payroll_details')
                ->join('payrolls', 'payrolls.id', '=', 'payroll_details.payroll_id')
                ->whereIn('payroll_details.employee_id', $filteredEmployeeIds)
                ->whereIn('payrolls.status', ['approved', 'paid'])
                ->whereBetween('payrolls.salary_month', [$start->toDateString(), $end->toDateString()])
                ->selectRaw("
                    DATE_FORMAT(payrolls.salary_month, '%Y-%m') as month_key,
                    COALESCE(SUM(payroll_details.final_salary), 0) as total_salary,
                    COALESCE(SUM(payroll_details.deduction_amount), 0) as total_deductions,
                    COUNT(DISTINCT payroll_details.employee_id) as employee_count,
                    ROUND(AVG(payroll_details.final_salary), 2) as avg_salary
                ")
                ->groupBy('month_key')
                ->orderBy('month_key')
                ->get()
                ->map(fn ($r) => [
                    'month' => Carbon::createFromFormat('Y-m', $r->month_key)->format('M Y'),
                    'total_salary' => round((float) $r->total_salary, 2),
                    'total_deductions' => round((float) $r->total_deductions, 2),
                    'employee_count' => (int) $r->employee_count,
                    'avg_salary' => round((float) $r->avg_salary, 2),
                ])
                ->values()->all();

            // ── Salary Distribution Histogram ────────────────────────────────
            $salaryDistribution = DB::table('payroll_details')
                ->join('payrolls', 'payrolls.id', '=', 'payroll_details.payroll_id')
                ->whereIn('payroll_details.employee_id', $filteredEmployeeIds)
                ->whereIn('payrolls.status', ['approved', 'paid'])
                ->whereRaw("payrolls.salary_month = (SELECT MAX(p2.salary_month) FROM payrolls p2 WHERE p2.status IN ('approved', 'paid'))")
                ->selectRaw("
                    CASE
                        WHEN final_salary < 3000000 THEN '<3M'
                        WHEN final_salary BETWEEN 3000000 AND 5000000 THEN '3-5M'
                        WHEN final_salary BETWEEN 5000001 AND 8000000 THEN '5-8M'
                        WHEN final_salary BETWEEN 8000001 AND 12000000 THEN '8-12M'
                        WHEN final_salary BETWEEN 12000001 AND 20000000 THEN '12-20M'
                        ELSE '20M+'
                    END as salary_range,
                    COUNT(*) as count
                ")
                ->groupBy('salary_range')
                ->orderByRaw("FIELD(salary_range, '<3M', '3-5M', '5-8M', '8-12M', '12-20M', '20M+')")
                ->get()
                ->map(fn ($r) => ['range' => $r->salary_range, 'count' => (int) $r->count])
                ->values()->all();

            // ── Tax & BPJS Contribution Trend ────────────────────────────────
            $taxBpjsTrend = DB::table('payroll_details')
                ->join('payrolls', 'payrolls.id', '=', 'payroll_details.payroll_id')
                ->whereIn('payroll_details.employee_id', $filteredEmployeeIds)
                ->whereIn('payrolls.status', ['approved', 'paid'])
                ->whereBetween('payrolls.salary_month', [$start->toDateString(), $end->toDateString()])
                ->selectRaw("
                    DATE_FORMAT(payrolls.salary_month, '%Y-%m') as month_key,
                    COALESCE(SUM(payroll_details.pph21_amount), 0) as pph21,
                    COALESCE(SUM(payroll_details.bpjs_tk_employee + payroll_details.bpjs_tk_employer), 0) as bpjs_tk,
                    COALESCE(SUM(payroll_details.bpjs_kes_employee + payroll_details.bpjs_kes_employer), 0) as bpjs_kes
                ")
                ->groupBy('month_key')
                ->orderBy('month_key')
                ->get()
                ->map(fn ($r) => [
                    'month' => Carbon::createFromFormat('Y-m', $r->month_key)->format('M Y'),
                    'pph21' => round((float) $r->pph21, 2),
                    'bpjs_tk' => round((float) $r->bpjs_tk, 2),
                    'bpjs_kes' => round((float) $r->bpjs_kes, 2),
                ])
                ->values()->all();

            // ── Cost per Department ──────────────────────────────────────────
            $costByDepartment = DB::table('payroll_details')
                ->join('payrolls', 'payrolls.id', '=', 'payroll_details.payroll_id')
                ->join('job_information', 'job_information.employee_id', '=', 'payroll_details.employee_id')
                ->join('teams', 'teams.id', '=', 'job_information.team_id')
                ->whereIn('payroll_details.employee_id', $filteredEmployeeIds)
                ->whereIn('payrolls.status', ['approved', 'paid'])
                ->whereRaw("payrolls.salary_month = (SELECT MAX(p2.salary_month) FROM payrolls p2 WHERE p2.status IN ('approved', 'paid'))")
                ->whereNotNull('teams.department')
                ->selectRaw("
                    teams.department,
                    COALESCE(SUM(payroll_details.final_salary), 0) as total_cost,
                    ROUND(AVG(payroll_details.final_salary), 2) as avg_salary,
                    COUNT(DISTINCT payroll_details.employee_id) as employee_count
                ")
                ->groupBy('teams.department')
                ->orderByDesc('total_cost')
                ->get()
                ->map(fn ($r) => [
                    'department' => $r->department,
                    'total_cost' => round((float) $r->total_cost, 2),
                    'avg_salary' => round((float) $r->avg_salary, 2),
                    'employee_count' => (int) $r->employee_count,
                ])
                ->values()->all();

            // ── Deduction Breakdown (latest month) ──────────────────────────
            $deductionBreakdown = DB::table('payroll_details')
                ->join('payrolls', 'payrolls.id', '=', 'payroll_details.payroll_id')
                ->whereIn('payroll_details.employee_id', $filteredEmployeeIds)
                ->whereIn('payrolls.status', ['approved', 'paid'])
                ->whereRaw("payrolls.salary_month = (SELECT MAX(p2.salary_month) FROM payrolls p2 WHERE p2.status IN ('approved', 'paid'))")
                ->selectRaw("
                    COALESCE(SUM(payroll_details.deduction_amount), 0) as attendance_deductions,
                    COALESCE(SUM(payroll_details.pph21_amount), 0) as tax,
                    COALESCE(SUM(payroll_details.bpjs_tk_employee), 0) as bpjs_tk_employee,
                    COALESCE(SUM(payroll_details.bpjs_kes_employee), 0) as bpjs_kes_employee
                ")
                ->first();

            $deductionData = [
                ['category' => 'Attendance Deductions', 'amount' => round((float) $deductionBreakdown->attendance_deductions, 2)],
                ['category' => 'PPh21 Tax', 'amount' => round((float) $deductionBreakdown->tax, 2)],
                ['category' => 'BPJS TK (Employee)', 'amount' => round((float) $deductionBreakdown->bpjs_tk_employee, 2)],
                ['category' => 'BPJS Kes (Employee)', 'amount' => round((float) $deductionBreakdown->bpjs_kes_employee, 2)],
            ];

            return [
                'period' => ['start' => $start->toDateString(), 'end' => $end->toDateString(), 'label' => $parsed['label']],
                'cost_trend' => $costTrend,
                'salary_distribution' => $salaryDistribution,
                'tax_bpjs_trend' => $taxBpjsTrend,
                'cost_by_department' => $costByDepartment,
                'deduction_breakdown' => $deductionData,
            ];
        });
    }

    public function getProjectAnalytics(string $period, ?int $projectId): array
    {
        $parsed = $this->parsePeriod($period);
        $cacheKey = CacheConstants::CACHE_KEY_ANALYTICS_PREFIX . 'projects_' . md5(json_encode([$period, $projectId])) . '_' . now()->format('Y-m-d-H');

        return cache()->remember($cacheKey, CacheConstants::ONE_HOUR, function () use ($parsed, $projectId) {
            $start = $parsed['start'];
            $end = $parsed['end'];

            // ── Task Velocity (tasks completed per month) ───────────────────
            $taskVelocity = DB::table('project_task_status_logs')
                ->join('project_tasks', 'project_tasks.id', '=', 'project_task_status_logs.project_task_id')
                ->where('project_task_status_logs.to_status', 'done')
                ->whereBetween('project_task_status_logs.changed_at', [$start->toDateString(), $end->toDateString()])
                ->when($projectId, fn ($q) => $q->where('project_tasks.project_id', $projectId))
                ->selectRaw("
                    DATE_FORMAT(project_task_status_logs.changed_at, '%Y-%m') as month_key,
                    COUNT(*) as completed_count
                ")
                ->groupBy('month_key')
                ->orderBy('month_key')
                ->get()
                ->map(fn ($r) => [
                    'month' => Carbon::createFromFormat('Y-m', $r->month_key)->format('M Y'),
                    'completed' => (int) $r->completed_count,
                ])
                ->values()->all();

            // ── Task Status Distribution ─────────────────────────────────────
            $taskStatusDistribution = DB::table('project_tasks')
                ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
                ->selectRaw("status, COUNT(*) as count")
                ->groupBy('status')
                ->orderByDesc('count')
                ->get()
                ->map(fn ($r) => ['status' => $r->status, 'count' => (int) $r->count])
                ->values()->all();

            // ── Task Priority Distribution ───────────────────────────────────
            $taskPriorityDistribution = DB::table('project_tasks')
                ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
                ->selectRaw("priority, COUNT(*) as count")
                ->groupBy('priority')
                ->orderByDesc('count')
                ->get()
                ->map(fn ($r) => ['priority' => $r->priority, 'count' => (int) $r->count])
                ->values()->all();

            // ── Overdue Tasks ────────────────────────────────────────────────
            $overdueTasks = DB::table('project_tasks')
                ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
                ->whereNotNull('due_date')
                ->where('due_date', '<', now()->toDateString())
                ->whereNotIn('status', ['done', 'cancelled'])
                ->count();

            $totalActiveTasks = DB::table('project_tasks')
                ->when($projectId, fn ($q) => $q->where('project_id', $projectId))
                ->whereNotIn('status', ['done', 'cancelled'])
                ->count();

            // ── Project Status Overview ──────────────────────────────────────
            $projectStatusOverview = DB::table('projects')
                ->selectRaw("status, COUNT(*) as count")
                ->groupBy('status')
                ->orderByDesc('count')
                ->get()
                ->map(fn ($r) => ['status' => $r->status, 'count' => (int) $r->count])
                ->values()->all();

            // ── Project Type Distribution ────────────────────────────────────
            $projectTypeDistribution = DB::table('projects')
                ->whereNotNull('type')
                ->selectRaw("type, COUNT(*) as count")
                ->groupBy('type')
                ->orderByDesc('count')
                ->get()
                ->map(fn ($r) => ['type' => $r->type, 'count' => (int) $r->count])
                ->values()->all();

            // ── Team Productivity (tasks completed per team per month) ──────
            $teamProductivity = DB::table('project_task_status_logs')
                ->join('project_tasks', 'project_tasks.id', '=', 'project_task_status_logs.project_task_id')
                ->join('team_members', function ($join) {
                    $join->on('team_members.employee_id', '=', 'project_tasks.assignee_id')
                        ->whereNull('team_members.left_at');
                })
                ->join('teams', 'teams.id', '=', 'team_members.team_id')
                ->where('project_task_status_logs.to_status', 'done')
                ->whereBetween('project_task_status_logs.changed_at', [$start->toDateString(), $end->toDateString()])
                ->when($projectId, fn ($q) => $q->where('project_tasks.project_id', $projectId))
                ->selectRaw("teams.name as team_name, COUNT(*) as completed_count")
                ->groupBy('teams.id', 'teams.name')
                ->orderByDesc('completed_count')
                ->get()
                ->map(fn ($r) => [
                    'team_name' => $r->team_name,
                    'completed' => (int) $r->completed_count,
                ])
                ->values()->all();

            return [
                'period' => ['start' => $start->toDateString(), 'end' => $end->toDateString(), 'label' => $parsed['label']],
                'task_velocity' => $taskVelocity,
                'task_status_distribution' => $taskStatusDistribution,
                'task_priority_distribution' => $taskPriorityDistribution,
                'overdue_tasks' => $overdueTasks,
                'total_active_tasks' => $totalActiveTasks,
                'project_status_overview' => $projectStatusOverview,
                'project_type_distribution' => $projectTypeDistribution,
                'team_productivity' => $teamProductivity,
            ];
        });
    }

    /**
     * Get filtered employee IDs based on department and team filters.
     */
    private function getFilteredEmployeeIds(?string $department, ?int $teamId): \Illuminate\Support\Collection
    {
        return DB::table('employee_profiles')
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

    /**
     * Get turnover rate metrics from snapshots
     */
    public function getTurnoverRate(string $period, ?string $department): array
    {
        $parsed = $this->parsePeriod($period);
        
        return $this->getSnapshotMetric(
            'workforce',
            'turnover_rate',
            'monthly',
            $parsed['start']->toDateString(),
            $parsed['end']->toDateString()
        ) ?? ['data' => [], 'average' => 0];
    }

    /**
     * Get average tenure metrics
     */
    public function getAverageTenure(?string $department): array
    {
        $latest = DB::table('analytics_snapshots')
            ->where('metric_type', 'workforce')
            ->where('metric_name', 'average_tenure_days')
            ->orderByDesc('period_end')
            ->first();

        return [
            'average_tenure_days' => $latest ? (float) $latest->value : 0,
            'average_tenure_years' => $latest ? round($latest->value / 365, 1) : 0,
        ];
    }

    /**
     * Get new hire trends
     */
    public function getNewHireTrends(string $period, ?string $department): array
    {
        $parsed = $this->parsePeriod($period);
        
        return $this->getSnapshotMetric(
            'workforce',
            'new_hires',
            'monthly',
            $parsed['start']->toDateString(),
            $parsed['end']->toDateString()
        ) ?? ['data' => [], 'total' => 0];
    }

    /**
     * Get attendance compliance rate
     */
    public function getAttendanceComplianceRate(string $period, ?string $department): array
    {
        $parsed = $this->parsePeriod($period);
        
        return $this->getSnapshotMetric(
            'attendance',
            'compliance_rate',
            'monthly',
            $parsed['start']->toDateString(),
            $parsed['end']->toDateString()
        ) ?? ['data' => [], 'average' => 0];
    }

    /**
     * Get attendance patterns (day of week analysis)
     */
    public function getAttendancePatterns(string $period, ?string $department): array
    {
        $parsed = $this->parsePeriod($period);
        $start = $parsed['start'];
        $end = $parsed['end'];

        $patterns = DB::table('attendances')
            ->whereBetween('date', [$start->toDateString(), $end->toDateString()])
            ->selectRaw("
                DAYNAME(date) as day_name,
                DAYOFWEEK(date) as day_number,
                COUNT(*) as total_records,
                COUNT(CASE WHEN status IN ('present', 'late', 'half_day') THEN 1 END) as attended,
                COUNT(CASE WHEN status = 'late' THEN 1 END) as late_count
            ")
            ->groupBy('day_name', 'day_number')
            ->orderBy('day_number')
            ->get()
            ->map(fn ($r) => [
                'day' => $r->day_name,
                'attendance_rate' => $r->total_records > 0 ? round(($r->attended / $r->total_records) * 100, 2) : 0,
                'late_rate' => $r->attended > 0 ? round(($r->late_count / $r->attended) * 100, 2) : 0,
            ])
            ->values()->all();

        return ['patterns' => $patterns];
    }

    /**
     * Get remote vs office ratio
     */
    public function getRemoteOfficeRatio(string $period, ?string $department): array
    {
        $parsed = $this->parsePeriod($period);
        
        return $this->getSnapshotMetric(
            'attendance',
            'remote_ratio',
            'daily',
            $parsed['start']->toDateString(),
            $parsed['end']->toDateString()
        ) ?? ['data' => [], 'average_remote_ratio' => 0];
    }

    /**
     * Get leave utilization rate
     */
    public function getLeaveUtilizationRate(string $period, ?string $department): array
    {
        $parsed = $this->parsePeriod($period);
        
        return $this->getSnapshotMetric(
            'leave',
            'utilization_rate',
            'monthly',
            $parsed['start']->toDateString(),
            $parsed['end']->toDateString()
        ) ?? ['data' => [], 'average' => 0];
    }

    /**
     * Get leave balance trends
     */
    public function getLeaveBalanceTrends(string $period, ?string $department): array
    {
        $parsed = $this->parsePeriod($period);
        $start = $parsed['start'];
        $end = $parsed['end'];

        $trends = DB::table('leave_entitlements')
            ->selectRaw("
                leave_type,
                SUM(total_days) as total_entitled,
                SUM(used_days) as total_used,
                SUM(total_days - used_days) as total_remaining
            ")
            ->groupBy('leave_type')
            ->get()
            ->map(fn ($r) => [
                'leave_type' => $r->leave_type,
                'entitled' => (float) $r->total_entitled,
                'used' => (float) $r->total_used,
                'remaining' => (float) $r->total_remaining,
                'utilization_rate' => $r->total_entitled > 0 ? round(($r->total_used / $r->total_entitled) * 100, 2) : 0,
            ])
            ->values()->all();

        return ['trends' => $trends];
    }

    /**
     * Get peak leave periods
     */
    public function getPeakLeavePeriods(string $period): array
    {
        $parsed = $this->parsePeriod($period);
        $start = $parsed['start'];
        $end = $parsed['end'];

        $peaks = DB::table('leave_requests')
            ->whereBetween('start_date', [$start->toDateString(), $end->toDateString()])
            ->where('status', 'approved')
            ->selectRaw("
                DATE_FORMAT(start_date, '%Y-%m') as month,
                COUNT(*) as request_count,
                SUM(DATEDIFF(end_date, start_date) + 1) as total_days
            ")
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->map(fn ($r) => [
                'month' => $r->month,
                'requests' => (int) $r->request_count,
                'total_days' => (int) $r->total_days,
            ])
            ->values()->all();

        return ['peak_periods' => $peaks];
    }

    /**
     * Get payroll cost trends
     */
    public function getPayrollCostTrends(string $period, ?string $department): array
    {
        $parsed = $this->parsePeriod($period);
        
        $costData = $this->getSnapshotMetric(
            'payroll',
            'total_cost',
            'monthly',
            $parsed['start']->toDateString(),
            $parsed['end']->toDateString()
        );

        $perEmployeeData = $this->getSnapshotMetric(
            'payroll',
            'cost_per_employee',
            'monthly',
            $parsed['start']->toDateString(),
            $parsed['end']->toDateString()
        );

        return [
            'total_cost_trend' => $costData['data'] ?? [],
            'cost_per_employee_trend' => $perEmployeeData['data'] ?? [],
        ];
    }

    /**
     * Get salary distribution
     */
    public function getSalaryDistribution(?string $department): array
    {
        $query = DB::table('payroll_details')
            ->join('payrolls', 'payrolls.id', '=', 'payroll_details.payroll_id')
            ->join('employee_profiles', 'employee_profiles.id', '=', 'payroll_details.employee_id')
            ->where('payrolls.status', 'paid')
            ->whereNull('employee_profiles.deleted_at');

        if ($department) {
            $query->join('job_information', 'job_information.employee_id', '=', 'employee_profiles.id')
                ->join('teams', 'teams.id', '=', 'job_information.team_id')
                ->where('teams.department', $department);
        }

        $distribution = $query
            ->selectRaw("
                CASE
                    WHEN original_salary < 5000000 THEN '< 5M'
                    WHEN original_salary < 10000000 THEN '5M - 10M'
                    WHEN original_salary < 15000000 THEN '10M - 15M'
                    WHEN original_salary < 20000000 THEN '15M - 20M'
                    ELSE '> 20M'
                END as salary_range,
                COUNT(DISTINCT payroll_details.employee_id) as employee_count
            ")
            ->groupBy('salary_range')
            ->get()
            ->map(fn ($r) => [
                'range' => $r->salary_range,
                'count' => (int) $r->employee_count,
            ])
            ->values()->all();

        return ['distribution' => $distribution];
    }

    /**
     * Get deduction analysis
     */
    public function getDeductionAnalysis(string $period, ?string $department): array
    {
        $parsed = $this->parsePeriod($period);
        
        return $this->getSnapshotMetric(
            'payroll',
            'deduction_rate',
            'monthly',
            $parsed['start']->toDateString(),
            $parsed['end']->toDateString()
        ) ?? ['data' => [], 'average' => 0];
    }

    /**
     * Get project timeline adherence
     */
    public function getProjectTimelineAdherence(string $period): array
    {
        $parsed = $this->parsePeriod($period);
        $start = $parsed['start'];
        $end = $parsed['end'];

        $adherence = DB::table('projects')
            ->whereBetween('end_date', [$start->toDateString(), $end->toDateString()])
            ->where('status', 'completed')
            ->selectRaw("
                COUNT(*) as total_completed,
                COUNT(CASE WHEN completed_at <= end_date THEN 1 END) as on_time_count,
                COUNT(CASE WHEN completed_at > end_date THEN 1 END) as late_count
            ")
            ->first();

        $adherenceRate = $adherence->total_completed > 0
            ? round(($adherence->on_time_count / $adherence->total_completed) * 100, 2)
            : 0;

        return [
            'adherence_rate' => $adherenceRate,
            'total_completed' => (int) $adherence->total_completed,
            'on_time' => (int) $adherence->on_time_count,
            'late' => (int) $adherence->late_count,
        ];
    }

    /**
     * Get task velocity
     */
    public function getTaskVelocity(string $period, ?int $teamId): array
    {
        $parsed = $this->parsePeriod($period);
        
        return $this->getSnapshotMetric(
            'project',
            'task_velocity',
            'monthly',
            $parsed['start']->toDateString(),
            $parsed['end']->toDateString()
        ) ?? ['data' => [], 'average' => 0];
    }

    /**
     * Get overdue trends
     */
    public function getOverdueTrends(string $period): array
    {
        $parsed = $this->parsePeriod($period);
        
        return $this->getSnapshotMetric(
            'project',
            'overdue_tasks',
            'daily',
            $parsed['start']->toDateString(),
            $parsed['end']->toDateString()
        ) ?? ['data' => [], 'current' => 0];
    }

    /**
     * Helper method to retrieve snapshot metrics
     */
    public function getSnapshotMetric(
        string $metricType,
        string $metricName,
        string $periodType,
        string $startDate,
        string $endDate
    ): ?array {
        $snapshots = DB::table('analytics_snapshots')
            ->where('metric_type', $metricType)
            ->where('metric_name', $metricName)
            ->where('period_type', $periodType)
            ->whereBetween('period_start', [$startDate, $endDate])
            ->orderBy('period_start')
            ->get();

        if ($snapshots->isEmpty()) {
            return null;
        }

        $data = $snapshots->map(fn ($s) => [
            'period' => $s->period_start,
            'value' => (float) $s->value,
            'metadata' => json_decode($s->metadata, true),
        ])->values()->all();

        $average = $snapshots->avg('value');

        return [
            'data' => $data,
            'average' => round($average, 2),
            'latest' => (float) $snapshots->last()->value,
        ];
    }

    /**
     * Get team performance summary
     */
    public function getTeamPerformanceSummary(int $teamId, ?int $cycleId = null): array
    {
        $query = DB::table('performance_reviews as pr')
            ->join('employee_profiles as ep', 'pr.employee_id', '=', 'ep.id')
            ->join('team_members as tm', 'tm.employee_id', '=', 'ep.id')
            ->where('tm.team_id', $teamId)
            ->whereNull('tm.left_at')
            ->whereNull('ep.deleted_at');

        if ($cycleId) {
            $query->where('pr.review_cycle_id', $cycleId);
        }

        $reviews = $query->select(
            'pr.id',
            'pr.employee_id',
            'pr.overall_rating',
            'pr.status',
            'ep.full_name as employee_name'
        )->get();

        $totalReviews = $reviews->count();
        $completedReviews = $reviews->where('status', 'completed')->count();
        $averageRating = $reviews->where('overall_rating', '>', 0)->avg('overall_rating');

        return [
            'team_id' => $teamId,
            'total_reviews' => $totalReviews,
            'completed_reviews' => $completedReviews,
            'completion_rate' => $totalReviews > 0 ? round(($completedReviews / $totalReviews) * 100, 1) : 0,
            'average_rating' => $averageRating ? round($averageRating, 2) : null,
            'reviews' => $reviews->toArray(),
        ];
    }

    /**
     * Get company-wide performance summary
     */
    public function getCompanyPerformanceSummary(?int $cycleId = null): array
    {
        $query = DB::table('performance_reviews as pr')
            ->join('employee_profiles as ep', 'pr.employee_id', '=', 'ep.id')
            ->whereNull('ep.deleted_at');

        if ($cycleId) {
            $query->where('pr.review_cycle_id', $cycleId);
        }

        $reviews = $query->select(
            'pr.id',
            'pr.employee_id',
            'pr.overall_rating',
            'pr.status',
            'pr.review_type'
        )->get();

        $totalReviews = $reviews->count();
        $completedReviews = $reviews->where('status', 'completed')->count();
        $averageRating = $reviews->where('overall_rating', '>', 0)->avg('overall_rating');

        // Rating distribution
        $ratingDistribution = $reviews
            ->where('overall_rating', '>', 0)
            ->groupBy('overall_rating')
            ->map(fn ($group) => $group->count())
            ->toArray();

        // Status breakdown
        $statusBreakdown = $reviews
            ->groupBy('status')
            ->map(fn ($group) => $group->count())
            ->toArray();

        return [
            'total_reviews' => $totalReviews,
            'completed_reviews' => $completedReviews,
            'completion_rate' => $totalReviews > 0 ? round(($completedReviews / $totalReviews) * 100, 1) : 0,
            'average_rating' => $averageRating ? round($averageRating, 2) : null,
            'rating_distribution' => $ratingDistribution,
            'status_breakdown' => $statusBreakdown,
        ];
    }

    /**
     * Get rating distribution across the company
     */
    public function getRatingDistribution(?int $cycleId = null): array
    {
        $query = DB::table('performance_reviews as pr')
            ->join('employee_profiles as ep', 'pr.employee_id', '=', 'ep.id')
            ->whereNull('ep.deleted_at')
            ->where('pr.status', 'completed')
            ->whereNotNull('pr.overall_rating');

        if ($cycleId) {
            $query->where('pr.review_cycle_id', $cycleId);
        }

        $ratings = $query->select('pr.overall_rating')->get();

        $distribution = [
            1 => 0,
            2 => 0,
            3 => 0,
            4 => 0,
            5 => 0,
        ];

        foreach ($ratings as $rating) {
            $ratingValue = (int) $rating->overall_rating;
            if (isset($distribution[$ratingValue])) {
                $distribution[$ratingValue]++;
            }
        }

        $total = $ratings->count();

        return [
            'distribution' => $distribution,
            'percentages' => array_map(
                fn ($count) => $total > 0 ? round(($count / $total) * 100, 1) : 0,
                $distribution
            ),
            'total' => $total,
            'average' => $total > 0 ? round($ratings->avg('overall_rating'), 2) : null,
        ];
    }

    /**
     * Get goal completion rate metrics
     */
    public function getGoalCompletionRate(?int $employeeId = null, ?int $teamId = null): array
    {
        $query = DB::table('performance_goals as pg')
            ->join('employee_profiles as ep', 'pg.employee_id', '=', 'ep.id')
            ->whereNull('ep.deleted_at');

        if ($employeeId) {
            $query->where('pg.employee_id', $employeeId);
        }

        if ($teamId) {
            $query->join('team_members as tm', 'tm.employee_id', '=', 'ep.id')
                ->where('tm.team_id', $teamId)
                ->whereNull('tm.left_at');
        }

        $goals = $query->select(
            'pg.id',
            'pg.employee_id',
            'pg.title',
            'pg.status',
            'pg.progress_percentage',
            'pg.category',
            'pg.target_date'
        )->get();

        $totalGoals = $goals->count();
        $completedGoals = $goals->where('status', 'completed')->count();
        $inProgressGoals = $goals->where('status', 'in_progress')->count();
        $notStartedGoals = $goals->where('status', 'not_started')->count();

        // Average progress
        $averageProgress = $goals->avg('progress_percentage');

        // Goals by category
        $byCategory = $goals->groupBy('category')->map(function ($categoryGoals) {
            $total = $categoryGoals->count();
            $completed = $categoryGoals->where('status', 'completed')->count();
            return [
                'total' => $total,
                'completed' => $completed,
                'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
            ];
        })->toArray();

        // Overdue goals
        $now = Carbon::now()->toDateString();
        $overdueGoals = $goals->filter(function ($goal) use ($now) {
            return $goal->status !== 'completed'
                && $goal->status !== 'cancelled'
                && $goal->target_date < $now;
        })->count();

        return [
            'total_goals' => $totalGoals,
            'completed_goals' => $completedGoals,
            'in_progress_goals' => $inProgressGoals,
            'not_started_goals' => $notStartedGoals,
            'completion_rate' => $totalGoals > 0 ? round(($completedGoals / $totalGoals) * 100, 1) : 0,
            'average_progress' => $averageProgress ? round($averageProgress, 1) : 0,
            'by_category' => $byCategory,
            'overdue_goals' => $overdueGoals,
        ];
    }

    /**
     * Get feedback metrics
     */
    public function getFeedbackMetrics(?int $employeeId = null, ?int $teamId = null): array
    {
        $query = DB::table('performance_feedback as pf')
            ->join('employee_profiles as ep', 'pf.employee_id', '=', 'ep.id')
            ->whereNull('ep.deleted_at');

        if ($employeeId) {
            $query->where(function ($q) use ($employeeId) {
                $q->where('pf.employee_id', $employeeId)
                    ->orWhere('pf.giver_id', $employeeId);
            });
        }

        if ($teamId) {
            $query->join('team_members as tm', 'tm.employee_id', '=', 'ep.id')
                ->where('tm.team_id', $teamId)
                ->whereNull('tm.left_at');
        }

        $feedback = $query->select(
            'pf.id',
            'pf.employee_id',
            'pf.giver_id',
            'pf.feedback_type',
            'pf.is_private',
            'pf.created_at'
        )->get();

        $totalFeedback = $feedback->count();

        // Feedback by type
        $byType = $feedback->groupBy('feedback_type')->map(fn ($group) => $group->count())->toArray();

        // Recent feedback (last 30 days)
        $thirtyDaysAgo = Carbon::now()->subDays(30);
        $recentFeedback = $feedback->filter(fn ($f) => Carbon::parse($f->created_at)->gte($thirtyDaysAgo))->count();

        return [
            'total_feedback' => $totalFeedback,
            'by_type' => $byType,
            'recent_feedback_30d' => $recentFeedback,
            'average_per_employee' => $employeeId ? null : ($totalFeedback > 0 ? round($totalFeedback / DB::table('employee_profiles')->whereNull('deleted_at')->count(), 2) : 0),
        ];
    }
}
