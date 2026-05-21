<?php

namespace Tests\Feature\Performance;

use App\Helpers\PerformanceRatingHelper;
use App\Models\PerformanceReview;
use App\Models\PerformanceReviewCycle;
use App\Models\PerformanceReviewResponse;
use App\Models\PerformanceReviewSection;
use App\Models\User;
use App\Services\Performance\ReviewerResolverService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\ActivatesLicense;
use Tests\TestCase;

class PerformanceTopsisControllerTest extends TestCase
{
    use ActivatesLicense, RefreshDatabase;

    private User $hrAdmin;

    private User $employee;

    private PerformanceReviewCycle $completedCycle;

    private PerformanceReviewCycle $activeCycle;

    private PerformanceReviewSection $sectionC1;

    private PerformanceReviewSection $sectionC2;

    private PerformanceReviewSection $sectionC3;

    private PerformanceReviewSection $sectionC4;

    private PerformanceReviewSection $sectionC5;

    protected function setUp(): void
    {
        parent::setUp();

        // Run seeders required for full data
        $this->artisan('db:seed', ['--class' => 'RoleSeeder']);
        $this->artisan('db:seed', ['--class' => 'PermissionSeeder']);
        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
        $this->artisan('db:seed', ['--class' => 'PerformanceReviewSectionSeeder']);
        $this->artisan('db:seed', ['--class' => 'ManagerSeeder']);
        $this->artisan('db:seed', ['--class' => 'EmployeeSeeder']);
        $this->artisan('db:seed', ['--class' => 'HrSeeder']);
        $this->artisan('db:seed', ['--class' => 'FinanceSeeder']);
        $this->artisan('db:seed', ['--class' => 'PerformanceDataSeeder']);
        $this->activateTestLicense();

        // Give HR permission
        $hrRole = Role::findByName('hr', 'sanctum');
        $permission = Permission::firstOrCreate(['name' => 'performance.cycles.view', 'guard_name' => 'sanctum']);
        $hrRole->givePermissionTo($permission);
        $permissionRank = Permission::firstOrCreate(['name' => 'performance.topsis.view', 'guard_name' => 'sanctum']);
        $hrRole->givePermissionTo($permissionRank);

        $this->hrAdmin = User::where('email', 'tasyia@teamsync.com')->first();
        $this->employee = User::where('email', 'agung@teamsync.com')->first();

        // Q4 2025 is completed, Q1 2026 is active
        $this->completedCycle = PerformanceReviewCycle::where('name', 'Q4 2025 Performance Review')->first();
        $this->activeCycle = PerformanceReviewCycle::where('name', 'Q1 2026 Performance Review')->first();
        $this->seedDifferentiatedCompletedReviews();
    }

