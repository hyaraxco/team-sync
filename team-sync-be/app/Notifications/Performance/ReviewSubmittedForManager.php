<?php

namespace App\Notifications\Performance;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ReviewSubmittedForManager extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected int $reviewId,
        protected string $employeeName,
        protected string $cycleName,
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
            'title' => 'Self-Assessment Diterima',
            'body' => "{$this->employeeName} telah mengirimkan self-assessment untuk siklus \"{$this->cycleName}\". Silakan lakukan penilaian.",
            'action_url' => "/admin/performance/reviews/{$this->reviewId}",
            'review_id' => $this->reviewId,
            'employee_name' => $this->employeeName,
            'cycle_name' => $this->cycleName,
        ];
    }
}
