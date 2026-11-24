<?php

use App\Interfaces\PerformanceReviewRepositoryInterface;
use App\Models\PerformanceGoal;
use App\Models\PerformanceReview;
use App\Models\PerformanceReviewCycle;
use App\Models\PerformanceReviewResponse;
use App\Models\PerformanceReviewSection;
use App\Models\StaffMemberProfile;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\ActivatesLicense;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);
uses(ActivatesLicense::class);

beforeEach(function () {
    $this->activateTestLicense();
    Role::firstOrCreate(['name' => 'staff', 'guard_name' => 'sanctum']);
    Permission::firstOrCreate(['name' => 'review-calibrate', 'guard_name' => 'sanctum']);
    $hrRole = Role::firstOrCreate(['name' => 'hr', 'guard_name' => 'sanctum']);
    $hrRole->givePermissionTo('review-calibrate');
});

function actingAsHRForReadiness(): array
{
    $user = User::factory()->create();
    $staff = StaffMemberProfile::factory()->create(['user_id' => $user->id]);
    $hrRole = Role::findByName('hr', 'sanctum');
    $user->assignRole($hrRole);
    Sanctum::actingAs($user);

    return ['user' => $user, 'staff' => $staff];
}

/** Create a PerformanceGoal with all NOT NULL columns satisfied */
function makeGoal(int $staffMemberId, array $overrides = []): PerformanceGoal
{
    $creatorUser = User::factory()->create();

    return PerformanceGoal::create(array_merge([
        'staff_member_id' => $staffMemberId,
        'title' => 'Test Goal',
        'goal_type' => 'individual',
        'status' => 'in_progress',
        'start_date' => now()->subMonths(3)->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'created_by' => $creatorUser->id,
        'completed_at' => null,
    ], $overrides));
}

function makeReviewWithCycle(array $cycleOverrides = [], string $status = 'pending_calibration'): array
{
    $cycle = PerformanceReviewCycle::factory()->create(array_merge([
        'start_date' => now()->subMonths(3)->toDateString(),
        'end_date' => now()->toDateString(),
    ], $cycleOverrides));

    $employee = StaffMemberProfile::factory()->create();
    $reviewer = StaffMemberProfile::factory()->create();

    $review = PerformanceReview::create([
        'cycle_id' => $cycle->id,
        'staff_member_id' => $employee->id,
        'reviewer_id' => $reviewer->id,
        'status' => $status,
        'manager_assessment_submitted_at' => now(),
    ]);

    return compact('cycle', 'employee', 'reviewer', 'review');
}

// ── Existing behaviour (regression) ─────────────────────────────────────────

it('returns is_ready true when manager assessment submitted and sections rated', function () {
    actingAsHRForReadiness();
    ['review' => $review] = makeReviewWithCycle();

    $section = PerformanceReviewSection::create(['name' => 'S1', 'weight' => 100, 'order' => 1, 'is_active' => true]);
    PerformanceReviewResponse::create(['review_id' => $review->id, 'section_id' => $section->id, 'manager_rating' => 4]);

    $response = $this->getJson("/api/v1/performance/reviews/{$review->id}/validate-readiness");

    $response->assertOk()
        ->assertJsonFragment(['is_ready' => true])
        ->assertJsonPath('data.summary.sections_rated', '1/1');
});

it('returns blocker when manager assessment not submitted', function () {
    actingAsHRForReadiness();
    $data = makeReviewWithCycle([], 'pending_calibration');
    // override: remove submitted_at
    $data['review']->update(['manager_assessment_submitted_at' => null]);

    $response = $this->getJson("/api/v1/performance/reviews/{$data['review']->id}/validate-readiness");

    $response->assertOk()
        ->assertJsonFragment(['is_ready' => false])
        ->assertJsonFragment(['code' => 'MANAGER_ASSESSMENT_MISSING']);
});

it('returns warning when no goals exist', function () {
    actingAsHRForReadiness();
    ['review' => $review] = makeReviewWithCycle();

    $response = $this->getJson("/api/v1/performance/reviews/{$review->id}/validate-readiness");

    $response->assertOk()
        ->assertJsonFragment(['code' => 'NO_GOALS'])
        ->assertJsonPath('data.summary.goals_count', 0);
});

it('returns warning when no positive feedback exists', function () {
    actingAsHRForReadiness();
    ['review' => $review] = makeReviewWithCycle();

    $response = $this->getJson("/api/v1/performance/reviews/{$review->id}/validate-readiness");

    $response->assertOk()
        ->assertJsonFragment(['code' => 'NO_POSITIVE_FEEDBACK'])
        ->assertJsonPath('data.summary.positive_feedback_count', 0);
});

// ── P2-4: New fields in summary ───────────────────────────────────────────

