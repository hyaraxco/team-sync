<?php

namespace App\Notifications;

use App\Models\OvertimeRecord;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class OvertimeApprovedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly OvertimeRecord $record,
        private readonly User $approver
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'overtime_approved',
            'category' => 'overtime',
            'title' => 'Overtime Request Approved',
            'body' => "Your overtime request for {$this->record->date->format('d M Y')} ({$this->record->hours} hours) has been approved by {$this->approver->name}.",
            'action_url' => '/admin/attendance/my-overtime',
            'overtime_record_id' => $this->record->id,
            'date' => $this->record->date->format('Y-m-d'),
            'hours' => (float) $this->record->hours,
            'approved_by' => $this->approver->name,
        ];
    }
}
