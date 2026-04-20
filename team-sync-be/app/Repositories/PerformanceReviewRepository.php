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
            ->where('employee_id', $employeeId);
            
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
        $query = PerformanceReview::with(['cycle', 'employee.user'])
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
        return PerformanceReview::with(['cycle', 'employee.user', 'reviewer.user', 'responses.section', 'calibrator'])
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
        
        $review->update([
            'status' => 'pending_calibration',
            'manager_assessment_submitted_at' => now(),
            'final_rating' => $data['final_rating'] ?? null,
        ]);
        
        return $review;
    }

    public function calibrateReview(int $reviewId, array $responses, array $data)
    {
        $review = $this->getReviewById($reviewId);
        
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
        
        $review->update([
            'status' => 'completed',
            'calibrated_at' => now(),
            'calibrated_by' => auth()->id(),
            'final_rating' => $data['final_rating'],
            'final_rating_label' => $data['final_rating_label'] ?? null,
            'completed_at' => now(),
        ]);
        
        return $review;
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
        $reviewScores = PerformanceReview::with(['employee.jobInformation.team', 'responses'])
            ->where('cycle_id', $cycleId)
            ->where('status', 'completed')
            ->get();

        if ($reviewScores->isEmpty()) {
            return [];
        }

        $candidates = [];
        foreach ($reviewScores as $review) {
            $employeeId = $review->employee_id;

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
            $positiveFeedbackCount = \App\Models\PerformanceFeedback::where('employee_id', $employeeId)
                ->where('feedback_type', 'positive')
                ->whereBetween('created_at', [
                    $cycle->start_date . ' 00:00:00',
                    $cycle->end_date   . ' 23:59:59',
                ])
                ->count();

            $candidates[] = [
                'employee_id'             => $employeeId,
                'employee_name'           => $review->employee->full_name ?? 'Unknown',
                'department'              => $review->employee->jobInformation->department ?? null,
                'team'                    => $review->employee->jobInformation->team->name ?? null,
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
