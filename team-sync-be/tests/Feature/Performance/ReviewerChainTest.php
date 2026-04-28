<?php

use App\Models\PerformanceReview;
use App\Models\PerformanceReviewCycle;
use App\Models\PerformanceReviewSection;
use App\Models\StaffMemberProfile;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Services\Performance\ReviewerResolverService;
use Database\Seeders\PerformanceReviewSectionSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\ReviewerRuleSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RolePermissionSeeder::class);
    $this->seed(ReviewerRuleSeeder::class);
    $this->seed(PerformanceReviewSectionSeeder::class);
});

function createUserWithRole(string $role): array
{
    $user = User::factory()->create();
    $user->syncRoles([$role]);

    $staffMember = StaffMemberProfile::factory()->create([
        'user_id' => $user->id,
    ]);

    return [$user, $staffMember];
}

function createPendingReview(StaffMemberProfile $staffMember, ?StaffMemberProfile $reviewer = null, string $status = 'pending_manager'): PerformanceReview
{
    $cycle = PerformanceReviewCycle::factory()->active()->create();

    return PerformanceReview::create([
        'cycle_id' => $cycle->id,
        'staff_member_id' => $staffMember->id,
        'reviewer_id' => $reviewer?->id,
        'status' => $status,
        'self_assessment_submitted_at' => now(),
    ]);
}

it('resolves staff reviewer to manager', function () {
    [, $manager] = createUserWithRole('manager');
    [, $staff] = createUserWithRole('staff');

    $team = Team::factory()->create([
        'team_lead_id' => $manager->user_id,
    ]);

    TeamMember::factory()->forTeam($team)->forEmployee($manager)->create();
    TeamMember::factory()->forTeam($team)->forEmployee($staff)->create();

    $resolvedReviewer = app(ReviewerResolverService::class)->resolve($staff);

    expect($resolvedReviewer)->not->toBeNull()
        ->and($resolvedReviewer->id)->toBe($manager->id);
});

it('resolves manager reviewer to hr', function () {
    [, $manager] = createUserWithRole('manager');
    [, $hr] = createUserWithRole('hr');

    $resolvedReviewer = app(ReviewerResolverService::class)->resolve($manager);

    expect($resolvedReviewer)->not->toBeNull()
        ->and($resolvedReviewer->id)->toBe($hr->id);
});

it('resolves finance reviewer to hr', function () {
    [, $finance] = createUserWithRole('finance');
    [, $hr] = createUserWithRole('hr');

    $resolvedReviewer = app(ReviewerResolverService::class)->resolve($finance);

    expect($resolvedReviewer)->not->toBeNull()
        ->and($resolvedReviewer->id)->toBe($hr->id);
});

it('resolves hr reviewer to another hr excluding self', function () {
    [, $hrOne] = createUserWithRole('hr');
    [, $hrTwo] = createUserWithRole('hr');

    $resolvedReviewer = app(ReviewerResolverService::class)->resolve($hrOne);

    expect($resolvedReviewer)->not->toBeNull()
        ->and($resolvedReviewer->id)->toBe($hrTwo->id)
        ->and($resolvedReviewer->id)->not->toBe($hrOne->id);
});

it('returns null when only one hr exists for hr-to-hr rule', function () {
    [, $hr] = createUserWithRole('hr');

    $resolvedReviewer = app(ReviewerResolverService::class)->resolve($hr);

    expect($resolvedReviewer)->toBeNull();
});

it('prevents self-assignment via assignReviewer endpoint', function () {
    [$hrUser] = createUserWithRole('hr');
    [, $staff] = createUserWithRole('staff');

    $review = createPendingReview($staff, null, 'pending_self');

    $response = $this->actingAs($hrUser)->putJson(
        "/api/v1/performance/reviews/{$review->id}/assign-reviewer",
        ['reviewer_id' => $staff->id]
    );

    $response->assertStatus(422);
});

it('prevents non-reviewer from submitting manager assessment', function () {
    [, $assignedManager] = createUserWithRole('manager');
    [$otherManagerUser] = createUserWithRole('manager');
    [, $staff] = createUserWithRole('staff');

    $review = createPendingReview($staff, $assignedManager, 'pending_manager');
    $section = PerformanceReviewSection::query()->firstOrFail();

    $response = $this->actingAs($otherManagerUser)->postJson(
        "/api/v1/performance/reviews/{$review->id}/manager-assessment",
        [
            'responses' => [
                [
                    'section_id' => $section->id,
                    'rating' => 4,
                    'comments' => 'Not my review to submit.',
                ],
            ],
        ]
    );

    $response->assertForbidden();
});
