<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LeaveRequestNeedsApproval extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected int $leaveRequestId,
        protected int $employeeId,
        protected string $leaveType,
        protected string $startDate,
        protected string $endDate,
        protected int $totalDays,
        protected ?string $requestedByName = null,
    ) {}

    public static function fromLeaveRequest(LeaveRequest $leaveRequest, ?string $requestedByName = null): self
    {
        return new self(
            leaveRequestId: (int) $leaveRequest->id,
            employeeId: (int) $leaveRequest->employee_id,
            leaveType: (string) ($leaveRequest->leave_type->value ?? $leaveRequest->leave_type),
            startDate: (string) optional($leaveRequest->start_date)->toDateString(),
            endDate: (string) optional($leaveRequest->end_date)->toDateString(),
            totalDays: (int) $leaveRequest->total_days,
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
        $requesterLabel = $this->requestedByName ?: 'An employee';

        return (new MailMessage)
            ->subject('Pengajuan Cuti Menunggu Persetujuan')
            ->greeting('Halo '.$notifiable->name.',')
            ->line($requesterLabel.' mengajukan cuti dan membutuhkan persetujuan kamu.')
            ->line('Jenis Cuti: '.str_replace('_', ' ', $this->leaveType))
            ->line('Tanggal: '.$this->startDate.' s/d '.$this->endDate)
            ->line('Total Hari: '.$this->totalDays.' hari')
            ->action('Tinjau Pengajuan Cuti', url('/admin/attendances'))
            ->line('Silakan review pengajuan ini di halaman attendance.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $requesterLabel = $this->requestedByName ?: 'An employee';

        return [
            'category' => 'leave_request',
            'title' => 'Leave Request Needs Approval',
            'body' => sprintf('%s submitted a leave request for your review.', $requesterLabel),
            'action_url' => '/admin/attendances',
            'leave_request_id' => $this->leaveRequestId,
            'employee_id' => $this->employeeId,
            'leave_type' => $this->leaveType,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'total_days' => $this->totalDays,
            'requested_by' => $this->requestedByName,
        ];
    }
}
