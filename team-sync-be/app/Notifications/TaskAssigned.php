<?php

namespace App\Notifications;

use App\Models\ProjectTask;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TaskAssigned extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected int $taskId,
        protected int $projectId,
        protected string $taskName,
        protected ?string $projectName,
        protected ?string $assignedByName = null,
        protected bool $isReassignment = false,
    ) {}

    public static function fromProjectTask(
        ProjectTask $task,
        ?string $assignedByName = null,
        bool $isReassignment = false,
    ): self {
        $task->loadMissing('project');

        return new self(
            taskId: (int) $task->id,
            projectId: (int) $task->project_id,
            taskName: (string) $task->name,
            projectName: $task->project?->name,
            assignedByName: $assignedByName,
            isReassignment: $isReassignment,
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
        $subject = $this->isReassignment ? 'Penugasan Task Diperbarui' : 'Task Baru Ditugaskan';
        $headline = $this->isReassignment ? 'Task kamu telah dialihkan.' : 'Kamu mendapatkan task baru.';
        $projectLabel = $this->projectName ?: 'Project #'.$this->projectId;

        $message = (new MailMessage)
            ->subject($subject)
            ->greeting('Halo '.$notifiable->name.',')
            ->line($headline)
            ->line('Task: '.$this->taskName)
            ->line('Project: '.$projectLabel);

        if ($this->assignedByName) {
            $message->line('Assigned by: '.$this->assignedByName);
        }

        return $message
            ->action('Lihat Detail Task', url('/admin/projects/'.$this->projectId))
            ->line('Silakan tinjau task dan lanjutkan progres pekerjaan.')
            ->salutation('Terima kasih, TeamSync');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $projectLabel = $this->projectName ?: 'Project #'.$this->projectId;

        return [
            'category' => 'task',
            'title' => $this->isReassignment ? 'Task Reassigned' : 'New Task Assigned',
            'body' => sprintf('%s in %s has been assigned to you.', $this->taskName, $projectLabel),
            'action_url' => '/admin/projects/'.$this->projectId,
            'task_id' => $this->taskId,
            'project_id' => $this->projectId,
            'task_name' => $this->taskName,
            'project_name' => $this->projectName,
            'assigned_by' => $this->assignedByName,
            'is_reassignment' => $this->isReassignment,
        ];
    }
}
