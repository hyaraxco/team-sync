<?php

namespace App\Console\Commands;

use App\Jobs\BroadcastMeetingJob;
use App\Services\MeetingService;
use Illuminate\Console\Command;

class SendMeetingReminders extends Command
{
    protected $signature = 'meetings:send-reminders';

    protected $description = 'Send reminders for meetings starting in 15 minutes';

    public function __construct(
        private readonly MeetingService $meetingService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $meetings = $this->meetingService->getNeedingReminder();

        foreach ($meetings as $meeting) {
            BroadcastMeetingJob::dispatch((int) $meeting->id, 'reminder')->onQueue('meetings');

            $meeting->forceFill([
                'reminder_sent_at' => now(),
            ])->save();
        }

        $this->info('Meeting reminders dispatched: '.$meetings->count());

        return self::SUCCESS;
    }
}
