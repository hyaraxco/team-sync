<?php

namespace Tests\Unit\Services;

use App\Interfaces\OvertimeRepositoryInterface;
use App\Models\AttendancePeriod;
use App\Models\OvertimeRecord;
use App\Models\User;
use App\Services\OvertimeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OvertimeServiceTest extends TestCase
{
    use RefreshDatabase;

    private OvertimeService $service;

    private OvertimeRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();

        Role::create(['name' => 'staff', 'guard_name' => 'sanctum']);

        $this->repository = $this->createMock(OvertimeRepositoryInterface::class);
        $this->service = new OvertimeService($this->repository);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Create
    // ─────────────────────────────────────────────────────────────────────────

    public function test_create_overtime_record_successfully(): void
    {
        $validated = [
            'staff_member_id' => 1,
            'date' => '2026-05-15',
            'start_time' => '17:00',
            'end_time' => '19:00',
            'overtime_type' => 'workday',
            'notes' => 'Extra work',
        ];

        $record = OvertimeRecord::factory()->make($validated);
        $record->hours = 2.0;
        $record->status = OvertimeRecord::STATUS_PENDING;

        $this->repository
            ->method('getWeeklyHoursForStaffMember')
            ->willReturn(0.0);

        $this->repository
            ->method('create')
            ->willReturn($record);

        $result = $this->service->create($validated);

        $this->assertTrue($result['success']);
        $this->assertEquals('Overtime Record Created Successfully', $result['message']);
        $this->assertNotNull($result['record']);
    }

    public function test_create_rejects_locked_period(): void
    {
        AttendancePeriod::factory()->create([
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-31',
            'status' => AttendancePeriod::STATUS_LOCKED,
        ]);

        $validated = [
            'staff_member_id' => 1,
            'date' => '2026-05-15',
            'start_time' => '17:00',
            'end_time' => '19:00',
            'overtime_type' => 'workday',
        ];

        $result = $this->service->create($validated);

        $this->assertFalse($result['success']);
        $this->assertEquals('Cannot create overtime for a date in a locked attendance period.', $result['message']);
        $this->assertNull($result['record']);
    }

    public function test_create_rejects_hours_exceeding_daily_max(): void
    {
        $validated = [
            'staff_member_id' => 1,
            'date' => '2026-05-15',
            'start_time' => '17:00',
            'end_time' => '22:00',
            'overtime_type' => 'workday',
        ];

        $this->repository
            ->method('getWeeklyHoursForStaffMember')
            ->willReturn(0.0);

        $result = $this->service->create($validated);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Overtime hours exceed maximum', $result['message']);
    }

    public function test_create_rejects_when_weekly_limit_exceeded(): void
    {
        $validated = [
            'staff_member_id' => 1,
            'date' => '2026-05-15',
            'start_time' => '17:00',
            'end_time' => '19:00',
            'overtime_type' => 'workday',
        ];

        $this->repository
            ->method('getWeeklyHoursForStaffMember')
            ->willReturn(17.0);

        $result = $this->service->create($validated);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Weekly overtime limit exceeded', $result['message']);
    }

    public function test_create_calculates_hours_correctly(): void
    {
        $validated = [
            'staff_member_id' => 1,
            'date' => '2026-05-15',
            'start_time' => '17:00',
            'end_time' => '19:30',
            'overtime_type' => 'workday',
        ];

        $this->repository
            ->method('getWeeklyHoursForStaffMember')
            ->willReturn(0.0);

        $this->repository
            ->method('create')
            ->willReturnCallback(function (array $data) {
                return OvertimeRecord::factory()->make($data);
            });

        $result = $this->service->create($validated);

        $this->assertTrue($result['success']);
        $this->assertEquals(2.5, $result['record']->hours);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Approve
    // ─────────────────────────────────────────────────────────────────────────

    public function test_approve_pending_overtime_record(): void
    {
        $record = OvertimeRecord::factory()->create([
            'status' => OvertimeRecord::STATUS_PENDING,
        ]);

        $approver = User::factory()->create();

        $this->repository
            ->method('getById')
            ->willReturn($record);

        $approvedRecord = clone $record;
        $approvedRecord->status = OvertimeRecord::STATUS_APPROVED;
        $approvedRecord->approved_by = $approver->id;
        $approvedRecord->approved_at = now();

        $this->repository
            ->method('approve')
            ->willReturn($approvedRecord);

        $result = $this->service->approve($record->id, $approver);

        $this->assertTrue($result['success']);
        $this->assertEquals('Overtime Record Approved Successfully', $result['message']);
    }

    public function test_approve_rejects_non_pending_record(): void
    {
        $record = OvertimeRecord::factory()->create([
            'status' => OvertimeRecord::STATUS_APPROVED,
            'approved_at' => now(),
        ]);
        $approver = User::factory()->create();

        $this->repository
            ->method('getById')
            ->willReturn($record);

        $result = $this->service->approve($record->id, $approver);

        $this->assertFalse($result['success']);
        $this->assertEquals('Only pending overtime records can be approved', $result['message']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Reject
    // ─────────────────────────────────────────────────────────────────────────

    public function test_reject_pending_overtime_record(): void
    {
        $record = OvertimeRecord::factory()->create([
            'status' => OvertimeRecord::STATUS_PENDING,
        ]);

        $rejector = User::factory()->create();

        $this->repository
            ->method('getById')
            ->willReturn($record);

        $rejectedRecord = clone $record;
        $rejectedRecord->status = OvertimeRecord::STATUS_REJECTED;
        $rejectedRecord->rejection_reason = 'Not approved';

        $this->repository
            ->method('reject')
            ->willReturn($rejectedRecord);

        $result = $this->service->reject($record->id, 'Not approved', $rejector);

        $this->assertTrue($result['success']);
        $this->assertEquals('Overtime Record Rejected Successfully', $result['message']);
    }

    public function test_reject_rejects_non_pending_record(): void
    {
        $record = OvertimeRecord::factory()->create([
            'status' => OvertimeRecord::STATUS_APPROVED,
            'approved_at' => now(),
        ]);
        $rejector = User::factory()->create();

        $this->repository
            ->method('getById')
            ->willReturn($record);

        $result = $this->service->reject($record->id, 'Too late', $rejector);

        $this->assertFalse($result['success']);
        $this->assertEquals('Only pending overtime records can be rejected', $result['message']);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Getters
    // ─────────────────────────────────────────────────────────────────────────

    public function test_get_by_id_delegates_to_repository(): void
    {
        $record = OvertimeRecord::factory()->create();

        $this->repository
            ->method('getById')
            ->willReturn($record);

        $result = $this->service->getById($record->id);

        $this->assertEquals($record->id, $result->id);
    }

    public function test_get_summary_delegates_to_repository(): void
    {
        $summary = [
            'total_pending' => 5,
            'total_approved' => 20,
            'total_rejected' => 3,
        ];

        $this->repository
            ->method('getSummary')
            ->willReturn($summary);

        $result = $this->service->getSummary();

        $this->assertEquals($summary, $result);
    }

    public function test_get_all_paginated_delegates_to_repository(): void
    {
        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            collect([]), 0, 15, 1
        );

        $this->repository
            ->method('getAllPaginated')
            ->willReturn($paginator);

        $result = $this->service->getAllPaginated('pending', 1, 'workday', '2026-05-01', '2026-05-31');

        $this->assertInstanceOf(\Illuminate\Contracts\Pagination\LengthAwarePaginator::class, $result);
    }

    public function test_get_by_staff_member_delegates_to_repository(): void
    {
        $paginator = new \Illuminate\Pagination\LengthAwarePaginator(
            collect([]), 0, 15, 1
        );

        $this->repository
            ->method('getByStaffMember')
            ->willReturn($paginator);

        $result = $this->service->getByStaffMember(1, 'pending');

        $this->assertInstanceOf(\Illuminate\Contracts\Pagination\LengthAwarePaginator::class, $result);
    }
}