    /**
     * Add differentiated completed reviews to Q4 2025 cycle so feature tests exercise
     * the multi-candidate TOPSIS path (weightedNormalize) instead of buildSingleResult.
     *
     * PerformanceDataSeeder only creates 1 completed review (Agung). With 1 candidate,
     * TopsisService::calculate() routes to buildSingleResult() which hardcodes CC=1.0
     * regardless of weights — meaning the criteria-key-mismatch bug doesn't manifest.
     *
     * This helper:
     *   - Overrides Agung's existing review responses to deterministic 4/4 (mid)
     *   - Adds Yudhis (manager) completed review with 5/5 ratings (high performer)
     *   - Adds Tasyia (HR) completed review with 2/2 ratings (low performer)
     *
     * Result: 3 differentiated candidates → multi-candidate TOPSIS path → bug observable.
     * Deterministic ordering: Yudhis > Agung > Tasyia.
     */
    private function seedDifferentiatedCompletedReviews(): void
    {
        $sections = PerformanceReviewSection::where('is_active', true)->orderBy('order')->get();
        $resolver = app(ReviewerResolverService::class);

        $manager = User::where('email', 'yudhis@teamsync.com')->first();
        $hr = User::where('email', 'tasyia@teamsync.com')->first();
        $employee = User::where('email', 'agung@teamsync.com')->first();

        $managerProfile = $manager->staffMemberProfile;
        $hrProfile = $hr->staffMemberProfile;
        $employeeProfile = $employee->staffMemberProfile;

        // 1. Override Agung's existing review responses to deterministic 4/4 (mid performer).
        $agungReview = PerformanceReview::where('cycle_id', $this->completedCycle->id)
            ->where('staff_member_id', $employeeProfile->id)
            ->first();

        if ($agungReview) {
            foreach ($sections as $section) {
                PerformanceReviewResponse::updateOrCreate(
                    ['review_id' => $agungReview->id, 'section_id' => $section->id],
                    [
                        'self_rating' => 4,
                        'self_comments' => 'Mid performer self-assessment.',
                        'manager_rating' => 4,
                        'manager_comments' => 'Solid mid-level performance.',
                        'final_rating' => 4,
                    ]
                );
            }

            $agungCalc = PerformanceRatingHelper::calculateFinalRating($agungReview->id);
            $agungMgr = PerformanceRatingHelper::calculateManagerRating($agungReview->id);
            $agungReview->update([
                'final_rating' => $agungCalc['final_rating'],
                'final_rating_label' => $agungCalc['final_rating_label'],
                'manager_recommended_rating' => $agungMgr,
            ]);
        }

        // 2. Yudhis (manager) — high performer (5/5 across all sections).
        $managerReview = PerformanceReview::create([
            'cycle_id' => $this->completedCycle->id,
            'staff_member_id' => $managerProfile->id,
            'reviewer_id' => $resolver->resolve($managerProfile)?->id,
            'status' => 'completed',
            'self_assessment_submitted_at' => Carbon::parse('2026-01-12'),
            'manager_assessment_submitted_at' => Carbon::parse('2026-01-26'),
            'calibrated_at' => Carbon::parse('2026-02-11'),
            'calibrated_by' => $hr->id,
            'completed_at' => Carbon::parse('2026-02-11'),
        ]);

        foreach ($sections as $section) {
            PerformanceReviewResponse::create([
                'review_id' => $managerReview->id,
                'section_id' => $section->id,
                'self_rating' => 5,
                'self_comments' => 'High performer self-assessment.',
                'manager_rating' => 5,
                'manager_comments' => 'Outstanding manager review.',
                'final_rating' => 5,
            ]);
        }

        $managerCalc = PerformanceRatingHelper::calculateFinalRating($managerReview->id);
        $managerMgrRating = PerformanceRatingHelper::calculateManagerRating($managerReview->id);
        $managerReview->update([
            'final_rating' => $managerCalc['final_rating'],
            'final_rating_label' => $managerCalc['final_rating_label'],
            'manager_recommended_rating' => $managerMgrRating,
        ]);

        // 3. Tasyia (HR) — low performer (2/2 across all sections).
        $hrReview = PerformanceReview::create([
            'cycle_id' => $this->completedCycle->id,
            'staff_member_id' => $hrProfile->id,
            'reviewer_id' => $resolver->resolve($hrProfile)?->id,
            'status' => 'completed',
            'self_assessment_submitted_at' => Carbon::parse('2026-01-13'),
            'manager_assessment_submitted_at' => Carbon::parse('2026-01-27'),
            'calibrated_at' => Carbon::parse('2026-02-12'),
            'calibrated_by' => $hr->id,
            'completed_at' => Carbon::parse('2026-02-12'),
        ]);

        foreach ($sections as $section) {
            PerformanceReviewResponse::create([
                'review_id' => $hrReview->id,
                'section_id' => $section->id,
                'self_rating' => 2,
                'self_comments' => 'Below expectations self-assessment.',
                'manager_rating' => 2,
                'manager_comments' => 'Below expectations.',
                'final_rating' => 2,
            ]);
        }

        $hrCalc = PerformanceRatingHelper::calculateFinalRating($hrReview->id);
        $hrMgrRating = PerformanceRatingHelper::calculateManagerRating($hrReview->id);
        $hrReview->update([
            'final_rating' => $hrCalc['final_rating'],
            'final_rating_label' => $hrCalc['final_rating_label'],
            'manager_recommended_rating' => $hrMgrRating,
        ]);
    }

