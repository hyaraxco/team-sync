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
        try {
            $rules = $this->repository->getOutcomeRules();

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
            $rule = $this->repository->createOutcomeRule($request->validated());

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
            $rule = $this->repository->getOutcomeRuleById($id);

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
            $rule = $this->repository->updateOutcomeRule($id, $request->validated());

            return ResponseHelper::jsonResponse(
                true,
                'Outcome rule updated successfully',
                new PerformanceOutcomeRuleResource($rule)
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
            $this->repository->deleteOutcomeRule($id);

            return ResponseHelper::jsonResponse(true, 'Outcome rule deleted successfully');
        } catch (ModelNotFoundException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('OutcomeRule destroy error: '.$e->getMessage());

            return ResponseHelper::jsonResponse(false, 'Terjadi kesalahan saat menghapus aturan.', null, 500);
        }
    }
}
