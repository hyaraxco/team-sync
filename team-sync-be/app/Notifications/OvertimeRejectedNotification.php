<?php

namespace App\Notifications;

use App\Models\OvertimeRecord;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class OvertimeRejectedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly OvertimeRecord $record,
        private readonly User $rejector
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'overtime_rejected',
            'category' => 'overtime',
            'title' => 'Overtime Request Rejected',
            'body' => "Your overtime request for {$this->record->date->format('d M Y')} ({$this->record->hours} hours) has been rejected. Reason: {$this->record->rejection_reason}",
            'action_url' => '/admin/attendance/my-overtime',
            'overtime_record_id' => $this->record->id,
            'date' => $this->record->date->format('Y-m-d'),
            'hours' => (float) $this->record->hours,
            'rejected_by' => $this->rejector->name,
            'rejection_reason' => $this->record->rejection_reason,
        ];
    }
}
