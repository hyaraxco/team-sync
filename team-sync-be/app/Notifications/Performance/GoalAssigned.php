<?php

namespace App\Notifications\Performance;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class GoalAssigned extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected int $goalId,
        protected string $goalTitle,
        protected string $assignedByName,
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
            'title' => 'Goal Baru Ditugaskan',
            'body' => "{$this->assignedByName} menugaskan goal baru: \"{$this->goalTitle}\"",
            'action_url' => "/admin/performance/goals/{$this->goalId}",
            'goal_id' => $this->goalId,
            'goal_title' => $this->goalTitle,
            'assigned_by' => $this->assignedByName,
        ];
    }
}
