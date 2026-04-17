<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AnalyticsExportController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceCorrectionController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeProfileController;
use App\Http\Controllers\LeaveBalanceController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OptionController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\PayrollSettingController;
use App\Http\Controllers\PayslipController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectTaskController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->group(function () {

        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:5,1');

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('me', [AuthController::class, 'me']);
            Route::put('me', [AuthController::class, 'updateProfile']);

            Route::post('logout', [AuthController::class, 'logout']);

            Route::get('teams/statistics', [TeamController::class, 'getStatistics']);
            Route::get('teams/all/paginated', [TeamController::class, 'getAllPaginated']);
            Route::get('teams/{team}/statistics', [TeamController::class, 'getTeamStatistics']);
            Route::get('teams/{team}/chart-data', [TeamController::class, 'getTeamChartData']);
            Route::post('teams/{team}/add-member', [TeamController::class, 'addMember']);
            Route::post('teams/{team}/remove-member', [TeamController::class, 'removeMember']);
            Route::apiResource('teams', TeamController::class);

            Route::get('my-profile', [EmployeeProfileController::class, 'getMyProfile']);
            Route::get('my-notifications/unread-count', [NotificationController::class, 'getUnreadCount']);
            Route::get('my-notifications', [NotificationController::class, 'getMyNotifications']);
            Route::post('my-notifications/{notificationId}/mark-as-read', [NotificationController::class, 'markAsRead']);
            Route::get('my-team', [EmployeeProfileController::class, 'getMyTeam']);
            Route::get('my-team/members', [EmployeeProfileController::class, 'getMyTeamMembers']);
            Route::get('my-team/projects', [EmployeeProfileController::class, 'getMyTeamProjects']);
            Route::get('employees/statistics', [EmployeeProfileController::class, 'getStatistics']);
            Route::get('employees/{id}/performance-statistics', [EmployeeProfileController::class, 'getPerformanceStatistics']);
            Route::get('employees/all/paginated', [EmployeeProfileController::class, 'getAllPaginated']);
            Route::post('employees/check-availability', [EmployeeProfileController::class, 'checkAvailability']);
            Route::apiResource('employees', EmployeeProfileController::class);

            Route::get('projects/statistics', [ProjectController::class, 'getStatistics']);
            Route::get('projects/all/paginated', [ProjectController::class, 'getAllPaginated']);
            Route::get('projects/{id}/squad-summary', [ProjectController::class, 'getSquadSummary']);
            Route::apiResource('projects', ProjectController::class);

            Route::apiResource('project-tasks', ProjectTaskController::class);
            Route::get('project-tasks/all/paginated', [ProjectTaskController::class, 'getAllPaginated']);
            Route::get('project-tasks/{id}/comments', [ProjectTaskController::class, 'getComments']);
            Route::post('project-tasks/{id}/comments', [ProjectTaskController::class, 'storeComment']);
            Route::put('project-tasks/{id}/comments/{commentId}', [ProjectTaskController::class, 'updateComment']);
            Route::delete('project-tasks/{id}/comments/{commentId}', [ProjectTaskController::class, 'deleteComment']);
            Route::get('project-tasks/{id}/attachments', [ProjectTaskController::class, 'getAttachments']);
            Route::get('project-tasks/{id}/status-logs', [ProjectTaskController::class, 'getStatusLogs']);
            Route::post('project-tasks/{id}/attachments', [ProjectTaskController::class, 'storeAttachment']);
            Route::delete('project-tasks/{id}/attachments/{attachmentId}', [ProjectTaskController::class, 'deleteAttachment']);

            Route::get('attendances/all/paginated', [AttendanceController::class, 'getAllPaginated']);
            Route::get('attendances/statistics', [AttendanceController::class, 'getStatistics']);
            Route::get('my-attendances', [AttendanceController::class, 'getMyAttendances']);
            Route::get('my-attendance-statistics', [AttendanceController::class, 'getMyAttendanceStatistics']);
            Route::get('attendances/last-attendance', [AttendanceController::class, 'getLastAttendance']);
            Route::post('attendances/check-in', [AttendanceController::class, 'checkIn']);
            Route::post('attendances/check-out', [AttendanceController::class, 'checkOut']);
            Route::post('attendance-policy-mismatches/{id}/acknowledge', [AttendanceController::class, 'acknowledgePolicyMismatch']);
            Route::post('attendance-policy-mismatches/{id}/resolve', [AttendanceController::class, 'resolvePolicyMismatch']);
            Route::apiResource('attendances', AttendanceController::class)->only(['index', 'show']);

            // Attendance Correction routes
            Route::get('attendance-corrections/all/paginated', [AttendanceCorrectionController::class, 'getAllPaginated']);
            Route::get('my-attendance-corrections', [AttendanceCorrectionController::class, 'getMyCorrections']);
            Route::get('attendance-corrections/{id}', [AttendanceCorrectionController::class, 'show']);
            Route::post('attendance-corrections', [AttendanceCorrectionController::class, 'store']);
            Route::post('attendance-corrections/{id}/approve', [AttendanceCorrectionController::class, 'approve']);
            Route::post('attendance-corrections/{id}/reject', [AttendanceCorrectionController::class, 'reject']);

            Route::apiResource('leave-requests', LeaveRequestController::class)->only(['index', 'show', 'store']);
            Route::get('leave-requests/all/paginated', [LeaveRequestController::class, 'getAllPaginated']);
            Route::get('my-leave-requests', [LeaveRequestController::class, 'getMyLeaveRequests']);
            Route::post('leave-requests/approve/{id}', [LeaveRequestController::class, 'approve']);
            Route::post('leave-requests/reject/{id}', [LeaveRequestController::class, 'reject']);
            Route::post('leave-requests/{id}/proof', [LeaveRequestController::class, 'uploadProof']);
            Route::post('leave-requests/{id}/proof-review', [LeaveRequestController::class, 'reviewProof']);

            // Leave Balance routes
            Route::get('my-leave-balances', [LeaveBalanceController::class, 'getMyBalances']);

            // Payroll routes
            Route::get('payrolls/statistics', [PayrollController::class, 'getStatistics']);
            Route::get('payrolls/analytics', [PayrollController::class, 'getAnalytics']);
            Route::get('payroll-settings', [PayrollSettingController::class, 'show']);
            Route::get('payroll-settings/history', [PayrollSettingController::class, 'history']);
            Route::put('payroll-settings', [PayrollSettingController::class, 'update']);
            Route::get('payrolls/all/paginated', [PayrollController::class, 'getAllPaginated']);
            Route::get('payrolls/export-report', [PayrollController::class, 'exportReport']);
            Route::get('payrolls/generate-readiness', [PayrollController::class, 'generateReadiness']);
            Route::get('payrolls/readiness-dashboard', [PayrollController::class, 'readinessDashboard']);
            Route::post('payrolls/generate', [PayrollController::class, 'generate']);
            Route::get('payrolls/{id}/statistics', [PayrollController::class, 'getPayrollStatistics']);
            Route::get('payrolls/{id}/details', [PayrollController::class, 'getDetails']); // Paginated details
            Route::get('payrolls/{id}/reconciliation', [PayrollController::class, 'getReconciliation']);
            Route::get('payrolls/{id}/activity-logs', [PayrollController::class, 'getActivityLogs']);
            Route::get('payrolls/{id}/notification-deliveries', [PayrollController::class, 'getNotificationDeliveries']);
            Route::get('payrolls/{id}/export-excel', [PayrollController::class, 'exportExcel']);
            Route::post('payrolls/{id}/approve', [PayrollController::class, 'approvePayroll']);
            Route::post('payrolls/{id}/mark-as-paid', [PayrollController::class, 'markAsPaid']);
            Route::post('payrolls/{id}/reopen', [PayrollController::class, 'reopenPayroll']);
            Route::post('payrolls/{id}/resend-notifications', [PayrollController::class, 'resendNotifications']);
            Route::put('payroll-details/{id}', [PayrollController::class, 'updateDetail']);
            Route::apiResource('payrolls', PayrollController::class)->only(['index', 'show']);
            Route::get('my-payslips', [PayslipController::class, 'index']);
            Route::get('my-payslips/{id}', [PayslipController::class, 'show']);
            Route::get('payslips/{id}/download', [PayslipController::class, 'download']);

            // Project Tasks by project
            Route::get('projects/{id}/tasks', [ProjectTaskController::class, 'getByProject']);

            // Options routes
            Route::get('options/departments', [OptionController::class, 'getDepartments']);
            Route::get('options/employment-types', [OptionController::class, 'getEmploymentTypes']);
            Route::get('options/job-statuses', [OptionController::class, 'getJobStatuses']);
            Route::get('options/task-priorities', [OptionController::class, 'getTaskPriorities']);
            Route::get('options/task-statuses', [OptionController::class, 'getTaskStatuses']);
            Route::get('options/leave-types', [OptionController::class, 'getLeaveTypes']);
            Route::get('options/work-locations', [OptionController::class, 'getWorkLocations']);
            Route::get('options/skill-levels', [OptionController::class, 'getSkillLevels']);
            Route::get('options/religions', [OptionController::class, 'getReligions']);
            Route::get('options/marital-statuses', [OptionController::class, 'getMaritalStatuses']);
            Route::get('options/blood-types', [OptionController::class, 'getBloodTypes']);
            Route::get('options/ptkp-statuses', [OptionController::class, 'getPtkpStatuses']);

            // Dashboard routes
            Route::get('dashboard/statistics', [DashboardController::class, 'getStatistics']);
            Route::get('dashboard/my-statistics', [DashboardController::class, 'getEmployeeStatistics']);
            Route::get('dashboard/today-attendance-overview', [DashboardController::class, 'getTodayAttendanceOverview']);

            // Analytics routes
            Route::get('analytics/executive-summary', [AnalyticsController::class, 'getExecutiveSummary']);
            Route::get('analytics/workforce', [AnalyticsController::class, 'getWorkforceAnalytics']);
            Route::get('analytics/attendance', [AnalyticsController::class, 'getAttendanceAnalytics']);
            Route::get('analytics/leave', [AnalyticsController::class, 'getLeaveAnalytics']);
            Route::get('analytics/payroll', [AnalyticsController::class, 'getPayrollAnalytics']);
            Route::get('analytics/projects', [AnalyticsController::class, 'getProjectAnalytics']);
            Route::get('analytics/export/excel', [AnalyticsExportController::class, 'exportExcel']);
            Route::get('analytics/export/pdf', [AnalyticsExportController::class, 'exportPdf']);
        });
    });
