<?php

namespace Tests\Feature\Performance;

use App\Models\PerformanceFeedback;
use App\Models\PerformanceReview;
use App\Models\PerformanceReviewCycle;
use App\Models\User;
use Carbon\Carbon;
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
        $this->insideCycleWindow();
        $emp = User::where('email', 'agung@teamsync.com')->first();
        $this->completedReviewFor($emp);

        // In-window constructive feedback must not count as positive feedback.
        $feedback = PerformanceFeedback::create([
            'staff_member_id' => $emp->staffMemberProfile->id,
            'given_by' => $this->hr->staffMemberProfile->id,
            'feedback_type' => 'constructive',
            'category' => 'teamwork',
            'content' => 'Constructive note',
        ]);

        $this->assertTrue($feedback->fresh()->created_at->betweenIncluded(
            $this->cycle->start_date->startOfDay(),
            $this->cycle->end_date->endOfDay(),
        ));

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

    /**
     * team-sync-6g5: cycle dates are Carbon casts, so `$cycle->end_date.' 23:59:59'`
     * concatenated onto an already-timestamped string produced malformed bounds
     * like "2026-03-31 00:00:00 23:59:59". Feedback created during the last day
     * of the cycle was therefore excluded.
     *
     * @test
     */
    public function test_last_day_of_cycle_feedback_is_counted(): void
    {
        $emp = User::where('email', 'agung@teamsync.com')->first();
        $this->completedReviewFor($emp);

        // 1 feedback on the first day, 4 on the last day of the cycle.
        Carbon::setTestNow('2026-01-01 00:00:00');
        $this->positiveFeedback($emp, 1);

        Carbon::setTestNow('2026-03-31 18:30:00');
        $this->positiveFeedback($emp, 4);

        // A day after the cycle must not count.
        Carbon::setTestNow('2026-04-01 00:00:01');
        $this->positiveFeedback($emp, 2);

        $candidates = $this->candidates();
        $row = collect($candidates)->firstWhere('staff_member_id', $emp->staffMemberProfile->id);

        // 5 in-window / cap max(10, 5) = 10 → 50.  The 2 out-of-window are ignored.
        $this->assertEqualsWithDelta(50.0, $row['feedback_score'], 0.01);
    }

    /**
     * A shared cycle maximum must be used, not a per-employee denominator.
     * Two candidates: 15 positive entries → 100, 7 entries → 46.6667 against
     * cap 15.  A fixed cap of 10 or a per-employee denominator would break this.
     *
     * @test
     */
    public function test_feedback_score_uses_shared_cycle_maximum_across_candidates(): void
    {
        $this->insideCycleWindow();

        $top = User::where('email', 'agung@teamsync.com')->first();
        // EmployeeSeeder only seeds this one account, so build a second candidate
        // explicitly instead of relying on another seeded email.
        $secondProfile = \App\Models\StaffMemberProfile::factory()->create();
        $second = $secondProfile->user;
        $this->assertNotNull($second, 'Second candidate needs a user');

        $this->completedReviewFor($top);
        $this->completedReviewFor($second);

        $this->positiveFeedback($top, 15);
        $this->positiveFeedback($second, 7);

        $candidates = collect($this->candidates());

        $topRow = $candidates->firstWhere('staff_member_id', $top->staffMemberProfile->id);
        $secondRow = $candidates->firstWhere('staff_member_id', $secondProfile->id);

        $this->assertNotNull($topRow, 'Top candidate row missing');
        $this->assertNotNull($secondRow, 'Second candidate row missing');

        // Cap is the cycle maximum 15.
        $this->assertEqualsWithDelta(100.0, $topRow['feedback_score'], 0.01);
        // 7 / 15 * 100 = 46.6667
        $this->assertEqualsWithDelta(46.6667, $secondRow['feedback_score'], 0.01);
    }

    /**
     * Goals created on the last day of the cycle must participate in C3/C4.
     *
     * @test
     */
    public function test_last_day_of_cycle_goals_are_included(): void
    {
        $emp = User::where('email', 'agung@teamsync.com')->first();
        $this->completedReviewFor($emp);

        Carbon::setTestNow('2026-03-31 20:00:00');

        $goal = \App\Models\PerformanceGoal::factory()->create([
            'staff_member_id' => $emp->staffMemberProfile->id,
            'title' => 'Last day goal',
            'goal_type' => 'kpi',
            'status' => 'completed',
            'start_date' => '2026-03-01',
            'due_date' => '2026-03-31',
            'completed_at' => '2026-03-31 19:00:00',
        ]);

        $candidates = $this->candidates();
        $row = collect($candidates)->firstWhere('staff_member_id', $emp->staffMemberProfile->id);

        // 1 goal, completed on time → 100% completion + 100% on-time
        $this->assertEqualsWithDelta(100.0, $row['goal_completion'], 0.01);
    }
}
