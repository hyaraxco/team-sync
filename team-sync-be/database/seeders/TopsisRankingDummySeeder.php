<?php

namespace Database\Seeders;

use App\Helpers\PerformanceRatingHelper;
use App\Models\Attendance;
use App\Models\PerformanceFeedback;
use App\Models\PerformanceGoal;
use App\Models\PerformanceReview;
use App\Models\PerformanceReviewCycle;
use App\Models\PerformanceReviewResponse;
use App\Models\PerformanceReviewSection;
use App\Models\StaffMemberProfile;
use App\Models\User;
use App\Services\Performance\ReviewerResolverService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Database\Seeder;

class TopsisRankingDummySeeder extends Seeder
{
    /**
     * Deterministic performance profiles for 10 employees.
     * Format: [selfRating, managerRating, attendanceProfile, goalsProfile, feedbackCount]
     */
    private function getEmployeeProfiles(): array
    {
        return [
            'yudhis@teamsync.com' => [
                'self_rating' => 5,
                'manager_rating' => 5,
                'attendance' => ['present' => 95, 'late' => 5, 'half_day' => 0, 'sick_leave' => 0, 'annual_leave' => 0, 'absent' => 0],
                'goals' => ['total' => 5, 'completed' => 5, 'on_time' => 5],
                'feedback_count' => 12,
            ],
            'agung@teamsync.com' => [
                'self_rating' => 4,
                'manager_rating' => 4,
                'attendance' => ['present' => 85, 'late' => 10, 'half_day' => 5, 'sick_leave' => 0, 'annual_leave' => 0, 'absent' => 0],
                'goals' => ['total' => 5, 'completed' => 4, 'on_time' => 3],
                'feedback_count' => 8,
            ],
            'tasyia@teamsync.com' => [
                'self_rating' => 4,
                'manager_rating' => 5,
                'attendance' => ['present' => 90, 'late' => 8, 'half_day' => 0, 'sick_leave' => 2, 'annual_leave' => 0, 'absent' => 0],
                'goals' => ['total' => 4, 'completed' => 4, 'on_time' => 4],
                'feedback_count' => 10,
            ],
            'dwimeta@teamsync.com' => [
                'self_rating' => 3,
                'manager_rating' => 4,
                'attendance' => ['present' => 80, 'late' => 15, 'half_day' => 0, 'sick_leave' => 0, 'annual_leave' => 5, 'absent' => 0],
                'goals' => ['total' => 5, 'completed' => 3, 'on_time' => 2],
                'feedback_count' => 6,
            ],
            'rina@teamsync.com' => [
                'self_rating' => 5,
                'manager_rating' => 4,
                'attendance' => ['present' => 92, 'late' => 5, 'half_day' => 0, 'sick_leave' => 3, 'annual_leave' => 0, 'absent' => 0],
                'goals' => ['total' => 5, 'completed' => 5, 'on_time' => 4],
                'feedback_count' => 9,
            ],
            'fajar@teamsync.com' => [
                'self_rating' => 3,
                'manager_rating' => 3,
                'attendance' => ['present' => 70, 'late' => 20, 'half_day' => 10, 'sick_leave' => 0, 'annual_leave' => 0, 'absent' => 0],
                'goals' => ['total' => 5, 'completed' => 2, 'on_time' => 1],
                'feedback_count' => 4,
            ],
            'sari@teamsync.com' => [
                'self_rating' => 4,
                'manager_rating' => 4,
                'attendance' => ['present' => 88, 'late' => 7, 'half_day' => 0, 'sick_leave' => 0, 'annual_leave' => 5, 'absent' => 0],
                'goals' => ['total' => 4, 'completed' => 3, 'on_time' => 3],
                'feedback_count' => 7,
            ],
            'budi@teamsync.com' => [
                'self_rating' => 2,
                'manager_rating' => 3,
                'attendance' => ['present' => 65, 'late' => 25, 'half_day' => 0, 'sick_leave' => 0, 'annual_leave' => 0, 'absent' => 10],
                'goals' => ['total' => 4, 'completed' => 2, 'on_time' => 1],
                'feedback_count' => 3,
            ],
            'dina@teamsync.com' => [
                'self_rating' => 5,
                'manager_rating' => 5,
                'attendance' => ['present' => 98, 'late' => 2, 'half_day' => 0, 'sick_leave' => 0, 'annual_leave' => 0, 'absent' => 0],
                'goals' => ['total' => 4, 'completed' => 4, 'on_time' => 4],
                'feedback_count' => 11,
            ],
            'mobile.pm01@teamsync.com' => [
                'self_rating' => 3,
                'manager_rating' => 2,
                'attendance' => ['present' => 60, 'late' => 30, 'half_day' => 0, 'sick_leave' => 0, 'annual_leave' => 0, 'absent' => 10],
                'goals' => ['total' => 4, 'completed' => 1, 'on_time' => 0],
                'feedback_count' => 2,
            ],
        ];
    }

