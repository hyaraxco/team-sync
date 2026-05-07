<?php

namespace Database\Factories;

use App\Models\AttendancePeriod;
use App\Models\Payroll;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payroll>
 */
class PayrollFactory extends Factory
{
    protected $model = Payroll::class;

    public function definition(): array
    {
        $salaryMonth = fake()->unique()->dateTimeBetween('-12 months', '+12 months')->format('Y-m-01');
        $status = fake()->randomElement(['pending', 'processing', 'approved', 'paid']);

        return [
            'salary_month' => $salaryMonth,
            'attendance_period_id' => fake()->boolean(80) ? AttendancePeriod::factory() : null,
            'payroll_setting_version_id' => null,
            'payment_date' => $status === 'paid' ? fake()->dateTimeBetween($salaryMonth, $salaryMonth.' +1 month') : null,
            'status' => $status,
            'correction_count' => 0,
        ];
    }
}
