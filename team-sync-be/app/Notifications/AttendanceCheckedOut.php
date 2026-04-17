<?php

namespace App\Notifications;

use App\Models\Attendance;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AttendanceCheckedOut extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected int $attendanceId,
        protected string $attendanceDate,
        protected ?string $checkOutAt,
        protected ?int $workedMinutes,
        protected string $status,
    ) {}

    public static function fromAttendance(Attendance $attendance): self
    {
        return new self(
            attendanceId: (int) $attendance->id,
            attendanceDate: (string) optional($attendance->date)->toDateString(),
            checkOutAt: optional($attendance->check_out)->toIso8601String(),
            workedMinutes: $attendance->worked_minutes,
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
            ->subject('Check-out Berhasil')
            ->greeting('Halo '.$notifiable->name.',')
            ->line('Check-out kamu berhasil tercatat.')
            ->line('Tanggal: '.$this->attendanceDate)
            ->when($this->checkOutAt !== null, function (MailMessage $message): MailMessage {
                return $message->line('Waktu Check-out: '.$this->checkOutAt);
            })
            ->when($this->workedMinutes !== null, function (MailMessage $message): MailMessage {
                return $message->line('Durasi kerja: '.(int) $this->workedMinutes.' menit');
            })
            ->action('Lihat Attendance', url('/admin/attendance/my-attendances'))
            ->line('Terima kasih atas kehadiranmu hari ini.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'category' => 'attendance',
            'title' => 'Check-out Recorded',
            'body' => 'Your check-out has been recorded successfully.',
            'action_url' => '/admin/attendance/my-attendances',
            'attendance_id' => $this->attendanceId,
            'attendance_date' => $this->attendanceDate,
            'check_out_at' => $this->checkOutAt,
            'worked_minutes' => $this->workedMinutes,
            'status' => $this->status,
        ];
    }
}
