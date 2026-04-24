<?php

namespace App\Interfaces;

use Illuminate\Pagination\LengthAwarePaginator;

interface PerformanceFeedbackRepositoryInterface
{
    public function getFeedbackForEmployee(string $employeeId, array $filters = []): LengthAwarePaginator;

    public function getFeedbackGivenByUser(string $userId, array $filters = []): LengthAwarePaginator;

    public function getFeedbackById(int $id);

    public function createFeedback(array $data);

    public function acknowledgeFeedback(int $id);
}
