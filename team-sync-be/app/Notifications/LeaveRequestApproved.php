<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LeaveRequestApproved extends Notification implements ShouldQueue
{
    use Queueable;

    protected LeaveRequest $leaveRequest;

    /**
     * Create a new notification instance.
     */
    public function __construct(LeaveRequest $leaveRequest)
    {
        $this->leaveRequest = $leaveRequest;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $startDate = Carbon::parse((string) $this->leaveRequest->start_date)->format('d M Y');
        $endDate = Carbon::parse((string) $this->leaveRequest->end_date)->format('d M Y');

        return (new MailMessage)
            ->subject('Permohonan Cuti Disetujui')
            ->greeting('Halo '.$notifiable->name.',')
            ->line('Selamat! Permohonan cuti kamu telah disetujui.')
            ->line('**Detail Permohonan:**')
            ->line('Jenis Cuti: '.$this->leaveRequest->leave_type->label())
            ->line('Tanggal Mulai: '.$startDate)
            ->line('Tanggal Selesai: '.$endDate)
            ->line('Total Hari: '.$this->leaveRequest->total_days.' hari kerja')
            ->line('Status: **Disetujui**')
            ->action('Lihat Detail', url('/admin/attendance/my-attendances'))
            ->line('Pastikan untuk merencanakan pekerjaan kamu dengan baik sebelum cuti dimulai.')
            ->salutation('Terima kasih, Tim HR');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'category' => 'leave_request',
            'title' => 'Leave Request Approved',
            'body' => 'Your leave request has been approved by HR.',
            'action_url' => '/admin/attendance/my-attendances',
            'leave_request_id' => $this->leaveRequest->id,
            'leave_type' => $this->leaveRequest->leave_type,
            'start_date' => $this->leaveRequest->start_date,
            'end_date' => $this->leaveRequest->end_date,
            'total_days' => $this->leaveRequest->total_days,
            'status' => 'approved',
        ];
    }
}
