<?php

namespace Tests;

use Database\Seeders\BpjsPayrollFixtureSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function seedBpjsPayroll(string $period, ?array $salaries = null): void
    {
        app(BpjsPayrollFixtureSeeder::class)->run($period, $salaries);
    }
}
