<?php

namespace Tests\Unit\Services;

use App\Interfaces\MeetingRepositoryInterface;
use App\Jobs\BroadcastMeetingJob;
use App\Models\Meeting;
use App\Models\User;
use App\Services\MeetingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class MeetingServiceTest extends TestCase
{
    use RefreshDatabase;

    private MeetingService $service;

    private MeetingRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = $this->createMock(MeetingRepositoryInterface::class);
        $this->service = new MeetingService($this->repository);
    }

    public function test_create_stores_meeting_and_dispatches_broadcast_job(): void
    {
        Bus::fake();

        $creator = User::factory()->create();

        $meetingData = [
            'title' => 'Weekly Sync',
            'scheduled_at' => '2026-05-15 10:00:00',
            'duration_minutes' => 60,
            'departments' => ['engineering'],
        ];

        $meeting = Meeting::factory()->create([
            'created_by' => $creator->id,
            'title' => 'Weekly Sync',
        ]);

        $this->repository
            ->method('create')
            ->willReturnCallback(function (array $data) use ($meeting) {
                $meeting->fill($data);
                $meeting->save();

                return $meeting;
            });

        $result = $this->service->create($meetingData, $creator);

        $this->assertInstanceOf(Meeting::class, $result);
        $this->assertEquals('Weekly Sync', $result->title);
        $this->assertEquals($creator->id, $result->created_by);

        Bus::assertDispatched(BroadcastMeetingJob::class, function ($job) use ($meeting) {
            return $job->meetingId === (int) $meeting->id
                && $job->notificationType === 'scheduled';
        });
    }

    public function test_get_by_id_delegates_to_repository(): void
    {
        $meeting = Meeting::factory()->create();

        $this->repository
            ->method('getById')
            ->with($meeting->id)
            ->willReturn($meeting);

        $result = $this->service->getById($meeting->id);

        $this->assertEquals($meeting->id, $result->id);
    }

    public function test_get_all_paginated_delegates_to_repository(): void
    {
        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            collect([]), 0, 15, 1
        );

        $this->repository
            ->method('getAllPaginated')
            ->willReturn($paginator);

        $result = $this->service->getAllPaginated('search', 'engineering', 15);

        $this->assertInstanceOf(\Illuminate\Contracts\Pagination\LengthAwarePaginator::class, $result);
    }

    public function test_get_upcoming_delegates_to_repository(): void
    {
        $meetings = Meeting::factory()->count(3)->create();

        $this->repository
            ->method('getUpcoming')
            ->willReturn($meetings);

        $result = $this->service->getUpcoming(10);

        $this->assertCount(3, $result);
    }

    public function test_get_needing_reminder_delegates_to_repository(): void
    {
        $meetings = Meeting::factory()->count(2)->create();

        $this->repository
            ->method('getNeedingReminder')
            ->willReturn($meetings);

        $result = $this->service->getNeedingReminder();

        $this->assertCount(2, $result);
    }
}
