<?php

namespace App\Notifications;

use App\Models\AttendanceCorrection;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AttendanceCorrectionNeedsApproval extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected int $correctionId,
        protected int $employeeId,
        protected string $date,
        protected string $reason,
        protected ?string $requestedByName = null,
    ) {}

    public static function fromCorrection(AttendanceCorrection $correction, string $date, ?string $requestedByName = null): self
    {
        return new self(
            correctionId: (int) $correction->id,
            employeeId: (int) $correction->employee_id,
            date: $date,
            reason: (string) $correction->reason,
            requestedByName: $requestedByName,
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
        $requesterLabel = $this->requestedByName ?: 'Seorang karyawan';

        return (new MailMessage)
            ->subject('Pengajuan Koreksi Absen Menunggu Persetujuan')
            ->greeting('Halo '.$notifiable->name.',')
            ->line($requesterLabel.' mengajukan koreksi absen dan membutuhkan persetujuan kamu.')
            ->line('Tanggal: '.\Carbon\Carbon::parse($this->date)->format('d M Y'))
            ->line('Alasan: '.$this->reason)
            ->action('Tinjau Pengajuan Koreksi', url('/admin/attendance-corrections'))
            ->line('Silakan review pengajuan ini di halaman corrections.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $requesterLabel = $this->requestedByName ?: 'An employee';

        return [
            'category' => 'attendance_correction',
            'title' => 'Attendance Correction Needs Approval',
            'body' => sprintf('%s submitted an attendance correction for your review.', $requesterLabel),
            'action_url' => '/admin/attendance-corrections',
            'correction_id' => $this->correctionId,
            'employee_id' => $this->employeeId,
            'date' => $this->date,
            'requested_by' => $this->requestedByName,
        ];
    }
}
