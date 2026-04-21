<?php

namespace App\Notifications;

use App\Models\AttendancePolicyMismatch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AttendanceMismatchRequiresReview extends Notification implements ShouldQueue
{
    use Queueable;

    public const EVENT_CREATED = 'created';

    public const EVENT_ESCALATED = 'escalated';

    public function __construct(
        protected int $mismatchId,
        protected string $mismatchDate,
        protected ?string $employeeName,
        protected string $plannedWorkMode,
        protected string $actualWorkMode,
        protected string $eventType,
        protected ?string $actorName = null,
    ) {}

    public static function fromMismatch(
        AttendancePolicyMismatch $mismatch,
        string $eventType,
        ?string $actorName = null,
    ): self {
        return new self(
            mismatchId: (int) $mismatch->id,
            mismatchDate: (string) optional($mismatch->mismatch_date)->toDateString(),
            employeeName: $mismatch->staffMember?->user?->name,
            plannedWorkMode: (string) $mismatch->planned_work_mode,
            actualWorkMode: (string) $mismatch->actual_work_mode,
            eventType: $eventType,
            actorName: $actorName,
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
            ->when($this->employeeName !== null && trim($this->employeeName) !== '', function (MailMessage $message): MailMessage {
                return $message->line('Employee: '.trim((string) $this->employeeName));
            })
            ->line('Tanggal mismatch: '.$this->mismatchDate)
            ->line('Planned mode: '.$this->plannedWorkMode)
            ->line('Actual mode: '.$this->actualWorkMode)
            ->when($this->actorName !== null && trim($this->actorName) !== '', function (MailMessage $message): MailMessage {
                return $message->line('Updated by: '.trim((string) $this->actorName));
            })
            ->action('Tinjau Mismatch', url('/admin/attendances'))
            ->line('Silakan lakukan review sesuai role kamu.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'category' => 'attendance',
            'title' => $this->title(),
            'body' => $this->body(),
            'action_url' => '/admin/attendances',
            'mismatch_id' => $this->mismatchId,
            'mismatch_date' => $this->mismatchDate,
            'employee_name' => $this->employeeName,
            'planned_work_mode' => $this->plannedWorkMode,
            'actual_work_mode' => $this->actualWorkMode,
            'event_type' => $this->eventType,
            'actor_name' => $this->actorName,
        ];
    }

    private function title(): string
    {
        return match ($this->eventType) {
            self::EVENT_CREATED => 'Attendance Mismatch Needs Acknowledgement',
            self::EVENT_ESCALATED => 'Attendance Mismatch Escalated to HR',
            default => 'Attendance Mismatch Requires Review',
        };
    }

    private function body(): string
    {
        return match ($this->eventType) {
            self::EVENT_CREATED => 'A new attendance mismatch requires manager acknowledgement.',
            self::EVENT_ESCALATED => 'An attendance mismatch has been escalated and requires HR resolution.',
            default => 'An attendance mismatch requires reviewer follow-up.',
        };
    }

    private function mailSubject(): string
    {
        return match ($this->eventType) {
            self::EVENT_CREATED => 'Mismatch Attendance Perlu Acknowledgement',
            self::EVENT_ESCALATED => 'Mismatch Attendance Butuh Tindak Lanjut HR',
            default => 'Mismatch Attendance Perlu Review',
        };
    }

    private function mailBody(): string
    {
        return match ($this->eventType) {
            self::EVENT_CREATED => 'Ada mismatch attendance baru yang perlu acknowledgement manager.',
            self::EVENT_ESCALATED => 'Mismatch attendance telah dieskalasi dan perlu resolusi HR.',
            default => 'Ada mismatch attendance yang membutuhkan tindak lanjut reviewer.',
        };
    }
}
