<?php

namespace Tests\Feature\Performance;

use App\Models\PerformanceFeedback;
use App\Models\PerformanceReview;
use App\Models\PerformanceReviewCycle;
use Carbon\Carbon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\ActivatesLicense;
use Tests\TestCase;

/**
 * Repository-level boundary tests for the TOPSIS candidate pipeline
 * (team-sync-lzo AC#3): feedback normalization floor/cap, tenure cap,
 * and completed-status filtering in getEmployeeScoresForCycle().
 */
class TopsisCandidatePipelineTest extends TestCase
{
    use ActivatesLicense, RefreshDatabase;

    private User $hr;

    private PerformanceReviewCycle $cycle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->activateTestLicense();

        $this->artisan('db:seed', ['--class' => 'RoleSeeder']);
        $this->artisan('db:seed', ['--class' => 'PermissionSeeder']);
        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
        $this->artisan('db:seed', ['--class' => 'PerformanceReviewSectionSeeder']);
        $this->artisan('db:seed', ['--class' => 'EmployeeSeeder']);
        $this->artisan('db:seed', ['--class' => 'HrSeeder']);

        $hrRole = Role::findByName('hr', 'sanctum');
        foreach (['review-cycle-manage', 'performance.cycles.view', 'performance.topsis.view'] as $perm) {
            $hrRole->givePermissionTo(Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'sanctum']));
        }
        $this->hr = User::where('email', 'tasyia@teamsync.com')->first();

        $this->cycle = PerformanceReviewCycle::create([
            'name' => 'Topsis Pipeline Cycle',
            'cycle_type' => 'quarterly',
            'start_date' => '2026-01-01',
            'end_date' => '2026-03-31',
            'review_period_start' => '2026-01-01',
            'review_period_end' => '2026-03-31',
            'status' => 'completed',
            'created_by' => $this->hr->id,
        ]);
    }

    private function completedReviewFor(User $user, int $finalRating = 4): PerformanceReview
    {
        $review = PerformanceReview::create([
            'cycle_id' => $this->cycle->id,
            'staff_member_id' => $user->staffMemberProfile->id,
            'reviewer_id' => $this->hr->staffMemberProfile->id,
            'status' => 'completed',
            'self_assessment_submitted_at' => now()->subDays(10),
            'manager_assessment_submitted_at' => now()->subDays(5),
        ]);

        $review->update(['final_rating' => $finalRating]);

        return $review;
    }

    private function candidates(): array
    {
        return app(\App\Interfaces\PerformanceReviewRepositoryInterface::class)
            ->getEmployeeScoresForCycle($this->cycle->id);
    }

    /**
     * PerformanceFeedback ignores a manual created_at (it is reset to now()
     * by the model layer), so these tests freeze the clock inside the cycle
     * window instead of backdating rows.
     */
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function insideCycleWindow(): void
    {
        Carbon::setTestNow('2026-02-10 10:00:00');
    }

    private function positiveFeedback(User $emp, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            PerformanceFeedback::create([
                'staff_member_id' => $emp->staffMemberProfile->id,
                'given_by' => $this->hr->staffMemberProfile->id,
                'feedback_type' => 'positive',
                'category' => 'teamwork',
                'content' => "Positive note {$i}",
            ]);
        }
    }

    /** @test */
    public function test_feedback_score_floors_at_ten_when_cycle_max_is_below_floor(): void
    {
        $this->insideCycleWindow();
        $emp = User::where('email', 'agung@teamsync.com')->first();
        $this->completedReviewFor($emp);

        // 3 positive feedbacks: below the floor of 10 → cap = max(10, 3) = 10
        $this->positiveFeedback($emp, 3);

        $candidates = $this->candidates();
        $row = collect($candidates)->firstWhere('staff_member_id', $emp->staffMemberProfile->id);

        // 3 / 10 * 100 = 30
        $this->assertEqualsWithDelta(30.0, $row['feedback_score'], 0.01);
    }

    /** @test */
    public function test_feedback_score_caps_at_one_hundred_when_count_exceeds_cycle_max(): void
    {
        $this->insideCycleWindow();
        $emp = User::where('email', 'agung@teamsync.com')->first();
        $this->completedReviewFor($emp);

        // 15 positive feedbacks → cycle max = 15 → cap = max(10, 15) = 15
        $this->positiveFeedback($emp, 15);

        $candidates = $this->candidates();
        $row = collect($candidates)->firstWhere('staff_member_id', $emp->staffMemberProfile->id);

        // 15 / 15 * 100 = 100 (capped at 1.0 before scaling)
        $this->assertEqualsWithDelta(100.0, $row['feedback_score'], 0.01);
    }

    /** @test */
    public function test_feedback_score_is_zero_without_positive_feedback(): void
    {
        $emp = User::where('email', 'agung@teamsync.com')->first();
        $this->completedReviewFor($emp);

        // Negative feedback must not count toward the positive cap.
        PerformanceFeedback::create([
            'staff_member_id' => $emp->staffMemberProfile->id,
            'given_by' => $this->hr->staffMemberProfile->id,
            'feedback_type' => 'constructive',
            'category' => 'teamwork',
            'content' => 'Constructive note',
        ]);

        $candidates = $this->candidates();
        $row = collect($candidates)->firstWhere('staff_member_id', $emp->staffMemberProfile->id);

        $this->assertEqualsWithDelta(0.0, $row['feedback_score'], 0.01);
    }

    /** @test */
    public function test_cycle_boundary_excludes_feedback_outside_cycle_dates(): void
    {
        $this->insideCycleWindow();
        $emp = User::where('email', 'agung@teamsync.com')->first();
        $this->completedReviewFor($emp);

        // One inside the window (frozen clock), then move the clock outside
        // the window for the second one → cap = max(10, 1) = 10
        $this->positiveFeedback($emp, 1);

        Carbon::setTestNow('2026-05-20 10:00:00');
        $this->positiveFeedback($emp, 1);

        $candidates = $this->candidates();
        $row = collect($candidates)->firstWhere('staff_member_id', $emp->staffMemberProfile->id);

        // Only 1 counts: 1 / 10 * 100 = 10
        $this->assertEqualsWithDelta(10.0, $row['feedback_score'], 0.01);
    }

    /** @test */
    public function test_tenure_factor_caps_at_one_hundred_for_very_long_tenure(): void
    {
        $emp = User::where('email', 'agung@teamsync.com')->first();
        $jobInfo = $emp->staffMemberProfile->jobInformation;
        $jobInfo->update(['start_date' => now()->subMonths(80)->toDateString()]);

        $this->completedReviewFor($emp);

        $candidates = $this->candidates();
        $row = collect($candidates)->firstWhere('staff_member_id', $emp->staffMemberProfile->id);

        // min(80, 60) / 60 * 100 = 100
        $this->assertEqualsWithDelta(100.0, $row['tenure_factor'], 0.01);
    }

    /** @test */
    public function test_only_completed_reviews_become_candidates(): void
    {
        $emp = User::where('email', 'agung@teamsync.com')->first();

        // In-review (not completed) must be excluded.
        PerformanceReview::create([
            'cycle_id' => $this->cycle->id,
            'staff_member_id' => $emp->staffMemberProfile->id,
            'reviewer_id' => $this->hr->staffMemberProfile->id,
            'status' => 'in_review',
        ]);

        $this->assertSame([], $this->candidates());
    }
}
