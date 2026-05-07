<?php

namespace App\Http\Controllers;

use App\DTOs\Performance\ReviewCycleDto;
use App\Helpers\ResponseHelper;
use App\Http\Requests\Performance\CreateReviewCycleRequest;
use App\Http\Requests\Performance\UpdateReviewCycleRequest;
use App\Interfaces\PerformanceReviewRepositoryInterface;
use App\Models\PerformanceReviewTemplate;
use App\Models\StaffMemberProfile;
use App\Notifications\Performance\ReviewCycleStarted;
use App\Services\Performance\ReviewerResolverService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Spatie\Permission\Middleware\PermissionMiddleware;

class PerformanceReviewCycleController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware(PermissionMiddleware::using('review-cycle-manage')),
        ];
    }

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

        if (in_array($cycle->status, ['completed', 'archived'])) {
            return ResponseHelper::jsonResponse(false, 'Cannot generate reviews for a completed or archived cycle', null, 422);
        }

        // Get active staff members (excluding those who already have a review for this cycle)
        $existingReviewStaffIds = $cycle->reviews()->pluck('staff_member_id')->toArray();

        $staffMembers = StaffMemberProfile::with('jobInformation')
            ->whereHas('jobInformation', function ($q) {
                $q->where('status', 'active');
            })
            ->whereNotIn('id', $existingReviewStaffIds)
            ->get();

        $assignments = $this->reviewerResolverService->resolveMany($staffMembers);

        // Fetch default template as fallback
        $defaultTemplateId = PerformanceReviewTemplate::where('is_default', true)->first()?->id;

        $createdCount = 0;
        foreach ($staffMembers as $staffMember) {
            $reviewerId = $assignments[$staffMember->id];
            // Guard: prevent self-review assignment
            if ($reviewerId === $staffMember->id) {
                $reviewerId = null;
            }
            $templateId = $staffMember->jobInformation?->review_template_id ?? $cycle->template_id ?? $defaultTemplateId;

            $this->repository->createReview([
                'cycle_id' => $cycle->id,
                'staff_member_id' => $staffMember->id,
                'reviewer_id' => $reviewerId,
                'review_template_id' => $templateId,
                'status' => 'pending_self',
            ]);
            $createdCount++;

            // Notify employee that a review cycle has started
            if ($staffMember->user) {
                $staffMember->user->notify(new ReviewCycleStarted(
                    cycleId: $cycle->id,
                    cycleName: $cycle->name,
                    startDate: $cycle->start_date->format('Y-m-d'),
                    endDate: $cycle->end_date->format('Y-m-d'),
                ));
            }
        }

        return ResponseHelper::jsonResponse(true, "Successfully generated reviews for {$createdCount} employees", [
            'generated_count' => $createdCount,
        ], 200);
    }
}
