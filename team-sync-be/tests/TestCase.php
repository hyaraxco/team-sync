<?php

namespace Tests;

use Carbon\Carbon;
use Database\Seeders\BpjsPayrollFixtureSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function seedBpjsPayroll(string $period, ?array $salaries = null): void
    {
        app(BpjsPayrollFixtureSeeder::class)->run($period, $salaries);
    }

    /**
     * Attendance timestamps are stored in UTC. Fixtures express them as company
     * wall-clock times, so convert before writing to the database.
     */
    protected function localAttendanceTime(string $local, string $timezone = 'Asia/Jakarta'): string
    {
        return Carbon::parse($local, $timezone)->setTimezone('UTC')->toDateTimeString();
    }
}
