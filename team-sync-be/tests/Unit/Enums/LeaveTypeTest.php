<?php

namespace Tests\Unit\Enums;

use App\Enums\LeaveType;
use PHPUnit\Framework\TestCase;

class LeaveTypeTest extends TestCase
{
    public function test_label_returns_human_readable_text(): void
    {
        $this->assertSame('Annual Leave', LeaveType::ANNUAL_LEAVE->label());
        $this->assertSame('Sick Leave', LeaveType::SICK_LEAVE->label());
        $this->assertSame('Personal Leave', LeaveType::PERSONAL_LEAVE->label());
        $this->assertSame('Emergency Leave', LeaveType::EMERGENCY_LEAVE->label());
        $this->assertSame('Maternity Leave', LeaveType::MATERNITY_LEAVE->label());
        $this->assertSame('Paternity Leave', LeaveType::PATERNITY_LEAVE->label());
        $this->assertSame('Compassionate Leave', LeaveType::COMPASSIONATE_LEAVE->label());
    }

    public function test_to_array_returns_value_and_label(): void
    {
        $this->assertSame([
            'value' => 'sick_leave',
            'label' => 'Sick Leave',
        ], LeaveType::SICK_LEAVE->toArray());
    }
}
