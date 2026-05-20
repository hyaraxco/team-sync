<?php

namespace App\Services\Payroll;

use App\Interfaces\PayrollRepositoryInterface;

class PayrollQueryService
{
    public function __construct(
        private readonly PayrollRepositoryInterface $payrollRepository,
    ) {}

    public function getAll(?string $search, ?int $limit, bool $execute)
    {
        return $this->payrollRepository->getAll($search, $limit, $execute);
    }

    public function getAllPaginated(?string $search, int $rowPerPage)
    {
        return $this->payrollRepository->getAllPaginated($search, $rowPerPage);
    }

    public function getById(string $id)
    {
        return $this->payrollRepository->getById($id);
    }

    public function findById(string $id)
    {
        return $this->payrollRepository->findById($id);
    }

    public function findByIdWithDetails(string $id)
    {
        return $this->payrollRepository->findByIdWithDetails($id);
    }

    public function getPayrollDetailsPaginated(string $payrollId, int $perPage)
    {
        return $this->payrollRepository->getPayrollDetailsPaginated($payrollId, $perPage);
    }

    public function getReconciliation(string $payrollId, array $filters = []): array
    {
        return $this->payrollRepository->getReconciliation($payrollId, $filters);
    }

    public function getReconciliationResolutions(string $payrollId): array
    {
        return $this->payrollRepository->getReconciliationResolutions($payrollId);
    }

    public function getStatistics()
    {
        return $this->payrollRepository->getStatistics();
    }

    public function getPayrollStatistics(string $payrollId)
    {
        return $this->payrollRepository->getPayrollStatistics($payrollId);
    }

    public function getPayrollReportRows(array $filters)
    {
        return $this->payrollRepository->getPayrollReportRows($filters);
    }

    public function getActivityLogs(string $payrollId)
    {
        return $this->payrollRepository->getActivityLogs($payrollId);
    }
}
