<?php

namespace Tests\Unit\Enums;

use App\Enums\AttendanceStatus;
use PHPUnit\Framework\TestCase;

class AttendanceStatusTest extends TestCase
{
    public function test_label_returns_human_readable_text(): void
    {
        $this->assertSame('Present', AttendanceStatus::PRESENT->label());
        $this->assertSame('Late', AttendanceStatus::LATE->label());
        $this->assertSame('Absent', AttendanceStatus::ABSENT->label());
        $this->assertSame('Half Day', AttendanceStatus::HALF_DAY->label());
        $this->assertSame('Sick Leave', AttendanceStatus::SICK_LEAVE->label());
        $this->assertSame('Annual Leave', AttendanceStatus::ANNUAL_LEAVE->label());
    }

    public function test_to_array_returns_value_and_label(): void
    {
        $this->assertSame([
            'value' => 'late',
            'label' => 'Late',
        ], AttendanceStatus::LATE->toArray());
    }
}
