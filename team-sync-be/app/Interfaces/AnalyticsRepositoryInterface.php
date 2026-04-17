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
}
