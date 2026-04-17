<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProjectLifecycleUpdated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected int $projectId,
        protected string $projectName,
        protected string $eventType,
        protected string $currentStatus,
        protected ?string $previousStatus = null,
        protected ?string $actorName = null,
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
            ->subject($this->mailSubject())
            ->greeting('Halo '.$notifiable->name.',')
            ->line($this->mailBody())
            ->line('Project: '.$this->projectName)
            ->line('Current status: '.$this->formatStatus($this->currentStatus))
            ->when($this->previousStatus !== null, function (MailMessage $message): MailMessage {
                return $message->line('Previous status: '.$this->formatStatus((string) $this->previousStatus));
            })
            ->when($this->actorName !== null && trim($this->actorName) !== '', function (MailMessage $message): MailMessage {
                return $message->line('Updated by: '.trim((string) $this->actorName));
            })
            ->action('Lihat Project', url('/admin/projects/'.$this->projectId))
            ->line('Silakan cek detail project untuk update terbaru.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'category' => 'project',
            'title' => $this->title(),
            'body' => $this->body(),
            'action_url' => '/admin/projects/'.$this->projectId,
            'project_id' => $this->projectId,
            'project_name' => $this->projectName,
            'event_type' => $this->eventType,
            'current_status' => $this->currentStatus,
            'previous_status' => $this->previousStatus,
            'actor_name' => $this->actorName,
        ];
    }

    private function title(): string
    {
        return match ($this->eventType) {
            'created' => 'New Project Assigned',
            'status_changed' => 'Project Status Updated',
            default => 'Project Updated',
        };
    }

    private function body(): string
    {
        return match ($this->eventType) {
            'created' => sprintf('%s has been created and assigned to your scope.', $this->projectName),
            'status_changed' => sprintf('%s status changed to %s.', $this->projectName, $this->formatStatus($this->currentStatus)),
            default => sprintf('%s has new updates.', $this->projectName),
        };
    }

    private function mailSubject(): string
    {
        return match ($this->eventType) {
            'created' => 'Project Baru Ditugaskan',
            'status_changed' => 'Status Project Diperbarui',
            default => 'Project Diperbarui',
        };
    }

    private function mailBody(): string
    {
        return match ($this->eventType) {
            'created' => 'Ada project baru yang relevan dengan tim kamu.',
            'status_changed' => 'Status project yang kamu ikuti telah berubah.',
            default => 'Ada pembaruan project terbaru.',
        };
    }

    private function formatStatus(string $status): string
    {
        return str_replace('_', ' ', ucfirst($status));
    }
}
