<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TeamLeadChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected int $teamId,
        protected string $teamName,
        protected ?string $oldLeaderName,
        protected ?string $newLeaderName,
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
        $oldLead = $this->oldLeaderName ?: '-';
        $newLead = $this->newLeaderName ?: '-';

        return (new MailMessage)
            ->subject('Perubahan Team Lead')
            ->greeting('Halo '.$notifiable->name.',')
            ->line('Team lead untuk '.$this->teamName.' telah diperbarui.')
            ->line('Lead sebelumnya: '.$oldLead)
            ->line('Lead baru: '.$newLead)
            ->when($this->actorName !== null && trim($this->actorName) !== '', function (MailMessage $message): MailMessage {
                return $message->line('Updated by: '.trim((string) $this->actorName));
            })
            ->action('Lihat Tim', url($this->actionUrl))
            ->line('Silakan cek detail tim untuk informasi terbaru.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'category' => 'team',
            'title' => 'Team Lead Changed',
            'body' => sprintf(
                'Team lead for %s changed from %s to %s.',
                $this->teamName,
                $this->oldLeaderName ?: '-',
                $this->newLeaderName ?: '-'
            ),
            'action_url' => $this->actionUrl,
            'team_id' => $this->teamId,
            'team_name' => $this->teamName,
            'old_leader_name' => $this->oldLeaderName,
            'new_leader_name' => $this->newLeaderName,
            'actor_name' => $this->actorName,
        ];
    }
}
