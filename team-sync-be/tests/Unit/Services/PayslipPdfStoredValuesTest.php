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

    public function test_rendered_slip_keeps_allowance_bonus_absence_and_other_deduction_rows_when_zero(): void
    {
        $detail = $this->historicalDetail(0, 0, 0);
        $detail->final_salary = 10_750_000;
        $detail->deduction_days = 0;

        $pdf = Mockery::mock(PDF::class);
        $pdf->shouldReceive('loadView')->once()->withArgs(function ($view, $data) {
            $html = view($view, $data)->render();
            foreach (['Tunjangan', 'Bonus', 'Potongan Ketidakhadiran (0,0 hari)', 'Potongan Lainnya'] as $label) {
                $this->assertMatchesRegularExpression('/'.preg_quote($label, '/').'<\/td>\s*<td class="amount">Rp 0<\/td>/', $html);
            }

            return true;
        })->andReturnSelf();
        $pdf->shouldReceive('setPaper')->once()->with('a4', 'portrait')->andReturnSelf();
        $pdf->shouldReceive('output')->once()->andReturn('synthetic-pdf');
        \Barryvdh\DomPDF\Facade\Pdf::clearResolvedInstance('dompdf.wrapper');
        app()->instance('dompdf.wrapper', $pdf);

        $this->assertSame('synthetic-pdf', app(PayslipPdfService::class)->render($detail));
    }

    public function test_synthetic_historical_record_renders_a_real_pdf_without_rate_tables(): void
    {
        DB::listen(fn () => throw new LogicException('PDF must not query current rates or history.'));
        $pdf = app(PayslipPdfService::class)->render($this->historicalDetail(123_456, 234_567, 45_678));

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringContainsString('%%EOF', $pdf);
    }

    public function test_real_pdf_paints_readable_net_pay_and_keeps_its_title_and_amount_together(): void
    {
        foreach ([0, 20] as $adjustmentCount) {
            $wrapper = app('dompdf.wrapper');
            $dompdf = $wrapper->getDomPDF();
            $canvas = new class('a4', 'portrait', $dompdf) extends \Dompdf\Adapter\CPDF
            {
                public array $texts = [];

                public array $backgrounds = [];

                public function text($x, $y, $text, $font, $size, $color = [0, 0, 0], $word_space = 0.0, $char_space = 0.0, $angle = 0.0)
                {
                    $this->texts[] = [$this->get_page_number(), $x, $y, $text, $color];
                    parent::text($x, $y, $text, $font, $size, $color, $word_space, $char_space, $angle);
                }

                public function filled_rectangle($x1, $y1, $w, $h, $color)
                {
                    $this->backgrounds[] = [$this->get_page_number(), $x1, $y1, $w, $h, $color];
                    parent::filled_rectangle($x1, $y1, $w, $h, $color);
                }
            };
            $dompdf->setCanvas($canvas);
            \Barryvdh\DomPDF\Facade\Pdf::clearResolvedInstance('dompdf.wrapper');
            app()->instance('dompdf.wrapper', $wrapper);
            $detail = $this->historicalDetail(123_456, 234_567, 45_678);
            $adjustments = collect();
            for ($index = 0; $index < $adjustmentCount; $index++) {
                $adjustments->push(new \App\Models\PayrollAdjustment([
                    'reason' => 'Synthetic correction '.$index,
                    'amount_delta' => 1_000,
                ]));
            }
            $detail->setRelation('appliedAdjustments', $adjustments);

            $output = app(PayslipPdfService::class)->render($detail);
            $this->assertStringStartsWith('%PDF-', $output);
            $title = array_values(array_filter($canvas->texts, fn ($text) => str_contains($text[3], 'GAJI BERSIH')));
            $amount = array_values(array_filter($canvas->texts, fn ($text) => $text[3] === 'Rp 8.000.000'));
            $this->assertCount(1, $title);
            $this->assertCount(1, $amount);
            $this->assertSame($title[0][0], $amount[0][0]);

            foreach ([$title[0], $amount[0]] as [$page, $x, $y, $text, $color]) {
                $painted = array_filter($canvas->backgrounds, fn ($box) =>
                    $box[0] === $page
                    && $box[1] <= $x && $box[1] + $box[3] > $x
                    && $box[2] <= $y && $box[2] + $box[4] > $y
                );
                $this->assertNotEmpty($painted, 'Net pay text must have a painted background.');
                $luminance = function (array $rgb): float {
                    $linear = array_map(fn ($channel) => $channel <= 0.04045 ? $channel / 12.92 : (($channel + 0.055) / 1.055) ** 2.4, array_slice($rgb, 0, 3));

                    return 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
                };
                $background = array_values($painted)[count($painted) - 1][5];
                $foregroundLuminance = $luminance($color);
                $backgroundLuminance = $luminance($background);
                $contrast = (max($foregroundLuminance, $backgroundLuminance) + 0.05) / (min($foregroundLuminance, $backgroundLuminance) + 0.05);
                $this->assertGreaterThanOrEqual(4.5, $contrast, 'Actual net-pay paint must have readable contrast.');
                $this->assertLessThan($canvas->get_height(), $y);
            }
            if ($adjustmentCount === 0) {
                $this->assertSame(1, $canvas->get_page_count(), 'The standard payslip should fit naturally on A4.');
            }
            app()->forgetInstance('dompdf.wrapper');
            \Barryvdh\DomPDF\Facade\Pdf::clearResolvedInstance('dompdf.wrapper');
        }
    }
}
