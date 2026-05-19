<?php

namespace App\Services\Payroll;

use App\Interfaces\PayrollRepositoryInterface;

class PayrollAnalyticsService
{
    public function __construct(
        private readonly PayrollRepositoryInterface $payrollRepository,
    ) {}

    /**
     * Get payroll analytics trends over N months.
     */
    public function getAnalytics(int $months = 6): array
    {
        return $this->payrollRepository->getAnalytics($months);
    }

    /**
     * Get month-over-month payroll comparison.
     */
    public function getComparison(string $month1, string $month2): array
    {
        return $this->payrollRepository->getComparison($month1, $month2);
    }

    /**
     * Get diff between a PayrollSettingVersion and its predecessor.
     */
    public function getSettingVersionDiff(int $versionId): array
    {
        return $this->payrollRepository->getSettingVersionDiff($versionId);
    }
}
