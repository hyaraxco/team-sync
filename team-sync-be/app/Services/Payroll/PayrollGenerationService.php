<?php

namespace App\Services\Payroll;

use App\Interfaces\PayrollRepositoryInterface;

class PayrollGenerationService
{
    public function __construct(
        private readonly PayrollRepositoryInterface $payrollRepository,
    ) {}

    public function generatePayroll(string $salaryMonth, ?int $actorId = null)
    {
        return $this->payrollRepository->generatePayroll($salaryMonth, $actorId);
    }

    public function getGenerateReadiness(string $salaryMonth): array
    {
        return $this->payrollRepository->getGenerateReadiness($salaryMonth);
    }

    public function getReadinessDashboard(string $salaryMonth): array
    {
        return $this->payrollRepository->getReadinessDashboard($salaryMonth);
    }

    public function getReadinessTeamSummary(string $salaryMonth): array
    {
        return $this->payrollRepository->getReadinessTeamSummary($salaryMonth);
    }
}