    public function run(): void
    {
        $cycle = PerformanceReviewCycle::where('name', 'Q4 2025 Performance Review')->firstOrFail();
        $sections = PerformanceReviewSection::where('is_active', true)->orderBy('order')->get();
        $resolver = app(ReviewerResolverService::class);
        $hrUser = User::where('email', 'tasyia@teamsync.com')->firstOrFail();

        $weekdays = $this->getWeekdays('2025-10-01', '2025-12-31');
        $profiles = $this->getEmployeeProfiles();

        // Collect all staff member profile IDs for cross-feedback
        $allProfileIds = [];
        foreach (array_keys($profiles) as $email) {
            $user = User::where('email', $email)->first();
            if ($user && $user->staffMemberProfile) {
                $allProfileIds[$email] = $user->staffMemberProfile->id;
            }
        }

        foreach ($profiles as $email => $profile) {
            $user = User::where('email', $email)->first();
            if (! $user || ! $user->staffMemberProfile) {
                $this->command->warn("Skipping {$email}: user or profile not found.");

                continue;
            }

            $staffProfile = $user->staffMemberProfile;

            $this->seedReview($cycle, $staffProfile, $resolver, $hrUser, $sections, $profile);
            $this->seedAttendance($staffProfile, $weekdays, $profile['attendance']);
            $this->seedGoals($staffProfile, $cycle, $profile['goals']);
            $this->seedFeedback($staffProfile, $allProfileIds, $email, $profile['feedback_count']);

            $this->command->info("Seeded TOPSIS data for {$email}");
        }

        $this->command->info('TopsisRankingDummySeeder completed successfully.');
    }

    private function seedReview(
        PerformanceReviewCycle $cycle,
        StaffMemberProfile $staffProfile,
        ReviewerResolverService $resolver,
        User $hrUser,
        $sections,
        array $profile
    ): void {
        $reviewer = $resolver->resolve($staffProfile);

        $review = PerformanceReview::updateOrCreate(
            ['cycle_id' => $cycle->id, 'staff_member_id' => $staffProfile->id],
            [
                'reviewer_id' => $reviewer?->id,
                'status' => 'completed',
                'self_assessment_submitted_at' => Carbon::parse('2026-01-10'),
                'manager_assessment_submitted_at' => Carbon::parse('2026-01-25'),
                'calibrated_at' => Carbon::parse('2026-02-10'),
                'calibrated_by' => $hrUser->id,
                'completed_at' => Carbon::parse('2026-02-10'),
            ]
        );

        // Seed responses per section with slight variation
        $sectionIndex = 0;
        foreach ($sections as $section) {
            // Vary ratings ±1 per section for realism, clamped to 1-5
            $selfVariation = $this->getSectionVariation($sectionIndex);
            $mgrVariation = $this->getSectionVariation($sectionIndex + 1);

            $selfRating = max(1, min(5, $profile['self_rating'] + $selfVariation));
            $managerRating = max(1, min(5, $profile['manager_rating'] + $mgrVariation));
            $finalRating = (int) round(($selfRating + $managerRating) / 2);

            PerformanceReviewResponse::updateOrCreate(
                ['review_id' => $review->id, 'section_id' => $section->id],
                [
                    'self_rating' => $selfRating,
                    'self_comments' => "Self-assessment for {$section->name}.",
                    'manager_rating' => $managerRating,
                    'manager_comments' => "Manager review for {$section->name}.",
                    'final_rating' => $finalRating,
                ]
            );

            $sectionIndex++;
        }

        // Calculate and update final ratings
        $calculated = PerformanceRatingHelper::calculateFinalRating($review->id);
        $managerRatingCalc = PerformanceRatingHelper::calculateManagerRating($review->id);
        $review->update([
            'final_rating' => $calculated['final_rating'],
            'final_rating_label' => $calculated['final_rating_label'],
            'manager_recommended_rating' => $managerRatingCalc,
        ]);
    }

