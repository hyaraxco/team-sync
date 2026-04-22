<?php

namespace App\Http\Controllers;

use App\DTOs\Performance\PerformanceReviewDto;
use App\Helpers\ResponseHelper;
use App\Interfaces\PerformanceReviewRepositoryInterface;
use App\Http\Requests\Performance\SubmitSelfAssessmentRequest;
use App\Http\Requests\Performance\SubmitManagerAssessmentRequest;
use App\Http\Requests\Performance\CalibrateReviewRequest;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Middleware\PermissionMiddleware;


class PerformanceReviewController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            // Self-service: view my reviews, sections, show a review, self-assessment
            new Middleware(
                PermissionMiddleware::using('review-self-submit'),
                only: ['getMyReviews', 'getActiveSections', 'show', 'submitSelfAssessment']
            ),
            // Team/manager: team reviews + manager assessment
            new Middleware(
                PermissionMiddleware::using('review-manager-submit'),
                only: ['getTeamReviews', 'submitManagerAssessment']
            ),
            // Calibration: already guarded in routes, but also enforce at controller level
            new Middleware(
                PermissionMiddleware::using('review-calibrate'),
                only: ['getPendingCalibration', 'getCalibrationContext', 'calibrateReview']
            ),
        ];
    }

    public function __construct(
        private PerformanceReviewRepositoryInterface $repository
    ) {}

    public function getMyReviews(Request $request)
    {
        $reviews = $this->repository->getReviewsForEmployee(Auth::user()->staffMemberProfile?->id, $request->all());
        return ResponseHelper::jsonResponse(true, 'My reviews retrieved successfully', $reviews);
    }

    public function getTeamReviews(Request $request)
    {
        $reviews = $this->repository->getReviewsForManager(Auth::user()->staffMemberProfile?->id, $request->all());
        return ResponseHelper::jsonResponse(true, 'Team reviews retrieved successfully', $reviews);
    }

    public function show(int $id)
    {
        $review = $this->repository->getReviewById($id);

        // Ownership check: staff can only view their own review unless they're a manager/HR
        $user = Auth::user();
        if (! $user->can('review-manager-submit') && $review->staff_member_id !== $user->staffMemberProfile?->id) {
            return ResponseHelper::jsonResponse(false, 'Forbidden.', null, 403);
        }

        return ResponseHelper::jsonResponse(true, 'Review retrieved successfully', $review);
    }

    public function getActiveSections()
    {
        $sections = $this->repository->getActiveSections();
        return ResponseHelper::jsonResponse(true, 'Active sections retrieved successfully', $sections);
    }

    public function submitSelfAssessment(SubmitSelfAssessmentRequest $request, int $id)
    {
        $review = $this->repository->getReviewById($id);

        // Ownership check: only the reviewee themselves can submit self-assessment
        $user = Auth::user();
        if ($review->staff_member_id !== $user->staffMemberProfile?->id) {
            return ResponseHelper::jsonResponse(false, 'You can only submit self-assessment for your own review.', null, 403);
        }

        $validated = $request->validated();
        $review = $this->repository->submitSelfAssessment($id, $validated['responses'], $validated);
        return ResponseHelper::jsonResponse(true, 'Self assessment submitted successfully', $review);
    }

    public function submitManagerAssessment(SubmitManagerAssessmentRequest $request, int $id)
    {
        $validated = $request->validated();
        $review = $this->repository->submitManagerAssessment($id, $validated['responses'], $validated);
        return ResponseHelper::jsonResponse(true, 'Manager assessment submitted successfully', $review);
    }

    public function getPendingCalibration(Request $request)
    {
        $reviews = $this->repository->getReviewsPendingCalibration($request->all());
        return ResponseHelper::jsonResponse(true, 'Pending calibration reviews retrieved successfully', $reviews);
    }

    public function getCalibrationContext(int $id)
    {
        $context = $this->repository->getCalibrationContext($id);
        return ResponseHelper::jsonResponse(true, 'Calibration context retrieved successfully', $context);
    }

    public function calibrateReview(CalibrateReviewRequest $request, int $id)
    {
        $validated = $request->validated();
        $review = $this->repository->calibrateReview($id, $validated['responses'] ?? [], $validated);
        return ResponseHelper::jsonResponse(true, 'Review calibrated successfully', $review);
    }
}

