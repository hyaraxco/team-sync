<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MeetingReminder extends Notification implements ShouldQueue
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
            ->subject('Reminder: '.$this->title.' starts in 15 minutes')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('This is a reminder from '.$this->creatorName.'.')
            ->line($this->title.' starts in 15 minutes.')
            ->line('Scheduled at: '.$this->scheduledAt)
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
            'title' => 'Meeting Reminder',
            'body' => sprintf('%s starts in 15 minutes', $this->title),
            'action_url' => $this->actionUrl,
            'meeting_id' => $this->meetingId,
        ];
    }
}
