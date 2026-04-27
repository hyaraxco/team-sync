<?php

namespace App\Notifications\Performance;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ReviewSubmittedForCalibration extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected int $reviewId,
        protected string $employeeName,
        protected string $managerName,
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
            'title' => 'Review Siap Dikalibrasi',
            'body' => "Review untuk {$this->employeeName} oleh {$this->managerName} siap dikalibrasi (siklus \"{$this->cycleName}\").",
            'action_url' => '/admin/performance/reviews/pending-calibration',
            'review_id' => $this->reviewId,
            'employee_name' => $this->employeeName,
            'manager_name' => $this->managerName,
            'cycle_name' => $this->cycleName,
        ];
    }
}