    /**
     * Deterministic variation pattern per section index.
     * Returns -1, 0, or +1 in a repeating pattern.
     */
    private function getSectionVariation(int $index): int
    {
        $pattern = [0, 1, 0, -1, 0, 1, -1, 0];

        return $pattern[$index % count($pattern)];
    }

    private function seedAttendance(StaffMemberProfile $staffProfile, array $weekdays, array $attendanceProfile): void
    {
        $totalDays = count($weekdays);

        // Calculate exact counts from percentages
        $counts = [
            'present' => (int) round($totalDays * $attendanceProfile['present'] / 100),
            'late' => (int) round($totalDays * $attendanceProfile['late'] / 100),
            'half_day' => (int) round($totalDays * $attendanceProfile['half_day'] / 100),
            'sick_leave' => (int) round($totalDays * $attendanceProfile['sick_leave'] / 100),
            'annual_leave' => (int) round($totalDays * $attendanceProfile['annual_leave'] / 100),
            'absent' => (int) round($totalDays * $attendanceProfile['absent'] / 100),
        ];

        // Adjust to match total days exactly (add/subtract from 'present')
        $sum = array_sum($counts);
        $counts['present'] += ($totalDays - $sum);

        // Build status array deterministically
        $statuses = [];
        foreach ($counts as $status => $count) {
            for ($i = 0; $i < $count; $i++) {
                $statuses[] = $status;
            }
        }

        // Distribute non-present statuses evenly across the period
        // Sort so present days fill in around the others
        $statusAssignments = array_fill(0, $totalDays, 'present');
        $nonPresentStatuses = array_filter($statuses, fn ($s) => $s !== 'present');
        $nonPresentStatuses = array_values($nonPresentStatuses);

        if (count($nonPresentStatuses) > 0) {
            $step = max(1, (int) floor($totalDays / count($nonPresentStatuses)));
            $pos = 2; // Start offset to avoid always putting issues on day 1
            foreach ($nonPresentStatuses as $status) {
                if ($pos < $totalDays) {
                    $statusAssignments[$pos] = $status;
                    $pos += $step;
                    if ($pos >= $totalDays) {
                        $pos = $totalDays - 1;
                    }
                }
            }
        }

        foreach ($weekdays as $dayIndex => $date) {
            $status = $statusAssignments[$dayIndex] ?? 'present';

            $checkIn = '09:00:00';
            $checkOut = '17:00:00';
            $workedMinutes = 480;

            if ($status === 'late') {
                $checkIn = '09:35:00';
                $checkOut = '17:35:00';
                $workedMinutes = 480;
            } elseif ($status === 'half_day') {
                $checkIn = '09:00:00';
                $checkOut = '13:00:00';
                $workedMinutes = 240;
            } elseif (in_array($status, ['sick_leave', 'annual_leave', 'absent'])) {
                $checkIn = null;
                $checkOut = null;
                $workedMinutes = 0;
            }

            Attendance::updateOrCreate(
                ['staff_member_id' => $staffProfile->id, 'date' => $date],
                [
                    'status' => $status,
                    'check_in' => $checkIn ? Carbon::parse("{$date} {$checkIn}") : null,
                    'check_out' => $checkOut ? Carbon::parse("{$date} {$checkOut}") : null,
                    'worked_minutes' => $workedMinutes,
                ]
            );
        }
    }

