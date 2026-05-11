<?php

namespace App\Jobs;

use App\Models\Meeting;
use App\Services\EmailService;
use App\Services\MeetingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class BroadcastMeetingJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(
        public int $meetingId,
        public string $notificationType,
    ) {
        $this->onQueue('meetings');
    }

    public function handle(MeetingService $meetingService, EmailService $emailService): void
    {
        $meeting = Meeting::query()
            ->with(['creator', 'teams.members.staffMember.user'])
            ->findOrFail($this->meetingId);

        $recipients = $meetingService->resolveRecipients($meeting);

        $recipients->chunk(200)->each(function ($chunk, $chunkIndex) use ($meeting, $emailService): void {
            try {
                if ($this->notificationType === 'scheduled') {
                    $emailService->sendMeetingScheduledNotification($meeting, $chunk);

                    return;
                }

                if ($this->notificationType === 'reminder') {
                    $emailService->sendMeetingReminderNotification($meeting, $chunk);
                }
            } catch (\Throwable $e) {
                Log::warning('Failed to send meeting notification chunk', [
                    'meeting_id' => $meeting->id,
                    'notification_type' => $this->notificationType,
                    'chunk_index' => $chunkIndex,
                    'recipient_count' => $chunk->count(),
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }
}
