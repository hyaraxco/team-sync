<?php

namespace Tests\Feature\Performance;

use App\Models\PerformanceGoal;
use App\Models\StaffMemberProfile;
use App\Models\User;
use App\Notifications\Performance\GoalDeadlineApproaching;
use Carbon\Carbon;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class NotifyGoalDeadlinesCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function createGoalWithOwner(string $status, string $dueDate): array
    {
        $user = User::factory()->create();
        $profile = StaffMemberProfile::factory()->create(['user_id' => $user->id]);

        $goal = PerformanceGoal::create([
            'staff_member_id' => $profile->id,
            'title' => 'Test Goal '.uniqid(),
            'goal_type' => 'okr',
            'status' => $status,
            'start_date' => Carbon::today()->subMonth()->toDateString(),
            'due_date' => $dueDate,
            'created_by' => $profile->id,
        ]);

        return [$user, $goal];
    }

    public function test_command_sends_notification_for_goals_due_within_7_days(): void
    {
        Notification::fake();
        Carbon::setTestNow('2026-05-01 09:00:00');

        [$user, $goal] = $this->createGoalWithOwner('in_progress', '2026-05-05');

        $this->artisan('performance:notify-goal-deadlines')
            ->assertSuccessful()
            ->expectsOutputToContain('1 goal deadline notification');

        Notification::assertSentTo($user, GoalDeadlineApproaching::class);
    }

    public function test_command_does_not_notify_for_completed_goals(): void
    {
        Notification::fake();
        Carbon::setTestNow('2026-05-01 09:00:00');

        [$user, $goal] = $this->createGoalWithOwner('completed', '2026-05-05');

        $this->artisan('performance:notify-goal-deadlines')
            ->assertSuccessful()
            ->expectsOutputToContain('0 goal deadline notification');

        Notification::assertNotSentTo($user, GoalDeadlineApproaching::class);
    }

    public function test_command_does_not_notify_for_goals_due_beyond_threshold(): void
    {
        Notification::fake();
        Carbon::setTestNow('2026-05-01 09:00:00');

        [$user, $goal] = $this->createGoalWithOwner('in_progress', '2026-06-01');

        $this->artisan('performance:notify-goal-deadlines')
            ->assertSuccessful()
            ->expectsOutputToContain('0 goal deadline notification');

        Notification::assertNotSentTo($user, GoalDeadlineApproaching::class);
    }

    public function test_command_does_not_notify_for_overdue_goals(): void
    {
        Notification::fake();
        Carbon::setTestNow('2026-05-01 09:00:00');

        [$user, $goal] = $this->createGoalWithOwner('in_progress', '2026-04-25');

        $this->artisan('performance:notify-goal-deadlines')
            ->assertSuccessful()
            ->expectsOutputToContain('0 goal deadline notification');

        Notification::assertNotSentTo($user, GoalDeadlineApproaching::class);
    }

    public function test_command_accepts_custom_days_option(): void
    {
        Notification::fake();
        Carbon::setTestNow('2026-05-01 09:00:00');

        // Goal due in 10 days — should be caught with --days=14 but not default 7
        [$user, $goal] = $this->createGoalWithOwner('in_progress', '2026-05-11');

        $this->artisan('performance:notify-goal-deadlines --days=14')
            ->assertSuccessful()
            ->expectsOutputToContain('1 goal deadline notification');

        Notification::assertSentTo($user, GoalDeadlineApproaching::class);
    }
}
