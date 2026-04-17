<?php

namespace Tests\Unit\Models;

use App\Models\PayrollSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollSettingTest extends TestCase
{
    use RefreshDatabase;

    public function test_defaults_return_expected_baseline_configuration(): void
    {
        $defaults = PayrollSetting::defaults();

        $this->assertSame(25, $defaults['payday_day']);
        $this->assertSame(25, $defaults['attendance_cutoff_day']);
        $this->assertSame('auto_business_days', $defaults['working_days_mode']);
        $this->assertSame(PayrollSetting::DEFAULT_NOTE_TEMPLATE, $defaults['note_template']);
    }

    public function test_current_creates_default_settings_once(): void
    {
        $first = PayrollSetting::current();
        $second = PayrollSetting::current();

        $this->assertTrue($first->is($second));
        $this->assertDatabaseCount('payroll_settings', 1);
    }

    public function test_resolve_active_version_creates_initial_snapshot(): void
    {
        $setting = PayrollSetting::create([
            ...PayrollSetting::defaults(),
            'updated_by' => null,
        ]);

        $version = $setting->resolveActiveVersion();

        $this->assertSame(1, $version->version_number);
        $this->assertSame((int) $setting->payday_day, $version->payday_day);
        $this->assertDatabaseCount('payroll_setting_versions', 1);
    }

    public function test_resolve_active_version_reuses_existing_snapshot_when_unchanged(): void
    {
        $setting = PayrollSetting::create([
            ...PayrollSetting::defaults(),
            'updated_by' => null,
        ]);

        $firstVersion = $setting->resolveActiveVersion();
        $secondVersion = $setting->resolveActiveVersion();

        $this->assertTrue($firstVersion->is($secondVersion));
        $this->assertDatabaseCount('payroll_setting_versions', 1);
    }

    public function test_resolve_active_version_creates_new_snapshot_when_versioned_fields_change(): void
    {
        $setting = PayrollSetting::create([
            ...PayrollSetting::defaults(),
            'updated_by' => null,
        ]);

        $setting->resolveActiveVersion();

        $setting->update([
            'default_working_days' => 24,
            'note_template' => 'Custom note template',
        ]);

        $newVersion = $setting->fresh()->resolveActiveVersion();

        $this->assertSame(2, $newVersion->version_number);
        $this->assertSame(24, $newVersion->default_working_days);
        $this->assertSame('Custom note template', $newVersion->note_template);
        $this->assertDatabaseCount('payroll_setting_versions', 2);
    }
}
