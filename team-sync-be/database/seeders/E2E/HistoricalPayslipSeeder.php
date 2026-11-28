<?php

namespace Database\Seeders\E2E;

use App\Models\Payroll;
use App\Models\PayrollDetail;
use App\Models\StaffMemberProfile;
use Illuminate\Database\Seeder;

class HistoricalPayslipSeeder extends Seeder
{
    public function run(): void
    {
        $month = now()->startOfMonth()->subMonths(2);
        $payroll = Payroll::whereDate('salary_month', $month->toDateString())->first() ?? Payroll::create([
            'salary_month' => $month->toDateString(),
            'payment_date' => $month->copy()->endOfMonth()->toDateString(),
            'status' => 'paid',
        ]);
        PayrollDetail::firstOrCreate([
            'payroll_id' => $payroll->id,
            'staff_member_id' => StaffMemberProfile::where('code', 'EMP001')->firstOrFail()->id,
        ], [
            'original_salary' => 10_000_000,
            'final_salary' => 10_000_000,
            'notes' => 'Synthetic paid payslip for role ownership checks',
        ]);
    }
}
