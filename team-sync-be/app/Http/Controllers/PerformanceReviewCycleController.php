<?php

namespace App\Http\Controllers;

use App\DTOs\Performance\ReviewCycleDto;
use App\Helpers\ResponseHelper;
use App\Interfaces\PerformanceReviewRepositoryInterface;
use App\Http\Requests\Performance\CreateReviewCycleRequest;
use App\Http\Requests\Performance\UpdateReviewCycleRequest;
use App\Models\StaffMemberProfile;
use App\Services\Performance\ReviewerResolverService;
use Illuminate\Http\Request;

class PerformanceReviewCycleController extends Controller
{
    public function __construct(
        private PerformanceReviewRepositoryInterface $repository,
        private ReviewerResolverService $reviewerResolverService
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

    public function generateReviews(int $id)
    {
        $cycle = $this->repository->getCycleById($id);

        // Get active staff members (excluding those who already have a review for this cycle)
        $existingReviewStaffIds = $cycle->reviews()->pluck('staff_member_id')->toArray();
        
        $staffMembers = StaffMemberProfile::whereHas('jobInformation', function ($q) {
                $q->where('status', 'active');
            })
            ->whereNotIn('id', $existingReviewStaffIds)
            ->get();

        $assignments = $this->reviewerResolverService->resolveMany($staffMembers);

        $createdCount = 0;
        foreach ($staffMembers as $staffMember) {
            $reviewerId = $assignments[$staffMember->id];
            
            $this->repository->createReview([
                'cycle_id' => $cycle->id,
                'staff_member_id' => $staffMember->id,
                'reviewer_id' => $reviewerId,
                'status' => 'pending_self',
            ]);
            $createdCount++;
        }

        return ResponseHelper::jsonResponse(true, "Successfully generated reviews for {$createdCount} employees", [
            'generated_count' => $createdCount
        ], 200);
    }
}
