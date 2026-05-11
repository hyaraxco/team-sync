<?php

namespace Tests\Feature\Commands;

use App\Services\Analytics\DailyMetricsCalculator;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CalculateDailyMetricsCommandTest extends TestCase
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

    public function test_command_calculates_metrics_for_yesterday_by_default(): void
    {
        $mockCalculator = Mockery::mock(DailyMetricsCalculator::class);
        $mockCalculator->shouldReceive('calculateDailyMetrics')
            ->once()
            ->with(null);

        $this->app->instance(DailyMetricsCalculator::class, $mockCalculator);

        $this->artisan('analytics:calculate-daily-metrics')
            ->assertSuccessful()
            ->expectsOutputToContain('Daily metrics calculated successfully');
    }

    public function test_command_calculates_metrics_for_specific_date(): void
    {
        $mockCalculator = Mockery::mock(DailyMetricsCalculator::class);
        $mockCalculator->shouldReceive('calculateDailyMetrics')
            ->once()
            ->with('2026-05-01');

        $this->app->instance(DailyMetricsCalculator::class, $mockCalculator);

        $this->artisan('analytics:calculate-daily-metrics --date=2026-05-01')
            ->assertSuccessful()
            ->expectsOutputToContain('2026-05-01');
    }

    public function test_command_calculates_metrics_for_date_range(): void
    {
        $mockCalculator = Mockery::mock(DailyMetricsCalculator::class);
        $mockCalculator->shouldReceive('calculateMetricsForRange')
            ->once()
            ->with('2026-04-01', '2026-04-30');

        $this->app->instance(DailyMetricsCalculator::class, $mockCalculator);

        $this->artisan('analytics:calculate-daily-metrics --range --start=2026-04-01 --end=2026-04-30')
            ->assertSuccessful()
            ->expectsOutputToContain('Range calculation completed successfully');
    }

    public function test_command_fails_when_range_missing_end_date(): void
    {
        $this->artisan('analytics:calculate-daily-metrics --range --start=2026-04-01')
            ->assertExitCode(1)
            ->expectsOutputToContain('Both --start and --end dates are required');
    }

    public function test_command_fails_when_range_missing_start_date(): void
    {
        $this->artisan('analytics:calculate-daily-metrics --range --end=2026-04-30')
            ->assertExitCode(1)
            ->expectsOutputToContain('Both --start and --end dates are required');
    }

    public function test_command_returns_failure_on_exception(): void
    {
        $mockCalculator = Mockery::mock(DailyMetricsCalculator::class);
        $mockCalculator->shouldReceive('calculateDailyMetrics')
            ->once()
            ->andThrow(new \RuntimeException('Calculation failed'));

        $this->app->instance(DailyMetricsCalculator::class, $mockCalculator);

        $this->artisan('analytics:calculate-daily-metrics')
            ->assertExitCode(1)
            ->expectsOutputToContain('Failed to calculate metrics');
    }
}
