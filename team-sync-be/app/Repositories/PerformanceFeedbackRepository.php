<?php

namespace App\Repositories;

use App\Interfaces\PerformanceFeedbackRepositoryInterface;
use App\Models\PerformanceFeedback;
use Illuminate\Pagination\LengthAwarePaginator;

class PerformanceFeedbackRepository implements PerformanceFeedbackRepositoryInterface
{
    public function getFeedbackForEmployee(string $employeeId, array $filters = []): LengthAwarePaginator
    {
        $query = PerformanceFeedback::with(['giver', 'linkedGoal'])
            ->where('employee_id', $employeeId);

        // Add logic here to filter out private feedback if the user is not the employee, their manager, or HR
        if (isset($filters['exclude_private']) && $filters['exclude_private']) {
            $query->where('is_private', false);
        }

        if (isset($filters['feedback_type'])) {
            $query->where('feedback_type', $filters['feedback_type']);
        }

        return $query->orderBy('created_at', 'desc')
            ->paginate($filters['per_page'] ?? 15);
    }

    public function getFeedbackGivenByUser(string $userId, array $filters = []): LengthAwarePaginator
    {
        $query = PerformanceFeedback::with(['staffMember', 'linkedGoal'])
            ->where('given_by', $userId);

        if (isset($filters['feedback_type'])) {
            $query->where('feedback_type', $filters['feedback_type']);
        }

        return $query->orderBy('created_at', 'desc')
            ->paginate($filters['per_page'] ?? 15);
    }

    public function getFeedbackById(int $id)
    {
        return PerformanceFeedback::with(['staffMember', 'giver', 'linkedGoal'])->findOrFail($id);
    }

    public function createFeedback(array $data)
    {
        return PerformanceFeedback::create($data);
    }

    public function acknowledgeFeedback(int $id)
    {
        $feedback = $this->getFeedbackById($id);
        $feedback->update(['acknowledged_at' => now()]);
        return $feedback;
    }
}
