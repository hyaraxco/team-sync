<?php

namespace Tests\Feature\Payroll;

use App\Models\PayrollSetting;
use App\Models\PayrollSettingVersion;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PayrollSettingVersionDiffTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_version_diff_returns_correct_changes(): void
    {
        $finance = $this->actingAsRole('finance');
        $setting = PayrollSetting::current();

        // Create version 1
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

        // Create version 2 with changes
        $v2 = PayrollSettingVersion::create([
            'payroll_setting_id' => $setting->id,
            'version_number' => 2,
            'payday_day' => 28,
            'attendance_cutoff_day' => 25,
            'working_days_mode' => 'fixed',
            'default_working_days' => 20,
            'absent_deduction_rate' => 1.50,
            'rounding_mode' => 'nearest',
            'rounding_unit' => 1000,
            'note_template' => 'Template v1',
            'updated_by' => $finance->id,
            'effective_at' => now(),
        ]);

        $response = $this->getJson("/api/v1/payroll-settings/versions/{$v2->id}/diff");

        $response->assertOk()
            ->assertJsonPath('data.version_number', 2)
            ->assertJsonPath('data.previous_version_number', 1)
            ->assertJsonPath('data.has_previous', true);

        $changes = $response->json('data.changes');
        $changedFields = array_column($changes, 'field');

        $this->assertContains('payday_day', $changedFields);
        $this->assertContains('working_days_mode', $changedFields);
        $this->assertContains('default_working_days', $changedFields);
        $this->assertContains('absent_deduction_rate', $changedFields);
        $this->assertNotContains('rounding_mode', $changedFields);
        $this->assertNotContains('note_template', $changedFields);
    }

    public function test_version_diff_for_first_version_has_no_previous(): void
    {
        $this->actingAsRole('finance');
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
            'note_template' => 'Template',
            'effective_at' => now(),
        ]);

        $response = $this->getJson("/api/v1/payroll-settings/versions/{$v1->id}/diff");

        $response->assertOk()
            ->assertJsonPath('data.has_previous', false)
            ->assertJsonPath('data.previous_version_number', null)
            ->assertJsonPath('data.changes', []);
    }

    public function test_version_diff_returns_404_for_nonexistent_version(): void
    {
        $this->actingAsRole('finance');

        $this->getJson('/api/v1/payroll-settings/versions/99999/diff')
            ->assertStatus(404);
    }

    private function actingAsRole(string $roleName): User
    {
        $user = User::factory()->create();
        $role = Role::findByName($roleName, 'sanctum');
        $user->assignRole($role);

        Sanctum::actingAs($user);

        return $user;
    }
}