    private function seedGoals(StaffMemberProfile $staffProfile, PerformanceReviewCycle $cycle, array $goalsProfile): void
    {
        $review = PerformanceReview::where('cycle_id', $cycle->id)
            ->where('staff_member_id', $staffProfile->id)
            ->first();

        $goalTitles = [
            'Improve code quality metrics',
            'Complete certification training',
            'Deliver project milestone on time',
            'Mentor junior team members',
            'Reduce bug backlog by 30%',
        ];

        $totalGoals = $goalsProfile['total'];
        $completedCount = $goalsProfile['completed'];
        $onTimeCount = $goalsProfile['on_time'];

        for ($i = 0; $i < $totalGoals; $i++) {
            $title = $goalTitles[$i];
            $isCompleted = $i < $completedCount;
            $isOnTime = $i < $onTimeCount;

            // Stagger due dates across the quarter
            $dueDate = Carbon::parse('2025-10-01')->addDays(20 + ($i * 15));
            if ($dueDate->isAfter('2025-12-31')) {
                $dueDate = Carbon::parse('2025-12-31');
            }

            $completedAt = null;
            $status = 'in_progress';
            if ($isCompleted) {
                $status = 'completed';
                if ($isOnTime) {
                    // Completed before due date
                    $completedAt = $dueDate->copy()->subDays(3);
                } else {
                    // Completed after due date (late)
                    $completedAt = $dueDate->copy()->addDays(7);
                }
            }

            PerformanceGoal::updateOrCreate(
                [
                    'staff_member_id' => $staffProfile->id,
                    'title' => $title,
                    'linked_review_id' => $review?->id,
                ],
                [
                    'goal_type' => 'kpi',
                    'category' => 'performance',
                    'start_date' => '2025-10-01',
                    'due_date' => $dueDate->format('Y-m-d'),
                    'status' => $status,
                    'completion_percentage' => $isCompleted ? 100 : (30 + ($i * 10)),
                    'completed_at' => $completedAt,
                    'created_by' => $staffProfile->user_id,
                ]
            );
        }
    }

    private function seedFeedback(StaffMemberProfile $staffProfile, array $allProfileIds, string $currentEmail, int $feedbackCount): void
    {
        // Get other profile IDs to use as feedback givers
        $otherProfiles = array_filter($allProfileIds, fn ($id, $email) => $email !== $currentEmail, ARRAY_FILTER_USE_BOTH);
        $giverIds = array_values($otherProfiles);

        $categories = ['teamwork', 'communication', 'leadership', 'technical', 'initiative'];
        $contents = [
            'Great collaboration on the project.',
            'Excellent communication with stakeholders.',
            'Showed strong leadership during sprint planning.',
            'Delivered high-quality technical solutions.',
            'Took initiative to improve team processes.',
            'Consistently helpful to team members.',
            'Proactive in identifying and resolving issues.',
            'Strong problem-solving skills demonstrated.',
            'Effective at knowledge sharing with the team.',
            'Reliable and consistent in meeting deadlines.',
            'Positive attitude that motivates the team.',
            'Excellent attention to detail in code reviews.',
        ];

        for ($i = 0; $i < $feedbackCount; $i++) {
            $giverIndex = $i % count($giverIds);
            $categoryIndex = $i % count($categories);
            $contentIndex = $i % count($contents);

            // Spread feedback dates across the cycle period (Oct-Dec 2025)
            $dayOffset = (int) floor(($i / $feedbackCount) * 90) + 1;
            $feedbackDate = Carbon::parse('2025-10-01')->addDays($dayOffset);
            if ($feedbackDate->isAfter('2025-12-31')) {
                $feedbackDate = Carbon::parse('2025-12-31');
            }

            PerformanceFeedback::firstOrCreate(
                [
                    'staff_member_id' => $staffProfile->id,
                    'content' => $contents[$contentIndex],
                    'created_at' => $feedbackDate,
                ],
                [
                    'given_by' => $giverIds[$giverIndex],
                    'feedback_type' => 'positive',
                    'category' => $categories[$categoryIndex],
                    'updated_at' => $feedbackDate,
                ]
            );
        }
    }

    /**
     * Get all weekday dates between start and end (inclusive).
     */
    private function getWeekdays(string $start, string $end): array
    {
        $weekdays = [];
        $period = CarbonPeriod::create($start, $end);

        foreach ($period as $date) {
            if ($date->isWeekday()) {
                $weekdays[] = $date->format('Y-m-d');
            }
        }

        return $weekdays;
    }
}
