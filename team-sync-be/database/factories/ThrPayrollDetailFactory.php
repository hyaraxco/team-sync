<?php

namespace Database\Factories;

use App\Models\ThrPayrollDetail;
use Illuminate\Database\Eloquent\Factories\Factory;

class ThrPayrollDetailFactory extends Factory
{
    protected $model = ThrPayrollDetail::class;

    public function definition(): array
    {
        $salary = $this->faker->randomFloat(2, 5_000_000, 20_000_000);
        $tenureMonths = $this->faker->numberBetween(1, 60);
        $proration = $tenureMonths >= 12 ? 1.0 : round($tenureMonths / 12, 4);
        $gross = round($salary * $proration, 2);
        $tax = round($gross * 0.05, 2);

        return [
            'religion' => 'islam',
            'monthly_salary' => $salary,
            'join_date' => now()->subMonths($tenureMonths),
            'tenure_months' => $tenureMonths,
            'proration_factor' => $proration,
            'gross_thr_amount' => $gross,
            'pph21_amount' => $tax,
            'net_thr_amount' => round($gross - $tax, 2),
            'ptkp_status' => 'TK/0',
            'has_npwp' => true,
        ];
    }
}
