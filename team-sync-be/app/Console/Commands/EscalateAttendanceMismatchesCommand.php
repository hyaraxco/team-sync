<?php

namespace App\Console\Commands;

use App\Services\Attendance\AttendancePolicyMismatchLifecycleService;
use Illuminate\Console\Command;

class EscalateAttendanceMismatchesCommand extends Command
{
    protected $signature = 'attendance-mismatches:escalate {--date= : Reference date (Y-m-d) for escalation check}';

    protected $description = 'Escalate pending attendance policy mismatches to HR when threshold is reached';

    public function __construct(
        private readonly AttendancePolicyMismatchLifecycleService $lifecycleService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $escalatedCount = $this->lifecycleService->escalatePendingReviewMismatches($this->option('date'));

        $this->info(sprintf('Escalated mismatches: %d', $escalatedCount));

        return self::SUCCESS;
    }
}
