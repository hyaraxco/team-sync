<?php

namespace App\Notifications;

use App\Models\Attendance;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AttendanceCheckedIn extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected int $attendanceId,
        protected string $attendanceDate,
        protected ?string $checkInAt,
        protected string $status,
    ) {}

    public static function fromAttendance(Attendance $attendance): self
    {
        return new self(
            attendanceId: (int) $attendance->id,
            attendanceDate: (string) optional($attendance->date)->toDateString(),
            checkInAt: optional($attendance->check_in)->toIso8601String(),
            status: (string) $attendance->status,
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
            ->subject('Check-in Berhasil')
            ->greeting('Halo '.$notifiable->name.',')
            ->line('Check-in kamu berhasil tercatat.')
            ->line('Tanggal: '.$this->attendanceDate)
            ->when($this->checkInAt !== null, function (MailMessage $message): MailMessage {
                return $message->line('Waktu Check-in: '.$this->checkInAt);
            })
            ->action('Lihat Attendance', url('/admin/attendance/my-attendances'))
            ->line('Pastikan untuk melakukan check-out setelah selesai bekerja.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'category' => 'attendance',
            'title' => 'Check-in Recorded',
            'body' => 'Your check-in has been recorded successfully.',
            'action_url' => '/admin/attendance/my-attendances',
            'attendance_id' => $this->attendanceId,
            'attendance_date' => $this->attendanceDate,
            'check_in_at' => $this->checkInAt,
            'status' => $this->status,
        ];
    }
}
