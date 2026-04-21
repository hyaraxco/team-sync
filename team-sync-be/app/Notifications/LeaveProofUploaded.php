<?php

namespace App\Notifications;

use App\Models\LeaveRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LeaveProofUploaded extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected int $leaveRequestId,
        protected int $employeeId,
        protected string $leaveType,
        protected string $startDate,
        protected string $endDate,
        protected ?string $proofFileName,
        protected ?string $uploadedByName = null,
    ) {}

    public static function fromLeaveRequest(LeaveRequest $leaveRequest, ?string $uploadedByName = null): self
    {
        return new self(
            leaveRequestId: (int) $leaveRequest->id,
            employeeId: (int) $leaveRequest->staff_member_id,
            leaveType: (string) ($leaveRequest->leave_type->value ?? $leaveRequest->leave_type),
            startDate: (string) optional($leaveRequest->start_date)->toDateString(),
            endDate: (string) optional($leaveRequest->end_date)->toDateString(),
            proofFileName: $leaveRequest->proof_file_name,
            uploadedByName: $uploadedByName,
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
        $uploaderLabel = $this->uploadedByName ?: 'An employee';

        return (new MailMessage)
            ->subject('Bukti Cuti Sakit Menunggu Review')
            ->greeting('Halo '.$notifiable->name.',')
            ->line($uploaderLabel.' mengunggah bukti cuti sakit dan menunggu review.')
            ->line('Periode Cuti: '.$this->startDate.' s/d '.$this->endDate)
            ->when($this->proofFileName, function (MailMessage $message): MailMessage {
                return $message->line('Dokumen: '.$this->proofFileName);
            })
            ->action('Review Bukti Cuti', url('/admin/attendances'))
            ->line('Silakan tinjau bukti pada detail leave request.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $uploaderLabel = $this->uploadedByName ?: 'An employee';

        return [
            'category' => 'leave_request',
            'title' => 'Sick Leave Proof Uploaded',
            'body' => sprintf('%s uploaded sick leave proof that needs your review.', $uploaderLabel),
            'action_url' => '/admin/attendances',
            'leave_request_id' => $this->leaveRequestId,
            'staff_member_id' => $this->employeeId,
            'leave_type' => $this->leaveType,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'proof_file_name' => $this->proofFileName,
            'uploaded_by' => $this->uploadedByName,
        ];
    }
}
