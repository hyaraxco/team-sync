<?php

namespace App\Http\Controllers;

use App\DTOs\Performance\ReviewCycleDto;
use App\Helpers\ResponseHelper;
use App\Interfaces\PerformanceReviewRepositoryInterface;
use App\Http\Requests\Performance\CreateReviewCycleRequest;
use App\Http\Requests\Performance\UpdateReviewCycleRequest;
use Illuminate\Http\Request;

class PerformanceReviewCycleController extends Controller
{
    public function __construct(
        private PerformanceReviewRepositoryInterface $repository
    ) {}

    public function index(Request $request)
    {
        $cycles = $this->repository->getCycles($request->all());
        return ResponseHelper::jsonResponse(true, 'Review cycles retrieved successfully', $cycles, 200);
    }

    public function store(CreateReviewCycleRequest $request)
    {
        $dto = ReviewCycleDto::fromRequest($request->validated());
        $cycle = $this->repository->createCycle($dto->toArray());
        return ResponseHelper::jsonResponse(true, 'Review cycle created successfully', $cycle, 201);
    }

    public function show(int $id)
    {
        $cycle = $this->repository->getCycleById($id);
        return ResponseHelper::jsonResponse(true, 'Review cycle retrieved successfully', $cycle, 200);
    }

    public function update(UpdateReviewCycleRequest $request, int $id)
    {
        $cycle = $this->repository->updateCycle($id, $request->validated());
        return ResponseHelper::jsonResponse(true, 'Review cycle updated successfully', $cycle, 200);
    }

    public function destroy(int $id)
    {
        $this->repository->deleteCycle($id);
        return ResponseHelper::jsonResponse(true, 'Review cycle deleted successfully', null, 200);
    }
}
