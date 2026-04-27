<?php

namespace App\Console\Commands;

use App\Models\PerformanceGoal;
use App\Notifications\Performance\GoalDeadlineApproaching;
use Carbon\Carbon;
use Illuminate\Console\Command;

class NotifyGoalDeadlines extends Command
{
    protected $signature = 'performance:notify-goal-deadlines
                            {--days=7 : Number of days before deadline to notify}';

    protected $description = 'Send notifications for performance goals approaching their deadline';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $targetDate = Carbon::today()->addDays($days);

        $goals = PerformanceGoal::with('staffMember.user')
            ->where('status', 'in_progress')
            ->whereDate('due_date', '<=', $targetDate)
            ->whereDate('due_date', '>=', Carbon::today())
            ->get();

        $notified = 0;

        foreach ($goals as $goal) {
            $user = $goal->staffMember?->user;
            if (! $user) {
                continue;
            }

            $daysRemaining = Carbon::today()->diffInDays($goal->due_date);

            $user->notify(new GoalDeadlineApproaching(
                goalId: $goal->id,
                goalTitle: $goal->title,
                dueDate: $goal->due_date->toDateString(),
                daysRemaining: $daysRemaining,
            ));

            $notified++;
        }

        $this->info("Sent {$notified} goal deadline notification(s) for goals due within {$days} days.");

        return self::SUCCESS;
    }
}
