<?php

namespace Tests\Unit\DTOs;

use App\DTOs\LeaveRequestDto;
use App\Models\LeaveRequest;
use Tests\TestCase;

class LeaveRequestDtoTest extends TestCase
{
    public function test_from_array_maps_all_fields(): void
    {
        $dto = LeaveRequestDto::fromArray($this->payload());

        $this->assertSame('uuid-123', $dto->id);
        $this->assertSame('1', $dto->employeeId);
        $this->assertSame('annual_leave', $dto->leaveType);
        $this->assertSame('2025-04-01', $dto->startDate);
        $this->assertSame('2025-04-03', $dto->endDate);
        $this->assertSame(3, $dto->totalDays);
        $this->assertSame('Vacation', $dto->reason);
        $this->assertSame('08123456789', $dto->emergencyContact);
        $this->assertSame('pending', $dto->status);
        $this->assertNull($dto->approvedBy);
    }

    public function test_from_array_uses_defaults_for_optional_fields(): void
    {
        $dto = LeaveRequestDto::fromArray([
            'staff_member_id' => '1',
            'leave_type' => 'sick_leave',
            'start_date' => '2025-04-01',
            'end_date' => '2025-04-01',
            'reason' => 'Feeling sick',
        ]);

        $this->assertNull($dto->id);
        $this->assertNull($dto->totalDays);
        $this->assertNull($dto->emergencyContact);
        $this->assertSame('pending', $dto->status);
        $this->assertNull($dto->approvedBy);
    }

    public function test_to_array_maps_fields_correctly(): void
    {
        $dto = LeaveRequestDto::fromArray($this->payload());
        $array = $dto->toArray();

        $this->assertSame('1', $array['staff_member_id']);
        $this->assertSame('annual_leave', $array['leave_type']);
        $this->assertSame('2025-04-01', $array['start_date']);
        $this->assertSame('2025-04-03', $array['end_date']);
        $this->assertSame(3, $array['total_days']);
        $this->assertSame('Vacation', $array['reason']);
        $this->assertSame('08123456789', $array['emergency_contact']);
        $this->assertSame('pending', $array['status']);
        $this->assertNull($array['approved_by']);
    }

    public function test_from_array_for_update_merges_with_existing_leave_request(): void
    {
        $leaveRequest = $this->makeLeaveRequest([
            'staff_member_id' => 10,
            'leave_type' => 'sick_leave',
            'start_date' => '2025-03-01',
            'end_date' => '2025-03-03',
            'total_days' => 3,
            'reason' => 'Flu',
            'emergency_contact' => '08111111',
            'status' => 'pending',
            'approved_by' => null,
        ]);

        $dto = LeaveRequestDto::fromArrayForUpdate([
            'reason' => 'Still sick',
            'status' => 'approved',
        ], $leaveRequest);

        $this->assertSame($leaveRequest->id, $dto->id);
        $this->assertSame('10', $dto->employeeId);
        $this->assertSame('Still sick', $dto->reason);
        $this->assertSame('approved', $dto->status);
        // Unchanged fields retain existing values
        $this->assertSame('sick_leave', $dto->leaveType);
        $this->assertSame('08111111', $dto->emergencyContact);
    }

    public function test_from_array_for_update_uses_all_provided_data(): void
    {
        $leaveRequest = $this->makeLeaveRequest([
            'staff_member_id' => 10,
            'leave_type' => 'sick_leave',
        ]);

        $dto = LeaveRequestDto::fromArrayForUpdate($this->payload(), $leaveRequest);

        $this->assertSame('1', $dto->employeeId);
        $this->assertSame('annual_leave', $dto->leaveType);
        $this->assertSame('Vacation', $dto->reason);
    }

    private function makeLeaveRequest(array $attributes): LeaveRequest
    {
        $leaveRequest = new LeaveRequest;
        foreach ($attributes as $key => $value) {
            $leaveRequest->{$key} = $value;
        }

        return $leaveRequest;
    }

    private function payload(): array
    {
        return [
            'id' => 'uuid-123',
            'staff_member_id' => '1',
            'leave_type' => 'annual_leave',
            'start_date' => '2025-04-01',
            'end_date' => '2025-04-03',
            'total_days' => 3,
            'reason' => 'Vacation',
            'emergency_contact' => '08123456789',
            'status' => 'pending',
            'approved_by' => null,
        ];
    }
}
