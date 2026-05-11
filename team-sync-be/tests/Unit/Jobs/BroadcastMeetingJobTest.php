<?php

namespace Tests\Unit\Jobs;

use App\Jobs\BroadcastMeetingJob;
use App\Models\User;
use App\Services\EmailService;
use App\Services\MeetingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BroadcastMeetingJobTest extends TestCase
{
    use RefreshDatabase;

    private int $meetingId;

    protected function setUp(): void
    {
        parent::setUp();

        $userId = DB::table('users')->insertGetId([
            'name' => 'Creator',
            'email' => 'creator@test.com',
            'password' => bcrypt('password'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->meetingId = DB::table('meetings')->insertGetId([
            'title' => 'Test Meeting',
            'scheduled_at' => now()->addHour(),
            'duration_minutes' => 60,
            'created_by' => $userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_job_implements_should_queue(): void
    {
        $job = new BroadcastMeetingJob(1, 'scheduled');

        $this->assertInstanceOf(ShouldQueue::class, $job);
    }

    public function test_job_has_correct_retry_count_and_timeout(): void
    {
        $job = new BroadcastMeetingJob(1, 'scheduled');

        $this->assertSame(3, $job->tries);
        $this->assertSame(300, $job->timeout);
    }

    public function test_job_is_queued_on_meetings_queue(): void
    {
        $job = new BroadcastMeetingJob(1, 'scheduled');

        $this->assertSame('meetings', $job->queue);
    }

    public function test_job_stores_meeting_id_and_notification_type(): void
    {
        $job = new BroadcastMeetingJob(42, 'reminder');

        $this->assertSame(42, $job->meetingId);
        $this->assertSame('reminder', $job->notificationType);
    }

    public function test_handle_sends_scheduled_notification(): void
    {
        // Create a real user as recipient
        $recipient = User::factory()->create();

        $mockMeetingService = \Mockery::mock(MeetingService::class);
        $mockMeetingService->shouldReceive('resolveRecipients')
            ->once()
            ->andReturn(new EloquentCollection([$recipient]));

        $mockEmailService = \Mockery::mock(EmailService::class);
        $mockEmailService->shouldReceive('sendMeetingScheduledNotification')
            ->once();

        $job = new BroadcastMeetingJob($this->meetingId, 'scheduled');
        $job->handle($mockMeetingService, $mockEmailService);

        // Mockery enforces shouldHaveReceived in tearDown
        $this->assertTrue(true);
    }

    public function test_handle_sends_reminder_notification(): void
    {
        $recipient = User::factory()->create();

        $mockMeetingService = \Mockery::mock(MeetingService::class);
        $mockMeetingService->shouldReceive('resolveRecipients')
            ->once()
            ->andReturn(new EloquentCollection([$recipient]));

        $mockEmailService = \Mockery::mock(EmailService::class);
        $mockEmailService->shouldReceive('sendMeetingReminderNotification')
            ->once();

        $job = new BroadcastMeetingJob($this->meetingId, 'reminder');
        $job->handle($mockMeetingService, $mockEmailService);

        // Mockery enforces shouldHaveReceived in tearDown
        $this->assertTrue(true);
    }

    public function test_handle_does_not_send_notification_for_unknown_type(): void
    {
        $recipient = User::factory()->create();

        $mockMeetingService = \Mockery::mock(MeetingService::class);
        $mockMeetingService->shouldReceive('resolveRecipients')
            ->once()
            ->andReturn(new EloquentCollection([$recipient]));

        $mockEmailService = \Mockery::mock(EmailService::class);
        $mockEmailService->shouldNotReceive('sendMeetingScheduledNotification');
        $mockEmailService->shouldNotReceive('sendMeetingReminderNotification');

        $job = new BroadcastMeetingJob($this->meetingId, 'unknown_type');
        $job->handle($mockMeetingService, $mockEmailService);

        // Verify no email methods were called (Mockery enforces shouldNotReceive)
        $this->assertTrue(true);
    }

    public function test_handle_processes_recipients_in_chunks(): void
    {
        // Create 250 real users to test chunking
        $users = User::factory()->count(250)->create();

        $mockMeetingService = \Mockery::mock(MeetingService::class);
        $mockMeetingService->shouldReceive('resolveRecipients')
            ->once()
            ->andReturn($users);

        // Should be called twice: chunk 1 (200) + chunk 2 (50)
        $mockEmailService = \Mockery::mock(EmailService::class);
        $mockEmailService->shouldReceive('sendMeetingScheduledNotification')
            ->twice();

        $job = new BroadcastMeetingJob($this->meetingId, 'scheduled');
        $job->handle($mockMeetingService, $mockEmailService);

        // Mockery enforces shouldHaveReceived in tearDown
        $this->assertTrue(true);
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }
}
