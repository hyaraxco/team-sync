<?php

namespace Tests\Feature\Payroll;

use App\Models\Payroll;
use App\Models\PayrollSetting;
use App\Models\PayrollSettingVersion;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\ActivatesLicense;
use Tests\TestCase;

class PayrollSettingVersionBackfillCommandTest extends TestCase
{
    use ActivatesLicense, RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_backfill_command_dry_run_keeps_legacy_rows_unchanged(): void
    {
        $this->activateTestLicense();

        Carbon::setTestNow('2026-04-01 08:00:00');

        $setting = PayrollSetting::current();
        $setting->resolveActiveVersion();

        $legacyPayroll = $this->createPayrollWithTimestamp('2026-04-01', '2026-04-20 09:00:00');

        $this->artisan('payroll-settings:backfill-payroll-versions --dry-run')
            ->assertExitCode(0);

        $legacyPayroll->refresh();

        $this->assertNull($legacyPayroll->payroll_setting_version_id);
        $this->assertSame(1, PayrollSettingVersion::query()->count());
    }

    public function test_backfill_command_assigns_versions_by_effective_time_and_is_idempotent(): void
    {
        $this->activateTestLicense();

        $setting = PayrollSetting::current();

        Carbon::setTestNow('2026-04-01 08:00:00');
        $versionOne = $setting->resolveActiveVersion();

        Carbon::setTestNow('2026-05-10 08:00:00');
        $setting->update([
            'working_days_mode' => 'fixed',
            'default_working_days' => 20,
            'absent_deduction_rate' => 1.5,
            'rounding_mode' => 'floor',
            'rounding_unit' => 500,
            'note_template' => 'Version 2 {absent_days}',
        ]);
        $versionTwo = $setting->resolveActiveVersion();

        $aprilLegacyPayroll = $this->createPayrollWithTimestamp('2026-04-01', '2026-04-20 09:00:00');
        $juneLegacyPayroll = $this->createPayrollWithTimestamp('2026-06-01', '2026-05-20 09:00:00');
        $alreadyVersionedPayroll = $this->createPayrollWithTimestamp(
            '2026-07-01',
            '2026-05-21 09:00:00',
            (int) $versionOne->id
        );

        $this->artisan('payroll-settings:backfill-payroll-versions')
            ->assertExitCode(0);

        $aprilLegacyPayroll->refresh();
        $juneLegacyPayroll->refresh();
        $alreadyVersionedPayroll->refresh();

        $this->assertSame((int) $versionOne->id, (int) $aprilLegacyPayroll->payroll_setting_version_id);
        $this->assertSame((int) $versionTwo->id, (int) $juneLegacyPayroll->payroll_setting_version_id);
        $this->assertSame((int) $versionOne->id, (int) $alreadyVersionedPayroll->payroll_setting_version_id);

        $this->assertSame(0, Payroll::query()->whereNull('payroll_setting_version_id')->count());

        $this->artisan('payroll-settings:backfill-payroll-versions')
            ->assertExitCode(0);

        $aprilLegacyPayroll->refresh();
        $juneLegacyPayroll->refresh();

        $this->assertSame((int) $versionOne->id, (int) $aprilLegacyPayroll->payroll_setting_version_id);
        $this->assertSame((int) $versionTwo->id, (int) $juneLegacyPayroll->payroll_setting_version_id);
    }

    private function createPayrollWithTimestamp(string $salaryMonthDate, string $createdAt, ?int $versionId = null): Payroll
    {
        DB::table('payrolls')->insert([
            'salary_month' => $salaryMonthDate,
            'status' => 'pending',
            'payroll_setting_version_id' => $versionId,
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);

        return Payroll::query()
            ->whereDate('salary_month', $salaryMonthDate)
            ->firstOrFail();
    }
}
