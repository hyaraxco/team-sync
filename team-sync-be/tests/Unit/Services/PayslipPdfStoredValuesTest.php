<?php

use App\Models\JobInformation;
use App\Models\Payroll;
use App\Models\PayrollDetail;
use App\Models\StaffMemberProfile;
use App\Models\User;
use App\Services\Payroll\TaxCalculationService;
use App\Services\PayslipPdfService;
use Barryvdh\DomPDF\PDF;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PayslipPdfStoredValuesTest extends TestCase
{
    private function historicalDetail(?float $tax, ?float $tk, ?float $kes): PayrollDetail
    {
        $payroll = new Payroll([
            'salary_month' => '2025-03-01',
            'payment_date' => '2025-03-25',
            'status' => 'paid',
        ]);
        $payroll->setRelation('payrollSettingVersion', null);
        $employee = new StaffMemberProfile(['ptkp_status' => 'K/3', 'npwp' => null]);
        $employee->setRelation('user', new User(['name' => 'Synthetic Employee']));
        $job = new JobInformation(['monthly_salary' => 90_000_000, 'job_title' => 'Engineer']);
        $job->setRelation('team', null);
        $employee->setRelation('jobInformation', $job);

        $detail = new PayrollDetail([
            'original_salary' => 10_000_000,
            'overtime_amount' => 750_000,
            'overtime_hours' => 10,
            'deduction_amount' => 0,
            'final_salary' => 8_000_000,
            'pph21_amount' => $tax,
            'bpjs_tk_employee' => $tk,
            'bpjs_kes_employee' => $kes,
            'bpjs_tk_employer' => 9_999_999,
            'bpjs_kes_employer' => 8_888_888,
            'tax_calculation_meta' => json_encode(['meta' => ['ptkp_status' => 'TK/0', 'has_npwp' => true]]),
        ]);
        $detail->setRelation('payroll', $payroll);
        $detail->setRelation('staffMember', $employee);

        return $detail;
    }

    public function test_rendered_deductions_use_only_stored_values_including_zero_null_and_negative_tax(): void
    {
        // No rate tables or employee history are available to this DB-free consumer test.
        DB::listen(fn () => throw new LogicException('PDF must not query current rates or history.'));
        app()->bind(TaxCalculationService::class, fn () => throw new LogicException('PDF must not resolve the tax calculator.'));

        foreach ([[123_456, 234_567, 45_678], [0, 0, 0], [null, null, null], [-123_456, 0, 0]] as [$tax, $tk, $kes]) {
            $html = null;
            $pdf = Mockery::mock(PDF::class);
            $pdf->shouldReceive('loadView')->once()->withArgs(function ($view, $data) use (&$html) {
                $html = view($view, $data)->render();

                return true;
            })->andReturnSelf();
            $pdf->shouldReceive('setPaper')->once()->with('a4', 'portrait')->andReturnSelf();
            $pdf->shouldReceive('output')->once()->andReturn('synthetic-pdf');
            \Barryvdh\DomPDF\Facade\Pdf::clearResolvedInstance('dompdf.wrapper');
            app()->instance('dompdf.wrapper', $pdf);

            $detail = $this->historicalDetail($tax, $tk, $kes);
            $this->assertSame('synthetic-pdf', app(PayslipPdfService::class)->render($detail));

            foreach ([
                'BPJS Kesehatan (Karyawan)' => $kes,
                'BPJS Ketenagakerjaan (Karyawan)' => $tk,
                'Subtotal BPJS' => ($tk ?? 0) + ($kes ?? 0),
                'PPh 21 (Pajak Penghasilan)' => $tax,
            ] as $label => $amount) {
                $formatted = 'Rp '.number_format($amount ?? 0, 0, ',', '.');
                $this->assertMatchesRegularExpression('/'.preg_quote($label, '/').'<\/td>\s*<td class="amount">'.preg_quote($formatted, '/').'<\/td>/', $html);
            }
            $this->assertStringContainsString('Rp 10.750.000', $html);
            $this->assertStringContainsString('Lembur (10,0 jam)', $html);
            $this->assertStringContainsString('Rp 8.000.000', $html);
            $this->assertStringNotContainsString('Jaminan Hari Tua', $html);
            $this->assertStringNotContainsString('Jaminan Pensiun', $html);
            $this->assertStringNotContainsString('Rp 9.999.999', $html);
            $this->assertStringNotContainsString('Rp 8.888.888', $html);
            $this->assertSame($tax === null ? null : number_format($tax, 2, '.', ''), $detail->pph21_amount);
        }
    }

    public function test_synthetic_historical_record_renders_a_real_pdf_without_rate_tables(): void
    {
        DB::listen(fn () => throw new LogicException('PDF must not query current rates or history.'));
        $pdf = app(PayslipPdfService::class)->render($this->historicalDetail(123_456, 234_567, 45_678));

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringContainsString('%%EOF', $pdf);
    }
}
