<?php

namespace App\Interfaces;

interface PayrollRepositoryInterface
{
    public function getAll(
        ?string $search,
        ?int $limit,
        bool $execute
    );

    public function getAllPaginated(
        ?string $search,
        int $rowPerPage
    );

    public function getById(string $id);

    public function getPayrollDetailsPaginated(string $payrollId, int $perPage);

    public function generatePayroll(string $salaryMonth, ?int $actorId = null);

    public function getGenerateReadiness(string $salaryMonth): array;

    public function getReadinessDashboard(string $salaryMonth): array;

    public function getReadinessTeamSummary(string $salaryMonth): array;

    public function getReconciliation(string $payrollId, array $filters = []): array;

    public function resolveReconciliationException(string $payrollId, array $data, ?int $actorId = null): array;

    public function getReconciliationResolutions(string $payrollId): array;

    public function updatePayrollDetail(string $id, array $data, ?int $actorId = null);

    public function approvePayroll(string $payrollId, ?int $actorId = null);

    public function markAsPaid(string $payrollId, string $paymentDate, ?int $actorId = null);

    public function reopenPayroll(string $payrollId, string $reason, ?int $actorId = null);

    public function resendNotifications(string $payrollId, ?int $actorId = null);

    public function getNotificationDeliverySummary(string $payrollId): array;

    public function getBpjsRateHistory();

    public function getStatistics();

    public function getAnalytics(int $months = 6): array;

    public function getComparison(string $month1, string $month2): array;

    public function getPayrollStatistics(string $payrollId);

    public function getPayrollReportRows(array $filters);

    public function getActivityLogs(string $payrollId);

    public function getSettingVersionDiff(int $versionId): array;

    public function getApprovalPolicies();

    public function createApprovalPolicy(array $data);

    public function updateApprovalPolicy(int $id, array $data);

    public function deleteApprovalPolicy(int $id): void;

    public function getApprovalStatus(string $payrollId): array;

    public function submitApprovalDecision(string $payrollId, array $data, ?int $actorId = null): array;

    public function getMyPayslipsPaginated(
        int $staffMemberId,
        ?string $search,
        ?int $year,
        int $rowPerPage
    );

    public function findOwnedPaidPayslipOrFail(string $id, int $staffMemberId);
}
