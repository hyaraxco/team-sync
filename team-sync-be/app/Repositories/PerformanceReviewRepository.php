<?php

namespace App\Repositories;

use App\Interfaces\PerformanceReviewRepositoryInterface;
use App\Models\PerformanceReviewCycle;
use App\Models\PerformanceReview;
use App\Models\PerformanceReviewSection;
use App\Models\PerformanceReviewResponse;
use Illuminate\Pagination\LengthAwarePaginator;

class PerformanceReviewRepository implements PerformanceReviewRepositoryInterface
{
    public function getCycles(array $filters = []): LengthAwarePaginator
    {
        $query = PerformanceReviewCycle::query();

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        
        if (isset($filters['cycle_type'])) {
            $query->where('cycle_type', $filters['cycle_type']);
        }

        return $query->orderBy('start_date', 'desc')
            ->paginate($filters['per_page'] ?? 15);
    }

    public function getCycleById(int $id)
    {
        return PerformanceReviewCycle::findOrFail($id);
    }

    public function createCycle(array $data)
    {
        return PerformanceReviewCycle::create($data);
    }

    public function updateCycle(int $id, array $data)
    {
        $cycle = $this->getCycleById($id);
        $cycle->update($data);
        return $cycle;
    }

    public function deleteCycle(int $id): bool
    {
        $cycle = $this->getCycleById($id);
        return $cycle->delete();
    }

    public function getReviewsForEmployee(string $employeeId, array $filters = []): LengthAwarePaginator
    {
        $query = PerformanceReview::with(['cycle', 'reviewer.user'])
            ->where('staff_member_id', $employeeId);
            
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        
        if (isset($filters['cycle_id'])) {
            $query->where('cycle_id', $filters['cycle_id']);
        }

        return $query->orderBy('created_at', 'desc')
            ->paginate($filters['per_page'] ?? 15);
    }

    public function getReviewsForManager(string $managerId, array $filters = []): LengthAwarePaginator
    {
        $query = PerformanceReview::with(['cycle', 'staffMember.user'])
            ->where('reviewer_id', $managerId);
            
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        
        if (isset($filters['cycle_id'])) {
            $query->where('cycle_id', $filters['cycle_id']);
        }

        return $query->orderBy('created_at', 'desc')
            ->paginate($filters['per_page'] ?? 15);
    }

    public function getReviewById(int $id)
    {
        return PerformanceReview::with(['cycle', 'staffMember.user', 'reviewer.user', 'responses.section', 'calibrator'])
            ->findOrFail($id);
    }

    public function createReview(array $data)
    {
        return PerformanceReview::create($data);
    }

    public function updateReview(int $id, array $data)
    {
        $review = $this->getReviewById($id);
        $review->update($data);
        return $review;
    }

    public function submitSelfAssessment(int $reviewId, array $responses, array $data)
    {
        $review = $this->getReviewById($reviewId);
        
        foreach ($responses as $response) {
            PerformanceReviewResponse::updateOrCreate(
                ['review_id' => $reviewId, 'section_id' => $response['section_id']],
                [
                    'self_rating' => $response['rating'],
                    'self_comments' => $response['comments'] ?? null,
                ]
            );
        }
        
        $review->update([
            'status' => 'pending_manager',
            'self_assessment_submitted_at' => now(),
        ]);
        
        return $review;
    }

    public function submitManagerAssessment(int $reviewId, array $responses, array $data)
    {
        $review = $this->getReviewById($reviewId);

        foreach ($responses as $response) {
            PerformanceReviewResponse::updateOrCreate(
                ['review_id' => $reviewId, 'section_id' => $response['section_id']],
                [
                    'manager_rating' => $response['rating'],
                    'manager_comments' => $response['comments'] ?? null,
                ]
            );
        }

        $managerRating = \App\Helpers\PerformanceRatingHelper::calculateManagerRating($reviewId);
        $calculated = \App\Helpers\PerformanceRatingHelper::calculateFinalRating($reviewId);

        $review->update([
            'status' => 'pending_calibration',
            'manager_assessment_submitted_at' => now(),
            'manager_recommended_rating' => $managerRating,
            'final_rating' => $calculated['final_rating'],
            'final_rating_label' => $calculated['final_rating_label'],
        ]);

        return $review->fresh()->load(['cycle', 'staffMember.user', 'reviewer.user', 'responses.section', 'calibrator']);
    }

    public function calibrateReview(int $reviewId, array $responses, array $data)
    {
        $review = $this->getReviewById($reviewId);

        $currentStaffId = auth()->user()->staffMemberProfile?->id;
        if ($currentStaffId && $review->staff_member_id == $currentStaffId) {
            abort(403, 'Cannot calibrate your own review');
        }

        if (!empty($responses)) {
            foreach ($responses as $response) {
                PerformanceReviewResponse::updateOrCreate(
                    ['review_id' => $reviewId, 'section_id' => $response['section_id']],
                    [
                        'final_rating' => $response['rating'],
                    ]
                );
            }
        }

        $calculated = \App\Helpers\PerformanceRatingHelper::calculateFinalRating($reviewId);

        $review->update([
            'status' => 'completed',
            'calibrated_at' => now(),
            'calibrated_by' => auth()->id(),
            'final_rating' => $calculated['final_rating'],
            'final_rating_label' => $calculated['final_rating_label'],
            'completed_at' => now(),
        ]);

        return $review->fresh()->load(['cycle', 'staffMember.user', 'reviewer.user', 'responses.section', 'calibrator']);
    }

