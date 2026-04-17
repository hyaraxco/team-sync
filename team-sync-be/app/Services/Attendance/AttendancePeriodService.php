<?php

namespace App\Services\Attendance;

use App\Models\AttendancePeriod;
use App\Models\PayrollSetting;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class AttendancePeriodService
{
    public function syncLifecycle(CarbonInterface|string|null $referenceDate = null): array
    {
        $reference = $this->normalizeDate($referenceDate);
        $settings = PayrollSetting::current();

        $period = $this->ensurePeriodForMonth($reference, (int) $settings->attendance_cutoff_day);
        $reviewTransitioned = $this->transitionOpenPeriodsToReview($reference);

        return [
            'current_period_id' => $period->id,
            'review_transitioned' => $reviewTransitioned,
        ];
    }

    public function ensurePeriodForMonth(CarbonInterface|string $monthDate, ?int $cutoffDay = null): AttendancePeriod
    {
        $month = Carbon::parse($monthDate)->startOfMonth();
        $endOfMonth = $month->copy()->endOfMonth();
        $resolvedCutoffDay = $this->resolveCutoffDay($month, $cutoffDay);
        $resolvedCutoffDate = $month->copy()->day($resolvedCutoffDay)->toDateString();

        $period = AttendancePeriod::query()
            ->whereDate('start_date', $month->toDateString())
            ->whereDate('end_date', $endOfMonth->toDateString())
            ->first();

        if (! $period) {
            $period = AttendancePeriod::query()->create([
                'start_date' => $month->toDateString(),
                'end_date' => $endOfMonth->toDateString(),
                'cutoff_date' => $resolvedCutoffDate,
                'status' => AttendancePeriod::STATUS_OPEN,
            ]);
        }

        $currentCutoffDate = Carbon::parse((string) $period->cutoff_date)->toDateString();

        if (! $period->isLocked() && $currentCutoffDate !== $resolvedCutoffDate) {
            $period->update([
                'cutoff_date' => $resolvedCutoffDate,
            ]);
        }

        return $period->fresh();
    }

    public function transitionOpenPeriodsToReview(CarbonInterface|string|null $referenceDate = null): int
    {
        $reference = $this->normalizeDate($referenceDate);

        return AttendancePeriod::query()
            ->where('status', AttendancePeriod::STATUS_OPEN)
            ->whereDate('cutoff_date', '<=', $reference->toDateString())
            ->update([
                'status' => AttendancePeriod::STATUS_REVIEW,
                'updated_at' => now(),
            ]);
    }

    public function periodForDate(CarbonInterface|string $date): ?AttendancePeriod
    {
        $resolvedDate = Carbon::parse($date)->toDateString();

        return AttendancePeriod::query()
            ->whereDate('start_date', '<=', $resolvedDate)
            ->whereDate('end_date', '>=', $resolvedDate)
            ->first();
    }

    public function canSubmitCorrection(CarbonInterface|string $date): bool
    {
        $period = $this->periodForDate($date);

        if (! $period) {
            return true;
        }

        return $period->isOpen();
    }

    public function lockPeriod(AttendancePeriod|int $period): AttendancePeriod
    {
        $attendancePeriod = $period instanceof AttendancePeriod
            ? $period
            : AttendancePeriod::query()->findOrFail($period);

        if ($attendancePeriod->isLocked()) {
            return $attendancePeriod;
        }

        $attendancePeriod->update([
            'status' => AttendancePeriod::STATUS_LOCKED,
            'locked_at' => now(),
        ]);

        return $attendancePeriod->fresh();
    }

    private function resolveCutoffDay(CarbonInterface $month, ?int $cutoffDay): int
    {
        $defaultCutoff = (int) (PayrollSetting::current()->attendance_cutoff_day ?? 25);
        $resolvedCutoff = $cutoffDay ?? $defaultCutoff;
        $resolvedCutoff = max(1, $resolvedCutoff);

        return min($resolvedCutoff, $month->copy()->endOfMonth()->day);
    }

    private function normalizeDate(CarbonInterface|string|null $referenceDate = null): Carbon
    {
        if ($referenceDate === null) {
            return now()->startOfDay();
        }

        return Carbon::parse($referenceDate)->startOfDay();
    }
}
