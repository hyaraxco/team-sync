<?php

namespace Tests\Feature\Commands;

use App\Services\Analytics\DailyMetricsCalculator;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CalculateDailyAnalyticsSnapshotsCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_command_calculates_snapshots_for_today_by_default(): void
    {
        $mockCalculator = Mockery::mock(DailyMetricsCalculator::class);
        $mockCalculator->shouldReceive('calculateDailyMetrics')
            ->once()
            ->with(now()->toDateString());

        $this->app->instance(DailyMetricsCalculator::class, $mockCalculator);

        $this->artisan('analytics:calculate-daily-snapshots')
            ->assertSuccessful()
            ->expectsOutputToContain('Daily analytics snapshots calculated successfully');
    }

    public function test_command_calculates_snapshots_for_specific_date(): void
    {
        $mockCalculator = Mockery::mock(DailyMetricsCalculator::class);
        $mockCalculator->shouldReceive('calculateDailyMetrics')
            ->once()
            ->with('2026-05-01');

        $this->app->instance(DailyMetricsCalculator::class, $mockCalculator);

        $this->artisan('analytics:calculate-daily-snapshots --date=2026-05-01')
            ->assertSuccessful()
            ->expectsOutputToContain('2026-05-01');
    }

    public function test_command_returns_failure_on_exception(): void
    {
        $mockCalculator = Mockery::mock(DailyMetricsCalculator::class);
        $mockCalculator->shouldReceive('calculateDailyMetrics')
            ->once()
            ->andThrow(new \RuntimeException('Database error'));

        $this->app->instance(DailyMetricsCalculator::class, $mockCalculator);

        $this->artisan('analytics:calculate-daily-snapshots')
            ->assertExitCode(1)
            ->expectsOutputToContain('Failed to calculate daily analytics snapshots');
    }
}
