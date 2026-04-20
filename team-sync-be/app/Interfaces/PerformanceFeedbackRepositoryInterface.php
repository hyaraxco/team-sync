<?php

namespace App\Interfaces;

interface PerformanceFeedbackRepositoryInterface
{
    public function getFeedbackForEmployee(string $employeeId, array $filters = []): \Illuminate\Pagination\LengthAwarePaginator;
    public function getFeedbackGivenByUser(string $userId, array $filters = []): \Illuminate\Pagination\LengthAwarePaginator;
    public function getFeedbackById(int $id);
    public function createFeedback(array $data);
    public function acknowledgeFeedback(int $id);
}
