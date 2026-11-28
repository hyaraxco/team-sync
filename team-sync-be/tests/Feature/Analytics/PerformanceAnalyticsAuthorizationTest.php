<?php

namespace Tests\Feature\Analytics;

use App\Models\PerformanceFeedback;
use App\Models\PerformanceGoal;
use App\Models\PerformanceReview;
use App\Models\PerformanceReviewCycle;
use App\Models\StaffMemberProfile;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\ActivatesLicense;
use Tests\TestCase;

class PerformanceAnalyticsAuthorizationTest extends TestCase
{
    use ActivatesLicense, RefreshDatabase;

    private User $manager;

    private Team $ownTeam;

    private Team $otherTeam;

    private StaffMemberProfile $ownMember;

    private StaffMemberProfile $otherMember;

    private StaffMemberProfile $formerMember;

    private PerformanceReviewCycle $cycle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 23)->startOfDay());
        $this->seed([PermissionSeeder::class, RolePermissionSeeder::class]);
        $this->activateTestLicense();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->manager = User::factory()->create();
        $this->manager->syncRoles(['manager']);
        $this->ownTeam = Team::factory()->active()->create(['team_lead_id' => $this->manager->id]);
        $this->otherTeam = Team::factory()->active()->create();
        $this->ownMember = StaffMemberProfile::factory()->create();
        $secondMember = StaffMemberProfile::factory()->create();
        $this->otherMember = StaffMemberProfile::factory()->create();
        $this->formerMember = StaffMemberProfile::factory()->create();
        $this->cycle = PerformanceReviewCycle::factory()->active()->create();

        foreach ([$this->ownMember, $secondMember, $this->otherMember, $this->formerMember] as $index => $member) {
            TeamMember::factory()->create([
                'team_id' => $index === 2 ? $this->otherTeam->id : $this->ownTeam->id,
                'staff_member_id' => $member->id,
                'joined_at' => '2026-10-01 00:00:00',
                'left_at' => $index === 3 ? '2026-11-01 00:00:00' : null,
            ]);
            PerformanceReview::create([
                'cycle_id' => $this->cycle->id,
                'staff_member_id' => $member->id,
                'reviewer_id' => $this->ownMember->id,
                'status' => 'completed',
                'final_rating' => [3, 5, 1, 2][$index],
            ]);
            PerformanceGoal::create([
                'staff_member_id' => $member->id,
                'title' => 'Scoped goal '.$index,
                'goal_type' => 'kpi',
                'category' => 'delivery',
                'start_date' => '2026-11-01',
                'due_date' => '2026-11-30',
                'status' => $index === 1 ? 'not_started' : 'completed',
                'completion_percentage' => $index === 1 ? 0 : 100,
                'created_by' => $this->manager->id,
            ]);
            PerformanceFeedback::create([
                'staff_member_id' => $member->id,
                // Outbound feedback by a team member must not expose other teams' metrics.
                'given_by' => $index === 2 ? $this->ownMember->id : $this->otherMember->id,
                'feedback_type' => ['positive', 'constructive', 'general', 'general'][$index],
                'content' => 'Scoped feedback '.$index,
                'is_private' => false,
            ]);
        }
    }

    public function test_manager_can_read_only_led_team_performance_and_active_member_metrics(): void
    {
        Sanctum::actingAs($this->manager);
        $teamFilter = '?team_id='.$this->ownTeam->id;

        $this->getJson('/api/v1/analytics/performance/team-summary'.$teamFilter.'&cycle_id='.$this->cycle->id)
            ->assertOk()->assertJsonPath('data.team_id', $this->ownTeam->id)
            ->assertJsonPath('data.total_reviews', 2)->assertJsonCount(2, 'data.reviews')
            ->assertJsonPath('data.average_rating', 4);
        $this->getJson('/api/v1/analytics/performance/goal-completion-rate'.$teamFilter)
            ->assertOk()->assertJsonPath('data.total_goals', 2)
            ->assertJsonPath('data.completed_goals', 1)->assertJsonPath('data.completion_rate', 50);
        $this->getJson('/api/v1/analytics/performance/feedback-metrics'.$teamFilter)
            ->assertOk()->assertJsonPath('data.total_feedback', 2)
            ->assertJsonPath('data.average_per_employee', 1)
            ->assertJsonPath('data.by_type', ['positive' => 1, 'constructive' => 1]);

        foreach (['goal-completion-rate' => 'total_goals', 'feedback-metrics' => 'total_feedback'] as $endpoint => $count) {
            $this->getJson('/api/v1/analytics/performance/'.$endpoint.$teamFilter.'&staff_member_id='.$this->ownMember->id)
                ->assertOk()->assertJsonPath('data.'.$count, 1);
            foreach ([$this->otherMember, $this->formerMember] as $forbiddenMember) {
                $this->getJson('/api/v1/analytics/performance/'.$endpoint.$teamFilter.'&staff_member_id='.$forbiddenMember->id)
                    ->assertForbidden();
            }
        }

        foreach (['team-summary', 'goal-completion-rate', 'feedback-metrics'] as $endpoint) {
            $this->getJson('/api/v1/analytics/performance/'.$endpoint.'?team_id='.$this->otherTeam->id)
                ->assertForbidden();
        }

        $secondLedTeam = Team::factory()->active()->create(['team_lead_id' => $this->manager->id]);
        $this->getJson('/api/v1/analytics/performance/team-summary?team_id='.$secondLedTeam->id)
            ->assertOk()->assertJsonPath('data.team_id', $secondLedTeam->id)->assertJsonPath('data.total_reviews', 0);
    }

    public function test_manager_cannot_omit_or_coerce_team_scope_or_read_company_metrics(): void
    {
        Sanctum::actingAs($this->manager);

        foreach (['team-summary', 'goal-completion-rate', 'feedback-metrics'] as $endpoint) {
            foreach (['', '?staff_member_id='.$this->ownMember->id, '?team_id=0', '?team_id[]=1', '?team_id=invalid'] as $query) {
                $this->getJson('/api/v1/analytics/performance/'.$endpoint.$query)
                    ->assertUnprocessable()->assertJsonValidationErrors('team_id');
            }
        }

        foreach (['goal-completion-rate', 'feedback-metrics'] as $endpoint) {
            $this->getJson('/api/v1/analytics/performance/'.$endpoint.'?team_id='.$this->ownTeam->id.'&staff_member_id=invalid')
                ->assertUnprocessable()->assertJsonValidationErrors('staff_member_id');
        }

        foreach (['company-summary', 'rating-distribution'] as $endpoint) {
            $this->getJson('/api/v1/analytics/performance/'.$endpoint)->assertForbidden();
            $this->getJson('/api/v1/analytics/performance/'.$endpoint.'?team_id='.$this->ownTeam->id)->assertForbidden();
        }
    }

    public function test_hr_and_superadmin_retain_global_and_unrelated_team_access_even_with_manager_role(): void
    {
        foreach (['hr', 'superadmin'] as $role) {
            $reader = User::factory()->create();
            $reader->syncRoles([$role, 'manager']);
            Sanctum::actingAs($reader);

            $this->getJson('/api/v1/analytics/performance/team-summary?team_id='.$this->otherTeam->id.'&cycle_id='.$this->cycle->id)
                ->assertOk()->assertJsonPath('data.total_reviews', 1)
                ->assertJsonPath('data.reviews.0.staff_member_id', $this->otherMember->id);
            $this->getJson('/api/v1/analytics/performance/company-summary?cycle_id='.$this->cycle->id)
                ->assertOk()->assertJsonPath('data.total_reviews', 4);
            $this->getJson('/api/v1/analytics/performance/rating-distribution?cycle_id='.$this->cycle->id)
                ->assertOk()->assertJsonPath('data.total', 4);
            $this->getJson('/api/v1/analytics/performance/goal-completion-rate')
                ->assertOk()->assertJsonPath('data.total_goals', 4);
            $this->getJson('/api/v1/analytics/performance/feedback-metrics')
                ->assertOk()->assertJsonPath('data.total_feedback', 4);
            $this->getJson('/api/v1/analytics/performance/goal-completion-rate?staff_member_id='.$this->otherMember->id)
                ->assertOk()->assertJsonPath('data.total_goals', 1);
            $this->getJson('/api/v1/analytics/performance/feedback-metrics?team_id='.$this->otherTeam->id)
                ->assertOk()->assertJsonPath('data.total_feedback', 1);
        }
    }

    public function test_team_and_company_access_follow_permissions_not_role_names(): void
    {
        $this->manager->syncRoles(['staff']);
        $this->manager->givePermissionTo('analytics-performance-view');
        Sanctum::actingAs($this->manager);

        $this->getJson('/api/v1/analytics/performance/team-summary?team_id='.$this->ownTeam->id)
            ->assertOk()->assertJsonPath('data.total_reviews', 2);
        $this->getJson('/api/v1/analytics/performance/company-summary')->assertForbidden();

        $this->manager->givePermissionTo('analytics-hr-view');
        $this->getJson('/api/v1/analytics/performance/company-summary')
            ->assertOk()->assertJsonPath('data.total_reviews', 4);
        $this->getJson('/api/v1/analytics/performance/goal-completion-rate')
            ->assertOk()->assertJsonPath('data.total_goals', 4);
    }
}
