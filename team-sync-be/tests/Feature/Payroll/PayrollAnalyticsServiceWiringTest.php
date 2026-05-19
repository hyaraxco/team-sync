<?php

namespace Tests\Feature\Payroll;

use App\Models\PayrollSetting;
use App\Models\PayrollSettingVersion;
use App\Models\User;
use App\Services\Payroll\PayrollAnalyticsService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\ActivatesLicense;
use Tests\TestCase;

class PayrollAnalyticsServiceWiringTest extends TestCase
{
    use ActivatesLicense;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->activateTestLicense();
        $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_payroll_analytics_service_is_resolvable_from_container(): void
    {
        $service = app(PayrollAnalyticsService::class);

        $this->assertInstanceOf(PayrollAnalyticsService::class, $service);
    }

    public function test_analytics_endpoint_returns_200_via_service(): void
    {
        $finance = User::factory()->create();
        $finance->assignRole('finance');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Sanctum::actingAs($finance);

        $this->getJson('/api/v1/payrolls/analytics')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'message', 'data' => ['periods_requested', 'trends']]);
    }

    public function test_comparison_endpoint_returns_200_via_service(): void
    {
        $finance = User::factory()->create();
        $finance->assignRole('finance');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Sanctum::actingAs($finance);

        $this->getJson('/api/v1/payrolls/compare?month1=2026-01&month2=2026-02')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'message', 'data' => ['month1', 'month2', 'variances']]);
    }

    public function test_version_diff_endpoint_returns_200_via_service(): void
    {
        $finance = User::factory()->create();
        $finance->assignRole('finance');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Sanctum::actingAs($finance);

        $setting = PayrollSetting::current();

        $v1 = PayrollSettingVersion::create([
            'payroll_setting_id' => $setting->id,
            'version_number' => 1,
            'payday_day' => 25,
            'attendance_cutoff_day' => 25,
            'working_days_mode' => 'auto_business_days',
            'default_working_days' => 22,
            'absent_deduction_rate' => 1.00,
            'rounding_mode' => 'nearest',
            'rounding_unit' => 1000,
            'note_template' => 'Template v1',
            'updated_by' => $finance->id,
            'effective_at' => now()->subDays(10),
        ]);

        $v2 = PayrollSettingVersion::create([
            'payroll_setting_id' => $setting->id,
            'version_number' => 2,
            'payday_day' => 28,
            'attendance_cutoff_day' => 25,
            'working_days_mode' => 'auto_business_days',
            'default_working_days' => 22,
            'absent_deduction_rate' => 1.00,
            'rounding_mode' => 'nearest',
            'rounding_unit' => 1000,
            'note_template' => 'Template v1',
            'updated_by' => $finance->id,
            'effective_at' => now()->subDays(5),
        ]);

        $this->getJson("/api/v1/payroll-settings/versions/{$v2->id}/diff")
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.version_id', $v2->id)
            ->assertJsonStructure(['data' => ['version_id', 'version_number', 'changes']]);
    }
}