    public function getReviewsPendingCalibration(array $filters = []): LengthAwarePaginator
    {
        $query = PerformanceReview::with(['cycle', 'staffMember.user', 'reviewer.user'])
            ->where('status', 'pending_calibration');

        if (isset($filters['cycle_id'])) {
            $query->where('cycle_id', $filters['cycle_id']);
        }

        $currentStaffId = auth()->user()->staffMemberProfile?->id;
        if ($currentStaffId) {
            $query->where('staff_member_id', '!=', $currentStaffId);
        }

        return $query->orderBy('created_at', 'desc')
            ->paginate($filters['per_page'] ?? 15);
    }

    public function getCalibrationContext(int $reviewId): array
    {
        $review = $this->getReviewById($reviewId);
        $cycleId = $review->cycle_id;

        $cycleReviews = PerformanceReview::with(['responses.section', 'reviewer.user'])
            ->where('cycle_id', $cycleId)
            ->whereNotNull('manager_assessment_submitted_at')
            ->get();

        $managerStats = [];
        foreach ($cycleReviews as $r) {
            $managerId = $r->reviewer_id;
            $managerName = $r->reviewer?->user?->name ?? 'Unknown';

            if (!isset($managerStats[$managerId])) {
                $managerStats[$managerId] = [
                    'manager_name' => $managerName,
                    'review_count' => 0,
                    'ratings' => [],
                ];
            }

            $managerStats[$managerId]['review_count']++;

            $avgRating = $r->responses->whereNotNull('manager_rating')->avg('manager_rating');
            if ($avgRating !== null) {
                $managerStats[$managerId]['ratings'][] = round($avgRating, 2);
            }
        }

        $result = [];
        foreach ($managerStats as $managerId => $stats) {
            $ratings = $stats['ratings'];
            $result[] = [
                'manager_id' => $managerId,
                'manager_name' => $stats['manager_name'],
                'review_count' => $stats['review_count'],
                'avg_rating' => count($ratings) > 0 ? round(array_sum($ratings) / count($ratings), 2) : null,
                'min_rating' => count($ratings) > 0 ? min($ratings) : null,
                'max_rating' => count($ratings) > 0 ? max($ratings) : null,
                'is_current_reviewer' => $managerId == $review->reviewer_id,
            ];
        }

        $allRatings = collect($result)->pluck('avg_rating')->filter()->values();

        return [
            'cycle_name' => $review->cycle->name,
            'total_reviews_in_cycle' => $cycleReviews->count(),
            'cycle_avg_rating' => $allRatings->isNotEmpty() ? round($allRatings->avg(), 2) : null,
            'manager_breakdown' => $result,
        ];
    }

    /**
     * Ambil data skor tiap karyawan dalam satu cycle untuk perhitungan TOPSIS.
     * Hanya mengambil review dengan status 'completed'.
     *
     * @return array  Array of candidates dengan 5 nilai kriteria siap hitung TOPSIS
     */
    public function getEmployeeScoresForCycle(int $cycleId): array
    {
        // C1 & C2: Dari performance_reviews + review_responses
        $reviewScores = PerformanceReview::with(['staffMember.jobInformation.team', 'responses'])
            ->where('cycle_id', $cycleId)
            ->where('status', 'completed')
            ->get();

        if ($reviewScores->isEmpty()) {
            return [];
        }

        $candidates = [];
        foreach ($reviewScores as $review) {
            $employeeId = $review->staff_member_id;

            // C1: Rata-rata manager_rating dari semua section responses
            $avgManagerRating = $review->responses
                ->whereNotNull('manager_rating')
                ->avg('manager_rating') ?? 0;

            // C2: Final rating setelah kalibrasi
            $finalRating = (float) ($review->final_rating ?? 0);

            // C3 & C4: Dari performance_goals yang linked ke review ini
            $goals = \App\Models\PerformanceGoal::where('linked_review_id', $review->id)->get();

            $avgGoalCompletion   = $goals->isNotEmpty() ? $goals->avg('completion_percentage') : 0;
            $totalGoals          = $goals->count();
            $completedGoals      = $goals->where('status', 'completed')->count();
            $goalCompletionRatio = $totalGoals > 0 ? ($completedGoals / $totalGoals) : 0;

            // C5: Jumlah positive feedback yang diterima selama periode cycle
            $cycle = $review->cycle ?? \App\Models\PerformanceReviewCycle::find($cycleId);
            $positiveFeedbackCount = \App\Models\PerformanceFeedback::where('staff_member_id', $employeeId)
                ->where('feedback_type', 'positive')
                ->whereBetween('created_at', [
                    $cycle->start_date . ' 00:00:00',
                    $cycle->end_date   . ' 23:59:59',
                ])
                ->count();

            $candidates[] = [
                'staff_member_id'             => $employeeId,
                'employee_name'           => $review->staffMember->full_name ?? 'Unknown',
                'department'              => $review->staffMember->jobInformation->department ?? null,
                'team'                    => $review->staffMember->jobInformation->team->name ?? null,
                'review_id'               => $review->id,
                'review_status'           => $review->status,
                // Kriteria TOPSIS
                'avg_manager_rating'      => round((float) $avgManagerRating, 4),
                'final_rating'            => $finalRating,
                'avg_goal_completion'     => round((float) $avgGoalCompletion, 4),
                'goal_completion_ratio'   => round((float) $goalCompletionRatio, 4),
                'positive_feedback_count' => (int) $positiveFeedbackCount,
            ];
        }

        return $candidates;
    }

    public function getActiveSections()
    {
        return PerformanceReviewSection::where('is_active', true)
            ->orderBy('order')
            ->get();
    }
}
