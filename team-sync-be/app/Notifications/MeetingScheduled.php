<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MeetingScheduled extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected int $meetingId,
        protected string $title,
        protected string $scheduledAt,
        protected ?string $location,
        protected string $creatorName,
        protected string $actionUrl,
    ) {
        $this->onQueue('meetings');
    }

    /**
     * @return array<int, string>
     */
    public function via(object $_notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Meeting Scheduled: '.$this->title)
            ->greeting('Hello '.$notifiable->name.',')
            ->line('A new meeting has been scheduled by '.$this->creatorName.'.')
            ->line('Date & time: '.$this->scheduledAt)
            ->when($this->location !== null && trim($this->location) !== '', function (MailMessage $message): MailMessage {
                return $message->line('Location: '.$this->location);
            })
            ->action('View Meeting', url($this->actionUrl));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $_notifiable): array
    {
        return [
            'category' => 'meeting',
            'title' => 'New Meeting Scheduled',
            'body' => sprintf('%s scheduled for %s', $this->title, $this->scheduledAt),
            'action_url' => $this->actionUrl,
            'meeting_id' => $this->meetingId,
        ];
    }
}