    public function test_hr_can_fetch_topsis_ranking_for_completed_cycle()
    {
        Sanctum::actingAs($this->hrAdmin);

        $response = $this->getJson("/api/v1/performance/cycles/{$this->completedCycle->id}/topsis-ranking");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'cycle_id',
                'cycle_name',
                'cycle_status',
                'total_candidates',
                'ranking',
                'weights',
                'ideal_positive',
                'ideal_negative',
            ],
        ]);

        $this->assertGreaterThanOrEqual(1, $response->json('data.total_candidates'));
        $this->assertNotEmpty($response->json('data.ranking'));

        // Regression guard: closeness_coefficient must be > 0 when candidates differ.
        // The original bug caused all coefficients to resolve to 0.0 due to weight/criteria key mismatch.
        $ranking = $response->json('data.ranking');
        $top = $ranking[0];
        $this->assertGreaterThan(0.0, $top['closeness_coefficient'],
            'Top candidate closeness_coefficient must be > 0 (regression guard for criteria-weight key mismatch)');

        // If multiple candidates, top CC should exceed bottom CC (proves ordering works).
        if (count($ranking) > 1) {
            $bottom = $ranking[count($ranking) - 1];
            $this->assertGreaterThanOrEqual(
                $bottom['closeness_coefficient'],
                $top['closeness_coefficient'],
                'Top candidate must rank >= bottom candidate'
            );
        }
    }

    public function test_topsis_endpoint_returns_422_empty_state_when_no_completed_reviews()
    {
        Sanctum::actingAs($this->hrAdmin);

        // Fetch ranking for active cycle which has NO completed reviews
        $response = $this->getJson("/api/v1/performance/cycles/{$this->activeCycle->id}/topsis-ranking");

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'data' => [
                'total_completed' => 0,
                'ranking' => [],
            ],
        ]);
    }

    public function test_topsis_endpoint_returns_404_for_missing_cycle()
    {
        Sanctum::actingAs($this->hrAdmin);

        $response = $this->getJson('/api/v1/performance/cycles/99999/topsis-ranking');

        $response->assertStatus(404);
        $response->assertJson([
            'success' => false,
            'message' => 'Review cycle tidak ditemukan',
        ]);
    }

    public function test_topsis_endpoint_applies_custom_weights()
    {
        Sanctum::actingAs($this->hrAdmin);

        // Custom PRD-aligned weights (must sum to 1.0)
        $response = $this->getJson(
            "/api/v1/performance/cycles/{$this->completedCycle->id}/topsis-ranking?".
            'w_performance_score=0.40&w_attendance_rate=0.20&'.
            'w_goal_completion=0.20&w_feedback_score=0.10&w_tenure_factor=0.10'
        );

        $response->assertStatus(200);

        $weights = $response->json('data.weights');

        // All 5 PRD criteria keys must be present
        $this->assertArrayHasKey('performance_score', $weights);
        $this->assertArrayHasKey('attendance_rate', $weights);
        $this->assertArrayHasKey('goal_completion', $weights);
        $this->assertArrayHasKey('feedback_score', $weights);
        $this->assertArrayHasKey('tenure_factor', $weights);

        // Custom weight values must be reflected
        $this->assertEquals(0.40, $weights['performance_score']);
        $this->assertEquals(0.20, $weights['attendance_rate']);
        $this->assertEquals(0.20, $weights['goal_completion']);
        $this->assertEquals(0.10, $weights['feedback_score']);
        $this->assertEquals(0.10, $weights['tenure_factor']);

        // Top candidate must still produce non-zero closeness with custom weights
        $this->assertGreaterThan(0.0, $response->json('data.ranking.0.closeness_coefficient'));
    }

    public function test_employee_cannot_access_topsis_ranking()
    {
        Sanctum::actingAs($this->employee);

        $response = $this->getJson("/api/v1/performance/cycles/{$this->completedCycle->id}/topsis-ranking");

        $this->assertTrue(in_array($response->status(), [403, 401]));
    }

    /**
     * Critical regression guard.
     *
     * The original bug: PerformanceTopsisController::DEFAULT_WEIGHTS used 7 keys
     * (avg_manager_rating, final_rating, etc.) but TopsisService::CRITERIA expects
     * 5 keys (performance_score, attendance_rate, goal_completion, feedback_score,
     * tenure_factor). Mismatch → all weights resolved to 0.0 → silent breakage.
     *
     * This test ensures any future change to DEFAULT_WEIGHTS or CRITERIA
     * triggers a failure if they drift apart.
     */
    public function test_response_weights_keys_exactly_match_topsis_criteria()
    {
        Sanctum::actingAs($this->hrAdmin);

        $response = $this->getJson("/api/v1/performance/cycles/{$this->completedCycle->id}/topsis-ranking");

        $response->assertStatus(200);

        $weightKeys = array_keys($response->json('data.weights'));
        sort($weightKeys);

        $expected = ['attendance_rate', 'feedback_score', 'goal_completion', 'performance_score', 'tenure_factor'];
        sort($expected);

        $this->assertEquals(
            $expected,
            $weightKeys,
            'Response weights keys must match TopsisService::CRITERIA exactly. '.
            'If this fails, DEFAULT_WEIGHTS in PerformanceTopsisController has drifted '.
            'from CRITERIA in TopsisService.'
        );
    }

    public function test_partial_custom_weights_fall_back_to_defaults_for_unspecified_criteria()
    {
        Sanctum::actingAs($this->hrAdmin);

        // Only specify ONE weight — other 4 must fall back to defaults
        $response = $this->getJson(
            "/api/v1/performance/cycles/{$this->completedCycle->id}/topsis-ranking?w_performance_score=0.50"
        );

        $response->assertStatus(200);
        $weights = $response->json('data.weights');

        // All 5 keys present (not just the one provided)
        $this->assertCount(5, $weights);
        $this->assertArrayHasKey('performance_score', $weights);
        $this->assertArrayHasKey('attendance_rate', $weights);
        $this->assertArrayHasKey('goal_completion', $weights);
        $this->assertArrayHasKey('feedback_score', $weights);
        $this->assertArrayHasKey('tenure_factor', $weights);

        // Total weights must normalize to ~1.0
        $total = array_sum($weights);
        $this->assertEqualsWithDelta(1.0, $total, 0.01,
            'Weights must auto-normalize to 1.0 when partial custom weights provided');
    }

    public function test_all_zero_custom_weights_returns_gracefully_without_division_error()
    {
        Sanctum::actingAs($this->hrAdmin);

        $response = $this->getJson(
            "/api/v1/performance/cycles/{$this->completedCycle->id}/topsis-ranking?".
            'w_performance_score=0&w_attendance_rate=0&w_goal_completion=0&'.
            'w_feedback_score=0&w_tenure_factor=0'
        );

        // Must NOT 500 — endpoint must handle pathological input gracefully
        $this->assertNotEquals(500, $response->status(),
            'All-zero weights must not cause server error');
    }

    /**
     * Negative weights would invert a criterion's TOPSIS contribution and produce
     * mathematically nonsensical rankings — same class of silent corruption as
     * the original key-mismatch bug (looks like it works, results are garbage).
     *
     * Implementation must clamp negative weights to 0.0 before normalization.
     */
    public function test_negative_weight_values_are_clamped_to_zero()
    {
        Sanctum::actingAs($this->hrAdmin);

        $response = $this->getJson(
            "/api/v1/performance/cycles/{$this->completedCycle->id}/topsis-ranking?".
            'w_performance_score=-0.5&w_attendance_rate=0.30&'.
            'w_goal_completion=0.30&w_feedback_score=0.20&w_tenure_factor=0.20'
        );

        $response->assertStatus(200);
        $weights = $response->json('data.weights');

        // Negative weight must be clamped to 0 (then normalization redistributes)
        $this->assertGreaterThanOrEqual(0.0, $weights['performance_score'],
            'Negative weight values must be clamped to 0.0 to prevent silent ranking corruption');
    }
}
