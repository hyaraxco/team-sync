<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseHelper;
use App\Http\Requests\Performance\StoreOutcomeRuleRequest;
use App\Http\Requests\Performance\UpdateOutcomeRuleRequest;
use App\Http\Resources\PerformanceOutcomeRuleResource;
use App\Models\PerformanceOutcomeRule;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Log;

class PerformanceOutcomeRuleController extends Controller
{
    public function index()
    {
        try {
            $rules = PerformanceOutcomeRule::orderBy('min_rating')->get();

            return ResponseHelper::jsonResponse(
                true,
                'Outcome rules retrieved successfully',
                PerformanceOutcomeRuleResource::collection($rules)
            );
        } catch (\Exception $e) {
            Log::error('OutcomeRule index error: '.$e->getMessage());

            return ResponseHelper::jsonResponse(false, 'Terjadi kesalahan saat mengambil aturan.', null, 500);
        }
    }

    public function store(StoreOutcomeRuleRequest $request)
    {
        try {
            $rule = PerformanceOutcomeRule::create($request->validated());

            return ResponseHelper::jsonResponse(
                true,
                'Outcome rule created successfully',
                new PerformanceOutcomeRuleResource($rule),
                201
            );
        } catch (\Exception $e) {
            Log::error('OutcomeRule store error: '.$e->getMessage());

            return ResponseHelper::jsonResponse(false, 'Terjadi kesalahan saat membuat aturan.', null, 500);
        }
    }

    public function show(int $id)
    {
        try {
            $rule = PerformanceOutcomeRule::findOrFail($id);

            return ResponseHelper::jsonResponse(
                true,
                'Outcome rule retrieved successfully',
                new PerformanceOutcomeRuleResource($rule)
            );
        } catch (ModelNotFoundException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('OutcomeRule show error: '.$e->getMessage());

            return ResponseHelper::jsonResponse(false, 'Terjadi kesalahan saat mengambil aturan.', null, 500);
        }
    }

    public function update(UpdateOutcomeRuleRequest $request, int $id)
    {
        try {
            $rule = PerformanceOutcomeRule::findOrFail($id);
            $rule->update($request->validated());

            return ResponseHelper::jsonResponse(
                true,
                'Outcome rule updated successfully',
                new PerformanceOutcomeRuleResource($rule->fresh())
            );
        } catch (ModelNotFoundException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('OutcomeRule update error: '.$e->getMessage());

            return ResponseHelper::jsonResponse(false, 'Terjadi kesalahan saat memperbarui aturan.', null, 500);
        }
    }

    public function destroy(int $id)
    {
        try {
            $rule = PerformanceOutcomeRule::findOrFail($id);
            $rule->delete();

            return ResponseHelper::jsonResponse(true, 'Outcome rule deleted successfully');
        } catch (ModelNotFoundException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('OutcomeRule destroy error: '.$e->getMessage());

            return ResponseHelper::jsonResponse(false, 'Terjadi kesalahan saat menghapus aturan.', null, 500);
        }
    }
}
