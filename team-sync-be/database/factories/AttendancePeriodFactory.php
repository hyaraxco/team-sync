<?php

namespace Database\Factories;

use App\Models\AttendancePeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttendancePeriodFactory extends Factory
{
    protected $model = AttendancePeriod::class;

    private static int $sequence = 0;

    public function definition(): array
    {
        // Use sequence to ensure unique periods across test runs
        $offset = self::$sequence++;
        $startDate = now()->subMonths(2)->addDays($offset);
        $endDate = (clone $startDate)->addMonth();
        $cutoffDate = (clone $endDate)->subDay();

        return [
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'cutoff_date' => $cutoffDate,
            'status' => AttendancePeriod::STATUS_OPEN,
            'locked_at' => null,
        ];
    }
}
