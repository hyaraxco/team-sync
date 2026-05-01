<?php

namespace Database\Factories;

use App\Models\ThrPayroll;
use Illuminate\Database\Eloquent\Factories\Factory;

class ThrPayrollFactory extends Factory
{
    protected $model = ThrPayroll::class;

    public function definition(): array
    {
        return [
            'year' => now()->year,
            'religion_event' => ThrPayroll::EVENT_IDUL_FITRI,
            'religion_holiday_date' => now()->addMonths(2),
            'payment_deadline' => now()->addMonths(2)->subDays(7),
            'status' => ThrPayroll::STATUS_DRAFT,
            'total_employees' => 0,
            'total_thr_amount' => 0,
            'total_tax_amount' => 0,
            'total_net_amount' => 0,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => ['status' => ThrPayroll::STATUS_PENDING]);
    }

    public function approved(): static
    {
        return $this->state(fn () => [
            'status' => ThrPayroll::STATUS_APPROVED,
            'approved_at' => now(),
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn () => [
            'status' => ThrPayroll::STATUS_PAID,
            'approved_at' => now()->subDay(),
            'payment_date' => now(),
        ]);
    }

    public function forEvent(string $event): static
    {
        return $this->state(fn () => ['religion_event' => $event]);
    }
}
