<?php

namespace Database\Seeders;

use App\Models\AttendancePolicy;
use Illuminate\Database\Seeder;

class AttendancePolicySeeder extends Seeder
{
    /**
     * Seed the application's attendance policies.
     */
    public function run(): void
    {
        $policies = [
            [
                'employment_type' => 'full_time',
                'work_start_time' => '09:00:00',
                'work_end_time' => '17:00:00',
                'work_days_per_week' => 5,
                'default_working_weekdays' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
                'late_grace_minutes' => 30,
                'half_day_min_hours' => 4.00,
                'warning_absent_pct' => 15.00,
            ],
            [
                'employment_type' => 'contract',
                'work_start_time' => '09:00:00',
                'work_end_time' => '17:00:00',
                'work_days_per_week' => 5,
                'default_working_weekdays' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
                'late_grace_minutes' => 30,
                'half_day_min_hours' => 4.00,
                'warning_absent_pct' => 15.00,
            ],
            [
                'employment_type' => 'intern',
                'work_start_time' => '09:00:00',
                'work_end_time' => '17:00:00',
                'work_days_per_week' => 5,
                'default_working_weekdays' => ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'],
                'late_grace_minutes' => 30,
                'half_day_min_hours' => 3.00,
                'warning_absent_pct' => 20.00,
            ],
            [
                'employment_type' => 'part_time',
                'work_start_time' => '09:00:00',
                'work_end_time' => '13:00:00',
                'work_days_per_week' => 3,
                'default_working_weekdays' => ['monday', 'wednesday', 'friday'],
                'late_grace_minutes' => 20,
                'half_day_min_hours' => 2.00,
                'warning_absent_pct' => 20.00,
            ],
        ];

        foreach ($policies as $policy) {
            AttendancePolicy::query()->updateOrCreate(
                ['employment_type' => $policy['employment_type']],
                $policy
            );
        }
    }
}
