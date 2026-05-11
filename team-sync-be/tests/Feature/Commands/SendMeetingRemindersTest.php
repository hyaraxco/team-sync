<?php

namespace Tests\Feature\Commands;

use App\Jobs\BroadcastMeetingJob;
use App\Models\Meeting;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SendMeetingRemindersTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_dispatches_reminder_jobs_for_meetings_needing_reminder(): void
    {
        Queue::fake();

        $meeting = Meeting::factory()->create([
            'reminder_sent_at' => null,
        ]);

        $mockService = \Mockery::mock(\App\Services\MeetingService::class);
        $mockService->shouldReceive('getNeedingReminder')
            ->once()
            ->andReturn(new EloquentCollection([$meeting]));

        $this->app->instance(\App\Services\MeetingService::class, $mockService);

        $exitCode = Artisan::call('meetings:send-reminders');

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Meeting reminders dispatched: 1', Artisan::output());

        Queue::assertPushed(BroadcastMeetingJob::class, function ($job) use ($meeting) {
            return $job->meetingId === (int) $meeting->id
                && $job->notificationType === 'reminder';
        });
    }

    public function test_command_returns_success_when_no_meetings_need_reminder(): void
    {
        Queue::fake();

        $mockService = \Mockery::mock(\App\Services\MeetingService::class);
        $mockService->shouldReceive('getNeedingReminder')
            ->once()
            ->andReturn(new EloquentCollection());

        $this->app->instance(\App\Services\MeetingService::class, $mockService);

        $exitCode = Artisan::call('meetings:send-reminders');

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Meeting reminders dispatched: 0', Artisan::output());
    }

    public function test_command_dispatches_jobs_on_meetings_queue(): void
    {
        Queue::fake();

        $meeting = Meeting::factory()->create([
            'reminder_sent_at' => null,
        ]);

        $mockService = \Mockery::mock(\App\Services\MeetingService::class);
        $mockService->shouldReceive('getNeedingReminder')
            ->once()
            ->andReturn(new EloquentCollection([$meeting]));

        $this->app->instance(\App\Services\MeetingService::class, $mockService);

        Artisan::call('meetings:send-reminders');

        Queue::assertPushed(BroadcastMeetingJob::class, function ($job) {
            return $job->queue === 'meetings';
        });
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}
