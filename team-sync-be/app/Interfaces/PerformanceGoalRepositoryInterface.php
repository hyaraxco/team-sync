<?php

namespace App\Interfaces;

use Illuminate\Pagination\LengthAwarePaginator;

interface PerformanceGoalRepositoryInterface
{
    public function getGoalsForEmployee(string $employeeId, array $filters = []): LengthAwarePaginator;

    public function getGoalsForManager(string $managerId, array $filters = []): LengthAwarePaginator;

    public function getGoalById(int $id);

    public function createGoal(array $data);

    public function updateGoal(int $id, array $data);

    public function deleteGoal(int $id): bool;

    // Goal Updates
    public function addProgressUpdate(int $goalId, array $data);

    public function getProgressUpdates(int $goalId);
}
