<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AnalyticsExportController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AttendanceCorrectionController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmailVerificationController;
use App\Http\Controllers\LeaveBalanceController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\MeetingController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OptionController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PayrollController;
use App\Http\Controllers\PayrollSettingController;
use App\Http\Controllers\PayslipController;
use App\Http\Controllers\PerformanceFeedbackController;
use App\Http\Controllers\PerformanceGoalController;
use App\Http\Controllers\PerformanceOutcomeRuleController;
use App\Http\Controllers\PerformanceReviewController;
use App\Http\Controllers\PerformanceReviewTemplateController;
use App\Http\Controllers\PerformanceReviewCycleController;
use App\Http\Controllers\PerformanceTopsisController;
use App\Http\Controllers\AttendancePeriodController;
use App\Http\Controllers\AttendancePolicyController;
use App\Http\Controllers\LeaveEntitlementController;
use App\Http\Controllers\PayrollAdjustmentController;
use App\Http\Controllers\HybridWorkScheduleController;
use App\Http\Controllers\HybridScheduleOverrideController;
use App\Http\Controllers\HolidayCalendarController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectTaskController;
use App\Http\Controllers\StaffMemberProfileController;
use App\Http\Controllers\TeamController;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Middleware\PermissionMiddleware;

Route::prefix('v1')
    ->group(function () {

        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:60,1');
        Route::post('forgot-password', [PasswordResetController::class, 'forgotPassword'])->middleware('throttle:60,1');
        Route::post('reset-password', [PasswordResetController::class, 'resetPassword'])->middleware('throttle:60,1');
        Route::post('email/verification-notification', [EmailVerificationController::class, 'sendVerificationEmail'])->middleware('throttle:60,1');
        Route::get('email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])->name('verification.verify');

        Route::middleware('auth:sanctum')->group(function () {
            Route::get('me', [AuthController::class, 'me']);
            Route::put('me', [AuthController::class, 'updateProfile']);

            Route::post('logout', [AuthController::class, 'logout']);
            Route::post('email/verify/send', [EmailVerificationController::class, 'sendVerificationEmail'])->middleware('throttle:60,1');

            Route::get('teams/statistics', [TeamController::class, 'getStatistics']);
            Route::get('teams/all/paginated', [TeamController::class, 'getAllPaginated']);
            Route::get('teams/{team}/statistics', [TeamController::class, 'getTeamStatistics']);
            Route::get('teams/{team}/chart-data', [TeamController::class, 'getTeamChartData']);
            Route::post('teams/{team}/add-member', [TeamController::class, 'addMember']);
            Route::post('teams/{team}/remove-member', [TeamController::class, 'removeMember']);
            Route::apiResource('teams', TeamController::class);

            Route::get('meetings/all/paginated', [MeetingController::class, 'getAllPaginated']);
            Route::get('meetings/upcoming', [MeetingController::class, 'getUpcoming']);
            Route::apiResource('meetings', MeetingController::class)->only(['index', 'show', 'store']);

            Route::get('my-profile', [StaffMemberProfileController::class, 'getMyProfile']);
            Route::get('my-notifications/unread-count', [NotificationController::class, 'getUnreadCount']);
            Route::get('my-notifications', [NotificationController::class, 'getMyNotifications']);
            Route::post('my-notifications/{notificationId}/mark-as-read', [NotificationController::class, 'markAsRead']);
            Route::get('my-team', [StaffMemberProfileController::class, 'getMyTeam']);
            Route::get('my-team/members', [StaffMemberProfileController::class, 'getMyTeamMembers']);
            Route::get('my-team/projects', [StaffMemberProfileController::class, 'getMyTeamProjects']);
            Route::get('staff-members/statistics', [StaffMemberProfileController::class, 'getStatistics']);
            Route::get('staff-members/{id}/performance-statistics', [StaffMemberProfileController::class, 'getPerformanceStatistics']);
            Route::get('staff-members/all/paginated', [StaffMemberProfileController::class, 'getAllPaginated']);
            Route::post('staff-members/check-availability', [StaffMemberProfileController::class, 'checkAvailability']);
            Route::apiResource('staff-members', StaffMemberProfileController::class);

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
            Route::get('attendances/employee/{id}/statistics', [AttendanceController::class, 'getEmployeeStatistics']);
            Route::get('attendance-policy-mismatches', [AttendanceController::class, 'getPolicyMismatches']);
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

            Route::get('leave-requests/all/paginated', [LeaveRequestController::class, 'getAllPaginated']);
            Route::get('leave-requests/all/calendar', [LeaveRequestController::class, 'getCalendarRequests']);
            Route::get('my-leave-requests', [LeaveRequestController::class, 'getMyLeaveRequests']);
            Route::post('leave-requests/approve/{id}', [LeaveRequestController::class, 'approve']);
            Route::post('leave-requests/reject/{id}', [LeaveRequestController::class, 'reject']);
            Route::post('leave-requests/bulk-action', [LeaveRequestController::class, 'bulkAction']);
            Route::post('leave-requests/{id}/proof', [LeaveRequestController::class, 'uploadProof']);
            Route::post('leave-requests/{id}/proof-review', [LeaveRequestController::class, 'reviewProof']);
            Route::apiResource('leave-requests', LeaveRequestController::class)->only(['index', 'show', 'store']);

            // Leave Balance routes
            Route::get('my-leave-balances', [LeaveBalanceController::class, 'getMyBalances']);

            // Payroll routes
            Route::get('payrolls/statistics', [PayrollController::class, 'getStatistics']);
            Route::get('payrolls/analytics', [PayrollController::class, 'getAnalytics']);
            Route::get('payroll-settings', [PayrollSettingController::class, 'show']);
            Route::get('payroll-settings/history', [PayrollSettingController::class, 'history']);
            Route::get('payroll-settings/bpjs-rate-history', [PayrollSettingController::class, 'bpjsRateHistory']);
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
            Route::post('payslips/{id}/email', [PayslipController::class, 'email']);

            // Project Tasks by project
            Route::get('projects/{id}/tasks', [ProjectTaskController::class, 'getByProject']);

            // Attendance Periods
            Route::apiResource('attendance-periods', AttendancePeriodController::class)
                ->only(['index', 'store', 'update'])
                ->middleware(PermissionMiddleware::using('attendance-menu'));

            // Payroll Adjustments
            Route::get('payroll-adjustments', [PayrollAdjustmentController::class, 'index'])
                ->middleware(PermissionMiddleware::using('payroll-menu'));
            Route::post('payroll-adjustments/{id}/approve', [PayrollAdjustmentController::class, 'approve'])
                ->middleware(PermissionMiddleware::using('payroll-menu'));

            // Attendance Policies & Leave Entitlements
            Route::apiResource('attendance-policies', AttendancePolicyController::class)
                ->only(['index', 'update'])
                ->middleware(PermissionMiddleware::using('attendance-menu'));
            
            Route::apiResource('leave-entitlements', LeaveEntitlementController::class)
                ->only(['index', 'update'])
                ->middleware(PermissionMiddleware::using('attendance-menu'));

            // Hybrid Work Schedules
            Route::get('hybrid-schedules', [HybridWorkScheduleController::class, 'index'])
                ->middleware(PermissionMiddleware::using('attendance-menu'));
            Route::get('my-hybrid-schedule', [HybridWorkScheduleController::class, 'mySchedule']);
            Route::get('my-hybrid-overrides', [HybridWorkScheduleController::class, 'myOverrides']);

            Route::post('hybrid-schedule-overrides', [HybridScheduleOverrideController::class, 'store']);
            Route::post('hybrid-schedule-overrides/{hybridScheduleOverride}/approve', [HybridScheduleOverrideController::class, 'approve'])
                ->middleware(PermissionMiddleware::using('attendance-menu'));
            Route::post('hybrid-schedule-overrides/{hybridScheduleOverride}/reject', [HybridScheduleOverrideController::class, 'reject'])
                ->middleware(PermissionMiddleware::using('attendance-menu'));

            // Holiday Calendars
            Route::apiResource('holiday-calendars', HolidayCalendarController::class)
                ->except(['index'])
                ->middleware(PermissionMiddleware::using('attendance-menu'));
            Route::get('holiday-calendars', [HolidayCalendarController::class, 'index']);

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
            Route::get('options/project-task-templates', [OptionController::class, 'getProjectTaskTemplates']);

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

            // Enhanced Analytics routes
            Route::prefix('analytics')->group(function () {
                // Workforce
                Route::get('workforce/turnover-rate', [AnalyticsController::class, 'getTurnoverRate']);
                Route::get('workforce/average-tenure', [AnalyticsController::class, 'getAverageTenure']);
                Route::get('workforce/new-hire-trends', [AnalyticsController::class, 'getNewHireTrends']);

                // Attendance
                Route::get('attendance/compliance-rate', [AnalyticsController::class, 'getAttendanceComplianceRate']);
                Route::get('attendance/patterns', [AnalyticsController::class, 'getAttendancePatterns']);
                Route::get('attendance/remote-office-ratio', [AnalyticsController::class, 'getRemoteOfficeRatio']);

                // Leave
                Route::get('leave/utilization-rate', [AnalyticsController::class, 'getLeaveUtilizationRate']);
                Route::get('leave/balance-trends', [AnalyticsController::class, 'getLeaveBalanceTrends']);
                Route::get('leave/peak-periods', [AnalyticsController::class, 'getPeakLeavePeriods']);

                // Payroll
                Route::get('payroll/cost-trends', [AnalyticsController::class, 'getPayrollCostTrends']);
                Route::get('payroll/salary-distribution', [AnalyticsController::class, 'getSalaryDistribution']);
                Route::get('payroll/deduction-analysis', [AnalyticsController::class, 'getDeductionAnalysis']);

                // Projects
                Route::get('projects/timeline-adherence', [AnalyticsController::class, 'getProjectTimelineAdherence']);
                Route::get('projects/task-velocity', [AnalyticsController::class, 'getTaskVelocity']);
                Route::get('projects/overdue-trends', [AnalyticsController::class, 'getOverdueTrends']);

                // Gap-fill endpoints (from spec audit)
                Route::get('workforce/demographics', [AnalyticsController::class, 'getWorkforceDemographics']);
                Route::get('attendance/correction-frequency', [AnalyticsController::class, 'getAttendanceCorrectionFrequency']);
                Route::get('leave/approval-turnaround', [AnalyticsController::class, 'getLeaveApprovalTurnaround']);
                Route::get('leave/type-distribution', [AnalyticsController::class, 'getLeaveTypeDistribution']);
                Route::get('payroll/cost-per-employee', [AnalyticsController::class, 'getPayrollCostPerEmployee']);
                Route::get('payroll/processing-time', [AnalyticsController::class, 'getPayrollProcessingTime']);
                Route::get('project/resource-utilization', [AnalyticsController::class, 'getProjectResourceUtilization']);

                // Performance Management
                Route::get('performance/team-summary', [AnalyticsController::class, 'getTeamPerformanceSummary']);
                Route::get('performance/company-summary', [AnalyticsController::class, 'getCompanyPerformanceSummary']);
                Route::get('performance/rating-distribution', [AnalyticsController::class, 'getRatingDistribution']);
                Route::get('performance/goal-completion-rate', [AnalyticsController::class, 'getGoalCompletionRate']);
                Route::get('performance/feedback-metrics', [AnalyticsController::class, 'getFeedbackMetrics']);
            });

            // Performance Management routes
            Route::prefix('performance')->group(function () {
                // Review Cycles (HR only)
                Route::middleware(PermissionMiddleware::using(['review-cycle-manage']))->group(function () {
                    Route::apiResource('cycles', PerformanceReviewCycleController::class);
                    Route::post('cycles/{id}/generate-reviews', [PerformanceReviewCycleController::class, 'generateReviews']);
                    Route::get('cycles/{id}/topsis-ranking', [PerformanceTopsisController::class, 'ranking']);
                    Route::apiResource('outcome-rules', PerformanceOutcomeRuleController::class);
                    Route::apiResource('templates', PerformanceReviewTemplateController::class);
                });

                // Reviews
                Route::get('reviews/my-reviews', [PerformanceReviewController::class, 'getMyReviews']);
                Route::get('reviews/team-reviews', [PerformanceReviewController::class, 'getTeamReviews']);
                Route::get('reviews/pending-calibration', [PerformanceReviewController::class, 'getPendingCalibration'])
                    ->middleware(PermissionMiddleware::using(['review-calibrate']));
                Route::get('reviews/sections', [PerformanceReviewController::class, 'getActiveSections']);
                Route::get('reviews/{id}', [PerformanceReviewController::class, 'show']);
                Route::get('reviews/{id}/calibration-context', [PerformanceReviewController::class, 'getCalibrationContext'])
                    ->middleware(PermissionMiddleware::using(['review-calibrate']));
                Route::get('reviews/{id}/validate-readiness', [PerformanceReviewController::class, 'validateReadiness'])
                    ->middleware(PermissionMiddleware::using(['review-calibrate']));
                Route::post('reviews/{id}/self-assessment', [PerformanceReviewController::class, 'submitSelfAssessment']);
                Route::post('reviews/{id}/manager-assessment', [PerformanceReviewController::class, 'submitManagerAssessment']);
                Route::post('reviews/{id}/calibrate', [PerformanceReviewController::class, 'calibrateReview'])
                    ->middleware(PermissionMiddleware::using(['review-calibrate']));
                Route::put('reviews/{id}/assign-reviewer', [PerformanceReviewController::class, 'assignReviewer'])
                    ->middleware(PermissionMiddleware::using(['review-assign-reviewer']));

                // Goals
                Route::get('goals/my-goals', [PerformanceGoalController::class, 'getMyGoals']);
                Route::get('goals/team-goals', [PerformanceGoalController::class, 'getTeamGoals']);
                Route::get('goals/{id}/updates', [PerformanceGoalController::class, 'getProgressUpdates']);
                Route::post('goals/{id}/update-progress', [PerformanceGoalController::class, 'addProgressUpdate']);
                Route::apiResource('goals', PerformanceGoalController::class);

                // Feedback
                Route::get('feedback/received', [PerformanceFeedbackController::class, 'getReceivedFeedback']);
                Route::get('feedback/given', [PerformanceFeedbackController::class, 'getGivenFeedback']);
                Route::post('feedback', [PerformanceFeedbackController::class, 'store']);
                Route::get('feedback/{id}', [PerformanceFeedbackController::class, 'show']);
                Route::post('feedback/{id}/acknowledge', [PerformanceFeedbackController::class, 'acknowledge']);
            });
        });
    });
