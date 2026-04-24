<?php

namespace App\Services;

use App\Models\PayrollDetail;
use Carbon\Carbon;

class PayslipPdfService
{
    public function render(PayrollDetail $payrollDetail): string
    {
        $payroll = $payrollDetail->payroll;
        $employee = $payrollDetail->staffMember;
        $jobInformation = $employee?->jobInformation;
        $totalDeductions = max(0, (float) $payrollDetail->original_salary - (float) $payrollDetail->final_salary);

        $lines = [
            'Payslip',
            'Period: '.Carbon::parse($payroll?->salary_month)->format('F Y'),
            'Employee: '.($employee?->user?->name ?? 'N/A'),
            'Employee Code: '.($employee?->code ?? 'N/A'),
            'Department: '.($jobInformation?->team?->name ?? $jobInformation?->job_title ?? 'N/A'),
            'Payment Date: '.Carbon::parse($payroll?->payment_date ?? $payroll?->created_at)->format('d F Y'),
            'Basic Salary: Rp '.number_format((float) $payrollDetail->original_salary, 0, ',', '.'),
            'Total Deductions: Rp '.number_format($totalDeductions, 0, ',', '.'),
            'Net Salary: Rp '.number_format((float) $payrollDetail->final_salary, 0, ',', '.'),
            'Attendance: '.$payrollDetail->attended_days.' present, '.$payrollDetail->sick_days.' sick, '.$payrollDetail->absent_days.' absent',
        ];

        if ($payrollDetail->notes) {
            $lines[] = 'Notes: '.$payrollDetail->notes;
        }

        return $this->buildPdf($lines);
    }

    private function buildPdf(array $lines): string
    {
        $content = "BT\n/F1 12 Tf\n14 TL\n50 780 Td\n";

        foreach ($lines as $index => $line) {
            $encoded = $this->encodeText($line);
            $content .= sprintf('(%s) Tj', $encoded);

            if ($index !== array_key_last($lines)) {
                $content .= "\nT*\n";
            }
        }

        $content .= "\nET";

        $objects = [
            "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj",
            "2 0 obj\n<< /Type /Pages /Count 1 /Kids [3 0 R] >>\nendobj",
            "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj",
            "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj",
            "5 0 obj\n<< /Length ".strlen($content)." >>\nstream\n{$content}\nendstream\nendobj",
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object."\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n";
        $pdf .= "0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf('%010d 00000 n ', $offset)."\n";
        }

        $pdf .= "trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }

    private function encodeText(string $text): string
    {
        $encoded = iconv('UTF-8', 'windows-1252//TRANSLIT//IGNORE', $text) ?: $text;
        $encoded = preg_replace('/[^\x20-\x7E]/', ' ', $encoded) ?? $encoded;

        return str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $encoded);
    }
}
