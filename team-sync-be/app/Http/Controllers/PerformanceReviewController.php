<?php

namespace App\Http\Controllers;

use App\DTOs\Performance\PerformanceReviewDto;
use App\Helpers\ResponseHelper;
use App\Interfaces\PerformanceReviewRepositoryInterface;
use App\Http\Requests\Performance\SubmitSelfAssessmentRequest;
use App\Http\Requests\Performance\SubmitManagerAssessmentRequest;
use App\Http\Requests\Performance\CalibrateReviewRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;


class PerformanceReviewController extends Controller
{
    public function __construct(
        private PerformanceReviewRepositoryInterface $repository
    ) {}

    public function getMyReviews(Request $request)
    {
        $reviews = $this->repository->getReviewsForEmployee(Auth::user()->employeeProfile?->id, $request->all());
        return ResponseHelper::jsonResponse(true, 'My reviews retrieved successfully', $reviews);
    }

    public function getTeamReviews(Request $request)
    {
        $reviews = $this->repository->getReviewsForManager(Auth::user()->employeeProfile?->id, $request->all());
        return ResponseHelper::jsonResponse(true, 'Team reviews retrieved successfully', $reviews);
    }

    public function show(int $id)
    {
        $review = $this->repository->getReviewById($id);
        return ResponseHelper::jsonResponse(true, 'Review retrieved successfully', $review);
    }

    public function getActiveSections()
    {
        $sections = $this->repository->getActiveSections();
        return ResponseHelper::jsonResponse(true, 'Active sections retrieved successfully', $sections);
    }

    public function submitSelfAssessment(SubmitSelfAssessmentRequest $request, int $id)
    {
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

    public function calibrateReview(CalibrateReviewRequest $request, int $id)
    {
        $validated = $request->validated();
        $review = $this->repository->calibrateReview($id, $validated['responses'] ?? [], $validated);
        return ResponseHelper::jsonResponse(true, 'Review calibrated successfully', $review);
    }
}
