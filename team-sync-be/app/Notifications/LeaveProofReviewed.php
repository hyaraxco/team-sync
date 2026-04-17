<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LeaveProofReviewed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected int $leaveRequestId,
        protected string $reviewStatus,
        protected ?string $reviewedByName = null,
    ) {}

    public static function fromLeaveRequest(
        LeaveRequest $leaveRequest,
        ?string $reviewedByName = null,
        ?string $reviewStatus = null
    ): self {
        return new self(
            leaveRequestId: (int) $leaveRequest->id,
            reviewStatus: (string) ($reviewStatus ?? $leaveRequest->proof_review_status ?? 'approved'),
            reviewedByName: $reviewedByName,
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
        $isApproved = $this->reviewStatus === 'approved';
        $subject = $isApproved ? 'Bukti Cuti Sakit Disetujui' : 'Bukti Cuti Sakit Ditolak';
        $headline = $isApproved
            ? 'Bukti cuti sakit kamu sudah disetujui.'
            : 'Bukti cuti sakit kamu belum disetujui.';
        $reviewerLabel = $this->reviewedByName ?: 'Reviewer';

        return (new MailMessage)
            ->subject($subject)
            ->greeting('Halo '.$notifiable->name.',')
            ->line($headline)
            ->line('Reviewer: '.$reviewerLabel)
            ->action('Lihat Detail Cuti', url('/admin/attendance/my-attendances'))
            ->line('Silakan cek detail leave request untuk informasi lengkap.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $isApproved = $this->reviewStatus === 'approved';

        return [
            'category' => 'leave_request',
            'title' => $isApproved ? 'Sick Leave Proof Approved' : 'Sick Leave Proof Rejected',
            'body' => $isApproved
                ? 'Your sick leave proof has been approved.'
                : 'Your sick leave proof was rejected. Open detail for notes.',
            'action_url' => '/admin/attendance/my-attendances',
            'leave_request_id' => $this->leaveRequestId,
            'review_status' => $this->reviewStatus,
            'reviewed_by' => $this->reviewedByName,
        ];
    }
}
