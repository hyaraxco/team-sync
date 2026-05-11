<?php

namespace Tests\Unit\DTOs;

use App\DTOs\AttendanceDto;
use App\Models\Attendance;
use Tests\TestCase;

class AttendanceDtoTest extends TestCase
{
    public function test_from_array_maps_all_fields(): void
    {
        $dto = AttendanceDto::fromArray($this->payload());

        $this->assertSame(1, $dto->staff_member_id);
        $this->assertSame('2025-03-15', $dto->date);
        $this->assertSame('09:00:00', $dto->check_in);
        $this->assertSame(-6.2088, $dto->check_in_lat);
        $this->assertSame(106.8456, $dto->check_in_long);
        $this->assertSame('17:00:00', $dto->check_out);
        $this->assertSame(-6.2099, $dto->check_out_lat);
        $this->assertSame(106.8467, $dto->check_out_long);
        $this->assertSame('present', $dto->status);
        $this->assertSame('office', $dto->actual_work_mode);
        $this->assertSame('Full day work', $dto->notes);
    }

    public function test_from_array_uses_defaults_for_optional_fields(): void
    {
        $dto = AttendanceDto::fromArray([
            'staff_member_id' => 1,
            'date' => '2025-03-15',
            'status' => 'present',
        ]);

        $this->assertNull($dto->check_in);
        $this->assertNull($dto->check_in_lat);
        $this->assertNull($dto->check_in_long);
        $this->assertNull($dto->check_out);
        $this->assertNull($dto->check_out_lat);
        $this->assertNull($dto->check_out_long);
        $this->assertNull($dto->actual_work_mode);
        $this->assertNull($dto->notes);
    }

    public function test_to_array_preserves_payload_shape(): void
    {
        $dto = AttendanceDto::fromArray($this->payload());

        $this->assertSame($this->payload(), $dto->toArray());
    }

    public function test_from_array_for_update_merges_with_existing_values(): void
    {
        $attendance = $this->makeAttendance([
            'staff_member_id' => 10,
            'date' => '2025-01-01',
            'check_in' => '08:00:00',
            'check_in_lat' => -6.1,
            'check_in_long' => 106.1,
            'check_out' => '16:00:00',
            'check_out_lat' => -6.2,
            'check_out_long' => 106.2,
            'status' => 'late',
            'actual_work_mode' => 'remote',
            'notes' => 'Old note',
        ]);

        $dto = AttendanceDto::fromArrayForUpdate([
            'check_out' => '17:30:00',
            'status' => 'present',
        ], $attendance);

        $this->assertSame(10, $dto->staff_member_id);
        $this->assertStringStartsWith('2025-01-01', $dto->date);
        $this->assertSame('present', $dto->status);
        $this->assertSame('17:30:00', $dto->check_out);
        // Unchanged fields retain existing values
        $this->assertSame(-6.1, $dto->check_in_lat);
        $this->assertSame(106.1, $dto->check_in_long);
        $this->assertSame('remote', $dto->actual_work_mode);
    }

    public function test_from_array_for_update_uses_all_provided_data(): void
    {
        $attendance = $this->makeAttendance([
            'staff_member_id' => 10,
            'date' => '2025-01-01',
            'check_in' => '08:00:00',
            'status' => 'late',
        ]);

        $dto = AttendanceDto::fromArrayForUpdate($this->payload(), $attendance);

        $this->assertSame(1, $dto->staff_member_id);
        $this->assertSame('present', $dto->status);
        $this->assertSame('09:00:00', $dto->check_in);
    }

    private function makeAttendance(array $attributes): Attendance
    {
        $attendance = new Attendance;
        foreach ($attributes as $key => $value) {
            $attendance->{$key} = $value;
        }

        return $attendance;
    }

    private function payload(): array
    {
        return [
            'staff_member_id' => 1,
            'date' => '2025-03-15',
            'check_in' => '09:00:00',
            'check_in_lat' => -6.2088,
            'check_in_long' => 106.8456,
            'check_out' => '17:00:00',
            'check_out_lat' => -6.2099,
            'check_out_long' => 106.8467,
            'status' => 'present',
            'actual_work_mode' => 'office',
            'notes' => 'Full day work',
        ];
    }
}
