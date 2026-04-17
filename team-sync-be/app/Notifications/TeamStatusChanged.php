<?php

namespace App\Notifications;

use App\Enums\TeamStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TeamStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected int $teamId,
        protected string $teamName,
        protected string $fromStatus,
        protected string $toStatus,
        protected ?string $actorName,
        protected string $actionUrl,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Status Tim Diperbarui')
            ->greeting('Halo '.$notifiable->name.',')
            ->line('Status tim '.$this->teamName.' telah diperbarui.')
            ->line('Status lama: '.$this->formatStatus($this->fromStatus))
            ->line('Status baru: '.$this->formatStatus($this->toStatus))
            ->when($this->actorName !== null && trim($this->actorName) !== '', function (MailMessage $message): MailMessage {
                return $message->line('Updated by: '.trim((string) $this->actorName));
            })
            ->action('Lihat Tim', url($this->actionUrl))
            ->line('Silakan cek detail tim untuk dampak perubahan status.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'category' => 'team',
            'title' => 'Team Status Updated',
            'body' => sprintf(
                '%s status changed from %s to %s.',
                $this->teamName,
                $this->formatStatus($this->fromStatus),
                $this->formatStatus($this->toStatus)
            ),
            'action_url' => $this->actionUrl,
            'team_id' => $this->teamId,
            'team_name' => $this->teamName,
            'from_status' => $this->fromStatus,
            'to_status' => $this->toStatus,
            'actor_name' => $this->actorName,
        ];
    }

    private function formatStatus(string $status): string
    {
        return TeamStatus::tryFrom($status)?->label() ?? str_replace('_', ' ', ucfirst($status));
    }
}
