<?php

namespace App\Notifications;

use App\Models\AttendancePolicyMismatch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AttendanceMismatchStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected int $mismatchId,
        protected string $mismatchDate,
        protected string $plannedWorkMode,
        protected string $actualWorkMode,
        protected string $status,
        protected ?string $resolutionNotes = null,
        protected ?string $actorName = null,
    ) {}

    public static function fromMismatch(AttendancePolicyMismatch $mismatch, ?string $actorName = null): self
    {
        return new self(
            mismatchId: (int) $mismatch->id,
            mismatchDate: (string) optional($mismatch->mismatch_date)->toDateString(),
            plannedWorkMode: (string) $mismatch->planned_work_mode,
            actualWorkMode: (string) $mismatch->actual_work_mode,
            status: (string) $mismatch->status,
            resolutionNotes: $mismatch->resolution_notes,
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
            ->line('Tanggal mismatch: '.$this->mismatchDate)
            ->line('Planned mode: '.$this->plannedWorkMode)
            ->line('Actual mode: '.$this->actualWorkMode)
            ->when($this->actorName !== null && trim($this->actorName) !== '', function (MailMessage $message): MailMessage {
                return $message->line('Updated by: '.trim((string) $this->actorName));
            })
            ->when($this->resolutionNotes !== null && trim($this->resolutionNotes) !== '', function (MailMessage $message): MailMessage {
                return $message->line('Notes: '.trim((string) $this->resolutionNotes));
            })
            ->action('Lihat Attendance', url('/admin/attendance/my-attendances'))
            ->line('Silakan cek detail attendance kamu untuk informasi lanjut.');
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
            'action_url' => '/admin/attendance/my-attendances',
            'mismatch_id' => $this->mismatchId,
            'mismatch_date' => $this->mismatchDate,
            'planned_work_mode' => $this->plannedWorkMode,
            'actual_work_mode' => $this->actualWorkMode,
            'status' => $this->status,
            'resolution_notes' => $this->resolutionNotes,
            'actor_name' => $this->actorName,
        ];
    }

    private function title(): string
    {
        return match ($this->status) {
            AttendancePolicyMismatch::STATUS_PENDING_REVIEW => 'Attendance Mismatch Detected',
            AttendancePolicyMismatch::STATUS_ACKNOWLEDGED => 'Attendance Mismatch Acknowledged',
            AttendancePolicyMismatch::STATUS_ESCALATED_HR => 'Attendance Mismatch Escalated',
            AttendancePolicyMismatch::STATUS_RESOLVED => 'Attendance Mismatch Resolved',
            default => 'Attendance Mismatch Updated',
        };
    }

    private function body(): string
    {
        return match ($this->status) {
            AttendancePolicyMismatch::STATUS_PENDING_REVIEW => 'A new attendance mismatch was detected and is pending review.',
            AttendancePolicyMismatch::STATUS_ACKNOWLEDGED => 'Your attendance mismatch has been acknowledged by manager.',
            AttendancePolicyMismatch::STATUS_ESCALATED_HR => 'Your attendance mismatch has been escalated to HR.',
            AttendancePolicyMismatch::STATUS_RESOLVED => 'Your attendance mismatch has been resolved by HR.',
            default => 'Your attendance mismatch status has been updated.',
        };
    }

    private function mailSubject(): string
    {
        return match ($this->status) {
            AttendancePolicyMismatch::STATUS_PENDING_REVIEW => 'Mismatch Attendance Terdeteksi',
            AttendancePolicyMismatch::STATUS_ACKNOWLEDGED => 'Mismatch Attendance Diakui',
            AttendancePolicyMismatch::STATUS_ESCALATED_HR => 'Mismatch Attendance Dieskalasi ke HR',
            AttendancePolicyMismatch::STATUS_RESOLVED => 'Mismatch Attendance Diselesaikan',
            default => 'Status Mismatch Attendance Diperbarui',
        };
    }

    private function mailBody(): string
    {
        return match ($this->status) {
            AttendancePolicyMismatch::STATUS_PENDING_REVIEW => 'Ada mismatch attendance baru yang menunggu review.',
            AttendancePolicyMismatch::STATUS_ACKNOWLEDGED => 'Mismatch attendance kamu sudah diakui dan sedang diproses.',
            AttendancePolicyMismatch::STATUS_ESCALATED_HR => 'Mismatch attendance kamu sudah dieskalasi ke HR.',
            AttendancePolicyMismatch::STATUS_RESOLVED => 'Mismatch attendance kamu sudah diselesaikan.',
            default => 'Status mismatch attendance kamu sudah diperbarui.',
        };
    }
}
