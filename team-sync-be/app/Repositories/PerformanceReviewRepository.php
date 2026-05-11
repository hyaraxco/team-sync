<?php

namespace App\Repositories;

use App\Helpers\PerformanceRatingHelper;
use App\Interfaces\PerformanceReviewRepositoryInterface;
use App\Models\Attendance;
use App\Models\PerformanceFeedback;
use App\Models\PerformanceGoal;
use App\Models\PerformanceOutcomeRule;
use App\Models\PerformanceReview;
use App\Models\PerformanceReviewCycle;
use App\Models\PerformanceReviewResponse;
use App\Models\PerformanceReviewSection;
use App\Models\PerformanceReviewTemplate;
use App\Models\StaffMemberProfile;
use App\Models\User;
use App\Services\Performance\PerformanceOutcomeService;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
        return PerformanceReviewCycle::with([
            'reviews.staffMember.user',
            'reviews.reviewer.user.roles',
            'reviews.staffMember.jobInformation',
            'reviews.reviewer.jobInformation',
        ])->findOrFail($id);
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

    public function getActiveStaffMembersForReview(int $cycleId)
    {
        $cycle = $this->getCycleById($cycleId);
        $existingReviewStaffIds = $cycle->reviews()->pluck('staff_member_id')->toArray();

        return StaffMemberProfile::with('jobInformation')
            ->whereHas('jobInformation', function ($q) {
                $q->where('status', 'active');
            })
            ->whereNotIn('id', $existingReviewStaffIds)
            ->get();
    }

    public function getDefaultTemplateId(): ?int
    {
        return PerformanceReviewTemplate::where('is_default', true)->first()?->id;
    }

    public function getHrUsers()
    {
        return User::role('hr')->get();
    }

    public function getGoalsForReview(int $staffMemberId, int $reviewId, $cycle)
    {
        return PerformanceGoal::where('staff_member_id', $staffMemberId)
            ->where(function ($q) use ($reviewId, $cycle) {
                $q->where('linked_review_id', $reviewId)
                    ->orWhere(function ($dateQ) use ($cycle) {
                        $dateQ->whereBetween('start_date', [
                            $cycle->start_date,
                            $cycle->end_date,
                        ])->orWhereBetween('created_at', [
                            $cycle->start_date.' 00:00:00',
                            $cycle->end_date.' 23:59:59',
                        ]);
                    });
            })
            ->get(['id', 'status', 'completed_at', 'due_date']);
    }

    public function getPositiveFeedbackCount(int $staffMemberId, $cycle): int
    {
        return PerformanceFeedback::where('staff_member_id', $staffMemberId)
            ->where('feedback_type', 'positive')
            ->whereBetween('created_at', [
                $cycle->start_date.' 00:00:00',
                $cycle->end_date.' 23:59:59',
            ])
            ->count();
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
        return PerformanceReview::with(['cycle', 'staffMember.user', 'reviewer.user.roles', 'responses.section', 'calibrator', 'outcomeRule'])
            ->findOrFail($id);
    }

    public function createReview(array $data)
    {
        if (isset($data['reviewer_id']) && isset($data['staff_member_id']) && $data['reviewer_id'] == $data['staff_member_id']) {
            $data['reviewer_id'] = null;
        }

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

        $managerRating = PerformanceRatingHelper::calculateManagerRating($reviewId);
        $calculated = PerformanceRatingHelper::calculateFinalRating($reviewId);

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

        $authUser = Auth::user();
        $currentStaffId = $authUser?->staffMemberProfile?->id;
        if ($currentStaffId && $review->staff_member_id == $currentStaffId) {
            abort(403, 'Cannot calibrate your own review');
        }

        if (! empty($responses)) {
            foreach ($responses as $response) {
                PerformanceReviewResponse::updateOrCreate(
                    ['review_id' => $reviewId, 'section_id' => $response['section_id']],
                    [
                        'final_rating' => $response['rating'],
                    ]
                );
            }
        }

        $calculated = PerformanceRatingHelper::calculateFinalRating($reviewId);

        $review->update([
            'status' => 'completed',
            'calibrated_at' => now(),
            'calibrated_by' => $authUser?->id,
            'final_rating' => $calculated['final_rating'],
            'final_rating_label' => $calculated['final_rating_label'],
            'completed_at' => now(),
        ]);

        $outcomeService = app(PerformanceOutcomeService::class);
        $review = $outcomeService->applyOutcome($review);

        return $review->fresh()->load(['cycle', 'staffMember.user', 'reviewer.user', 'responses.section', 'calibrator', 'outcomeRule']);
    }

    public function getReviewsPendingCalibration(array $filters = []): LengthAwarePaginator
    {
        $query = PerformanceReview::with(['cycle', 'staffMember.user', 'reviewer.user'])
            ->where('status', 'pending_calibration');

        if (isset($filters['cycle_id'])) {
            $query->where('cycle_id', $filters['cycle_id']);
        }

        $authUser = Auth::user();
        $currentStaffId = $authUser?->staffMemberProfile?->id;
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

            if (! isset($managerStats[$managerId])) {
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
     * Kriteria TOPSIS (5):
     *   performance_score = avg(competency, KPI) * 20 → 0-100
     *   attendance_rate   = attendance quality score → 0-100
     *   goal_completion   = 80% completion rate + 20% on-time bonus → 0-100
     *   feedback_score    = positive feedback count
     *   tenure_factor     = months since hire_date, capped 60, scaled 0-100
     *
     * @return array Array of candidates dengan 5 nilai kriteria siap hitung TOPSIS
     */
    public function getEmployeeScoresForCycle(int $cycleId): array
    {
        // Eager-load responses with their section (including topsis_category)
        $reviewScores = PerformanceReview::with(['staffMember.jobInformation.team', 'responses.section'])
            ->where('cycle_id', $cycleId)
            ->where('status', 'completed')
            ->get();

        if ($reviewScores->isEmpty()) {
            return [];
        }

        // Fetch all templates used in this cycle to cache weights
        $templateIds = $reviewScores->pluck('review_template_id')->filter()->unique();
        $templateWeights = [];
        foreach ($templateIds as $templateId) {
            $templateWeights[$templateId] = DB::table('review_template_sections')
                ->where('template_id', $templateId)
                ->pluck('weight', 'section_id')
                ->toArray();
        }

        // Pre-load section weights and categories (fallback)
        $sections = PerformanceReviewSection::where('is_active', true)->get()->keyBy('id');

        $candidates = [];
        foreach ($reviewScores as $review) {
            $employeeId = $review->staff_member_id;
            $reviewTemplateId = $review->review_template_id;

            // C1 & C2: Calculate weighted averages per topsis_category
            $competencyWeightedSum = 0;
            $competencyTotalWeight = 0;
            $kpiWeightedSum = 0;
            $kpiTotalWeight = 0;

            foreach ($review->responses as $response) {
                $section = $response->section ?? $sections->get($response->section_id);
                if (! $section || ($section->topsis_category ?? 'kpi') === 'excluded') {
                    continue;
                }

                // Use template weight if available, otherwise fallback to global section weight
                $weight = (float) ($templateWeights[$reviewTemplateId][$section->id] ?? $section->weight);

                // For KPI sections: use calibrated (final_rating) if available, else manager_rating
                // For competency sections: use manager_rating (competency is not typically calibrated)
                if (($section->topsis_category ?? 'kpi') === 'kpi') {
                    $score = $response->final_rating !== null
                        ? (float) $response->final_rating
                        : (float) ($response->manager_rating ?? 0);
                    $kpiWeightedSum += $score * $weight;
                    $kpiTotalWeight += $weight;
                } else {
                    // competency
                    $score = (float) ($response->manager_rating ?? 0);
                    $competencyWeightedSum += $score * $weight;
                    $competencyTotalWeight += $weight;
                }
            }

            // C1: Competency Score (normalized to 1-5 scale)
            $c1CompetencyScore = $competencyTotalWeight > 0
                ? $competencyWeightedSum / $competencyTotalWeight
                : 0;

            // C2: KPI Score (normalized to 1-5 scale)
            $c2KpiScore = $kpiTotalWeight > 0
                ? $kpiWeightedSum / $kpiTotalWeight
                : 0;

            // C3 & C4: From performance_goals within the cycle period
            $cycle = $review->cycle ?? PerformanceReviewCycle::find($cycleId);
            $goals = PerformanceGoal::where('staff_member_id', $employeeId)
                ->where(function ($q) use ($review, $cycle) {
                    // Goals linked to this review OR created within the cycle period
                    $q->where('linked_review_id', $review->id)
                        ->orWhereBetween('created_at', [
                            $cycle->start_date.' 00:00:00',
                            $cycle->end_date.' 23:59:59',
                        ]);
                })
                ->get();

            $totalGoals = $goals->count();
            $completedGoals = $goals->where('status', 'completed')->count();
            $avgGoalCompletion = $totalGoals > 0 ? ($completedGoals / $totalGoals) * 100 : 0;

            // C4: On-time ratio (goals completed before or on due_date)
            $onTimeGoals = $goals->where('status', 'completed')
                ->filter(function ($g) {
                    return $g->completed_at && $g->due_date && $g->completed_at <= $g->due_date;
                })
                ->count();
            $goalCompletionRatio = $completedGoals > 0 ? ($onTimeGoals / $completedGoals) : 0;

            // C5: Positive feedback count within cycle period
            $positiveFeedbackCount = PerformanceFeedback::where('staff_member_id', $employeeId)
                ->where('feedback_type', 'positive')
                ->whereBetween('created_at', [
                    $cycle->start_date.' 00:00:00',
                    $cycle->end_date.' 23:59:59',
                ])
                ->count();

            $attendanceRecords = Attendance::where('staff_member_id', $employeeId)
                ->whereBetween('date', [
                    $cycle->start_date,
                    $cycle->end_date,
                ])
                ->get(['status']);

            $attendanceQuality = 0.0;
            if ($attendanceRecords->isNotEmpty()) {
                $attendancePoints = $attendanceRecords->sum(function ($attendance) {
                    return match ((string) $attendance->status) {
                        'present' => 1.0,
                        'late' => 0.7,
                        'half_day' => 0.5,
                        'sick_leave', 'annual_leave' => 0.8,
                        default => 0.0,
                    };
                });

                $attendanceQuality = ($attendancePoints / $attendanceRecords->count()) * 100;
            }

            // Tenure factor: months since hire_date, capped at 60 months, scaled 0-100
            $startDate = $review->staffMember->jobInformation?->start_date;
            $tenureFactor = 0.0;
            if ($startDate) {
                $tenureMonths = Carbon::parse($startDate)->diffInMonths(now());
                $tenureFactor = min($tenureMonths, 60) / 60 * 100;
            }

            // Merged C1+C2: performance_score = average of competency + KPI (1-5 scale) → 0-100
            $performanceScore = (($c1CompetencyScore + $c2KpiScore) / 2) * 20;

            // Merged C3+C4: goal_completion = 80% completion rate + 20% on-time bonus
            $goalCompletion = ($avgGoalCompletion * 0.8) + ($goalCompletionRatio * 100 * 0.2);

            $candidates[] = [
                'staff_member_id' => $employeeId,
                'employee_name' => $review->staffMember->full_name ?? 'Unknown',
                'department' => $review->staffMember->jobInformation->department ?? null,
                'team' => $review->staffMember->jobInformation->team->name ?? null,
                'review_id' => $review->id,
                'review_status' => $review->status,
                // Kriteria TOPSIS (5 criteria)
                'performance_score' => round((float) $performanceScore, 4),        // Merged C1+C2
                'attendance_rate' => round((float) $attendanceQuality, 4),          // C6 (renamed)
                'goal_completion' => round((float) $goalCompletion, 4),             // Merged C3+C4
                'feedback_score' => (int) $positiveFeedbackCount,                   // C5 (renamed)
                'tenure_factor' => round((float) $tenureFactor, 4),                // NEW
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

    public function getTemplates()
    {
        return PerformanceReviewTemplate::withCount('sections')->get();
    }

    public function createTemplate(array $data, array $sections)
    {
        return DB::transaction(function () use ($data, $sections) {
            if (($data['is_default'] ?? false) === true) {
                PerformanceReviewTemplate::query()
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            $template = PerformanceReviewTemplate::query()->create($data);

            foreach ($sections as $section) {
                $template->sections()->attach($section['id'], ['weight' => $section['weight']]);
            }

            return $template->load('sections');
        });
    }

    public function getTemplateById(int $id)
    {
        return PerformanceReviewTemplate::with('sections')->findOrFail($id);
    }

    public function updateTemplate(int $id, array $data, ?array $sections = null)
    {
        $template = PerformanceReviewTemplate::query()->findOrFail($id);

        return DB::transaction(function () use ($template, $data, $sections) {
            if (($data['is_default'] ?? false) === true) {
                PerformanceReviewTemplate::query()
                    ->where('id', '!=', $template->id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            $template->update($data);

            if ($sections !== null) {
                $syncData = [];
                foreach ($sections as $section) {
                    $syncData[$section['id']] = ['weight' => $section['weight']];
                }
                $template->sections()->sync($syncData);
            }

            return $template->load('sections');
        });
    }

    public function deleteTemplate(int $id): bool
    {
        $template = PerformanceReviewTemplate::query()->findOrFail($id);

        if ($template->performanceReviews()->exists()) {
            throw new \RuntimeException('Cannot delete template that is already used in reviews');
        }

        return (bool) $template->delete();
    }

    public function getOutcomeRules()
    {
        return PerformanceOutcomeRule::query()
            ->orderBy('min_rating')
            ->get();
    }

    public function createOutcomeRule(array $data)
    {
        return PerformanceOutcomeRule::query()->create($data);
    }

    public function getOutcomeRuleById(int $id)
    {
        return PerformanceOutcomeRule::query()->findOrFail($id);
    }

    public function updateOutcomeRule(int $id, array $data)
    {
        $rule = PerformanceOutcomeRule::query()->findOrFail($id);
        $rule->update($data);

        return $rule->fresh();
    }

    public function deleteOutcomeRule(int $id): bool
    {
        $rule = PerformanceOutcomeRule::query()->findOrFail($id);

        return (bool) $rule->delete();
    }
}
