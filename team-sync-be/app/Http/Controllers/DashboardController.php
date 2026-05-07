<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\TeamPulseNudgeRequest;
use App\Interfaces\DashboardRepositoryInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Log;
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
            // Company-wide statistics: HR/Superadmin only
            new Middleware(PermissionMiddleware::using(['dashboard-hr-view']), only: ['getStatistics', 'getTodayAttendanceOverview']),
            // Self-service employee dashboard: any authenticated user with dashboard-view
            new Middleware(PermissionMiddleware::using(['dashboard-view']), only: ['getEmployeeStatistics']),
            // Team pulse: manager-scoped
            new Middleware(PermissionMiddleware::using(['review-manager-submit']), only: ['getTeamPulse', 'sendTeamPulseNudge']),
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
            Log::error('DashboardController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

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
            Log::error('DashboardController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function getTodayAttendanceOverview()
    {
        try {
            $overview = $this->dashboardRepository->getTodayAttendanceOverview();

            return ResponseHelper::jsonResponse(true, 'Today Attendance Overview Retrieved Successfully', $overview, 200);
        } catch (\Throwable $e) {
            Log::error('DashboardController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function getTeamPulse()
    {
        try {
            $pulse = $this->dashboardRepository->getTeamPulse();

            return ResponseHelper::jsonResponse(true, 'Team Pulse Retrieved Successfully', $pulse, 200);
        } catch (AuthorizationException $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage() ?: 'Forbidden.', null, 403);
        } catch (\Throwable $e) {
            Log::error('DashboardController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }

    public function sendTeamPulseNudge(TeamPulseNudgeRequest $request, int $staffMemberId)
    {
        try {
            $result = $this->dashboardRepository->sendTeamPulseNudge(
                $staffMemberId,
                $request->validated('message')
            );

            return ResponseHelper::jsonResponse(true, 'Team Pulse nudge sent successfully', $result, 200);
        } catch (AuthorizationException $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage() ?: 'Forbidden.', null, 403);
        } catch (ModelNotFoundException $e) {
            return ResponseHelper::jsonResponse(false, 'Staff member not found.', null, 404);
        } catch (\Throwable $e) {
            Log::error('DashboardController Error: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return ResponseHelper::jsonResponse(false, 'Internal Server Error', null, 500);
        }
    }
}
