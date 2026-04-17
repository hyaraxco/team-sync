<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProjectTaskCollaborationUpdated extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected int $taskId,
        protected int $projectId,
        protected string $taskName,
        protected ?string $projectName,
        protected string $eventType,
        protected ?string $actorName = null,
        protected ?string $commentSnippet = null,
        protected ?string $fileName = null,
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
            ->line('Task: '.$this->taskName)
            ->line('Project: '.($this->projectName ?: ('Project #'.$this->projectId)))
            ->when($this->actorName !== null && trim($this->actorName) !== '', function (MailMessage $message): MailMessage {
                return $message->line('Updated by: '.trim((string) $this->actorName));
            })
            ->when($this->eventType === 'comment_added' && $this->commentSnippet !== null, function (MailMessage $message): MailMessage {
                return $message->line('Comment: '.(string) $this->commentSnippet);
            })
            ->when($this->eventType === 'attachment_added' && $this->fileName !== null, function (MailMessage $message): MailMessage {
                return $message->line('Attachment: '.(string) $this->fileName);
            })
            ->action('Lihat Detail Task', url('/admin/projects/'.$this->projectId))
            ->line('Silakan tinjau update kolaborasi terbaru.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'category' => 'task',
            'title' => $this->title(),
            'body' => $this->body(),
            'action_url' => '/admin/projects/'.$this->projectId,
            'task_id' => $this->taskId,
            'project_id' => $this->projectId,
            'task_name' => $this->taskName,
            'project_name' => $this->projectName,
            'event_type' => $this->eventType,
            'actor_name' => $this->actorName,
            'comment_snippet' => $this->commentSnippet,
            'file_name' => $this->fileName,
        ];
    }

    private function title(): string
    {
        return match ($this->eventType) {
            'comment_added' => 'New Task Comment',
            'attachment_added' => 'New Task Attachment',
            default => 'Task Collaboration Updated',
        };
    }

    private function body(): string
    {
        return match ($this->eventType) {
            'comment_added' => sprintf('A new comment was added on %s.', $this->taskName),
            'attachment_added' => sprintf('A new attachment was uploaded to %s.', $this->taskName),
            default => sprintf('%s has new collaboration updates.', $this->taskName),
        };
    }

    private function mailSubject(): string
    {
        return match ($this->eventType) {
            'comment_added' => 'Komentar Baru di Task',
            'attachment_added' => 'Lampiran Baru di Task',
            default => 'Update Kolaborasi Task',
        };
    }

    private function mailBody(): string
    {
        return match ($this->eventType) {
            'comment_added' => 'Ada komentar baru pada task yang kamu ikuti.',
            'attachment_added' => 'Ada lampiran baru pada task yang kamu ikuti.',
            default => 'Ada update kolaborasi pada task.',
        };
    }
}
