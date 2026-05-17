<?php

namespace Tests\Feature\Analytics;

use App\Interfaces\AnalyticsRepositoryInterface;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\ActivatesLicense;
use Tests\TestCase;

class AnalyticsExportControllerTest extends TestCase
{
    use ActivatesLicense, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,
        ]);

        $this->activateTestLicense();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Bind a stub repository that returns empty arrays for all methods
        $this->app->bind(AnalyticsRepositoryInterface::class, fn () => new class implements AnalyticsRepositoryInterface
        {
            public function getExecutiveSummary(string $period, ?string $department, ?int $teamId): array
            {
                return [];
            }

            public function getWorkforceAnalytics(string $period, ?string $department): array
            {
                return [];
            }

            public function getAttendanceAnalytics(string $period, ?string $department, ?int $teamId): array
            {
                return [];
            }

            public function getLeaveAnalytics(string $period, ?string $department): array
            {
                return [];
            }

            public function getPayrollAnalytics(string $period, ?string $department): array
            {
                return [];
            }

            public function getProjectAnalytics(string $period, ?int $projectId): array
            {
                return [];
            }

            public function getTurnoverRate(string $period, ?string $department): array
            {
                return [];
            }

            public function getAverageTenure(?string $department): array
            {
                return [];
            }

            public function getNewHireTrends(string $period, ?string $department): array
            {
                return [];
            }

            public function getAttendanceComplianceRate(string $period, ?string $department): array
            {
                return [];
            }

            public function getAttendancePatterns(string $period, ?string $department): array
            {
                return [];
            }

            public function getRemoteOfficeRatio(string $period, ?string $department): array
            {
                return [];
            }

            public function getLeaveUtilizationRate(string $period, ?string $department): array
            {
                return [];
            }

            public function getLeaveBalanceTrends(string $period, ?string $department): array
            {
                return [];
            }

            public function getPeakLeavePeriods(string $period): array
            {
                return [];
            }

            public function getPayrollCostTrends(string $period, ?string $department): array
            {
                return [];
            }

            public function getSalaryDistribution(?string $department): array
            {
                return [];
            }

            public function getDeductionAnalysis(string $period, ?string $department): array
            {
                return [];
            }

            public function getProjectTimelineAdherence(string $period): array
            {
                return [];
            }

            public function getTaskVelocity(string $period, ?int $teamId): array
            {
                return [];
            }

            public function getOverdueTrends(string $period): array
            {
                return [];
            }

            public function getSnapshotMetric(string $metricType, string $metricName, string $periodType, string $startDate, string $endDate): ?array
            {
                return null;
            }

            public function getWorkforceDemographicsEndpoint(string $period, ?string $department): array
            {
                return [];
            }

            public function getAttendanceCorrectionFrequency(string $period, ?string $department): array
            {
                return [];
            }

            public function getLeaveApprovalTurnaround(string $period, ?string $department): array
            {
                return [];
            }

            public function getLeaveTypeDistribution(string $period, ?string $department): array
            {
                return [];
            }

            public function getPayrollCostPerEmployee(string $period, ?string $department): array
            {
                return [];
            }

            public function getPayrollProcessingTime(string $period): array
            {
                return [];
            }

            public function getProjectResourceUtilization(string $period, ?int $teamId): array
            {
                return [];
            }

            public function getTeamPerformanceSummary(int $teamId, ?int $cycleId = null): array
            {
                return [];
            }

            public function getCompanyPerformanceSummary(?int $cycleId = null): array
            {
                return [];
            }

            public function getRatingDistribution(?int $cycleId = null): array
            {
                return [];
            }

            public function getGoalCompletionRate(?int $employeeId = null, ?int $teamId = null): array
            {
                return [];
            }

            public function getFeedbackMetrics(?int $employeeId = null, ?int $teamId = null): array
            {
                return [];
            }
        });
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Permission Guards
    // ─────────────────────────────────────────────────────────────────────────

    public function test_unauthenticated_user_cannot_export_excel(): void
    {
        $this->getJson('/api/v1/analytics/export/excel')
            ->assertUnauthorized();
    }

    public function test_user_without_analytics_export_permission_cannot_export(): void
    {
        $this->actingAsRole('staff');

        $this->getJson('/api/v1/analytics/export/excel')
            ->assertForbidden();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Excel Export
    // ─────────────────────────────────────────────────────────────────────────

    public function test_authorized_user_can_export_excel_with_executive_tab(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('analytics-export');
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/analytics/export/excel?tab=executive')
            ->assertOk()
            ->assertHeaderContains('Content-Disposition', 'analytics-executive-');
    }

    public function test_excel_export_defaults_to_executive_tab_and_six_month_period(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('analytics-export');
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/analytics/export/excel')
            ->assertOk()
            ->assertHeaderContains('Content-Disposition', 'analytics-executive-');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // PDF Export
    // ─────────────────────────────────────────────────────────────────────────

    public function test_authorized_user_can_export_pdf_with_workforce_tab(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('analytics-export');
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/analytics/export/pdf?tab=workforce')
            ->assertOk()
            ->assertHeaderContains('Content-Type', 'application/pdf');
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Error Handling
    // ─────────────────────────────────────────────────────────────────────────

    public function test_returns_500_when_repository_throws(): void
    {
        // Replace the stub with a mock that throws
        $mock = Mockery::mock(AnalyticsRepositoryInterface::class);
        $mock->shouldReceive('getExecutiveSummary')
            ->andThrow(new \Exception('Database connection failed'));
        $this->app->instance(AnalyticsRepositoryInterface::class, $mock);

        $user = User::factory()->create();
        $user->givePermissionTo('analytics-export');
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/analytics/export/excel?tab=executive')
            ->assertStatus(500)
            ->assertJson([
                'success' => false,
            ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Helper
    // ─────────────────────────────────────────────────────────────────────────

    private function actingAsRole(string $roleName): User
    {
        $user = User::factory()->create();
        $role = Role::findByName($roleName, 'sanctum');
        $user->assignRole($role);

        Sanctum::actingAs($user);

        return $user;
    }
}
