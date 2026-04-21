<?php

namespace Tests\Feature\Payroll;

use App\Interfaces\PayrollRepositoryInterface;
use App\Models\Attendance;
use App\Models\StaffMemberProfile;
use App\Models\JobInformation;
use App\Models\Payroll;
use App\Models\PayrollSetting;
use App\Models\PayrollSettingVersion;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PayrollSettingsTest extends TestCase
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

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_finance_can_view_default_payroll_settings(): void
    {
        $this->actingAsRole('finance');

        $this->getJson('/api/v1/payroll-settings')
            ->assertOk()
            ->assertJsonPath('data.payday_day', 25)
            ->assertJsonPath('data.attendance_cutoff_day', 25)
            ->assertJsonPath('data.working_days_mode', 'auto_business_days')
            ->assertJsonPath('data.rounding_mode', 'nearest')
            ->assertJsonPath('data.active_version.version_number', 1)
            ->assertJsonPath('data.active_version.payday_day', 25);

        $this->assertDatabaseHas('payroll_setting_versions', [
            'version_number' => 1,
            'payday_day' => 25,
        ]);
    }

    public function test_finance_can_update_payroll_settings_and_actor_is_recorded(): void
    {
        $finance = $this->actingAsRole('finance');

        $this->putJson('/api/v1/payroll-settings', [
            'payday_day' => 27,
            'attendance_cutoff_day' => 24,
            'working_days_mode' => 'fixed',
            'default_working_days' => 20,
            'absent_deduction_rate' => 1.5,
            'rounding_mode' => 'floor',
            'rounding_unit' => 500,
            'note_template' => 'Alpha {absent_days} | Potongan Rp {deduction}',
        ])->assertOk()
            ->assertJsonPath('data.payday_day', 27)
            ->assertJsonPath('data.updated_by.email', $finance->email)
            ->assertJsonPath('data.active_version.version_number', 1);

        $this->putJson('/api/v1/payroll-settings', [
            'payday_day' => 28,
            'attendance_cutoff_day' => 24,
            'working_days_mode' => 'fixed',
            'default_working_days' => 20,
            'absent_deduction_rate' => 1.5,
            'rounding_mode' => 'floor',
            'rounding_unit' => 500,
            'note_template' => 'Alpha {absent_days} | Potongan Rp {deduction}',
        ])->assertOk()
            ->assertJsonPath('data.payday_day', 28)
            ->assertJsonPath('data.active_version.version_number', 2);

        $setting = PayrollSetting::current();
        $latestVersion = PayrollSettingVersion::query()->latest('version_number')->firstOrFail();

        $this->assertSame(28, $setting->payday_day);
        $this->assertSame(24, $setting->attendance_cutoff_day);
        $this->assertSame('fixed', $setting->working_days_mode);
        $this->assertSame(20, $setting->default_working_days);
        $this->assertSame('1.50', $setting->absent_deduction_rate);
        $this->assertSame('floor', $setting->rounding_mode);
        $this->assertSame(500, $setting->rounding_unit);
        $this->assertSame($finance->id, $setting->updated_by);

        $this->assertSame(2, PayrollSettingVersion::query()->count());
        $this->assertSame(2, (int) $latestVersion->version_number);
        $this->assertSame(28, (int) $latestVersion->payday_day);
        $this->assertSame($finance->id, $latestVersion->updated_by);
    }

    public function test_finance_can_view_payroll_setting_history_versions(): void
    {
        $this->actingAsRole('finance');

        $this->putJson('/api/v1/payroll-settings', [
            'payday_day' => 26,
            'attendance_cutoff_day' => 24,
            'working_days_mode' => 'fixed',
            'default_working_days' => 21,
            'absent_deduction_rate' => 1.25,
            'rounding_mode' => 'nearest',
            'rounding_unit' => 1000,
            'note_template' => 'Template V1 {absent_days}',
        ])->assertOk();

        $this->putJson('/api/v1/payroll-settings', [
            'payday_day' => 27,
            'attendance_cutoff_day' => 23,
            'working_days_mode' => 'fixed',
            'default_working_days' => 20,
            'absent_deduction_rate' => 1.50,
            'rounding_mode' => 'floor',
            'rounding_unit' => 500,
            'note_template' => 'Template V2 {absent_days}',
        ])->assertOk();

        $this->getJson('/api/v1/payroll-settings/history')
            ->assertOk()
            ->assertJsonPath('data.0.version_number', 2)
            ->assertJsonPath('data.1.version_number', 1);
    }

    public function test_hr_cannot_access_payroll_settings(): void
    {
        $this->actingAsRole('hr');

        $this->getJson('/api/v1/payroll-settings')->assertForbidden();
        $this->getJson('/api/v1/payroll-settings/history')->assertForbidden();
        $this->putJson('/api/v1/payroll-settings', [
            'payday_day' => 26,
            'attendance_cutoff_day' => 24,
            'working_days_mode' => 'fixed',
            'default_working_days' => 21,
            'absent_deduction_rate' => 1,
            'rounding_mode' => 'nearest',
            'rounding_unit' => 1000,
            'note_template' => PayrollSetting::DEFAULT_NOTE_TEMPLATE,
        ])->assertForbidden();
    }

    public function test_generated_payroll_uses_active_settings_for_future_drafts(): void
    {
        Carbon::setTestNow('2026-04-28 09:00:00');
        PayrollSetting::current()->update([
            'working_days_mode' => 'fixed',
            'default_working_days' => 20,
            'absent_deduction_rate' => 1.5,
            'rounding_mode' => 'nearest',
            'rounding_unit' => 1000,
            'note_template' => 'Alpha {absent_days} | Potongan Rp {deduction}',
        ]);

        $employee = StaffMemberProfile::withoutSyncingToSearch(function () {
            return StaffMemberProfile::factory()->create();
        });

        JobInformation::factory()
            ->forEmployee($employee)
            ->active()
            ->state([
                'monthly_salary' => 10000000,
                'status' => 'active',
                'employment_type' => 'full_time',
                'work_location' => 'remote',
            ])
            ->create();

        $month = now()->startOfMonth();
        $absentDates = [
            $month->copy()->startOfMonth()->addDay()->toDateString(),
            $month->copy()->startOfMonth()->addDays(2)->toDateString(),
        ];

        $this->seedFullMonthAttendance($employee, $month, $absentDates);

        $payroll = app(PayrollRepositoryInterface::class)->generatePayroll($month->format('Y-m'));
        $detail = $payroll->payrollDetails()->firstOrFail();

        $this->assertSame(8500000.0, (float) $detail->final_salary);
        $this->assertSame('Alpha 2 | Potongan Rp 1.500.000', $detail->notes);
        $this->assertNotNull($payroll->payroll_setting_version_id);
        $this->assertDatabaseHas('payroll_setting_versions', [
            'id' => $payroll->payroll_setting_version_id,
            'working_days_mode' => 'fixed',
            'default_working_days' => 20,
            'absent_deduction_rate' => 1.50,
        ]);
    }

    public function test_existing_payroll_keeps_its_setting_version_after_new_setting_update(): void
    {
        Carbon::setTestNow('2026-06-28 09:00:00');
        PayrollSetting::current()->update([
            'working_days_mode' => 'fixed',
            'default_working_days' => 20,
            'absent_deduction_rate' => 1.0,
            'rounding_mode' => 'nearest',
            'rounding_unit' => 1000,
            'note_template' => 'Version 1 {absent_days}',
        ]);

        $employee = StaffMemberProfile::withoutSyncingToSearch(function () {
            return StaffMemberProfile::factory()->create();
        });

        JobInformation::factory()
            ->forEmployee($employee)
            ->active()
            ->state([
                'monthly_salary' => 10000000,
                'status' => 'active',
                'employment_type' => 'full_time',
                'work_location' => 'remote',
            ])
            ->create();

        $aprilMonth = Carbon::createFromFormat('Y-m-d', '2026-04-01')->startOfMonth();
        $mayMonth = Carbon::createFromFormat('Y-m-d', '2026-05-01')->startOfMonth();

        $this->seedFullMonthAttendance($employee, $aprilMonth);
        $this->seedFullMonthAttendance($employee, $mayMonth);

        $repository = app(PayrollRepositoryInterface::class);
        $aprilPayroll = $repository->generatePayroll($aprilMonth->format('Y-m'));
        $aprilVersionId = (int) $aprilPayroll->payroll_setting_version_id;

        PayrollSetting::current()->update([
            'working_days_mode' => 'fixed',
            'default_working_days' => 24,
            'absent_deduction_rate' => 1.5,
            'rounding_mode' => 'floor',
            'rounding_unit' => 500,
            'note_template' => 'Version 2 {absent_days}',
        ]);

        $mayPayroll = $repository->generatePayroll($mayMonth->format('Y-m'));

        $aprilPayroll = Payroll::with('payrollSettingVersion')->findOrFail($aprilPayroll->id);
        $mayPayroll = Payroll::with('payrollSettingVersion')->findOrFail($mayPayroll->id);

        $this->assertSame($aprilVersionId, (int) $aprilPayroll->payroll_setting_version_id);
        $this->assertNotSame(
            (int) $aprilPayroll->payroll_setting_version_id,
            (int) $mayPayroll->payroll_setting_version_id
        );

        $this->assertSame(20, (int) $aprilPayroll->payrollSettingVersion->default_working_days);
        $this->assertSame('nearest', $aprilPayroll->payrollSettingVersion->rounding_mode);
        $this->assertSame(24, (int) $mayPayroll->payrollSettingVersion->default_working_days);
        $this->assertSame('floor', $mayPayroll->payrollSettingVersion->rounding_mode);
    }

    public function test_payroll_resource_marks_legacy_when_setting_version_is_missing(): void
    {
        $finance = $this->actingAsRole('finance');

        $legacyPayroll = Payroll::create([
            'salary_month' => '2026-07-01',
            'status' => 'pending',
        ]);

        $this->getJson("/api/v1/payrolls/{$legacyPayroll->id}")
            ->assertOk()
            ->assertJsonPath('data.is_legacy_settings_version', true)
            ->assertJsonPath('data.payroll_setting_version_id', null)
            ->assertJsonPath('data.payroll_setting_version', null);

        $version = PayrollSetting::current()->resolveActiveVersion($finance->id);
        $legacyPayroll->update([
            'payroll_setting_version_id' => $version->id,
        ]);

        $this->getJson("/api/v1/payrolls/{$legacyPayroll->id}")
            ->assertOk()
            ->assertJsonPath('data.is_legacy_settings_version', false)
            ->assertJsonPath('data.payroll_setting_version_id', $version->id)
            ->assertJsonPath('data.payroll_setting_version.version_number', $version->version_number);
    }

    private function actingAsRole(string $roleName): User
    {
        $user = User::factory()->create();
        $role = Role::findByName($roleName, 'sanctum');
        $user->assignRole($role);

        Sanctum::actingAs($user);

        return $user;
    }

    private function seedFullMonthAttendance(StaffMemberProfile $employee, Carbon $month, array $absentDates = []): void
    {
        $absentDateLookup = array_fill_keys($absentDates, true);
        $cursor = $month->copy()->startOfMonth();
        $monthEnd = $month->copy()->endOfMonth();

        while ($cursor->lte($monthEnd)) {
            if (! $cursor->isWeekend()) {
                $dateKey = $cursor->toDateString();
                $status = isset($absentDateLookup[$dateKey]) ? 'absent' : 'present';

                Attendance::create([
                    'staff_member_id' => $employee->id,
                    'date' => $dateKey,
                    'status' => $status,
                    'check_in' => $status === 'present'
                        ? $cursor->copy()->format('Y-m-d').' 08:00:00'
                        : null,
                    'check_out' => $status === 'present'
                        ? $cursor->copy()->format('Y-m-d').' 17:00:00'
                        : null,
                ]);
            }

            $cursor->addDay();
        }
    }
}
