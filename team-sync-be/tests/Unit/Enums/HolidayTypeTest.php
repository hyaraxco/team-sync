<?php

namespace Tests\Unit\Enums;

use App\Enums\HolidayType;
use PHPUnit\Framework\TestCase;

class HolidayTypeTest extends TestCase
{
    public function test_label_returns_human_readable_text(): void
    {
        $this->assertSame('National Holiday', HolidayType::NATIONAL_HOLIDAY->label());
        $this->assertSame('Collective Leave (Cuti Bersama)', HolidayType::COLLECTIVE_LEAVE->label());
    }

    public function test_description_returns_descriptive_text(): void
    {
        $this->assertSame('Public holiday - no work required', HolidayType::NATIONAL_HOLIDAY->description());
        $this->assertSame('Company-wide collective leave - no leave request needed', HolidayType::COLLECTIVE_LEAVE->description());
    }

    public function test_values_returns_all_enum_values(): void
    {
        $values = HolidayType::values();

        $this->assertContains('national_holiday', $values);
        $this->assertContains('collective_leave', $values);
        $this->assertCount(2, $values);
    }
}
