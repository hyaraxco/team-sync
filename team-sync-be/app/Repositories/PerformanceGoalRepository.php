<?php

namespace App\Repositories;

use App\Interfaces\PerformanceGoalRepositoryInterface;
use App\Models\PerformanceGoal;
use App\Models\PerformanceGoalUpdate;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;

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

    public function getGoalsForTeam(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = PerformanceGoal::with(['staffMember', 'linkedReview']);

        if (! $user->hasAnyRole(['hr', 'superadmin'])) {
            $teamIds = $user->leadTeams()->pluck('teams.id');
            $query->whereHas('staffMember', function ($q) use ($teamIds) {
                $q->where(function ($inner) use ($teamIds) {
                    $inner->whereHas('teamMembers', fn ($m) => $m
                        ->whereNull('left_at')->whereIn('team_id', $teamIds))
                        ->orWhereHas('jobInformation', fn ($j) => $j->whereIn('team_id', $teamIds));
                });
            });
        }

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

        // Auto-set completed_at when status changes to 'completed'
        if (isset($data['status']) && $data['status'] === 'completed' && $goal->status !== 'completed') {
            $data['completed_at'] = now();
        }
        // Clear completed_at if status reverts from completed
        if (isset($data['status']) && $data['status'] !== 'completed' && $goal->status === 'completed') {
            $data['completed_at'] = null;
        }

        $goal->update($data);

        return $goal;
    }

    public function deleteGoal(int $id): bool
    {
        $goal = $this->getGoalById($id);
        // Add logic to check if it can be deleted (e.g., not linked to a completed review)
        if ($goal->linkedReview && $goal->linkedReview->status === 'completed') {
            throw new \Exception('Cannot delete a goal linked to a completed performance review.');
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
            // Auto-set completed_at when status changes to 'completed'
            if ($data['new_status'] === 'completed' && $goal->status !== 'completed') {
                $goalUpdates['completed_at'] = now();
            }
            // Clear completed_at if status reverts from completed
            if ($data['new_status'] !== 'completed' && $goal->status === 'completed') {
                $goalUpdates['completed_at'] = null;
            }
        }
        if (isset($data['completion_percentage'])) {
            $goalUpdates['completion_percentage'] = $data['completion_percentage'];
        }

        if (! empty($goalUpdates)) {
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
