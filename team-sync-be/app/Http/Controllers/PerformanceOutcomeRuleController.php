<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\Performance\StoreOutcomeRuleRequest;
use App\Http\Requests\Performance\UpdateOutcomeRuleRequest;
use App\Http\Resources\PerformanceOutcomeRuleResource;
use App\Models\PerformanceOutcomeRule;

class PerformanceOutcomeRuleController extends Controller
{
    public function index()
    {
        $rules = PerformanceOutcomeRule::orderBy('min_rating')->get();
        return ResponseHelper::jsonResponse(
            true,
            'Outcome rules retrieved successfully',
            PerformanceOutcomeRuleResource::collection($rules)
        );
    }

    public function store(StoreOutcomeRuleRequest $request)
    {
        $rule = PerformanceOutcomeRule::create($request->validated());
        return ResponseHelper::jsonResponse(
            true,
            'Outcome rule created successfully',
            new PerformanceOutcomeRuleResource($rule),
            201
        );
    }

    public function show(int $id)
    {
        $rule = PerformanceOutcomeRule::findOrFail($id);
        return ResponseHelper::jsonResponse(
            true,
            'Outcome rule retrieved successfully',
            new PerformanceOutcomeRuleResource($rule)
        );
    }

    public function update(UpdateOutcomeRuleRequest $request, int $id)
    {
        $rule = PerformanceOutcomeRule::findOrFail($id);
        $rule->update($request->validated());
        return ResponseHelper::jsonResponse(
            true,
            'Outcome rule updated successfully',
            new PerformanceOutcomeRuleResource($rule->fresh())
        );
    }

    public function destroy(int $id)
    {
        $rule = PerformanceOutcomeRule::findOrFail($id);
        $rule->delete();
        return ResponseHelper::jsonResponse(true, 'Outcome rule deleted successfully');
    }
}
