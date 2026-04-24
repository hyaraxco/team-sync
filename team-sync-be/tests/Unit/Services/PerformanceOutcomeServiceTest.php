<?php

namespace Tests\Unit\Services;

use App\Models\PerformanceOutcomeRule;
use App\Models\PerformanceReview;
use App\Models\PerformanceReviewCycle;
use App\Models\StaffMemberProfile;
use App\Models\User;
use App\Services\Performance\PerformanceOutcomeService;
use Database\Seeders\PerformanceOutcomeRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerformanceOutcomeServiceTest extends TestCase
{
    use RefreshDatabase;

    private PerformanceOutcomeService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PerformanceOutcomeService;
        $this->seed(PerformanceOutcomeRuleSeeder::class);
    }

    private function createReview(array $overrides = []): PerformanceReview
    {
        $user = User::factory()->create();
        $profile = StaffMemberProfile::factory()->create(['user_id' => $user->id]);
        $cycle = PerformanceReviewCycle::factory()->create(['created_by' => $user->id]);

        return PerformanceReview::create(array_merge([
            'cycle_id' => $cycle->id,
            'staff_member_id' => $profile->id,
            'reviewer_id' => $profile->id,
            'status' => 'completed',
            'final_rating' => 4.00,
        ], $overrides));
    }

    public function test_applies_outstanding_outcome(): void
    {
        $review = $this->createReview(['final_rating' => 4.75]);

        $result = $this->service->applyOutcome($review);

        $this->assertNotNull($result->outcome_rule_id);
        $this->assertEquals(3.00, (float) $result->bonus_months);
        $this->assertEquals(10.00, (float) $result->salary_increase_pct);
        $this->assertTrue($result->promotion_eligible);
        $this->assertFalse($result->pip_required);
        $this->assertNotNull($result->outcome_applied_at);
    }

    public function test_applies_exceeds_expectations_outcome(): void
    {
        $review = $this->createReview(['final_rating' => 3.80]);

        $result = $this->service->applyOutcome($review);

        $this->assertEquals(2.00, (float) $result->bonus_months);
        $this->assertEquals(7.00, (float) $result->salary_increase_pct);
        $this->assertFalse($result->promotion_eligible);
    }

    public function test_applies_meets_expectations_outcome(): void
    {
        $review = $this->createReview(['final_rating' => 3.00]);

        $result = $this->service->applyOutcome($review);

        $this->assertEquals(1.00, (float) $result->bonus_months);
        $this->assertEquals(4.00, (float) $result->salary_increase_pct);
    }

    public function test_applies_pip_for_needs_improvement(): void
    {
        $review = $this->createReview(['final_rating' => 2.00]);

        $result = $this->service->applyOutcome($review);

        $this->assertEquals(0.00, (float) $result->bonus_months);
        $this->assertEquals(0.00, (float) $result->salary_increase_pct);
        $this->assertFalse($result->promotion_eligible);
        $this->assertTrue($result->pip_required);
    }

    public function test_applies_pip_for_unsatisfactory(): void
    {
        $review = $this->createReview(['final_rating' => 1.20]);

        $result = $this->service->applyOutcome($review);

        $this->assertTrue($result->pip_required);
        $this->assertEquals(0.00, (float) $result->bonus_months);
    }

    public function test_returns_unchanged_when_no_final_rating(): void
    {
        $review = $this->createReview(['final_rating' => null]);

        $result = $this->service->applyOutcome($review);

        $this->assertNull($result->outcome_rule_id);
        $this->assertNull($result->outcome_applied_at);
    }

    public function test_returns_unchanged_when_no_matching_rule(): void
    {
        PerformanceOutcomeRule::query()->update(['is_active' => false]);

        $review = $this->createReview(['final_rating' => 4.00]);

        $result = $this->service->applyOutcome($review);

        $this->assertNull($result->outcome_rule_id);
    }
}
