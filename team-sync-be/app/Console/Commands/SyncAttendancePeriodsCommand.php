<?php

namespace App\Console\Commands;

use App\Services\Attendance\AttendancePeriodService;
use Illuminate\Console\Command;

class SyncAttendancePeriodsCommand extends Command
{
    protected $signature = 'attendance-periods:sync {--date= : Reference date (Y-m-d) for lifecycle sync}';

    protected $description = 'Sync attendance period lifecycle by creating monthly period and transitioning open periods to review';

    public function __construct(
        private readonly AttendancePeriodService $attendancePeriodService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $result = $this->attendancePeriodService->syncLifecycle($this->option('date'));

        $this->info('Attendance period lifecycle synchronized.');
        $this->line(sprintf('Current period id: %d', $result['current_period_id']));
        $this->line(sprintf('Periods moved to review: %d', $result['review_transitioned']));

        return self::SUCCESS;
    }
}
