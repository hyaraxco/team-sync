<?php

namespace App\Http\Controllers;

use App\DTOs\Performance\GoalDto;
use App\Helpers\ResponseHelper;
use App\Interfaces\PerformanceGoalRepositoryInterface;
use App\Http\Requests\Performance\CreateGoalRequest;
use App\Http\Requests\Performance\UpdateGoalRequest;
use App\Http\Requests\Performance\ProgressUpdateGoalRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;


class PerformanceGoalController extends Controller
{
    public function __construct(
        private PerformanceGoalRepositoryInterface $repository
    ) {}

    public function getMyGoals(Request $request)
    {
        $goals = $this->repository->getGoalsForEmployee(Auth::user()->employeeProfile?->id, $request->all());
        return ResponseHelper::jsonResponse(true, 'My goals retrieved successfully', $goals);
    }

    public function getTeamGoals(Request $request)
    {
        $goals = $this->repository->getGoalsForManager(Auth::user()->employeeProfile?->id, $request->all());
        return ResponseHelper::jsonResponse(true, 'Team goals retrieved successfully', $goals);
    }

    public function store(CreateGoalRequest $request)
    {
        $dto = GoalDto::fromRequest($request->validated());
        $goal = $this->repository->createGoal($dto->toArray());
        return ResponseHelper::jsonResponse(true, 'Goal created successfully', $goal, 201);
    }

    public function show(int $id)
    {
        $goal = $this->repository->getGoalById($id);
        return ResponseHelper::jsonResponse(true, 'Goal retrieved successfully', $goal);
    }

    public function update(UpdateGoalRequest $request, int $id)
    {
        $goal = $this->repository->updateGoal($id, $request->validated());
        return ResponseHelper::jsonResponse(true, 'Goal updated successfully', $goal);
    }

    public function destroy(int $id)
    {
        try {
            $this->repository->deleteGoal($id);
            return ResponseHelper::jsonResponse(true, 'Goal deleted successfully');
        } catch (\Exception $e) {
            return ResponseHelper::jsonResponse(false, $e->getMessage(), null, 400);
        }
    }

    public function addProgressUpdate(ProgressUpdateGoalRequest $request, int $id)
    {
        $update = $this->repository->addProgressUpdate($id, $request->validated());
        return ResponseHelper::jsonResponse(true, 'Progress update added successfully', $update, 201);
    }

    public function getProgressUpdates(int $id)
    {
        $updates = $this->repository->getProgressUpdates($id);
        return ResponseHelper::jsonResponse(true, 'Progress updates retrieved successfully', $updates);
    }
}
