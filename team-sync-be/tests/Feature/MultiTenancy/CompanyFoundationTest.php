<?php

namespace Tests\Feature\MultiTenancy;

use App\Models\AttendancePeriod;
use App\Models\Company;
use App\Models\Payroll;
use App\Models\PayrollSetting;
use App\Models\StaffMemberProfile;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\CompanySeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CompanyFoundationTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------
    // Company CRUD
    // ---------------------------------------------------------------

    public function test_company_can_be_created(): void
    {
        $company = Company::factory()->create([
            'name' => 'Acme Corp',
            'slug' => 'acme-corp',
        ]);

        $this->assertDatabaseHas('companies', [
            'id' => $company->id,
            'name' => 'Acme Corp',
            'slug' => 'acme-corp',
            'timezone' => 'Asia/Jakarta',
            'locale' => 'id',
            'currency' => 'IDR',
            'is_active' => true,
        ]);
    }

    public function test_company_seeder_creates_default_company(): void
    {
        $this->seed(CompanySeeder::class);

        $this->assertDatabaseHas('companies', [
            'name' => 'Team Sync Pro',
            'slug' => 'team-sync-pro',
        ]);
    }

    public function test_company_seeder_is_idempotent(): void
    {
        $this->seed(CompanySeeder::class);
        $this->seed(CompanySeeder::class);

        $this->assertSame(1, Company::where('slug', 'team-sync-pro')->count());
    }

    // ---------------------------------------------------------------
    // Company::current()
    // ---------------------------------------------------------------

    public function test_company_current_returns_default_company(): void
    {
        $this->seed(CompanySeeder::class);

        $current = Company::current();

        $this->assertNotNull($current);
        $this->assertSame('team-sync-pro', $current->slug);
    }

    public function test_company_current_returns_null_when_no_companies_exist(): void
    {
        $this->assertNull(Company::current());
    }

    // ---------------------------------------------------------------
    // BelongsToCompany trait
    // ---------------------------------------------------------------

    public function test_user_belongs_to_company(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        $this->assertTrue($user->company->is($company));
    }

    public function test_staff_member_profile_belongs_to_company(): void
    {
        $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $company = Company::factory()->create();
        $profile = StaffMemberProfile::factory()->create(['company_id' => $company->id]);

        $this->assertTrue($profile->company->is($company));
    }

    public function test_team_belongs_to_company(): void
    {
        $company = Company::factory()->create();
        $team = Team::factory()->create(['company_id' => $company->id]);

        $this->assertTrue($team->company->is($company));
    }

    public function test_payroll_belongs_to_company(): void
    {
        $company = Company::factory()->create();
        $payroll = Payroll::factory()->create(['company_id' => $company->id]);

        $this->assertTrue($payroll->company->is($company));
    }

    public function test_attendance_period_belongs_to_company(): void
    {
        $company = Company::factory()->create();
        $period = AttendancePeriod::factory()->create(['company_id' => $company->id]);

        $this->assertTrue($period->company->is($company));
    }

    public function test_payroll_setting_belongs_to_company(): void
    {
        $company = Company::factory()->create();
        $setting = PayrollSetting::create(PayrollSetting::defaults());
        $setting->company()->associate($company);
        $setting->save();

        $this->assertTrue($setting->fresh()->company->is($company));
    }

    // ---------------------------------------------------------------
    // Company hasMany relationships
    // ---------------------------------------------------------------

    public function test_company_has_many_users(): void
    {
        $company = Company::factory()->create();
        User::factory()->count(3)->create(['company_id' => $company->id]);

        $this->assertCount(3, $company->users);
    }

    public function test_company_has_many_teams(): void
    {
        $company = Company::factory()->create();
        Team::factory()->count(2)->create(['company_id' => $company->id]);

        $this->assertCount(2, $company->teams);
    }

    // ---------------------------------------------------------------
    // Nullable company_id — existing data is not broken
    // ---------------------------------------------------------------

    public function test_records_can_exist_without_company_id(): void
    {
        $user = User::factory()->create();
        $this->assertNull($user->company_id);
        $this->assertNull($user->company);

        $payroll = Payroll::factory()->create();
        $this->assertNull($payroll->company_id);

        $period = AttendancePeriod::factory()->create();
        $this->assertNull($period->company_id);
    }

    // ---------------------------------------------------------------
    // Backfill command
    // ---------------------------------------------------------------

    public function test_backfill_command_assigns_records_to_default_company(): void
    {
        $this->seed(CompanySeeder::class);
        $company = Company::where('slug', 'team-sync-pro')->first();

        // Create records without company_id
        $user = User::factory()->create();
        $payroll = Payroll::factory()->create();
        $period = AttendancePeriod::factory()->create();
        $team = Team::factory()->create();

        $this->assertNull($user->fresh()->company_id);

        $this->artisan('tenant:backfill')
            ->assertExitCode(0);

        $this->assertSame($company->id, $user->fresh()->company_id);
        $this->assertSame($company->id, $payroll->fresh()->company_id);
        $this->assertSame($company->id, $period->fresh()->company_id);
        $this->assertSame($company->id, $team->fresh()->company_id);
    }

    public function test_backfill_command_dry_run_does_not_modify_records(): void
    {
        $this->seed(CompanySeeder::class);

        $user = User::factory()->create();

        $this->artisan('tenant:backfill --dry-run')
            ->assertExitCode(0);

        $this->assertNull($user->fresh()->company_id);
    }

    public function test_backfill_command_fails_without_default_company(): void
    {
        $this->artisan('tenant:backfill')
            ->assertExitCode(1);
    }

    public function test_backfill_command_is_idempotent(): void
    {
        $this->seed(CompanySeeder::class);
        $company = Company::where('slug', 'team-sync-pro')->first();

        $user = User::factory()->create();

        $this->artisan('tenant:backfill')->assertExitCode(0);
        $this->artisan('tenant:backfill')->assertExitCode(0);

        $this->assertSame($company->id, $user->fresh()->company_id);
    }

    // ---------------------------------------------------------------
    // Regression: existing payroll flow still works
    // ---------------------------------------------------------------

    public function test_payroll_setting_current_still_works(): void
    {
        $setting = PayrollSetting::current();

        $this->assertNotNull($setting);
        $this->assertSame(25, $setting->payday_day);
    }

    public function test_payroll_can_be_created_and_queried(): void
    {
        $payroll = Payroll::factory()->create(['status' => 'pending']);

        $this->assertDatabaseHas('payrolls', [
            'id' => $payroll->id,
            'status' => 'pending',
        ]);

        $found = Payroll::find($payroll->id);
        $this->assertNotNull($found);
        $this->assertSame('pending', $found->status);
    }

    public function test_attendance_period_can_be_created_and_queried(): void
    {
        $period = AttendancePeriod::factory()->create([
            'status' => AttendancePeriod::STATUS_OPEN,
        ]);

        $this->assertDatabaseHas('attendance_periods', [
            'id' => $period->id,
            'status' => 'open',
        ]);
    }

    // ---------------------------------------------------------------
    // Middleware (unit-level)
    // ---------------------------------------------------------------

    public function test_resolve_company_context_middleware_sets_singleton(): void
    {
        $company = Company::factory()->create();
        $user = User::factory()->create(['company_id' => $company->id]);

        $this->actingAs($user);

        $middleware = new \App\Http\Middleware\ResolveCompanyContext;
        $request = \Illuminate\Http\Request::create('/test');

        $middleware->handle($request, function () {
            // no-op
            return response('ok');
        });

        $this->assertTrue(app()->bound('current_company'));
        $this->assertTrue(app('current_company')->is($company));
    }

    public function test_resolve_company_context_middleware_skips_when_no_user(): void
    {
        $middleware = new \App\Http\Middleware\ResolveCompanyContext;
        $request = \Illuminate\Http\Request::create('/test');

        $middleware->handle($request, function () {
            return response('ok');
        });

        $this->assertFalse(app()->bound('current_company'));
    }
}
