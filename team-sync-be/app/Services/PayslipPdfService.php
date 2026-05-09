<?php

namespace App\Services;

use App\Models\PayrollDetail;
use App\Services\Payroll\TaxCalculationService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;

class PayslipPdfService
{
    public function __construct(
        private readonly TaxCalculationService $taxService
    ) {}

    public function render(PayrollDetail $payrollDetail): string
    {
        $payroll = $payrollDetail->payroll;
        $employee = $payrollDetail->staffMember;
        $jobInformation = $employee?->jobInformation;
        $payrollSettingVersion = $payroll?->payrollSettingVersion;

        // Basic salary components
        $basicSalary = (float) $payrollDetail->original_salary;
        $overtimeAmount = (float) $payrollDetail->overtime_amount;
        $overtimeHours = (float) $payrollDetail->overtime_hours;
        $allowances = 0; // Not yet in PayrollDetail schema — placeholder for future feature
        $bonus = 0; // Not yet in PayrollDetail schema — placeholder for future feature
        $grossSalary = $basicSalary + $overtimeAmount + $allowances + $bonus;

        // Calculate BPJS breakdown
        $bpjsBreakdown = $this->calculateBpjsBreakdown($basicSalary);

        // Calculate tax
        $taxResult = $this->taxService->calculateMonthlyPph21(
            $basicSalary,
            $employee?->ptkp_status,
            ! empty($employee?->npwp)
        );
        $tax = $taxResult['pph21_monthly'];

        // Absence deduction
        $absenceDeduction = (float) $payrollDetail->deduction_amount;
        $deductionDays = (float) $payrollDetail->deduction_days;

        // Other deductions (calculated as remainder)
        $totalBpjs = $bpjsBreakdown['total'];
        $otherDeductions = max(0, ($grossSalary - (float) $payrollDetail->final_salary) - $totalBpjs - $tax - $absenceDeduction);
        $totalDeductions = $totalBpjs + $tax + $absenceDeduction + $otherDeductions;

        // Net salary
        $netSalary = (float) $payrollDetail->final_salary;

        // Adjustments
        $adjustments = [];
        $adjustmentTotalAmount = 0;
        if ($payrollDetail->relationLoaded('appliedAdjustments')) {
            foreach ($payrollDetail->appliedAdjustments as $adj) {
                $amount = (float) $adj->amount_delta;
                $adjustments[] = [
                    'reason' => $adj->reason ?? 'Penyesuaian',
                    'amount_delta' => $amount,
                    'formatted_amount' => $this->formatSignedRupiah($amount),
                ];
                $adjustmentTotalAmount += $amount;
            }
        }

        // Prepare data for Blade template
        $data = [
            'companyName' => config('app.name', 'Team Sync Pro'),
            'companyAddress' => 'Indonesia',
            'period' => Carbon::parse($payroll->salary_month)->locale('id')->translatedFormat('F Y'),
            'employeeName' => $employee?->user?->name ?? $employee?->full_name ?? 'N/A',
            'employeeCode' => $employee?->employee_id ?? 'N/A',
            'department' => $jobInformation?->team?->name ?? $jobInformation?->job_title ?? 'N/A',
            'paymentDate' => Carbon::parse($payroll->payment_date ?? $payroll->created_at)->locale('id')->translatedFormat('d F Y'),

            // Earnings
            'basicSalary' => $this->formatRupiah($basicSalary),
            'overtimeHours' => number_format($overtimeHours, 1, ',', '.'),
            'overtimeAmount' => $this->formatRupiah($overtimeAmount),
            'allowances' => $this->formatRupiah($allowances),
            'bonus' => $this->formatRupiah($bonus),
            'grossSalary' => $this->formatRupiah($grossSalary),

            // Deductions - BPJS
            'bpjsKesehatan' => $this->formatRupiah($bpjsBreakdown['bpjs_kesehatan']),
            'bpjsKesehatanRate' => $bpjsBreakdown['bpjs_kesehatan_rate'],
            'bpjsJht' => $this->formatRupiah($bpjsBreakdown['jht']),
            'bpjsJhtRate' => $bpjsBreakdown['jht_rate'],
            'bpjsJp' => $this->formatRupiah($bpjsBreakdown['jp']),
            'bpjsJpRate' => $bpjsBreakdown['jp_rate'],
            'totalBpjs' => $this->formatRupiah($totalBpjs),

            // Deductions - Tax & Others
            'tax' => $this->formatRupiah($tax),
            'absenceDeduction' => $this->formatRupiah($absenceDeduction),
            'deductionDays' => number_format($deductionDays, 1, ',', '.'),
            'otherDeductions' => $this->formatRupiah($otherDeductions),
            'totalDeductions' => $this->formatRupiah($totalDeductions),

            // Net Salary
            'netSalary' => $this->formatRupiah($netSalary),

            // Attendance
            'effectiveWorkingDays' => $payrollDetail->effective_working_days,
            'attendedDays' => $payrollDetail->attended_days,
            'presentDays' => $payrollDetail->present_days,
            'lateDays' => $payrollDetail->late_days,
            'sickDays' => $payrollDetail->sick_days,
            'paidLeaveDays' => $payrollDetail->paid_leave_days,
            'unpaidLeaveDays' => $payrollDetail->unpaid_leave_days,
            'absentDays' => $payrollDetail->absent_days,

            // Adjustments
            'adjustments' => $adjustments,
            'adjustmentTotalAmount' => $adjustmentTotalAmount,
            'adjustmentTotalFormatted' => $this->formatSignedRupiah($adjustmentTotalAmount),

            // Notes
            'notes' => $payrollDetail->notes,
            'generatedAt' => now()->locale('id')->translatedFormat('d F Y H:i'),
        ];

        // Generate PDF using dompdf
        $pdf = Pdf::loadView('exports.payslip-pdf', $data);
        $pdf->setPaper('a4', 'portrait');

        return $pdf->output();
    }

    private function calculateBpjsBreakdown(float $basicSalary): array
    {
        $bpjs = $this->taxService->calculateBpjs($basicSalary);
        $breakdown = $bpjs['breakdown'];

        return [
            'bpjs_kesehatan' => $breakdown['bpjs_kesehatan_employee'] ?? 0,
            'bpjs_kesehatan_rate' => 1, // 1% employee contribution
            'jht' => $breakdown['jht_employee'] ?? 0,
            'jht_rate' => 2, // 2% employee contribution
            'jp' => $breakdown['jp_employee'] ?? 0,
            'jp_rate' => 1, // 1% employee contribution
            'total' => $bpjs['employee_share'],
        ];
    }

    private function formatRupiah(float $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }

    private function formatSignedRupiah(float $amount): string
    {
        $formatted = $this->formatRupiah(abs($amount));

        if ($amount > 0) {
            return '+'.$formatted;
        }
        if ($amount < 0) {
            return '-'.$formatted;
        }

        return $formatted;
    }
}
