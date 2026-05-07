<?php

namespace App\Interfaces;

interface DashboardRepositoryInterface
{
    public function getStatistics();

    public function getEmployeeStatistics(int $employeeId);

    public function getTodayAttendanceOverview();

    public function getTeamPulse(): array;

    public function sendTeamPulseNudge(int $staffMemberId, ?string $message = null): array;
}
