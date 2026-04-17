<?php

namespace Database\Seeders;

use App\Interfaces\PayrollRepositoryInterface;
use App\Models\Payroll;
use Illuminate\Database\Seeder;

class MinimalPayrollE2EReadySeeder extends Seeder
{
    /**
     * Seed minimum payroll data and make it immediately visible for employee payslip page.
     */
    public function run(): void
    {
        $this->call([
            MinimalPayrollE2ESeeder::class,
        ]);

        $salaryMonth = now()->startOfMonth()->format('Y-m');
        $paymentDate = now()->endOfMonth()->toDateString();
        $repository = app(PayrollRepositoryInterface::class);
        $originalMailer = config('mail.default');
        $originalQueue = config('queue.default');

        config([
            'mail.default' => 'array',
            'queue.default' => 'sync',
        ]);

        try {
            $payroll = $repository->generatePayroll($salaryMonth);
            $repository->approvePayroll($payroll->id);
            $repository->markAsPaid($payroll->id, $paymentDate);
        } finally {
            config([
                'mail.default' => $originalMailer,
                'queue.default' => $originalQueue,
            ]);
        }

        $this->command?->info(sprintf(
            'Payroll %s generated and marked as paid. Employee agung@teamsync.com can now see My Payroll data.',
            Payroll::query()->findOrFail($payroll->id)->salary_month
        ));
    }
}
