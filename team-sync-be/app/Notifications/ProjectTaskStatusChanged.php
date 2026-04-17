<?php

namespace App\Notifications;

use App\Enums\TaskStatus;
use App\Models\ProjectTask;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProjectTaskStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected int $taskId,
        protected int $projectId,
        protected string $taskName,
        protected ?string $projectName,
        protected string $fromStatus,
        protected string $toStatus,
        protected ?string $reason = null,
        protected ?string $changedByName = null,
    ) {}

    public static function fromProjectTask(
        ProjectTask $task,
        string $fromStatus,
        string $toStatus,
        ?string $reason = null,
        ?string $changedByName = null,
    ): self {
        $task->loadMissing('project');

        return new self(
            taskId: (int) $task->id,
            projectId: (int) $task->project_id,
            taskName: (string) $task->name,
            projectName: $task->project?->name,
            fromStatus: $fromStatus,
            toStatus: $toStatus,
            reason: $reason,
            changedByName: $changedByName,
        );
    }

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
            ->line('Status: '.$this->formatStatus($this->fromStatus).' -> '.$this->formatStatus($this->toStatus))
            ->when($this->reason !== null && trim($this->reason) !== '', function (MailMessage $message): MailMessage {
                return $message->line('Reason: '.trim((string) $this->reason));
            })
            ->when($this->changedByName !== null && trim($this->changedByName) !== '', function (MailMessage $message): MailMessage {
                return $message->line('Updated by: '.trim((string) $this->changedByName));
            })
            ->action('Lihat Detail Task', url('/admin/projects/'.$this->projectId))
            ->line('Silakan cek detail task untuk tindak lanjut.');
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
            'from_status' => $this->fromStatus,
            'to_status' => $this->toStatus,
            'reason' => $this->reason,
            'changed_by' => $this->changedByName,
        ];
    }

    private function title(): string
    {
        return match ($this->toStatus) {
            TaskStatus::REVIEW->value => 'Task Ready for Review',
            TaskStatus::REJECTED->value => 'Task Rejected',
            TaskStatus::DONE->value => 'Task Completed',
            default => 'Task Status Updated',
        };
    }

    private function body(): string
    {
        return match ($this->toStatus) {
            TaskStatus::REVIEW->value => sprintf('%s is waiting for your review.', $this->taskName),
            TaskStatus::REJECTED->value => sprintf('%s was rejected and needs follow-up.', $this->taskName),
            TaskStatus::DONE->value => sprintf('%s has been marked as done.', $this->taskName),
            default => sprintf('%s status changed to %s.', $this->taskName, $this->formatStatus($this->toStatus)),
        };
    }

    private function mailSubject(): string
    {
        return match ($this->toStatus) {
            TaskStatus::REVIEW->value => 'Task Siap Direview',
            TaskStatus::REJECTED->value => 'Task Ditolak',
            TaskStatus::DONE->value => 'Task Selesai',
            default => 'Status Task Diperbarui',
        };
    }

    private function mailBody(): string
    {
        return match ($this->toStatus) {
            TaskStatus::REVIEW->value => 'Ada task yang membutuhkan review kamu.',
            TaskStatus::REJECTED->value => 'Task ditandai rejected dan memerlukan perbaikan.',
            TaskStatus::DONE->value => 'Task telah selesai dikerjakan.',
            default => 'Status task telah diperbarui.',
        };
    }

    private function formatStatus(string $status): string
    {
        return TaskStatus::tryFrom($status)?->label() ?? str_replace('_', ' ', ucfirst($status));
    }
}
