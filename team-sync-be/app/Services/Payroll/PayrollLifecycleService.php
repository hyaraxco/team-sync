<?php

namespace App\Services\Payroll;

use App\Interfaces\PayrollRepositoryInterface;

class PayrollLifecycleService
{
    public function __construct(
        private readonly PayrollRepositoryInterface $payrollRepository,
    ) {}

    public function approvePayroll(string $payrollId, ?int $actorId = null)
    {
        return $this->payrollRepository->approvePayroll($payrollId, $actorId);
    }

    public function markAsPaid(string $payrollId, string $paymentDate, ?int $actorId = null)
    {
        return $this->payrollRepository->markAsPaid($payrollId, $paymentDate, $actorId);
    }

    public function reopenPayroll(string $payrollId, string $reason, ?int $actorId = null)
    {
        return $this->payrollRepository->reopenPayroll($payrollId, $reason, $actorId);
    }

    public function updatePayrollDetail(string $id, array $data, ?int $actorId = null)
    {
        return $this->payrollRepository->updatePayrollDetail($id, $data, $actorId);
    }

    public function resendNotifications(string $payrollId, ?int $actorId = null)
    {
        return $this->payrollRepository->resendNotifications($payrollId, $actorId);
    }

    public function getNotificationDeliverySummary(string $payrollId): array
    {
        return $this->payrollRepository->getNotificationDeliverySummary($payrollId);
    }

    public function resolveReconciliationException(string $payrollId, array $data, ?int $actorId = null): array
    {
        return $this->payrollRepository->resolveReconciliationException($payrollId, $data, $actorId);
    }
}
