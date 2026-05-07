<?php

namespace App\Services\Attendance;

use App\Models\AttendancePolicyMismatch;
use App\Services\EmailService;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;

class AttendancePolicyMismatchLifecycleService
{
    public function __construct(
        private readonly WorkingDaysCalculator $workingDaysCalculator,
        private readonly EmailService $emailService,
    ) {}

    public function escalatePendingReviewMismatches(CarbonInterface|string|null $referenceDate = null): int
    {
        $asOf = $referenceDate
            ? Carbon::parse($referenceDate)->startOfDay()
            : Carbon::now()->startOfDay();

        $pendingMismatches = AttendancePolicyMismatch::query()
            ->where('status', AttendancePolicyMismatch::STATUS_PENDING_REVIEW)
            ->whereDate('mismatch_date', '<', $asOf->toDateString())
            ->get();

        $escalatedCount = 0;

        /** @var AttendancePolicyMismatch $mismatch */
        foreach ($pendingMismatches as $mismatch) {
            if (! $this->hasReachedEscalationThreshold($mismatch, $asOf)) {
                continue;
            }

            $mismatch->update([
                'status' => AttendancePolicyMismatch::STATUS_ESCALATED_HR,
                'escalated_at' => Carbon::now(),
            ]);

            $this->emailService->sendAttendanceMismatchEscalatedNotification($mismatch->fresh(['staffMember.user']));

            $escalatedCount++;
        }

        return $escalatedCount;
    }

    private function hasReachedEscalationThreshold(AttendancePolicyMismatch $mismatch, CarbonInterface $asOf): bool
    {
        $start = Carbon::parse((string) $mismatch->mismatch_date)->addDay()->startOfDay();

        if ($start->greaterThan($asOf)) {
            return false;
        }

        try {
            $elapsedWorkingDays = $this->workingDaysCalculator->calculateForEmployee(
                $mismatch->staff_member_id,
                $start,
                $asOf
            );
        } catch (\Throwable $e) {
            Log::error('Failed to calculate working days for mismatch escalation.', [
                'mismatch_id' => $mismatch->id,
                'staff_member_id' => $mismatch->staff_member_id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        return $elapsedWorkingDays >= 3;
    }
}
