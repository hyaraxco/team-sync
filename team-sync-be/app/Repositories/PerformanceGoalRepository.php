<?php

namespace App\Repositories;

use App\Interfaces\PerformanceGoalRepositoryInterface;
use App\Models\PerformanceGoal;
use App\Models\PerformanceGoalUpdate;
use Illuminate\Support\Facades\Auth;

use Illuminate\Pagination\LengthAwarePaginator;

class PerformanceGoalRepository implements PerformanceGoalRepositoryInterface
{
    public function getGoalsForEmployee(string $employeeId, array $filters = []): LengthAwarePaginator
    {
        $query = PerformanceGoal::with(['assigner', 'linkedReview'])
            ->where('staff_member_id', $employeeId);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        
        if (isset($filters['goal_type'])) {
            $query->where('goal_type', $filters['goal_type']);
        }

        return $query->orderBy('due_date', 'asc')
            ->paginate($filters['per_page'] ?? 15);
    }

    public function getGoalsForManager(string $managerId, array $filters = []): LengthAwarePaginator
    {
        // Assuming a manager can see goals they assigned OR goals of their direct reports.
        // For simplicity based on the current model, let's fetch goals assigned by this manager.
        $query = PerformanceGoal::with(['staffMember', 'linkedReview'])
            ->where('assigned_by', $managerId);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->orderBy('due_date', 'asc')
            ->paginate($filters['per_page'] ?? 15);
    }

    public function getGoalById(int $id)
    {
        return PerformanceGoal::with(['staffMember', 'creator', 'assigner', 'updates.updater', 'linkedReview'])
            ->findOrFail($id);
    }

    public function createGoal(array $data)
    {
        return PerformanceGoal::create($data);
    }

    public function updateGoal(int $id, array $data)
    {
        $goal = $this->getGoalById($id);
        $goal->update($data);
        return $goal;
    }

    public function deleteGoal(int $id): bool
    {
        $goal = $this->getGoalById($id);
        // Add logic to check if it can be deleted (e.g., not linked to a completed review)
        if ($goal->linkedReview && $goal->linkedReview->status === 'completed') {
            throw new \Exception("Cannot delete a goal linked to a completed performance review.");
        }
        return $goal->delete();
    }

    public function addProgressUpdate(int $goalId, array $data)
    {
        $goal = $this->getGoalById($goalId);
        
        $updateData = array_merge($data, [
            'goal_id' => $goalId,
            'updated_by' => Auth::id(),
            'previous_value' => $goal->current_value,
            'previous_status' => $goal->status,
        ]);

        $update = PerformanceGoalUpdate::create($updateData);

        // Update the goal itself based on the new update
        $goalUpdates = [];
        if (isset($data['new_value'])) {
            $goalUpdates['current_value'] = $data['new_value'];
        }
        if (isset($data['new_status'])) {
            $goalUpdates['status'] = $data['new_status'];
        }
        if (isset($data['completion_percentage'])) {
            $goalUpdates['completion_percentage'] = $data['completion_percentage'];
        }
        
        if (!empty($goalUpdates)) {
             $goal->update($goalUpdates);
        }

        return $update;
    }

    public function getProgressUpdates(int $goalId)
    {
         return PerformanceGoalUpdate::with('updater')
            ->where('goal_id', $goalId)
            ->orderBy('created_at', 'desc')
            ->get();
    }
}
