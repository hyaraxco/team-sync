<?php

namespace App\Http\Controllers;

use App\DTOs\Performance\GoalDto;
use App\Helpers\ResponseHelper;
use App\Http\Requests\Performance\CreateGoalRequest;
use App\Http\Requests\Performance\ProgressUpdateGoalRequest;
use App\Http\Requests\Performance\UpdateGoalRequest;
use App\Interfaces\PerformanceGoalRepositoryInterface;
use App\Notifications\Performance\GoalAssigned;
use App\Notifications\Performance\GoalProgressUpdated;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Middleware\PermissionMiddleware;

class PerformanceGoalController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            // My goals / team goals / show / progress: require goal-create-own (everyone with self-service)
            new Middleware(
                PermissionMiddleware::using('goal-create-own'),
                only: ['getMyGoals', 'getProgressUpdates', 'show']
            ),
            // Team goals: only those who can assign goals to others
            new Middleware(
                PermissionMiddleware::using('goal-assign-team'),
                only: ['getTeamGoals']
            ),
            // Create, update, delete, progress update: require goal-create-own minimum
            new Middleware(
                PermissionMiddleware::using('goal-create-own'),
                only: ['store', 'update', 'destroy', 'addProgressUpdate']
            ),
        ];
    }

    public function __construct(
        private PerformanceGoalRepositoryInterface $repository
    ) {}

    public function getMyGoals(Request $request)
    {
        $goals = $this->repository->getGoalsForEmployee(Auth::user()->staffMemberProfile?->id, $request->all());

        return ResponseHelper::jsonResponse(true, 'My goals retrieved successfully', $goals);
    }

    public function getTeamGoals(Request $request)
    {
        $goals = $this->repository->getGoalsForManager(Auth::user()->staffMemberProfile?->id, $request->all());

        return ResponseHelper::jsonResponse(true, 'Team goals retrieved successfully', $goals);
    }

    public function store(CreateGoalRequest $request)
    {
        $dto = GoalDto::fromRequest($request->validated());
        $goal = $this->repository->createGoal($dto->toArray());

        // Notify the assigned staff member when a manager assigns a goal to them
        $goal->load(['staffMember.user']);
        $assigner = Auth::user();
        if ($goal->staffMember?->user && $goal->staff_member_id !== $assigner->staffMemberProfile?->id) {
            $goal->staffMember->user->notify(new GoalAssigned(
                goalId: $goal->id,
                goalTitle: $goal->title,
                assignedByName: $assigner->name ?? 'Manager',
            ));
        }

        return ResponseHelper::jsonResponse(true, 'Goal created successfully', $goal, 201);
    }

    public function show(int $id)
    {
        $goal = $this->repository->getGoalById($id);

        // Ownership check: staff can only see their own goals unless they can assign team goals
        $user = Auth::user();
        if (! $user->can('goal-assign-team') && $goal->staff_member_id !== $user->staffMemberProfile?->id) {
            return ResponseHelper::jsonResponse(false, 'Forbidden.', null, 403);
        }

        return ResponseHelper::jsonResponse(true, 'Goal retrieved successfully', $goal);
    }

    public function update(UpdateGoalRequest $request, int $id)
    {
        $goal = $this->repository->getGoalById($id);

        // Ownership check: only assigner/team managers can update others' goals
        $user = Auth::user();
        if (! $user->can('goal-assign-team') && $goal->staff_member_id !== $user->staffMemberProfile?->id) {
            return ResponseHelper::jsonResponse(false, 'You can only update your own goals.', null, 403);
        }

        $goal = $this->repository->updateGoal($id, $request->validated());

        return ResponseHelper::jsonResponse(true, 'Goal updated successfully', $goal);
    }

    public function destroy(int $id)
    {
        try {
            $goal = $this->repository->getGoalById($id);

            // Ownership check: only assigner/team managers can delete others' goals
            $user = Auth::user();
            if (! $user->can('goal-assign-team') && $goal->staff_member_id !== $user->staffMemberProfile?->id) {
                return ResponseHelper::jsonResponse(false, 'You can only delete your own goals.', null, 403);
            }

            $this->repository->deleteGoal($id);

            return ResponseHelper::jsonResponse(true, 'Goal deleted successfully');
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('PerformanceGoalController::destroy: ' . $e->getMessage());
            return ResponseHelper::jsonResponse(false, 'Failed to delete goal. It may be linked to a completed review.', null, 400);
        }
    }

    public function addProgressUpdate(ProgressUpdateGoalRequest $request, int $id)
    {
        $goal = $this->repository->getGoalById($id);

        // Ownership check: only the goal owner or a team manager can add progress
        $user = Auth::user();
        if (! $user->can('goal-assign-team') && $goal->staff_member_id !== $user->staffMemberProfile?->id) {
            return ResponseHelper::jsonResponse(false, 'You can only update progress on your own goals.', null, 403);
        }

        $update = $this->repository->addProgressUpdate($id, $request->validated());

        // Notify the goal assigner/manager about progress update
        $goal->load(['assigner.user']);
        $updater = Auth::user();
        if ($goal->assigner?->user && $goal->assigner->user->id !== $updater->id) {
            $goal->assigner->user->notify(new GoalProgressUpdated(
                goalId: $goal->id,
                goalTitle: $goal->title,
                employeeName: $updater->name ?? 'Employee',
                progressPercentage: (int) ($request->validated()['completion_percentage'] ?? $goal->completion_percentage ?? 0),
            ));
        }

        return ResponseHelper::jsonResponse(true, 'Progress update added successfully', $update, 201);
    }

    public function getProgressUpdates(int $id)
    {
        $goal = $this->repository->getGoalById($id);

        // Ownership check: only the goal owner or a team manager can see progress
        $user = Auth::user();
        if (! $user->can('goal-assign-team') && $goal->staff_member_id !== $user->staffMemberProfile?->id) {
            return ResponseHelper::jsonResponse(false, 'Forbidden.', null, 403);
        }

        $updates = $this->repository->getProgressUpdates($id);

        return ResponseHelper::jsonResponse(true, 'Progress updates retrieved successfully', $updates);
    }
}
