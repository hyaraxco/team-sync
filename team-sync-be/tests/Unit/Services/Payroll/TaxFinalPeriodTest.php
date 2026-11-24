<?php

use App\Services\Payroll\TaxCalculationService;
use Database\Seeders\PtkpAmountSeeder;
use Database\Seeders\TaxBracketSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(Tests\TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->seed([PtkpAmountSeeder::class, TaxBracketSeeder::class]);
});

it('reconciles actual year or part-year income without treating a resident joiner as newly tax resident', function (array $facts, float $expected) {
    $result = app(TaxCalculationService::class)->calculateFinalPeriodPph21(array_merge([
        'gross_income' => 120000000, 'employee_pension_contributions' => 3600000,
        'deductible_donations' => 0, 'employment_months' => 12, 'tax_liability_months' => 12,
        'annualize' => false, 'previously_withheld' => 2200000, 'government_borne_tax' => 0,
        'final_period' => '2026-12',
    ], $facts), 'TK/0', true);
    expect((float) $result['pph21_monthly'])->toBe($expected);
})->with([
    'whole year' => [[], 620000.0],
    'resident joins September' => [['gross_income' => 62000000, 'employee_pension_contributions' => 400000,
        'employment_months' => 4, 'previously_withheld' => 2092500], -1812500.0],
    'resident leaves June' => [['gross_income' => 60000000, 'employee_pension_contributions' => 1800000,
        'employment_months' => 6, 'previously_withheld' => 1000000, 'final_period' => '2026-06'], -940000.0],
    'subjective liability ends June' => [['gross_income' => 60000000, 'employee_pension_contributions' => 1800000,
        'employment_months' => 6, 'tax_liability_months' => 6, 'annualize' => true,
        'previously_withheld' => 1000000, 'final_period' => '2026-06'], 410000.0],
]);

it('credits DTP separately without refunding government funded excess', function (float $gross, float $employeeWithheld, float $government, float $payable, float $balance, float $nonrefundable) {
    $result = app(TaxCalculationService::class)->calculateFinalPeriodPph21([
        'gross_income' => $gross, 'employee_pension_contributions' => 0, 'deductible_donations' => 0,
        'employment_months' => 12, 'tax_liability_months' => 12, 'annualize' => false,
        'previously_withheld' => $employeeWithheld, 'government_borne_tax' => $government, 'final_period' => '2026-12',
    ], 'TK/0', true);
    expect((float) $result['pph21_monthly'])->toBe($payable)
        ->and((float) $result['meta']['signed_balance'])->toBe($balance)
        ->and((float) $result['meta']['nonrefundable_government_overpayment'])->toBe($nonrefundable);
})->with([
    'employee and DTP excess' => [30000000.0, 1000000.0, 800000.0, -1000000.0, -1800000.0, 800000.0],
    'liability between employee and combined credits' => [120000000.0, 2000000.0, 2000000.0, -1000000.0, -1000000.0, 0.0],
    'only DTP creates excess' => [120000000.0, 0.0, 4000000.0, 0.0, -1000000.0, 1000000.0],
]);
