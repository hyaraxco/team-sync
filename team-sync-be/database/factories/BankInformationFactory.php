<?php

namespace Database\Factories;

use App\Models\EmployeeProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BankInformation>
 */
class BankInformationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $banks = [
            'bca',
            'mandiri',
            'bri',
            'bni',
            'cimb_niaga',
            'danamon',
            'permata',
            'maybank_indonesia',
            'ocbc_nisp',
            'panin_bank',
        ];

        return [
            'employee_id' => EmployeeProfile::factory(),
            'bank_name' => fake()->randomElement($banks),
            'account_number' => $this->generateAccountNumber(),
            'account_holder_name' => fake()->name(),
        ];
    }

    /**
     * Generate realistic bank account number
     */
    private function generateAccountNumber(): string
    {
        return fake()->numerify('##########');
    }

    /**
     * Set specific bank
     */
    public function bank(string $bankKey): static
    {
        return $this->state(fn(array $attributes) => [
            'bank_name' => $bankKey,
        ]);
    }

    /**
     * Assign to specific employee
     */
    public function forEmployee(EmployeeProfile $employee): static
    {
        return $this->state(fn(array $attributes) => [
            'employee_id' => $employee->id,
            'account_holder_name' => $employee->user->name ?? fake()->name(),
        ]);
    }
}
