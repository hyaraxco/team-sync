<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Interfaces\AnalyticsRepositoryInterface;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
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
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
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
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
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
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
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
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
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
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
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
            return ResponseHelper::jsonResponse(false, 'Internal Server Error: ' . $e->getMessage(), null, 500);
        }
    }
}
