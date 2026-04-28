<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\Performance\StoreOutcomeRuleRequest;
use App\Http\Requests\Performance\UpdateOutcomeRuleRequest;
use App\Http\Resources\PerformanceOutcomeRuleResource;
use App\Interfaces\PerformanceReviewRepositoryInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Middleware\PermissionMiddleware;

class PerformanceOutcomeRuleController extends Controller implements HasMiddleware
{
    public function __construct(private PerformanceReviewRepositoryInterface $repository) {}

    public static function middleware(): array
    {
        return [
            new Middleware(PermissionMiddleware::using('review-cycle-manage')),
        ];
    }

    public function index()
    {
        $rules = $this->repository->getOutcomeRules();

        return ResponseHelper::jsonResponse(
            true,
            'Outcome rules retrieved successfully',
            PerformanceOutcomeRuleResource::collection($rules)
        );
    }

    public function store(StoreOutcomeRuleRequest $request)
    {
        $rule = $this->repository->createOutcomeRule($request->validated());

        return ResponseHelper::jsonResponse(
            true,
            'Outcome rule created successfully',
            new PerformanceOutcomeRuleResource($rule),
            201
        );
    }

    public function show(int $id)
    {
        $rule = $this->repository->getOutcomeRuleById($id);

        return ResponseHelper::jsonResponse(
            true,
            'Outcome rule retrieved successfully',
            new PerformanceOutcomeRuleResource($rule)
        );
    }

    public function update(UpdateOutcomeRuleRequest $request, int $id)
    {
        $rule = $this->repository->updateOutcomeRule($id, $request->validated());

        return ResponseHelper::jsonResponse(
            true,
            'Outcome rule updated successfully',
            new PerformanceOutcomeRuleResource($rule)
        );
    }

    public function destroy(int $id)
    {
        $this->repository->deleteOutcomeRule($id);

        return ResponseHelper::jsonResponse(true, 'Outcome rule deleted successfully');
    }
}
