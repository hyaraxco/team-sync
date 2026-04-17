<?php

namespace App\Notifications;

use App\Models\AttendanceCorrection;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AttendanceCorrectionApproved extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(protected AttendanceCorrection $correction)
    {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $date = \Carbon\Carbon::parse(optional($this->correction->attendance)->date)->format('d M Y');

        return (new MailMessage)
            ->subject('Pengajuan Koreksi Absen Disetujui')
            ->greeting('Halo '.$notifiable->name.',')
            ->line('Pengajuan koreksi absen kamu untuk tanggal '.$date.' telah disetujui.')
            ->action('Lihat Detail', url('/admin/attendance/my-attendances'))
            ->line('Terima kasih!');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'category' => 'attendance_correction',
            'title' => 'Attendance Correction Approved',
            'body' => 'Your attendance correction request has been approved.',
            'action_url' => '/admin/attendance/my-attendances',
            'correction_id' => $this->correction->id,
            'status' => 'approved',
        ];
    }
}
