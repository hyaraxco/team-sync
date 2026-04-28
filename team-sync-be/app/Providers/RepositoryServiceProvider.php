<?php

namespace App\Providers;

use App\Interfaces\AnalyticsRepositoryInterface;
use App\Interfaces\AttendanceCorrectionRepositoryInterface;
use App\Interfaces\AttendanceRepositoryInterface;
use App\Interfaces\AuthRepositoryInterface;
use App\Interfaces\BankInformationRepositoryInterface;
use App\Interfaces\DashboardRepositoryInterface;
use App\Interfaces\EmergencyContactRepositoryInterface;
use App\Interfaces\JobInformationRepositoryInterface;
use App\Interfaces\HybridWorkScheduleRepositoryInterface;
use App\Interfaces\LeaveEntitlementRepositoryInterface;
use App\Interfaces\LeaveRequestRepositoryInterface;
use App\Interfaces\OptionRepositoryInterface;
use App\Interfaces\PayrollRepositoryInterface;
use App\Interfaces\PerformanceFeedbackRepositoryInterface;
use App\Interfaces\PerformanceGoalRepositoryInterface;
use App\Interfaces\PerformanceReviewRepositoryInterface;
use App\Interfaces\ProjectRepositoryInterface;
use App\Interfaces\ProjectTaskRepositoryInterface;
use App\Interfaces\StaffMemberProfileRepositoryInterface;
use App\Interfaces\TeamRepositoryInterface;
use App\Interfaces\UserRepositoryInterface;
use App\Repositories\AnalyticsRepository;
use App\Repositories\AttendanceCorrectionRepository;
use App\Repositories\AttendanceRepository;
use App\Repositories\AuthRepository;
use App\Repositories\BankInformationRepository;
use App\Repositories\DashboardRepository;
use App\Repositories\EmergencyContactRepository;
use App\Repositories\HybridWorkScheduleRepository;
use App\Repositories\JobInformationRepository;
use App\Repositories\LeaveEntitlementRepository;
use App\Repositories\LeaveRequestRepository;
use App\Repositories\OptionRepository;
use App\Repositories\PayrollRepository;
use App\Repositories\PerformanceFeedbackRepository;
use App\Repositories\PerformanceGoalRepository;
use App\Repositories\PerformanceReviewRepository;
use App\Repositories\ProjectRepository;
use App\Repositories\ProjectTaskRepository;
use App\Repositories\StaffMemberProfileRepository;
use App\Repositories\TeamRepository;
use App\Repositories\UserRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(AnalyticsRepositoryInterface::class, AnalyticsRepository::class);
        $this->app->bind(AttendanceCorrectionRepositoryInterface::class, AttendanceCorrectionRepository::class);
        $this->app->bind(AttendanceRepositoryInterface::class, AttendanceRepository::class);
        $this->app->bind(AuthRepositoryInterface::class, AuthRepository::class);
        $this->app->bind(BankInformationRepositoryInterface::class, BankInformationRepository::class);
        $this->app->bind(DashboardRepositoryInterface::class, DashboardRepository::class);
        $this->app->bind(EmergencyContactRepositoryInterface::class, EmergencyContactRepository::class);
        $this->app->bind(StaffMemberProfileRepositoryInterface::class, StaffMemberProfileRepository::class);
        $this->app->bind(JobInformationRepositoryInterface::class, JobInformationRepository::class);
        $this->app->bind(HybridWorkScheduleRepositoryInterface::class, HybridWorkScheduleRepository::class);
        $this->app->bind(LeaveEntitlementRepositoryInterface::class, LeaveEntitlementRepository::class);
        $this->app->bind(LeaveRequestRepositoryInterface::class, LeaveRequestRepository::class);
        $this->app->bind(OptionRepositoryInterface::class, OptionRepository::class);
        $this->app->bind(PayrollRepositoryInterface::class, PayrollRepository::class);
        $this->app->bind(ProjectRepositoryInterface::class, ProjectRepository::class);
        $this->app->bind(ProjectTaskRepositoryInterface::class, ProjectTaskRepository::class);
        $this->app->bind(TeamRepositoryInterface::class, TeamRepository::class);
        $this->app->bind(UserRepositoryInterface::class, UserRepository::class);
        $this->app->bind(PerformanceReviewRepositoryInterface::class, PerformanceReviewRepository::class);
        $this->app->bind(PerformanceGoalRepositoryInterface::class, PerformanceGoalRepository::class);
        $this->app->bind(PerformanceFeedbackRepositoryInterface::class, PerformanceFeedbackRepository::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