it('summary includes goals_completed count', function () {
    actingAsHRForReadiness();
    ['review' => $review, 'employee' => $employee, 'cycle' => $cycle] = makeReviewWithCycle();

    // 2 completed goals, 1 not completed
    makeGoal($employee->id, ['title' => 'Goal A', 'status' => 'completed', 'completed_at' => now()->subDays(5), 'start_date' => $cycle->start_date, 'due_date' => now()->subDays(1)->toDateString()]);
    makeGoal($employee->id, ['title' => 'Goal B', 'status' => 'completed', 'completed_at' => now()->subDays(2), 'start_date' => $cycle->start_date, 'due_date' => now()->subDays(1)->toDateString()]);
    makeGoal($employee->id, ['title' => 'Goal C', 'status' => 'in_progress', 'completed_at' => null, 'start_date' => $cycle->start_date]);

    $response = $this->getJson("/api/v1/performance/reviews/{$review->id}/validate-readiness");

    $response->assertOk()
        ->assertJsonPath('data.summary.goals_count', 3)
        ->assertJsonPath('data.summary.goals_completed', 2);
});

it('summary includes goals_on_time count', function () {
    actingAsHRForReadiness();
    ['review' => $review, 'employee' => $employee, 'cycle' => $cycle] = makeReviewWithCycle();

    // completed_at > due_date = late
    makeGoal($employee->id, ['title' => 'Late Goal', 'status' => 'completed', 'completed_at' => now()->subDays(2), 'start_date' => $cycle->start_date, 'due_date' => now()->subDays(4)->toDateString()]);
    // completed_at <= due_date = on-time
    makeGoal($employee->id, ['title' => 'On-Time Goal', 'status' => 'completed', 'completed_at' => now()->subDays(5), 'start_date' => $cycle->start_date, 'due_date' => now()->subDays(3)->toDateString()]);

    $response = $this->getJson("/api/v1/performance/reviews/{$review->id}/validate-readiness");

    $response->assertOk()
        ->assertJsonPath('data.summary.goals_completed', 2)
        ->assertJsonPath('data.summary.goals_on_time', 1);
});

it('summary goals_on_time is zero when no completed goals', function () {
    actingAsHRForReadiness();
    ['review' => $review] = makeReviewWithCycle();

    $response = $this->getJson("/api/v1/performance/reviews/{$review->id}/validate-readiness");

    $response->assertOk()
        ->assertJsonPath('data.summary.goals_completed', 0)
        ->assertJsonPath('data.summary.goals_on_time', 0);
});

it('keeps readiness and ranking on-time scores consistent at the due-date boundary', function (?string $completedAt, int $onTime, int $score) {
    actingAsHRForReadiness();
    ['review' => $review, 'employee' => $employee, 'cycle' => $cycle] = makeReviewWithCycle([
        'start_date' => '2026-01-01',
        'end_date' => '2026-03-31',
    ], 'completed');

    $goal = PerformanceGoal::factory()->create([
        'staff_member_id' => $employee->id,
        'linked_review_id' => $review->id,
        'status' => 'completed',
        'start_date' => '2026-01-01',
        'due_date' => '2026-03-31',
        'completed_at' => $completedAt,
    ]);
    $dueDate = $goal->due_date->copy();

    $this->getJson("/api/v1/performance/reviews/{$review->id}/validate-readiness")
        ->assertOk()
        ->assertJsonPath('data.summary.goals_count', 1)
        ->assertJsonPath('data.summary.goals_completed', 1)
        ->assertJsonPath('data.summary.goals_on_time', $onTime);

    $candidates = app(PerformanceReviewRepositoryInterface::class)->getEmployeeScoresForCycle($cycle->id);
    $row = collect($candidates)->firstWhere('staff_member_id', $employee->id);

    $this->assertNotNull($row);
    $this->assertEqualsWithDelta($score, $row['goal_completion'], 0.01);
    $this->assertSame($dueDate->format('Y-m-d H:i:s.u e'), $goal->fresh()->due_date->format('Y-m-d H:i:s.u e'));
})->with([
    'due-date evening' => ['2026-03-31 19:00:00', 1, 100],
    'due-date final second' => ['2026-03-31 23:59:59', 1, 100],
    'next midnight' => ['2026-04-01 00:00:00', 0, 80],
    'missing completion timestamp' => [null, 0, 80],
]);

it('preserves the readiness goal date instance and timezone and ignores a missing due date', function () {
    actingAsHRForReadiness();
    ['review' => $review] = makeReviewWithCycle();
    $dueDate = Carbon::parse('2026-03-31 00:00:00', 'Asia/Jakarta');
    $completedAt = Carbon::parse('2026-03-31 19:00:00', 'Asia/Jakarta');
    $goals = collect([
        (object) ['status' => 'completed', 'due_date' => $dueDate, 'completed_at' => $completedAt],
        (object) ['status' => 'completed', 'due_date' => null, 'completed_at' => $completedAt],
    ]);
    $repository = $this->mock(PerformanceReviewRepositoryInterface::class);
    $repository->shouldReceive('getReviewById')->once()->with($review->id)->andReturn($review);
    $repository->shouldReceive('getActiveSections')->once()->andReturn(collect());
    $repository->shouldReceive('getGoalsForReview')->once()->andReturn($goals);
    $repository->shouldReceive('getPositiveFeedbackCount')->once()->andReturn(0);

    $this->getJson("/api/v1/performance/reviews/{$review->id}/validate-readiness")
        ->assertOk()
        ->assertJsonPath('data.summary.goals_count', 2)
        ->assertJsonPath('data.summary.goals_on_time', 1);

    $this->assertSame('2026-03-31 00:00:00.000000 Asia/Jakarta', $dueDate->format('Y-m-d H:i:s.u e'));
});
