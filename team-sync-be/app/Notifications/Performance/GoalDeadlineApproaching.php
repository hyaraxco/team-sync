<?php

namespace App\Notifications\Performance;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class GoalDeadlineApproaching extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected int $goalId,
        protected string $goalTitle,
        protected string $dueDate,
        protected int $daysRemaining,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'category' => 'performance',
            'title' => 'Deadline Goal Mendekat',
            'body' => "Goal \"{$this->goalTitle}\" akan jatuh tempo dalam {$this->daysRemaining} hari ({$this->dueDate}).",
            'action_url' => "/admin/performance/goals/{$this->goalId}",
            'goal_id' => $this->goalId,
            'goal_title' => $this->goalTitle,
            'due_date' => $this->dueDate,
            'days_remaining' => $this->daysRemaining,
        ];
    }
}
