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
                only: ['getPendingCalibration', 'getCalibrationContext', 'calibrateReview', 'validateReadiness']
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

    /**
     * Validate review readiness before calibration finalize.
     *
     * Checks if the review has enough data for meaningful TOPSIS calculation:
     * - C1/C2: manager has rated all active sections
     * - C3/C4: employee has goals within the cycle period
     * - C5: employee has received feedback within the cycle period
     *
     * Returns warnings (proceed-able) and blockers (must fix first).
     *
     * GET /api/v1/performance/reviews/{id}/validate-readiness
     */
    public function validateReadiness(int $id)
    {
        $review = $this->repository->getReviewById($id);
        $cycle = $review->cycle;

        $warnings = [];
        $blockers = [];

        // Blocker: Review must be at least pending_calibration
        if (!in_array($review->status, ['pending_calibration', 'completed'])) {
            $blockers[] = [
                'code' => 'STATUS_NOT_READY',
                'message' => 'Review belum siap dikalibrasi. Status saat ini: ' . $review->status,
            ];
        }

        // Blocker: Manager assessment must be submitted
        if (!$review->manager_assessment_submitted_at) {
            $blockers[] = [
                'code' => 'MANAGER_ASSESSMENT_MISSING',
                'message' => 'Manager belum mengisi assessment untuk review ini.',
            ];
        }

        // C1/C2 Check: Are all active sections rated by manager?
        $activeSections = $this->repository->getActiveSections();
        $ratedSectionIds = $review->responses
            ->whereNotNull('manager_rating')
            ->pluck('section_id')
            ->toArray();
        $missingSections = $activeSections->filter(fn($s) => !in_array($s->id, $ratedSectionIds));

        if ($missingSections->isNotEmpty()) {
            $warnings[] = [
                'code' => 'INCOMPLETE_SECTION_RATINGS',
                'message' => 'Manager belum menilai ' . $missingSections->count() . ' section: ' .
                    $missingSections->pluck('name')->join(', '),
                'criteria_affected' => ['C1', 'C2'],
            ];
        }

        // C3/C4 Check: Does employee have goals?
        $goalCount = \App\Models\PerformanceGoal::where('staff_member_id', $review->staff_member_id)
            ->where(function ($q) use ($review, $cycle) {
                $q->where('linked_review_id', $review->id)
                  ->orWhereBetween('created_at', [
                      $cycle->start_date . ' 00:00:00',
                      $cycle->end_date . ' 23:59:59',
                  ]);
            })
            ->count();

        if ($goalCount === 0) {
            $warnings[] = [
                'code' => 'NO_GOALS',
                'message' => 'Karyawan tidak memiliki goal dalam periode ini. C3 dan C4 akan bernilai 0.',
                'criteria_affected' => ['C3', 'C4'],
            ];
        }

        // C5 Check: Does employee have feedback?
        $feedbackCount = \App\Models\PerformanceFeedback::where('staff_member_id', $review->staff_member_id)
            ->where('feedback_type', 'positive')
            ->whereBetween('created_at', [
                $cycle->start_date . ' 00:00:00',
                $cycle->end_date . ' 23:59:59',
            ])
            ->count();

        if ($feedbackCount === 0) {
            $warnings[] = [
                'code' => 'NO_POSITIVE_FEEDBACK',
                'message' => 'Karyawan tidak memiliki feedback positif dalam periode ini. C5 akan bernilai 0.',
                'criteria_affected' => ['C5'],
            ];
        }

        $isReady = empty($blockers);
        $hasWarnings = !empty($warnings);

        return ResponseHelper::jsonResponse(true, $isReady
            ? ($hasWarnings ? 'Review siap dikalibrasi tetapi ada warning.' : 'Review siap dikalibrasi.')
            : 'Review belum siap dikalibrasi.', [
            'review_id' => $id,
            'is_ready' => $isReady,
            'has_warnings' => $hasWarnings,
            'blockers' => $blockers,
            'warnings' => $warnings,
            'summary' => [
                'sections_rated' => count($ratedSectionIds) . '/' . $activeSections->count(),
                'goals_count' => $goalCount,
                'positive_feedback_count' => $feedbackCount,
            ],
        ]);
    }
}
