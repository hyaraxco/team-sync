<?php

namespace App\Enums;

enum HolidayType: string
{
    case NATIONAL_HOLIDAY = 'national_holiday';
    case COLLECTIVE_LEAVE = 'collective_leave';

    public function label(): string
    {
        return match ($this) {
            self::NATIONAL_HOLIDAY => 'National Holiday',
            self::COLLECTIVE_LEAVE => 'Collective Leave (Cuti Bersama)',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::NATIONAL_HOLIDAY => 'Public holiday - no work required',
            self::COLLECTIVE_LEAVE => 'Company-wide collective leave - no leave request needed',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
