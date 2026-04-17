<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TeamMemberAdded extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected int $teamId,
        protected string $teamName,
        protected string $memberName,
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
        $actorLine = $this->actorName ? 'Updated by: '.$this->actorName : null;

        return (new MailMessage)
            ->subject('Anggota Tim Ditambahkan')
            ->greeting('Halo '.$notifiable->name.',')
            ->line($this->memberName.' ditambahkan ke tim '.$this->teamName.'.')
            ->when($actorLine !== null, function (MailMessage $message) use ($actorLine): MailMessage {
                return $message->line((string) $actorLine);
            })
            ->action('Lihat Tim', url($this->actionUrl))
            ->line('Silakan cek detail tim terbaru.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'category' => 'team',
            'title' => 'Team Member Added',
            'body' => sprintf('%s was added to %s.', $this->memberName, $this->teamName),
            'action_url' => $this->actionUrl,
            'team_id' => $this->teamId,
            'team_name' => $this->teamName,
            'member_name' => $this->memberName,
            'actor_name' => $this->actorName,
        ];
    }
}
