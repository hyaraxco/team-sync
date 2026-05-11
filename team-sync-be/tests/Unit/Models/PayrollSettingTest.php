<?php

use App\Models\PayrollSetting;
use App\Models\PayrollSettingVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('defaults returns array with expected keys', function () {
    $defaults = PayrollSetting::defaults();

    expect($defaults)->toBeArray()
        ->toHaveKeys([
            'payday_day',
            'attendance_cutoff_day',
            'working_days_mode',
            'default_working_days',
            'absent_deduction_rate',
            'rounding_mode',
            'rounding_unit',
            'note_template',
            'payroll_bank_name',
            'payroll_bank_code',
        ])
        ->and($defaults['payday_day'])->toBe(25)
        ->and($defaults['attendance_cutoff_day'])->toBe(25)
        ->and($defaults['working_days_mode'])->toBe('auto_business_days')
        ->and($defaults['default_working_days'])->toBe(22)
        ->and($defaults['absent_deduction_rate'])->toBe(1.00)
        ->and($defaults['rounding_mode'])->toBe('nearest')
        ->and($defaults['rounding_unit'])->toBe(1000)
        ->and($defaults['note_template'])->toBe(PayrollSetting::DEFAULT_NOTE_TEMPLATE)
        ->and($defaults['payroll_bank_name'])->toBeNull()
        ->and($defaults['payroll_bank_code'])->toBeNull();
});

it('current returns existing instance when one already exists', function () {
    $first = PayrollSetting::current();
    $second = PayrollSetting::current();

    expect($first->id)->toBe($second->id);
    $this->assertDatabaseCount('payroll_settings', 1);
});

it('current creates new record with defaults when none exists', function () {
    $setting = PayrollSetting::current();

    expect($setting)->toBeInstanceOf(PayrollSetting::class)
        ->and($setting->payday_day)->toBe(25)
        ->and($setting->working_days_mode)->toBe('auto_business_days');
    $this->assertDatabaseCount('payroll_settings', 1);
});

it('resolveActiveVersion creates new version when no prior version exists', function () {
    $setting = PayrollSetting::create([
        ...PayrollSetting::defaults(),
        'updated_by' => null,
    ]);

    $version = $setting->resolveActiveVersion();

    expect($version)->toBeInstanceOf(PayrollSettingVersion::class)
        ->and($version->version_number)->toBe(1)
        ->and($version->payday_day)->toBe((int) $setting->payday_day)
        ->and($version->attendance_cutoff_day)->toBe((int) $setting->attendance_cutoff_day)
        ->and($version->working_days_mode)->toBe((string) $setting->working_days_mode)
        ->and($version->default_working_days)->toBe((int) $setting->default_working_days)
        ->and($version->rounding_mode)->toBe((string) $setting->rounding_mode)
        ->and($version->rounding_unit)->toBe((int) $setting->rounding_unit);

    $this->assertDatabaseCount('payroll_setting_versions', 1);
});

it('resolveActiveVersion creates new version when fields differ from latest version', function () {
    $setting = PayrollSetting::create([
        ...PayrollSetting::defaults(),
        'updated_by' => null,
    ]);

    $setting->resolveActiveVersion();

    // Mutate a versioned field
    $setting->update([
        'default_working_days' => 24,
        'note_template' => 'Custom note template',
    ]);

    $newVersion = $setting->fresh()->resolveActiveVersion();

    expect($newVersion->version_number)->toBe(2)
        ->and($newVersion->default_working_days)->toBe(24)
        ->and($newVersion->note_template)->toBe('Custom note template');

    $this->assertDatabaseCount('payroll_setting_versions', 2);
});

it('resolveActiveVersion returns existing version when no changes', function () {
    $setting = PayrollSetting::create([
        ...PayrollSetting::defaults(),
        'updated_by' => null,
    ]);

    $firstVersion = $setting->resolveActiveVersion();
    $secondVersion = $setting->resolveActiveVersion();

    expect($firstVersion->id)->toBe($secondVersion->id)
        ->and($firstVersion->version_number)->toBe($secondVersion->version_number);

    $this->assertDatabaseCount('payroll_setting_versions', 1);
});

