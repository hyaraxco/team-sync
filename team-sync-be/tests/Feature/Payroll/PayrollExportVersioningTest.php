<?php

namespace Tests\Feature\Payroll;

use App\Exports\PayrollExport;
use App\Models\JobInformation;
use App\Models\Payroll;
use App\Models\PayrollDetail;
use App\Models\PayrollSetting;
use App\Models\StaffMemberProfile;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Tests\Concerns\ActivatesLicense;
use Tests\TestCase;

class PayrollExportVersioningTest extends TestCase
{
    use ActivatesLicense, RefreshDatabase;

    public function test_export_uses_settings_version_working_days_when_detail_value_is_missing(): void
    {
        $this->activateTestLicense();

        $setting = PayrollSetting::current();
        $setting->update([
            'working_days_mode' => 'fixed',
            'default_working_days' => 20,
            'absent_deduction_rate' => 1.5,
            'rounding_mode' => 'floor',
            'rounding_unit' => 500,
            'note_template' => 'Fixed mode {working_days}',
        ]);

        $version = $setting->resolveActiveVersion();
        $detail = $this->createPayrollDetail('2026-08-01', (int) $version->id, 0);

        $export = new PayrollExport($detail->payroll_id);
        $row = $export->map($detail);

        $this->assertSame(20, $row[8]);
    }

    public function test_export_prefers_effective_working_days_from_detail_when_available(): void
    {
        $this->activateTestLicense();

        $setting = PayrollSetting::current();
        $setting->update([
            'working_days_mode' => 'fixed',
            'default_working_days' => 20,
            'absent_deduction_rate' => 1.5,
            'rounding_mode' => 'floor',
            'rounding_unit' => 500,
            'note_template' => 'Fixed mode {working_days}',
        ]);

        $version = $setting->resolveActiveVersion();
        $detail = $this->createPayrollDetail('2026-09-01', (int) $version->id, 18);

        $export = new PayrollExport($detail->payroll_id);
        $row = $export->map($detail);

        $this->assertSame(18, $row[8]);
    }

    public function test_export_uses_safe_default_for_legacy_payroll_without_settings_version(): void
    {
        $this->activateTestLicense();

        $detail = $this->createPayrollDetail('2026-10-01', null, 0);

        $export = new PayrollExport($detail->payroll_id);
        $row = $export->map($detail);

        $this->assertSame(22, $row[8]);
    }

    public function test_xlsx_reconciles_stored_overtime_corrections_refunds_and_rounding(): void
    {
        $this->activateTestLicense();

        $cases = [
            ['2026-08-01', 'floor', 125000, 12345, 512345, 9862500, -280],
            ['2026-09-01', 'ceil', -125000, -550000, -50000, 10175500, 375],
        ];

        foreach ($cases as [$month, $roundingMode, $correction, $tax, $deductions, $net, $rounding]) {
            $setting = PayrollSetting::current();
            $setting->update(['rounding_mode' => $roundingMode, 'rounding_unit' => 500]);
            $version = $setting->resolveActiveVersion();
            $detail = $this->createPayrollDetail($month, (int) $version->id, 20);
            $detail->update([
                'overtime_amount' => 250125,
                'taxable_adjustment_amount' => $correction,
                'deduction_amount' => 100000,
                'pph21_amount' => $tax,
                'bpjs_tk_employee' => 300000,
                'bpjs_kes_employee' => 100000,
                'final_salary' => $net,
            ]);

            $export = new PayrollExport($detail->payroll_id);
            $response = Excel::download($export, 'payroll.xlsx');
            $path = $response->getFile()->getPathname();
            $workbook = null;

            try {
                $workbook = IOFactory::load($path);
                $sheet = $workbook->getActiveSheet();

                $this->assertSame('T', $sheet->getHighestColumn());
                $this->assertSame($export->headings(), $sheet->rangeToArray('A6:T6')[0]);
                $this->assertSame([
                    'Gaji Pokok',
                    'Upah Lembur',
                    'Koreksi Penghasilan Kena Pajak',
                    'Potongan',
                    'Penyesuaian Pembulatan',
                    'Gaji Bersih',
                    'Status',
                    'Catatan',
                ], $sheet->rangeToArray('M6:T6')[0]);

                $money = $sheet->rangeToArray('M7:R7', null, true, false)[0];
                $this->assertEquals([10000000, 250125, $correction, $deductions, $rounding, $net], $money);
                $this->assertEquals(
                    (float) $detail->final_salary,
                    $money[0] + $money[1] + $money[2] - $money[3] + $money[4]
                );
                $this->assertSame('Menunggu', $sheet->getCell('S7')->getValue());
                $this->assertSame('Export payload', $sheet->getCell('T7')->getValue());
                $this->assertSame('Total Karyawan: 1', $sheet->getCell('A3')->getValue());
                $this->assertSame('Total Gaji: Rp '.number_format($net, 0, ',', '.'), $sheet->getCell('D3')->getValue());
                $this->assertSame('Rata-rata Gaji: Rp '.number_format($net, 0, ',', '.'), $sheet->getCell('A4')->getValue());
                $this->assertContains('A1:T1', $sheet->getMergeCells());
                foreach (range('M', 'R') as $column) {
                    $this->assertSame('#,##0', $sheet->getStyle($column.'7')->getNumberFormat()->getFormatCode());
                    $this->assertSame(Alignment::HORIZONTAL_RIGHT, $sheet->getStyle($column.'7')->getAlignment()->getHorizontal());
                }
                $this->assertSame(Alignment::HORIZONTAL_CENTER, $sheet->getStyle('S7')->getAlignment()->getHorizontal());
                $this->assertTrue($sheet->getStyle('T5')->getFont()->getBold());
                $this->assertEquals($tax, $detail->fresh()->pph21_amount);
            } finally {
                $workbook?->disconnectWorksheets();
                unlink($path);
            }
        }
    }

    private function createPayrollDetail(string $salaryMonth, ?int $settingsVersionId, int $effectiveWorkingDays): PayrollDetail
    {
        $user = User::factory()->create();
        $employee = StaffMemberProfile::withoutSyncingToSearch(function () use ($user) {
            return StaffMemberProfile::factory()->for($user)->create();
        });

        $team = Team::factory()->create();

        JobInformation::factory()
            ->forEmployee($employee)
            ->forTeam($team)
            ->active()
            ->state([
                'monthly_salary' => 10000000,
                'status' => 'active',
            ])
            ->create();

        $employee->bankInformation()->create([
            'staff_member_id' => $employee->id,
            'bank_name' => 'BCA',
            'account_number' => '556677'.str_pad((string) $employee->id, 4, '0', STR_PAD_LEFT),
            'account_holder_name' => 'Payroll Export User',
            'account_type' => 'saving',
        ]);

        $payroll = Payroll::create([
            'salary_month' => $salaryMonth,
            'status' => 'pending',
            'payroll_setting_version_id' => $settingsVersionId,
        ]);

        $detail = PayrollDetail::create([
            'payroll_id' => $payroll->id,
            'staff_member_id' => $employee->id,
            'original_salary' => 10000000,
            'final_salary' => 9500000,
            'effective_working_days' => $effectiveWorkingDays,
            'attended_days' => 20,
            'sick_days' => 1,
            'absent_days' => 1,
            'notes' => 'Export payload',
        ]);

        return $detail->load([
            'staffMember.user',
            'staffMember.jobInformation.team',
            'staffMember.bankInformation',
        ]);
    }
}
