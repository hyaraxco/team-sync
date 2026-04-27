<?php

namespace App\Notifications\Performance;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ReviewCycleStarted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected int $cycleId,
        protected string $cycleName,
        protected string $startDate,
        protected string $endDate,
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
            'title' => 'Siklus Review Dimulai',
            'body' => "Siklus review \"{$this->cycleName}\" telah dimulai (periode {$this->startDate} s/d {$this->endDate}).",
            'action_url' => '/admin/performance/reviews/my-reviews',
            'cycle_id' => $this->cycleId,
            'cycle_name' => $this->cycleName,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
        ];
    }
}
