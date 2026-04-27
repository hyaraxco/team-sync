<?php

namespace App\Notifications\Performance;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class FeedbackReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected int $feedbackId,
        protected string $giverName,
        protected string $feedbackType,
        protected string $contentPreview,
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
        $typeLabel = $this->feedbackType === 'positive' ? 'Positif' : ($this->feedbackType === 'constructive' ? 'Konstruktif' : ucfirst($this->feedbackType));

        return [
            'category' => 'performance',
            'title' => "Feedback {$typeLabel} Diterima",
            'body' => "{$this->giverName} memberikan feedback {$typeLabel} untuk Anda: \"{$this->contentPreview}\"",
            'action_url' => '/performance/feedback',
            'feedback_id' => $this->feedbackId,
            'giver_name' => $this->giverName,
            'feedback_type' => $this->feedbackType,
        ];
    }
}
