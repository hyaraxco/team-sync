<?php

namespace App\Notifications\Performance;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class ReviewCalibrated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected int $reviewId,
        protected string $cycleName,
        protected float $finalRating,
        protected ?string $outcome = null,
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
        $outcomeText = $this->outcome ? " — {$this->outcome}" : '';

        return [
            'category' => 'performance',
            'title' => 'Review Telah Dikalibrasi',
            'body' => "Review Anda untuk siklus \"{$this->cycleName}\" telah selesai dikalibrasi. Rating akhir: {$this->finalRating}{$outcomeText}.",
            'action_url' => "/performance/reviews/{$this->reviewId}",
            'review_id' => $this->reviewId,
            'cycle_name' => $this->cycleName,
            'final_rating' => $this->finalRating,
            'outcome' => $this->outcome,
        ];
    }
}
