<?php

namespace App\Notifications\Performance;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class GoalProgressUpdated extends Notification
{
    use Queueable;

    public function __construct(
        public int $goalId,
        public string $goalTitle,
        public string $employeeName,
        public int $progressPercentage,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'category' => 'performance',
            'title' => 'Goal Progress Updated',
            'body' => "{$this->employeeName} updated progress on goal \"{$this->goalTitle}\" to {$this->progressPercentage}%.",
            'action_url' => "/admin/performance/goals/{$this->goalId}",
            'goal_id' => $this->goalId,
        ];
    }
}