it('resolveActiveVersion creates new version when actorId is provided', function () {
    $user = User::factory()->create();

    $setting = PayrollSetting::create([
        ...PayrollSetting::defaults(),
        'updated_by' => null,
    ]);

    $version = $setting->resolveActiveVersion(actorId: $user->id);

    expect($version->updated_by)->toBe($user->id);
});

it('hasVersionMismatch detects changes in VERSIONED_FIELDS', function () {
    $setting = PayrollSetting::create([
        ...PayrollSetting::defaults(),
        'updated_by' => null,
    ]);

    $version = $setting->resolveActiveVersion();

    // Change a versioned field
    $setting->update(['default_working_days' => 20]);
    $setting->refresh();

    $newVersion = $setting->fresh()->resolveActiveVersion();

    expect($newVersion->version_number)->toBe(2)
        ->and($newVersion->default_working_days)->toBe(20);

    $this->assertDatabaseCount('payroll_setting_versions', 2);
});

it('hasVersionMismatch returns false when no changes', function () {
    $setting = PayrollSetting::create([
        ...PayrollSetting::defaults(),
        'updated_by' => null,
    ]);

    $setting->resolveActiveVersion();

    // "Update" without changing versioned fields (payroll_bank_name is NOT in VERSIONED_FIELDS)
    $setting->update(['payroll_bank_name' => 'BCA']);
    $setting->refresh();

    $reusedVersion = $setting->fresh()->resolveActiveVersion();

    expect($reusedVersion->version_number)->toBe(1);

    $this->assertDatabaseCount('payroll_setting_versions', 1);
});

it('normalizeSnapshotValue handles absent_deduction_rate with float precision', function () {
    $setting = PayrollSetting::create([
        ...PayrollSetting::defaults(),
        'absent_deduction_rate' => 1.00,
        'updated_by' => null,
    ]);

    // Version with exactly same values should reuse
    $version = $setting->resolveActiveVersion();

    // Update to a value that might have float precision issues
    $setting->update(['absent_deduction_rate' => 1.00]);
    $setting->refresh();

    $reused = $setting->fresh()->resolveActiveVersion();

    expect($reused->id)->toBe($version->id);

    $this->assertDatabaseCount('payroll_setting_versions', 1);
});

it('normalizeSnapshotValue handles note_template by trimming strings', function () {
    $setting = PayrollSetting::create([
        ...PayrollSetting::defaults(),
        'note_template' => 'same content',
        'updated_by' => null,
    ]);

    // First version stores 'same content' (already trimmed)
    $version = $setting->resolveActiveVersion();

    // Update with leading/trailing whitespace — toVersionAttributes() trims it,
    // so the snapshot becomes 'same content' again, matching the stored version.
    $setting->update(['note_template' => '  same content  ']);
    $setting->refresh();

    $reused = $setting->fresh()->resolveActiveVersion();

    expect($reused->id)->toBe($version->id);

    $this->assertDatabaseCount('payroll_setting_versions', 1);
});

it('resolveActiveVersion handles null note_template by using default', function () {
    $setting = PayrollSetting::create([
        ...PayrollSetting::defaults(),
        'note_template' => null,
        'updated_by' => null,
    ]);

    $version = $setting->resolveActiveVersion();

    expect($version->note_template)->toBe(PayrollSetting::DEFAULT_NOTE_TEMPLATE);
});

it('resolveActiveVersion increments version_number sequentially', function () {
    $setting = PayrollSetting::create([
        ...PayrollSetting::defaults(),
        'updated_by' => null,
    ]);

    $v1 = $setting->resolveActiveVersion();

    $setting->update(['default_working_days' => 20]);
    $setting->refresh();
    $v2 = $setting->fresh()->resolveActiveVersion();

    $setting->update(['default_working_days' => 24]);
    $setting->refresh();
    $v3 = $setting->fresh()->resolveActiveVersion();

    expect($v1->version_number)->toBe(1)
        ->and($v2->version_number)->toBe(2)
        ->and($v3->version_number)->toBe(3);

    $this->assertDatabaseCount('payroll_setting_versions', 3);
});
