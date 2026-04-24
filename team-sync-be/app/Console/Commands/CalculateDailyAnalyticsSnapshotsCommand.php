<?php

namespace App\Console\Commands;

use App\Services\Analytics\DailyMetricsCalculator;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

class CalculateDailyAnalyticsSnapshotsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'analytics:calculate-daily-snapshots {--date= : The date to calculate metrics for (Y-m-d format)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate and store daily analytics snapshots for performance optimization';

    /**
     * Execute the console command.
     */
    public function handle(DailyMetricsCalculator $calculator): int
    {
        $dateString = $this->option('date');
        $date = $dateString ? Carbon::parse($dateString) : Carbon::today();

        $this->info("Calculating daily analytics snapshots for {$date->toDateString()}...");

        try {
            $calculator->calculateDailyMetrics($date->toDateString());
            $this->info('✓ Daily analytics snapshots calculated successfully');

            return SymfonyCommand::SUCCESS;
        } catch (\Exception $e) {
            $this->error('✗ Failed to calculate daily analytics snapshots');
            $this->error($e->getMessage());

            return SymfonyCommand::FAILURE;
        }
    }
}
