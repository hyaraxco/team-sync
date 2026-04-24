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
use Tests\TestCase;

class PayrollExportVersioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_export_uses_settings_version_working_days_when_detail_value_is_missing(): void
    {
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
        $detail = $this->createPayrollDetail('2026-10-01', null, 0);

        $export = new PayrollExport($detail->payroll_id);
        $row = $export->map($detail);

        $this->assertSame(22, $row[8]);
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
            'account_number' => '5566778899',
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
