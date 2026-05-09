<?php

namespace App\Interfaces;

use Illuminate\Pagination\LengthAwarePaginator;

interface PerformanceReviewRepositoryInterface
{
    // Cycle Management
    public function getCycles(array $filters = []): LengthAwarePaginator;

    public function getCycleById(int $id);

    public function createCycle(array $data);

    public function updateCycle(int $id, array $data);

    public function deleteCycle(int $id): bool;

    public function getActiveStaffMembersForReview(int $cycleId);

    public function getDefaultTemplateId(): ?int;

    public function getHrUsers();

    public function getGoalsForReview(int $staffMemberId, int $reviewId, $cycle);

    public function getPositiveFeedbackCount(int $staffMemberId, $cycle): int;

    // Review Management
    public function getReviewsForEmployee(string $employeeId, array $filters = []): LengthAwarePaginator;

    public function getReviewsForManager(string $managerId, array $filters = []): LengthAwarePaginator;

    public function getReviewById(int $id);

    public function createReview(array $data);

    public function updateReview(int $id, array $data);

    // Assessment/Calibration
    public function submitSelfAssessment(int $reviewId, array $responses, array $data);

    public function submitManagerAssessment(int $reviewId, array $responses, array $data);

    public function calibrateReview(int $reviewId, array $responses, array $data);

    public function getReviewsPendingCalibration(array $filters = []): LengthAwarePaginator;

    public function getCalibrationContext(int $reviewId): array;

    // Section Management
    public function getActiveSections();

    // TOPSIS Data
    public function getEmployeeScoresForCycle(int $cycleId): array;

    // Template Management
    public function getTemplates();

    public function createTemplate(array $data, array $sections);

    public function getTemplateById(int $id);

    public function updateTemplate(int $id, array $data, ?array $sections = null);

    public function deleteTemplate(int $id): bool;

    // Outcome Rule Management
    public function getOutcomeRules();

    public function createOutcomeRule(array $data);

    public function getOutcomeRuleById(int $id);

    public function updateOutcomeRule(int $id, array $data);

    public function deleteOutcomeRule(int $id): bool;
}
