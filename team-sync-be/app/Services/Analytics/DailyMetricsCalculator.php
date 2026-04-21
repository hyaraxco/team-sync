<?php

namespace App\Services\Analytics;

use App\Models\AnalyticsSnapshot;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DailyMetricsCalculator
{
    /**
     * Calculate and store all daily metrics
     */
    public function calculateDailyMetrics(?string $date = null): void
    {
        $date = $date ?? Carbon::yesterday()->toDateString();
        
        Log::info("Starting daily metrics calculation for {$date}");

        try {
            DB::beginTransaction();

            $this->calculatePerformanceMetrics($date);
            $this->calculateGoalMetrics($date);
            $this->calculateFeedbackMetrics($date);

            DB::commit();
            Log::info("Daily metrics calculation completed successfully for {$date}");
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error("Daily metrics calculation failed for {$date}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Calculate performance review metrics
     */
    private function calculatePerformanceMetrics(string $date): void
    {
        // Total reviews
        $totalReviews = DB::table('performance_reviews')
            ->whereDate('created_at', '<=', $date)
            ->count();

        $this->storeSnapshot('performance', 'total_reviews', 'daily', $date, $totalReviews);

        // Completed reviews
        $completedReviews = DB::table('performance_reviews')
            ->where('status', 'completed')
            ->whereDate('completed_at', '<=', $date)
            ->count();

        $this->storeSnapshot('performance', 'completed_reviews', 'daily', $date, $completedReviews);

        // Average rating
        $avgRating = DB::table('performance_reviews')
            ->where('status', 'completed')
            ->whereNotNull('overall_rating')
            ->whereDate('completed_at', '<=', $date)
            ->avg('overall_rating');

        if ($avgRating) {
            $this->storeSnapshot('performance', 'average_rating', 'daily', $date, round($avgRating, 2));
        }

        // Reviews by status
        $statusCounts = DB::table('performance_reviews')
            ->select('status', DB::raw('COUNT(*) as count'))
            ->whereDate('created_at', '<=', $date)
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $this->storeSnapshot('performance', 'reviews_by_status', 'daily', $date, 0, $statusCounts);

        // Rating distribution
        $ratingDistribution = DB::table('performance_reviews')
            ->select('overall_rating', DB::raw('COUNT(*) as count'))
            ->where('status', 'completed')
            ->whereNotNull('overall_rating')
            ->whereDate('completed_at', '<=', $date)
            ->groupBy('overall_rating')
            ->pluck('count', 'overall_rating')
            ->toArray();

        $this->storeSnapshot('performance', 'rating_distribution', 'daily', $date, 0, $ratingDistribution);
    }

    /**
     * Calculate goal metrics
     */
    private function calculateGoalMetrics(string $date): void
    {
        // Total goals
        $totalGoals = DB::table('performance_goals')
            ->whereDate('created_at', '<=', $date)
            ->count();

        $this->storeSnapshot('performance', 'total_goals', 'daily', $date, $totalGoals);

        // Completed goals
        $completedGoals = DB::table('performance_goals')
            ->where('status', 'completed')
            ->whereDate('completed_at', '<=', $date)
            ->count();

        $this->storeSnapshot('performance', 'completed_goals', 'daily', $date, $completedGoals);

        // Goal completion rate
        $completionRate = $totalGoals > 0 ? round(($completedGoals / $totalGoals) * 100, 1) : 0;
        $this->storeSnapshot('performance', 'goal_completion_rate', 'daily', $date, $completionRate);

        // Average progress
        $avgProgress = DB::table('performance_goals')
            ->whereDate('created_at', '<=', $date)
            ->avg('progress_percentage');

        if ($avgProgress) {
            $this->storeSnapshot('performance', 'average_goal_progress', 'daily', $date, round($avgProgress, 1));
        }

        // Goals by status
        $statusCounts = DB::table('performance_goals')
            ->select('status', DB::raw('COUNT(*) as count'))
            ->whereDate('created_at', '<=', $date)
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $this->storeSnapshot('performance', 'goals_by_status', 'daily', $date, 0, $statusCounts);

        // Goals by category
        $categoryCounts = DB::table('performance_goals')
            ->select('category', DB::raw('COUNT(*) as count'))
            ->whereDate('created_at', '<=', $date)
            ->groupBy('category')
            ->pluck('count', 'category')
            ->toArray();

        $this->storeSnapshot('performance', 'goals_by_category', 'daily', $date, 0, $categoryCounts);

        // Overdue goals
        $overdueGoals = DB::table('performance_goals')
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->where('target_date', '<', $date)
            ->count();

        $this->storeSnapshot('performance', 'overdue_goals', 'daily', $date, $overdueGoals);
    }

    /**
     * Calculate feedback metrics
     */
    private function calculateFeedbackMetrics(string $date): void
    {
        // Total feedback
        $totalFeedback = DB::table('performance_feedback')
            ->whereDate('created_at', '<=', $date)
            ->count();

        $this->storeSnapshot('performance', 'total_feedback', 'daily', $date, $totalFeedback);

        // Feedback by type
        $typeCounts = DB::table('performance_feedback')
            ->select('feedback_type', DB::raw('COUNT(*) as count'))
            ->whereDate('created_at', '<=', $date)
            ->groupBy('feedback_type')
            ->pluck('count', 'feedback_type')
            ->toArray();

        $this->storeSnapshot('performance', 'feedback_by_type', 'daily', $date, 0, $typeCounts);

        // Feedback created today
        $todayFeedback = DB::table('performance_feedback')
            ->whereDate('created_at', $date)
            ->count();

        $this->storeSnapshot('performance', 'daily_feedback_count', 'daily', $date, $todayFeedback);

        // Average feedback per employee
        $employeeCount = DB::table('staff_member_profiles')
            ->whereNull('deleted_at')
            ->count();

        $avgPerEmployee = $employeeCount > 0 ? round($totalFeedback / $employeeCount, 2) : 0;
        $this->storeSnapshot('performance', 'avg_feedback_per_employee', 'daily', $date, $avgPerEmployee);
    }

    /**
     * Store a snapshot in the database
     */
    private function storeSnapshot(
        string $metricType,
        string $metricName,
        string $periodType,
        string $date,
        float $value,
        ?array $metadata = null
    ): void {
        AnalyticsSnapshot::updateOrCreate(
            [
                'metric_type' => $metricType,
                'metric_name' => $metricName,
                'period_type' => $periodType,
                'period_start' => $date,
            ],
            [
                'value' => $value,
                'metadata' => $metadata ? json_encode($metadata) : null,
            ]
        );
    }

    /**
     * Calculate metrics for a date range (useful for backfilling)
     */
    public function calculateMetricsForRange(string $startDate, string $endDate): void
    {
        $start = Carbon::parse($startDate);
        $end = Carbon::parse($endDate);

        Log::info("Calculating metrics for range: {$startDate} to {$endDate}");

        while ($start->lte($end)) {
            $this->calculateDailyMetrics($start->toDateString());
            $start->addDay();
        }

        Log::info("Range calculation completed");
    }
}
