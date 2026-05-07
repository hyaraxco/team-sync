<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Interfaces\AnalyticsRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Middleware\PermissionMiddleware;

class AnalyticsController extends Controller implements HasMiddleware
{
    private AnalyticsRepositoryInterface $analyticsRepository;

    public function __construct(AnalyticsRepositoryInterface $analyticsRepository)
    {
        $this->analyticsRepository = $analyticsRepository;
    }

    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['analytics-view'])),
        ];
    }

    public function getExecutiveSummary(Request $request)
    {
        try {
            $data = $this->analyticsRepository->getExecutiveSummary(
                $request->input('period', '6m'),
                $request->input('department'),
                $request->input('team_id') ? (int) $request->input('team_id') : null,
            );

            return ResponseHelper::jsonResponse(true, 'Executive summary retrieved successfully', $data, 200);
        } catch (\Throwable $e) {
            Log::error('AnalyticsController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function getWorkforceAnalytics(Request $request)
    {
        try {
            $data = $this->analyticsRepository->getWorkforceAnalytics(
                $request->input('period', '12m'),
                $request->input('department'),
            );

            return ResponseHelper::jsonResponse(true, 'Workforce analytics retrieved successfully', $data, 200);
        } catch (\Throwable $e) {
            Log::error('AnalyticsController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function getAttendanceAnalytics(Request $request)
    {
        try {
            $data = $this->analyticsRepository->getAttendanceAnalytics(
                $request->input('period', '6m'),
                $request->input('department'),
                $request->input('team_id') ? (int) $request->input('team_id') : null,
            );

            return ResponseHelper::jsonResponse(true, 'Attendance analytics retrieved successfully', $data, 200);
        } catch (\Throwable $e) {
            Log::error('AnalyticsController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function getLeaveAnalytics(Request $request)
    {
        try {
            $data = $this->analyticsRepository->getLeaveAnalytics(
                $request->input('period', '12m'),
                $request->input('department'),
            );

            return ResponseHelper::jsonResponse(true, 'Leave analytics retrieved successfully', $data, 200);
        } catch (\Throwable $e) {
            Log::error('AnalyticsController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function getPayrollAnalytics(Request $request)
    {
        try {
            $data = $this->analyticsRepository->getPayrollAnalytics(
                $request->input('period', '12m'),
                $request->input('department'),
            );

            return ResponseHelper::jsonResponse(true, 'Payroll analytics retrieved successfully', $data, 200);
        } catch (\Throwable $e) {
            Log::error('AnalyticsController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function getProjectAnalytics(Request $request)
    {
        try {
            $data = $this->analyticsRepository->getProjectAnalytics(
                $request->input('period', '6m'),
                $request->input('project_id') ? (int) $request->input('project_id') : null,
            );

            return ResponseHelper::jsonResponse(true, 'Project analytics retrieved successfully', $data, 200);
        } catch (\Throwable $e) {
            Log::error('AnalyticsController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    // Enhanced Workforce Analytics
    public function getTurnoverRate(Request $request)
    {
        try {
            $data = $this->analyticsRepository->getTurnoverRate(
                $request->input('period', '12m'),
                $request->input('department'),
            );

            return ResponseHelper::jsonResponse(true, 'Turnover rate retrieved successfully', $data, 200);
        } catch (\Throwable $e) {
            Log::error('AnalyticsController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function getAverageTenure(Request $request)
    {
        try {
            $data = $this->analyticsRepository->getAverageTenure(
                $request->input('department'),
            );

            return ResponseHelper::jsonResponse(true, 'Average tenure retrieved successfully', $data, 200);
        } catch (\Throwable $e) {
            Log::error('AnalyticsController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function getNewHireTrends(Request $request)
    {
        try {
            $data = $this->analyticsRepository->getNewHireTrends(
                $request->input('period', '12m'),
                $request->input('department'),
            );

            return ResponseHelper::jsonResponse(true, 'New hire trends retrieved successfully', $data, 200);
        } catch (\Throwable $e) {
            Log::error('AnalyticsController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    // Enhanced Attendance Analytics
    public function getAttendanceComplianceRate(Request $request)
    {
        try {
            $data = $this->analyticsRepository->getAttendanceComplianceRate(
                $request->input('period', '6m'),
                $request->input('department'),
            );

            return ResponseHelper::jsonResponse(true, 'Attendance compliance rate retrieved successfully', $data, 200);
        } catch (\Throwable $e) {
            Log::error('AnalyticsController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function getAttendancePatterns(Request $request)
    {
        try {
            $data = $this->analyticsRepository->getAttendancePatterns(
                $request->input('period', '3m'),
                $request->input('department'),
            );

            return ResponseHelper::jsonResponse(true, 'Attendance patterns retrieved successfully', $data, 200);
        } catch (\Throwable $e) {
            Log::error('AnalyticsController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function getRemoteOfficeRatio(Request $request)
    {
        try {
            $data = $this->analyticsRepository->getRemoteOfficeRatio(
                $request->input('period', '3m'),
                $request->input('department'),
            );

            return ResponseHelper::jsonResponse(true, 'Remote/office ratio retrieved successfully', $data, 200);
        } catch (\Throwable $e) {
            Log::error('AnalyticsController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    // Enhanced Leave Analytics
    public function getLeaveUtilizationRate(Request $request)
    {
        try {
            $data = $this->analyticsRepository->getLeaveUtilizationRate(
                $request->input('period', '12m'),
                $request->input('department'),
            );

            return ResponseHelper::jsonResponse(true, 'Leave utilization rate retrieved successfully', $data, 200);
        } catch (\Throwable $e) {
            Log::error('AnalyticsController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function getLeaveBalanceTrends(Request $request)
    {
        try {
            $data = $this->analyticsRepository->getLeaveBalanceTrends(
                $request->input('period', '12m'),
                $request->input('department'),
            );

            return ResponseHelper::jsonResponse(true, 'Leave balance trends retrieved successfully', $data, 200);
        } catch (\Throwable $e) {
            Log::error('AnalyticsController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function getPeakLeavePeriods(Request $request)
    {
        try {
            $data = $this->analyticsRepository->getPeakLeavePeriods(
                $request->input('period', '12m'),
            );

            return ResponseHelper::jsonResponse(true, 'Peak leave periods retrieved successfully', $data, 200);
        } catch (\Throwable $e) {
            Log::error('AnalyticsController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    // Enhanced Payroll Analytics
    public function getPayrollCostTrends(Request $request)
    {
        try {
            $data = $this->analyticsRepository->getPayrollCostTrends(
                $request->input('period', '12m'),
                $request->input('department'),
            );

            return ResponseHelper::jsonResponse(true, 'Payroll cost trends retrieved successfully', $data, 200);
        } catch (\Throwable $e) {
            Log::error('AnalyticsController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function getSalaryDistribution(Request $request)
    {
        try {
            $data = $this->analyticsRepository->getSalaryDistribution(
                $request->input('department'),
            );

            return ResponseHelper::jsonResponse(true, 'Salary distribution retrieved successfully', $data, 200);
        } catch (\Throwable $e) {
            Log::error('AnalyticsController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function getDeductionAnalysis(Request $request)
    {
        try {
            $data = $this->analyticsRepository->getDeductionAnalysis(
                $request->input('period', '12m'),
                $request->input('department'),
            );

            return ResponseHelper::jsonResponse(true, 'Deduction analysis retrieved successfully', $data, 200);
        } catch (\Throwable $e) {
            Log::error('AnalyticsController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    // Enhanced Project Analytics
    public function getProjectTimelineAdherence(Request $request)
    {
        try {
            $data = $this->analyticsRepository->getProjectTimelineAdherence(
                $request->input('period', '6m'),
            );

            return ResponseHelper::jsonResponse(true, 'Project timeline adherence retrieved successfully', $data, 200);
        } catch (\Throwable $e) {
            Log::error('AnalyticsController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function getTaskVelocity(Request $request)
    {
        try {
            $data = $this->analyticsRepository->getTaskVelocity(
                $request->input('period', '6m'),
                $request->input('team_id') ? (int) $request->input('team_id') : null,
            );

            return ResponseHelper::jsonResponse(true, 'Task velocity retrieved successfully', $data, 200);
        } catch (\Throwable $e) {
            Log::error('AnalyticsController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function getOverdueTrends(Request $request)
    {
        try {
            $data = $this->analyticsRepository->getOverdueTrends(
                $request->input('period', '3m'),
            );

            return ResponseHelper::jsonResponse(true, 'Overdue trends retrieved successfully', $data, 200);
        } catch (\Throwable $e) {
            Log::error('AnalyticsController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    // Gap-fill Analytics Endpoints (from spec audit)
    public function getWorkforceDemographics(Request $request)
    {
        try {
            $data = $this->analyticsRepository->getWorkforceDemographicsEndpoint(
                $request->input('period', '12m'),
                $request->input('department'),
            );

            return ResponseHelper::jsonResponse(true, 'Workforce demographics retrieved successfully', $data, 200);
        } catch (\Throwable $e) {
            Log::error('AnalyticsController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function getAttendanceCorrectionFrequency(Request $request)
    {
        try {
            $data = $this->analyticsRepository->getAttendanceCorrectionFrequency(
                $request->input('period', '6m'),
                $request->input('department'),
            );

            return ResponseHelper::jsonResponse(true, 'Attendance correction frequency retrieved successfully', $data, 200);
        } catch (\Throwable $e) {
            Log::error('AnalyticsController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function getLeaveApprovalTurnaround(Request $request)
    {
        try {
            $data = $this->analyticsRepository->getLeaveApprovalTurnaround(
                $request->input('period', '12m'),
                $request->input('department'),
            );

            return ResponseHelper::jsonResponse(true, 'Leave approval turnaround retrieved successfully', $data, 200);
        } catch (\Throwable $e) {
            Log::error('AnalyticsController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function getLeaveTypeDistribution(Request $request)
    {
        try {
            $data = $this->analyticsRepository->getLeaveTypeDistribution(
                $request->input('period', '12m'),
                $request->input('department'),
            );

            return ResponseHelper::jsonResponse(true, 'Leave type distribution retrieved successfully', $data, 200);
        } catch (\Throwable $e) {
            Log::error('AnalyticsController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function getPayrollCostPerEmployee(Request $request)
    {
        try {
            $data = $this->analyticsRepository->getPayrollCostPerEmployee(
                $request->input('period', '12m'),
                $request->input('department'),
            );

            return ResponseHelper::jsonResponse(true, 'Payroll cost per employee retrieved successfully', $data, 200);
        } catch (\Throwable $e) {
            Log::error('AnalyticsController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function getPayrollProcessingTime(Request $request)
    {
        try {
            $data = $this->analyticsRepository->getPayrollProcessingTime(
                $request->input('period', '12m'),
            );

            return ResponseHelper::jsonResponse(true, 'Payroll processing time retrieved successfully', $data, 200);
        } catch (\Throwable $e) {
            Log::error('AnalyticsController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function getProjectResourceUtilization(Request $request)
    {
        try {
            $data = $this->analyticsRepository->getProjectResourceUtilization(
                $request->input('period', '6m'),
                $request->input('team_id') ? (int) $request->input('team_id') : null,
            );

            return ResponseHelper::jsonResponse(true, 'Project resource utilization retrieved successfully', $data, 200);
        } catch (\Throwable $e) {
            Log::error('AnalyticsController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    // Performance Management Analytics
    public function getTeamPerformanceSummary(Request $request)
    {
        try {
            $data = $this->analyticsRepository->getTeamPerformanceSummary(
                (int) $request->input('team_id'),
                $request->input('cycle_id') ? (int) $request->input('cycle_id') : null,
            );

            return ResponseHelper::jsonResponse(true, 'Team performance summary retrieved successfully', $data, 200);
        } catch (\Throwable $e) {
            Log::error('AnalyticsController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function getCompanyPerformanceSummary(Request $request)
    {
        try {
            $data = $this->analyticsRepository->getCompanyPerformanceSummary(
                $request->input('cycle_id') ? (int) $request->input('cycle_id') : null,
            );

            return ResponseHelper::jsonResponse(true, 'Company performance summary retrieved successfully', $data, 200);
        } catch (\Throwable $e) {
            Log::error('AnalyticsController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function getRatingDistribution(Request $request)
    {
        try {
            $data = $this->analyticsRepository->getRatingDistribution(
                $request->input('cycle_id') ? (int) $request->input('cycle_id') : null,
            );

            return ResponseHelper::jsonResponse(true, 'Rating distribution retrieved successfully', $data, 200);
        } catch (\Throwable $e) {
            Log::error('AnalyticsController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function getGoalCompletionRate(Request $request)
    {
        try {
            $data = $this->analyticsRepository->getGoalCompletionRate(
                $request->input('staff_member_id') ? (int) $request->input('staff_member_id') : null,
                $request->input('team_id') ? (int) $request->input('team_id') : null,
            );

            return ResponseHelper::jsonResponse(true, 'Goal completion rate retrieved successfully', $data, 200);
        } catch (\Throwable $e) {
            Log::error('AnalyticsController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function getFeedbackMetrics(Request $request)
    {
        try {
            $data = $this->analyticsRepository->getFeedbackMetrics(
                $request->input('staff_member_id') ? (int) $request->input('staff_member_id') : null,
                $request->input('team_id') ? (int) $request->input('team_id') : null,
            );

            return ResponseHelper::jsonResponse(true, 'Feedback metrics retrieved successfully', $data, 200);
        } catch (\Throwable $e) {
            Log::error('AnalyticsController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }
}
