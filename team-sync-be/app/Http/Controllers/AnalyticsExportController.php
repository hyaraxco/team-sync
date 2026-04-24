<?php

namespace App\Http\Controllers;

use App\Exports\AnalyticsExport;
use App\Exports\AnalyticsMultiSheetExport;
use App\Helpers\ResponseHelper;
use App\Interfaces\AnalyticsRepositoryInterface;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Middleware\PermissionMiddleware;

class AnalyticsExportController extends Controller implements HasMiddleware
{
    private AnalyticsRepositoryInterface $analyticsRepository;

    public function __construct(AnalyticsRepositoryInterface $analyticsRepository)
    {
        $this->analyticsRepository = $analyticsRepository;
    }

    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['analytics-export'])),
        ];
    }

    /**
     * Export analytics data as Excel (multi-sheet)
     */
    public function exportExcel(Request $request)
    {
        try {
            $tab = $request->input('tab', 'executive');
            $period = $request->input('period', '6m');
            $department = $request->input('department');
            $teamId = $request->input('team_id') ? (int) $request->input('team_id') : null;

            $sheets = $this->buildSheets($tab, $period, $department, $teamId);
            $filename = 'analytics-'.$tab.'-'.now()->format('Y-m-d').'.xlsx';

            return Excel::download(new AnalyticsMultiSheetExport($sheets), $filename);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Export failed: '.$e->getMessage(), null, 500);
        }
    }

    /**
     * Export analytics data as PDF
     */
    public function exportPdf(Request $request)
    {
        try {
            $tab = $request->input('tab', 'executive');
            $period = $request->input('period', '6m');
            $department = $request->input('department');
            $teamId = $request->input('team_id') ? (int) $request->input('team_id') : null;

            $data = $this->fetchData($tab, $period, $department, $teamId);
            $filename = 'analytics-'.$tab.'-'.now()->format('Y-m-d').'.pdf';

            $pdf = Pdf::loadView('exports.analytics-pdf', [
                'tab' => $tab,
                'data' => $data,
                'period' => $period,
                'department' => $department,
                'generatedAt' => now()->format('d M Y H:i'),
            ]);

            $pdf->setPaper('a4', 'landscape');

            return $pdf->download($filename);
        } catch (\Throwable $e) {
            return ResponseHelper::jsonResponse(false, 'Export failed: '.$e->getMessage(), null, 500);
        }
    }

    private function fetchData(string $tab, string $period, ?string $department, ?int $teamId): array
    {
        return match ($tab) {
            'executive' => $this->analyticsRepository->getExecutiveSummary($period, $department, $teamId),
            'workforce' => $this->analyticsRepository->getWorkforceAnalytics($period, $department),
            'attendance' => $this->analyticsRepository->getAttendanceAnalytics($period, $department, $teamId),
            'leave' => $this->analyticsRepository->getLeaveAnalytics($period, $department),
            'payroll' => $this->analyticsRepository->getPayrollAnalytics($period, $department),
            'projects' => $this->analyticsRepository->getProjectAnalytics($period, null),
            default => [],
        };
    }

    /**
     * Build Excel sheets based on tab data
     */
    private function buildSheets(string $tab, string $period, ?string $department, ?int $teamId): array
    {
        $data = $this->fetchData($tab, $period, $department, $teamId);

        return match ($tab) {
            'executive' => $this->buildExecutiveSheets($data),
            'workforce' => $this->buildWorkforceSheets($data),
            'attendance' => $this->buildAttendanceSheets($data),
            'leave' => $this->buildLeaveSheets($data),
            'payroll' => $this->buildPayrollSheets($data),
            'projects' => $this->buildProjectSheets($data),
            default => [],
        };
    }

    private function buildExecutiveSheets(array $data): array
    {
        $sheets = [];

        // KPIs sheet
        $kpis = $data['kpis'] ?? [];
        $sheets[] = new AnalyticsExport(
            collect([
                ['Total Employees', $kpis['total_employees'] ?? 0],
                ['Employee Growth (%)', $kpis['employee_growth'] ?? 0],
                ['Attendance Rate (%)', $kpis['attendance_rate'] ?? 0],
                ['Average Salary', $kpis['average_salary'] ?? 0],
                ['Active Projects', $kpis['active_projects'] ?? 0],
                ['Task Completion Rate (%)', $kpis['task_completion_rate'] ?? 0],
                ['Leave Utilization (%)', $kpis['leave_utilization'] ?? 0],
            ]),
            ['Metric', 'Value'],
            'KPIs'
        );

        // Attendance vs Deduction Trend
        if (! empty($data['attendance_vs_deduction_trend'])) {
            $sheets[] = new AnalyticsExport(
                collect($data['attendance_vs_deduction_trend'])->map(fn ($r) => [
                    $r['month'], $r['attendance_rate'], $r['total_deductions'],
                ]),
                ['Month', 'Attendance Rate (%)', 'Total Deductions'],
                'Attendance vs Deductions'
            );
        }

        // Monthly HR Cost
        if (! empty($data['monthly_hr_cost'])) {
            $sheets[] = new AnalyticsExport(
                collect($data['monthly_hr_cost'])->map(fn ($r) => [
                    $r['month'], $r['salary'], $r['tax'], $r['bpjs'], $r['deductions'],
                ]),
                ['Month', 'Salary', 'Tax (PPh21)', 'BPJS', 'Deductions'],
                'Monthly HR Cost'
            );
        }

        // Team Performance
        if (! empty($data['team_performance'])) {
            $sheets[] = new AnalyticsExport(
                collect($data['team_performance'])->map(fn ($r) => [
                    $r['team_name'], $r['attendance_rate'], $r['task_completion'], $r['member_count'],
                ]),
                ['Team', 'Attendance Rate (%)', 'Task Completion (%)', 'Members'],
                'Team Performance'
            );
        }

        return $sheets;
    }

    private function buildWorkforceSheets(array $data): array
    {
        $sheets = [];

        if (! empty($data['headcount_trend'])) {
            $sheets[] = new AnalyticsExport(
                collect($data['headcount_trend'])->map(fn ($r) => [$r['month'], $r['count']]),
                ['Month', 'Headcount'],
                'Headcount Trend'
            );
        }

        $simpleSheets = [
            'gender_distribution' => ['Gender', 'Count', 'Gender Distribution', 'gender'],
            'employment_types' => ['Type', 'Count', 'Employment Types', 'type'],
            'work_locations' => ['Location', 'Count', 'Work Locations', 'location'],
            'department_headcount' => ['Department', 'Count', 'Department Headcount', 'department'],
            'skill_levels' => ['PTKP Status', 'Count', 'PTKP Status Distribution', 'level'],
            'age_distribution' => ['Age Range', 'Count', 'Age Distribution', 'range'],
            'tenure_distribution' => ['Tenure', 'Count', 'Tenure Distribution', 'range'],
        ];

        foreach ($simpleSheets as $key => [$col1, $col2, $title, $field]) {
            if (! empty($data[$key])) {
                $sheets[] = new AnalyticsExport(
                    collect($data[$key])->map(fn ($r) => [$r[$field], $r['count']]),
                    [$col1, $col2],
                    $title
                );
            }
        }

        return $sheets;
    }

    private function buildAttendanceSheets(array $data): array
    {
        $sheets = [];

        if (! empty($data['monthly_attendance_rate'])) {
            $sheets[] = new AnalyticsExport(
                collect($data['monthly_attendance_rate'])->map(fn ($r) => [
                    $r['month'], $r['attendance_rate'], $r['present'], $r['late'],
                    $r['absent'], $r['half_day'], $r['sick_leave'], $r['annual_leave'], $r['avg_hours'],
                ]),
                ['Month', 'Rate (%)', 'Present', 'Late', 'Absent', 'Half Day', 'Sick Leave', 'Annual Leave', 'Avg Hours'],
                'Monthly Attendance'
            );
        }

        if (! empty($data['top_late_employees'])) {
            $sheets[] = new AnalyticsExport(
                collect($data['top_late_employees'])->map(fn ($r) => [
                    $r['employee_name'], $r['employee_code'], $r['late_count'],
                ]),
                ['Name', 'Code', 'Late Count'],
                'Top Late Employees'
            );
        }

        if (! empty($data['correction_trend'])) {
            $sheets[] = new AnalyticsExport(
                collect($data['correction_trend'])->map(fn ($r) => [
                    $r['month'], $r['total'], $r['approved'], $r['rejected'], $r['pending'], $r['approval_rate'],
                ]),
                ['Month', 'Total', 'Approved', 'Rejected', 'Pending', 'Approval Rate (%)'],
                'Correction Requests'
            );
        }

        return $sheets;
    }

    private function buildLeaveSheets(array $data): array
    {
        $sheets = [];

        if (! empty($data['monthly_trend'])) {
            $sheets[] = new AnalyticsExport(
                collect($data['monthly_trend'])->map(fn ($r) => [
                    $r['month'], $r['total'], $r['approved'], $r['rejected'], $r['pending'],
                ]),
                ['Month', 'Total', 'Approved', 'Rejected', 'Pending'],
                'Monthly Leave Requests'
            );
        }

        if (! empty($data['type_distribution'])) {
            $sheets[] = new AnalyticsExport(
                collect($data['type_distribution'])->map(fn ($r) => [
                    $r['type'], $r['count'], $r['total_days'],
                ]),
                ['Leave Type', 'Count', 'Total Days'],
                'Leave Type Distribution'
            );
        }

        if (! empty($data['top_leave_takers'])) {
            $sheets[] = new AnalyticsExport(
                collect($data['top_leave_takers'])->map(fn ($r) => [
                    $r['employee_name'], $r['employee_code'], $r['total_days'], $r['request_count'],
                ]),
                ['Name', 'Code', 'Total Days', 'Requests'],
                'Top Leave Takers'
            );
        }

        return $sheets;
    }

    private function buildPayrollSheets(array $data): array
    {
        $sheets = [];

        if (! empty($data['cost_trend'])) {
            $sheets[] = new AnalyticsExport(
                collect($data['cost_trend'])->map(fn ($r) => [
                    $r['month'], $r['total_salary'], $r['total_deductions'], $r['employee_count'], $r['avg_salary'],
                ]),
                ['Month', 'Total Salary', 'Total Deductions', 'Employees', 'Avg Salary'],
                'Payroll Cost Trend'
            );
        }

        if (! empty($data['tax_bpjs_trend'])) {
            $sheets[] = new AnalyticsExport(
                collect($data['tax_bpjs_trend'])->map(fn ($r) => [
                    $r['month'], $r['pph21'], $r['bpjs_tk'], $r['bpjs_kes'],
                ]),
                ['Month', 'PPh21', 'BPJS TK', 'BPJS Kesehatan'],
                'Tax & BPJS Trend'
            );
        }

        if (! empty($data['cost_by_department'])) {
            $sheets[] = new AnalyticsExport(
                collect($data['cost_by_department'])->map(fn ($r) => [
                    $r['department'], $r['total_cost'], $r['avg_salary'], $r['employee_count'],
                ]),
                ['Department', 'Total Cost', 'Avg Salary', 'Employees'],
                'Cost by Department'
            );
        }

        return $sheets;
    }

    private function buildProjectSheets(array $data): array
    {
        $sheets = [];

        if (! empty($data['task_velocity'])) {
            $sheets[] = new AnalyticsExport(
                collect($data['task_velocity'])->map(fn ($r) => [$r['month'], $r['completed']]),
                ['Month', 'Tasks Completed'],
                'Task Velocity'
            );
        }

        if (! empty($data['task_status_distribution'])) {
            $sheets[] = new AnalyticsExport(
                collect($data['task_status_distribution'])->map(fn ($r) => [$r['status'], $r['count']]),
                ['Status', 'Count'],
                'Task Status'
            );
        }

        if (! empty($data['team_productivity'])) {
            $sheets[] = new AnalyticsExport(
                collect($data['team_productivity'])->map(fn ($r) => [$r['team_name'], $r['completed']]),
                ['Team', 'Tasks Completed'],
                'Team Productivity'
            );
        }

        return $sheets;
    }
}
