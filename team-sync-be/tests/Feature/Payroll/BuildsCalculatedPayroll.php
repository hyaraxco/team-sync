<?php

namespace Tests\Feature\Payroll;

use App\Interfaces\PayrollRepositoryInterface;
use App\Models\Payroll;
use App\Models\PayrollSetting;
use App\Services\Payroll\PayrollTaxIntegrationService;
use Database\Seeders\BpjsRateSeeder;
use Database\Seeders\PtkpAmountSeeder;
use Database\Seeders\TaxBracketSeeder;

trait BuildsCalculatedPayroll
{
    private function seedPayrollTaxFixtures(): void
    {
        $this->seed([BpjsRateSeeder::class, PtkpAmountSeeder::class, TaxBracketSeeder::class]);
        PayrollSetting::current()->update(['payday_day' => 15]);
    }

    private function payrollSettingVersionId(): int
    {
        return PayrollSetting::current()->resolveActiveVersion()->id;
    }

    private function calculatePayrollFixture(Payroll $payroll, string $status): void
    {
        if ($status === 'paid') {
            return;
        }

        app(PayrollTaxIntegrationService::class)->refreshPayroll($payroll);
        if ($status === 'approved') {
            app(PayrollRepositoryInterface::class)->approvePayroll((string) $payroll->id);
        }
        $payroll->refresh();
    }
}
