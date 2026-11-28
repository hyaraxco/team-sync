<?php

namespace Tests\Feature\Analytics;

use App\Enums\AttendanceStatus;
use App\Enums\ProjectStatus;
use App\Enums\TaskStatus;
use App\Models\Attendance;
use App\Models\Payroll;
use App\Models\PayrollDetail;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\StaffMemberProfile;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Repositories\AnalyticsRepository;
use Carbon\Carbon;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use ReflectionMethod;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\ActivatesLicense;
use Tests\TestCase;

/**
 * Uneven-data aggregation tests for team-sync-dzo: member-based utilization,
 * per-member attendance/task aggregation (no row multiplication), and the
 * ProjectStatus::ACTIVE KPI filter.
 */
class AnalyticsRepositoryAggregationTest extends TestCase
{
    use ActivatesLicense, RefreshDatabase;

    private AnalyticsRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->repository = app(AnalyticsRepository::class);
    }

    public function test_resource_utilization_uses_member_ratio_not_task_row_average(): void
    {
        $team = Team::factory()->active()->create();
        $members = StaffMemberProfile::factory()->count(3)->create();

        foreach ($members as $member) {
            TeamMember::factory()->forTeam($team)->forEmployee($member)->create();
        }

        $project = Project::factory()->create();

        // One member carries five tasks, one carries a single task, one carries none.
        $this->createTasks($project, $members[0], 5);
        $this->createTasks($project, $members[1], 1);

        $result = $this->repository->getProjectResourceUtilization('1m', $team->id);
        $row = $result['team_utilization'][0];

        $this->assertSame(3, $row['total_members']);
        $this->assertSame(2, $row['members_with_tasks']);
        $this->assertSame(6, $row['total_tasks']);
        // 2 of 3 members have tasks. Averaging task rows would report 85.7.
        $this->assertSame(66.7, $row['utilization_rate']);
    }

    public function test_team_performance_aggregates_attendance_and_tasks_without_row_multiplication(): void
    {
        $team = Team::factory()->active()->create();
        $alice = StaffMemberProfile::factory()->create();
        $bob = StaffMemberProfile::factory()->create();

        TeamMember::factory()->forTeam($team)->forEmployee($alice)->create();
        TeamMember::factory()->forTeam($team)->forEmployee($bob)->create();

        // Alice: 3 present + 1 absent. Bob: 1 present.
        foreach ([0, 1, 2] as $offset) {
            $this->createAttendance($alice, Carbon::today()->subDays(10 + $offset), AttendanceStatus::PRESENT);
        }
        $this->createAttendance($alice, Carbon::today()->subDays(13), AttendanceStatus::ABSENT);
        $this->createAttendance($bob, Carbon::today()->subDays(11), AttendanceStatus::PRESENT);

        // Alice: 1 of 2 tasks done. Bob: 3 of 3 tasks done.
        $project = Project::factory()->create();
        $this->createTasks($project, $alice, 1, TaskStatus::DONE);
        $this->createTasks($project, $alice, 1, TaskStatus::TODO);
        $this->createTasks($project, $bob, 3, TaskStatus::DONE);

        $method = new ReflectionMethod(AnalyticsRepository::class, 'getTeamPerformanceData');
        $method->setAccessible(true);

        $rows = $method->invoke(
            $this->repository,
            Carbon::today()->subDays(30),
            Carbon::today(),
            null,
            $team->id,
        );

        $row = collect($rows)->firstWhere('team_name', $team->name);

        $this->assertSame(2, $row['member_count']);
        // TRUE: 4 of 5 attendances = 80.0. The cartesian join reported 81.8.
        $this->assertSame(80.0, $row['attendance_rate']);
        // TRUE: 4 of 5 tasks done = 80.0. The cartesian join reported 63.6.
        $this->assertSame(80.0, $row['task_completion_rate']);
    }

    public function test_task_created_before_period_with_past_due_date_does_not_count(): void
    {
        $team = Team::factory()->active()->create();
        $member = StaffMemberProfile::factory()->create();
        TeamMember::factory()->forTeam($team)->forEmployee($member)->create();

        $project = Project::factory()->create();

        // Finished long before the period: not part of this period's utilization.
        ProjectTask::factory()->create([
            'project_id' => $project->id,
            'assignee_id' => $member->id,
            'status' => TaskStatus::DONE->value,
            'due_date' => Carbon::today()->subDays(120)->toDateString(),
        ]);

        $result = $this->repository->getProjectResourceUtilization('1m', $team->id);
        $row = $result['team_utilization'][0];

        $this->assertSame(0, $row['members_with_tasks']);
        $this->assertSame(0.0, $row['utilization_rate']);
    }

    public function test_open_task_without_due_date_counts(): void
    {
        $team = Team::factory()->active()->create();
        $member = StaffMemberProfile::factory()->create();
        TeamMember::factory()->forTeam($team)->forEmployee($member)->create();

        $project = Project::factory()->create();

        ProjectTask::factory()->create([
            'project_id' => $project->id,
            'assignee_id' => $member->id,
            'status' => TaskStatus::IN_PROGRESS->value,
            'due_date' => null,
        ]);

        $result = $this->repository->getProjectResourceUtilization('1m', $team->id);
        $row = $result['team_utilization'][0];

        $this->assertSame(1, $row['members_with_tasks']);
        $this->assertSame(100.0, $row['utilization_rate']);
    }

    public function test_team_without_active_members_is_not_reported(): void
    {
        $team = Team::factory()->active()->create();

        // A member who left: the only membership row is closed.
        $member = StaffMemberProfile::factory()->create();
        TeamMember::factory()->forTeam($team)->forEmployee($member)->create([
            'left_at' => Carbon::today()->subMonth()->toDateString(),
        ]);

        $result = $this->repository->getProjectResourceUtilization('1m', $team->id);

        $this->assertSame([], $result['team_utilization']);
    }

    public function test_project_kpis_count_active_enum_status_and_ignore_concluded_projects(): void
    {
        Project::factory()->create(['status' => ProjectStatus::ACTIVE->value]);
        Project::factory()->create(['status' => ProjectStatus::ON_HOLD->value]);
        Project::factory()->create(['status' => ProjectStatus::COMPLETED->value]);
        Project::factory()->create(['status' => ProjectStatus::DRAFT->value]);

        $method = new ReflectionMethod(AnalyticsRepository::class, 'getProjectKpis');
        $method->setAccessible(true);

        $kpis = $method->invoke($this->repository, []);

        $this->assertSame(2, $kpis['active_projects']);
    }

    public function test_team_performance_excludes_tasks_outside_reporting_window(): void
    {
        $team = Team::factory()->active()->create();
        $member = StaffMemberProfile::factory()->create();
        TeamMember::factory()->forTeam($team)->forEmployee($member)->create();

        // 1 present attendance in period
        $this->createAttendance($member, Carbon::today()->subDays(5), AttendanceStatus::PRESENT);

        $project = Project::factory()->create();

        // Task 1: in window (created 15 days ago, due 5 days ago) - DONE
        ProjectTask::factory()->create([
            'project_id' => $project->id,
            'assignee_id' => $member->id,
            'status' => TaskStatus::DONE->value,
            'created_at' => Carbon::today()->subDays(15),
            'due_date' => Carbon::today()->subDays(5)->toDateString(),
        ]);

        // Task 2: finished long before window (due 60 days ago) - TODO (would drag completion rate down if included)
        ProjectTask::factory()->create([
            'project_id' => $project->id,
            'assignee_id' => $member->id,
            'status' => TaskStatus::TODO->value,
            'created_at' => Carbon::today()->subDays(90),
            'due_date' => Carbon::today()->subDays(60)->toDateString(),
        ]);

        // Task 3: created in the future after reporting window - TODO
        ProjectTask::factory()->create([
            'project_id' => $project->id,
            'assignee_id' => $member->id,
            'status' => TaskStatus::TODO->value,
            'created_at' => Carbon::today()->addDays(5),
            'due_date' => Carbon::today()->addDays(10)->toDateString(),
        ]);

        $method = new ReflectionMethod(AnalyticsRepository::class, 'getTeamPerformanceData');
        $method->setAccessible(true);

        $rows = $method->invoke(
            $this->repository,
            Carbon::today()->subDays(30),
            Carbon::today(),
            null,
            $team->id,
        );

        $row = collect($rows)->firstWhere('team_name', $team->name);

        $this->assertSame(1, $row['member_count']);
        // Only Task 1 participates: 1 of 1 task done = 100.0% completion rate
        $this->assertSame(100.0, $row['task_completion_rate']);
    }

    public function test_performance_analytics_aggregates_populated_rows_by_scope_and_schema(): void
    {
        $manager = StaffMemberProfile::factory()->create();
        $staff = StaffMemberProfile::factory()->create();
        $outside = StaffMemberProfile::factory()->create();
        $team = Team::factory()->active()->create();
        TeamMember::factory()->forTeam($team)->forEmployee($manager)->create();
        TeamMember::factory()->forTeam($team)->forEmployee($staff)->create();

        $cycle = \App\Models\PerformanceReviewCycle::factory()->create();
        foreach ([[$staff, 4.5], [$outside, 2.0]] as [$profile, $rating]) {
            \App\Models\PerformanceReview::create([
                'cycle_id' => $cycle->id,
                'staff_member_id' => $profile->id,
                'reviewer_id' => $manager->id,
                'status' => 'completed',
                'final_rating' => $rating,
            ]);
        }
        \App\Models\PerformanceGoal::create([
            'staff_member_id' => $staff->id, 'title' => 'Scoped goal', 'goal_type' => 'okr',
            'category' => 'delivery', 'start_date' => Carbon::today()->subMonth(),
            'due_date' => Carbon::today()->subDay(), 'status' => 'in_progress',
            'completion_percentage' => 40, 'created_by' => $manager->user_id,
        ]);
        \App\Models\PerformanceGoal::create([
            'staff_member_id' => $outside->id, 'title' => 'Outside goal', 'goal_type' => 'okr',
            'category' => 'quality', 'start_date' => Carbon::today()->subMonth(),
            'due_date' => Carbon::today()->addDay(), 'status' => 'in_progress',
            'completion_percentage' => 10, 'created_by' => $manager->user_id,
        ]);
        \App\Models\PerformanceFeedback::create([
            'staff_member_id' => $staff->id, 'given_by' => $manager->id,
            'feedback_type' => 'positive', 'content' => 'Useful feedback',
        ]);
        \App\Models\PerformanceFeedback::create([
            'staff_member_id' => $outside->id, 'given_by' => $manager->id,
            'feedback_type' => 'constructive', 'content' => 'Outside feedback',
        ]);

        $teamSummary = $this->repository->getTeamPerformanceSummary($team->id, $cycle->id);
        $this->assertSame(1, $teamSummary['total_reviews']);
        $this->assertSame(4.5, (float) $teamSummary['reviews'][0]->overall_rating);
        $this->assertSame($staff->user->name, $teamSummary['reviews'][0]->employee_name);

        $companySummary = $this->repository->getCompanyPerformanceSummary($cycle->id);
        $this->assertSame(2, $companySummary['total_reviews']);
        $this->assertSame(2, $companySummary['completed_reviews']);

        $ratings = $this->repository->getRatingDistribution($cycle->id);
        $this->assertSame(2, $ratings['total']);
        $this->assertSame(1, $ratings['distribution'][4]);
        $this->assertSame(1, $ratings['distribution'][2]);

        $goals = $this->repository->getGoalCompletionRate(null, $team->id);
        $this->assertSame(1, $goals['total_goals']);
        $this->assertSame(40.0, $goals['average_progress']);
        $this->assertSame(1, $goals['overdue_goals']);

        $feedback = $this->repository->getFeedbackMetrics(null, $team->id);
        $this->assertSame(1, $feedback['total_feedback']);
        $this->assertSame(['positive' => 1], $feedback['by_type']);
    }

    public function test_payroll_analytics_endpoint_returns_200_with_deterministic_totals_on_mysql(): void
    {
        // The endpoint uses DATE_FORMAT, which SQLite does not provide.
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Payroll analytics requires MySQL; SQLite lacks DATE_FORMAT.');
        }

        $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);
        $this->activateTestLicense();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        cache()->flush();

        // salary_month is unique: one payroll per month, with two employees in the latest.
        $approvedPayroll = Payroll::factory()->create([
            'salary_month' => Carbon::now()->startOfMonth()->format('Y-m-01'),
            'status' => 'approved',
        ]);
        $paidPayroll = Payroll::factory()->create([
            'salary_month' => Carbon::now()->subMonth()->startOfMonth()->format('Y-m-01'),
            'status' => 'paid',
        ]);
        $pendingPayroll = Payroll::factory()->create([
            'salary_month' => Carbon::now()->subMonths(2)->startOfMonth()->format('Y-m-01'),
            'status' => 'pending',
        ]);

        $alice = StaffMemberProfile::factory()->create();
        $bob = StaffMemberProfile::factory()->create();
        $this->createPayrollDetail($approvedPayroll, $alice, 5_000_000.0, 100_000.0);
        $this->createPayrollDetail($approvedPayroll, $bob, 3_000_000.0, 50_000.0);
        $this->createPayrollDetail($paidPayroll, $alice, 2_000_000.0, 25_000.0);
        $this->createPayrollDetail($pendingPayroll, $alice, 9_000_000.0, 900_000.0);

        $user = User::factory()->create();
        $user->assignRole(Role::findByName('finance', 'sanctum'));
        Sanctum::actingAs($user);

        $response = $this->getJson('/api/v1/analytics/payroll?period=3m');

        $response->assertOk()->assertJson(['success' => true]);
        $trend = collect($response->json('data.cost_trend'))->keyBy('month');

        $this->assertCount(2, $trend);
        $this->assertSame(8_000_000, $trend[Carbon::now()->format('M Y')]['total_salary']);
        $this->assertSame(150_000, $trend[Carbon::now()->format('M Y')]['total_deductions']);
        $this->assertSame(2, $trend[Carbon::now()->format('M Y')]['employee_count']);
        $this->assertSame(4_000_000, $trend[Carbon::now()->format('M Y')]['avg_salary']);
        $this->assertSame(2_000_000, $trend[Carbon::now()->subMonth()->format('M Y')]['total_salary']);
    }

    public function test_payroll_analytics_does_not_reference_missing_model_status_constants(): void
    {
        $this->assertSame(
            0,
            preg_match_all('/Payroll::STATUS_[A-Z_]+/', (string) file_get_contents(app_path('Repositories/AnalyticsRepository.php'))),
            'AnalyticsRepository must use PayrollStatus::*->value; the Payroll model defines no STATUS_* constants.'
        );
    }

    private function createPayrollDetail(Payroll $payroll, StaffMemberProfile $employee, float $finalSalary, float $deduction): void
    {
        PayrollDetail::create([
            'payroll_id' => $payroll->id,
            'staff_member_id' => $employee->id,
            'original_salary' => $finalSalary + $deduction,
            'final_salary' => $finalSalary,
            'deduction_amount' => $deduction,
            'pph21_amount' => 0,
            'bpjs_tk_employee' => 0,
            'bpjs_tk_employer' => 0,
            'bpjs_kes_employee' => 0,
            'bpjs_kes_employer' => 0,
            'overtime_amount' => 0,
        ]);
    }

    private function createTasks(Project $project, StaffMemberProfile $assignee, int $count, TaskStatus $status = TaskStatus::TODO): void
    {
        ProjectTask::factory()->count($count)->create([
            'project_id' => $project->id,
            'assignee_id' => $assignee->id,
            'status' => $status->value,
        ]);
    }

    private function createAttendance(StaffMemberProfile $member, Carbon $date, AttendanceStatus $status): void
    {
        Attendance::factory()->create([
            'staff_member_id' => $member->id,
            'date' => $date->toDateString(),
            'status' => $status->value,
        ]);
    }
}
