<?php

namespace App\Interfaces;

interface AnalyticsRepositoryInterface
{
    public function getExecutiveSummary(string $period, ?string $department, ?int $teamId): array;

    public function getWorkforceAnalytics(string $period, ?string $department): array;

    public function getAttendanceAnalytics(string $period, ?string $department, ?int $teamId): array;

    public function getLeaveAnalytics(string $period, ?string $department): array;

    public function getPayrollAnalytics(string $period, ?string $department): array;

    public function getProjectAnalytics(string $period, ?int $projectId): array;

    // Enhanced Analytics Methods
    public function getTurnoverRate(string $period, ?string $department): array;

    public function getAverageTenure(?string $department): array;

    public function getNewHireTrends(string $period, ?string $department): array;

    public function getAttendanceComplianceRate(string $period, ?string $department): array;

    public function getAttendancePatterns(string $period, ?string $department): array;

    public function getRemoteOfficeRatio(string $period, ?string $department): array;

    public function getLeaveUtilizationRate(string $period, ?string $department): array;

    public function getLeaveBalanceTrends(string $period, ?string $department): array;

    public function getPeakLeavePeriods(string $period): array;

    public function getPayrollCostTrends(string $period, ?string $department): array;

    public function getSalaryDistribution(?string $department): array;

    public function getDeductionAnalysis(string $period, ?string $department): array;

    public function getProjectTimelineAdherence(string $period): array;

    public function getTaskVelocity(string $period, ?int $teamId): array;

    public function getOverdueTrends(string $period): array;

    // Snapshot retrieval helper
    public function getSnapshotMetric(string $metricType, string $metricName, string $periodType, string $startDate, string $endDate): ?array;

    // Performance Management Analytics
    public function getTeamPerformanceSummary(int $teamId, ?int $cycleId = null): array;

    public function getCompanyPerformanceSummary(?int $cycleId = null): array;

    public function getRatingDistribution(?int $cycleId = null): array;

    public function getGoalCompletionRate(?int $employeeId = null, ?int $teamId = null): array;

    public function getFeedbackMetrics(?int $employeeId = null, ?int $teamId = null): array;
}
