<?php

namespace App\Notifications\Performance;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class GoalProgressUpdated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected int $goalId,
        protected string $goalTitle,
        protected string $employeeName,
        protected int $progressPercentage,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'category' => 'performance',
            'title' => 'Progress Goal Diperbarui',
            'body' => "{$this->employeeName} memperbarui progress goal \"{$this->goalTitle}\" menjadi {$this->progressPercentage}%.",
            'action_url' => "/admin/performance/goals/{$this->goalId}",
            'goal_id' => $this->goalId,
            'goal_title' => $this->goalTitle,
            'employee_name' => $this->employeeName,
            'progress_percentage' => $this->progressPercentage,
        ];
    }
}
