<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Interfaces\DashboardRepositoryInterface;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;

class DashboardController extends Controller implements HasMiddleware
{
    private DashboardRepositoryInterface $dashboardRepository;

    public function __construct(DashboardRepositoryInterface $dashboardRepository)
    {
        $this->dashboardRepository = $dashboardRepository;
    }

    public static function middleware()
    {
        return [
            new Middleware(PermissionMiddleware::using(['dashboard-view']), only: ['getStatistics', 'getEmployeeStatistics', 'getTodayAttendanceOverview']),
        ];
    }

    /**
     * Get dashboard statistics
     */
    public function getStatistics()
    {
        try {
            $statistics = $this->dashboardRepository->getStatistics();

            return ResponseHelper::jsonResponse(true, 'Dashboard Statistics Retrieved Successfully', $statistics, 200);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('DashboardController Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function getEmployeeStatistics()
    {
        try {
            $profile = auth()->user()->staffMemberProfile;
            if (! $profile) {
                return ResponseHelper::jsonResponse(true, 'Employee Dashboard Statistics Retrieved Successfully', [
                    'total_days' => 0,
                    'present_days' => 0,
                    'sick_days' => 0,
                    'absent_days' => 0,
                    'avg_hours' => 0,
                    'leave_balance' => 0,
                ], 200);
            }

            $employeeId = $profile->id;
            $statistics = $this->dashboardRepository->getEmployeeStatistics($employeeId);

            return ResponseHelper::jsonResponse(true, 'Employee Dashboard Statistics Retrieved Successfully', $statistics, 200);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('DashboardController Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function getTodayAttendanceOverview()
    {
        try {
            $overview = $this->dashboardRepository->getTodayAttendanceOverview();

            return ResponseHelper::jsonResponse(true, 'Today Attendance Overview Retrieved Successfully', $overview, 200);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('DashboardController Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }
}
