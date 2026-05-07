<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TeamPulseNudge extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly int $staffMemberId,
        private readonly string $staffMemberName,
        private readonly string $actorName,
        private readonly string $message,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Manager check-in from Team Pulse')
            ->greeting('Halo '.$notifiable->name.',')
            ->line($this->actorName.' baru saja mengirimkan sapaan dari Team Pulse.')
            ->line($this->message)
            ->action('Buka Notifikasi', url('/admin/notifications'))
            ->line('Balas atau tindak lanjuti jika ada blocker yang perlu dibantu.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'category' => 'team_pulse_nudge',
            'title' => 'Tanya Kabar dari Manager',
            'body' => $this->message,
            'action_url' => '/admin/notifications',
            'staff_member_id' => $this->staffMemberId,
            'staff_member_name' => $this->staffMemberName,
            'actor_name' => $this->actorName,
            'message' => $this->message,
        ];
    }
}
