<?php

namespace App\Interfaces;

interface PerformanceReviewRepositoryInterface
{
    // Cycle Management
    public function getCycles(array $filters = []): \Illuminate\Pagination\LengthAwarePaginator;
    public function getCycleById(int $id);
    public function createCycle(array $data);
    public function updateCycle(int $id, array $data);
    public function deleteCycle(int $id): bool;

    // Review Management
    public function getReviewsForEmployee(string $employeeId, array $filters = []): \Illuminate\Pagination\LengthAwarePaginator;
    public function getReviewsForManager(string $managerId, array $filters = []): \Illuminate\Pagination\LengthAwarePaginator;
    public function getReviewById(int $id);
    public function createReview(array $data);
    public function updateReview(int $id, array $data);

    // Assessment/Calibration
    public function submitSelfAssessment(int $reviewId, array $responses, array $data);
    public function submitManagerAssessment(int $reviewId, array $responses, array $data);
    public function calibrateReview(int $reviewId, array $responses, array $data);
    
    // Section Management
    public function getActiveSections();

    // TOPSIS Data
    public function getEmployeeScoresForCycle(int $cycleId): array;
}
