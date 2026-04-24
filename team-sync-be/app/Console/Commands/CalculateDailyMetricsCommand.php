<?php

namespace App\Console\Commands;

use App\Services\Analytics\DailyMetricsCalculator;
use Illuminate\Console\Command;
use Symfony\Component\Console\Command\Command as SymfonyCommand;

class CalculateDailyMetricsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'analytics:calculate-daily-metrics 
                            {--date= : The date to calculate metrics for (YYYY-MM-DD). Defaults to yesterday}
                            {--range : Calculate for a date range}
                            {--start= : Start date for range calculation (YYYY-MM-DD)}
                            {--end= : End date for range calculation (YYYY-MM-DD)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculate and store daily performance metrics snapshots';

    /**
     * Execute the console command.
     */
    public function handle(DailyMetricsCalculator $calculator): int
    {
        try {
            if ($this->option('range')) {
                $start = $this->option('start');
                $end = $this->option('end');

                if (! $start || ! $end) {
                    $this->error('Both --start and --end dates are required for range calculation');

                    return SymfonyCommand::FAILURE;
                }

                $this->info("Calculating metrics for range: {$start} to {$end}");
                $calculator->calculateMetricsForRange($start, $end);
                $this->info('Range calculation completed successfully');
            } else {
                $date = $this->option('date');
                $dateStr = $date ?: 'yesterday';

                $this->info("Calculating daily metrics for {$dateStr}...");
                $calculator->calculateDailyMetrics($date);
                $this->info('Daily metrics calculated successfully');
            }

            return SymfonyCommand::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Failed to calculate metrics: '.$e->getMessage());
            $this->error($e->getTraceAsString());

            return SymfonyCommand::FAILURE;
        }
    }
}
