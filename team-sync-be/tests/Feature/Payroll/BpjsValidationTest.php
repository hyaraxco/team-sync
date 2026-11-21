<?php

namespace Tests\Feature\Payroll;

use App\Models\BpjsRate;
use App\Models\User;
use App\Services\Payroll\TaxCalculationService;
use Carbon\Carbon;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\PtkpAmountSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\TaxBracketSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\ActivatesLicense;
use Tests\TestCase;

class BpjsValidationTest extends TestCase
{
    use ActivatesLicense, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->activateTestLicense();

        $this->seed([
            RoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,
            TaxBracketSeeder::class,
            PtkpAmountSeeder::class,
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function seedSinglePeriodRates(array $overrides = []): void
    {
        $defaults = [
            'effective_date' => Carbon::now()->subMonths(3)->toDateString(),
            'valid_until' => Carbon::now()->addYear()->toDateString(),
        ];

        $opts = array_merge($defaults, $overrides);

        BpjsRate::create(['component' => 'jht', 'employee_rate' => 2.00, 'employer_rate' => 3.70, 'max_salary_base' => null, 'effective_date' => $opts['effective_date'], 'valid_until' => $opts['valid_until'], 'description' => 'JHT']);
        BpjsRate::create(['component' => 'jkk', 'employee_rate' => 0.00, 'employer_rate' => 0.24, 'max_salary_base' => null, 'effective_date' => $opts['effective_date'], 'valid_until' => $opts['valid_until'], 'description' => 'JKK']);
        BpjsRate::create(['component' => 'jkm', 'employee_rate' => 0.00, 'employer_rate' => 0.30, 'max_salary_base' => null, 'effective_date' => $opts['effective_date'], 'valid_until' => $opts['valid_until'], 'description' => 'JKM']);
        BpjsRate::create(['component' => 'jp', 'employee_rate' => 1.00, 'employer_rate' => 2.00, 'max_salary_base' => 10_042_300, 'effective_date' => $opts['effective_date'], 'valid_until' => $opts['valid_until'], 'description' => 'JP']);
        BpjsRate::create(['component' => 'bpjs_kesehatan', 'employee_rate' => 1.00, 'employer_rate' => 4.00, 'max_salary_base' => 12_000_000, 'effective_date' => $opts['effective_date'], 'valid_until' => $opts['valid_until'], 'description' => 'Kesehatan']);
    }

    /** @test */
    public function test_validate_bpjs_rates_returns_valid_when_rates_are_current(): void
    {
        $this->seedSinglePeriodRates();

        $service = app(TaxCalculationService::class);
        $result = $service->validateBpjsRates();

        $this->assertTrue($result['is_valid']);
        $this->assertEmpty($result['warnings']);
        $this->assertNotEmpty($result['rates']);
    }

    /** @test */
    public function test_validate_bpjs_rates_detects_expired_rates(): void
    {
        $this->seedSinglePeriodRates();

        BpjsRate::where('component', 'jp')->update([
            'valid_until' => Carbon::now()->subMonth()->toDateString(),
        ]);

        $service = app(TaxCalculationService::class);
        $result = $service->validateBpjsRates();

        $this->assertFalse($result['is_valid']);

        $expiredWarnings = array_filter($result['warnings'], fn ($w) => $w['type'] === 'expired');
        $this->assertNotEmpty($expiredWarnings);

        $jpExpired = array_filter($expiredWarnings, fn ($w) => $w['component'] === 'jp');
        $this->assertNotEmpty($jpExpired);
    }

    /** @test */
    public function test_validate_bpjs_rates_detects_potentially_outdated_rates(): void
    {
        $this->seedSinglePeriodRates([
            'effective_date' => Carbon::now()->subMonths(13)->toDateString(),
            'valid_until' => null,
        ]);

        $service = app(TaxCalculationService::class);
        $result = $service->validateBpjsRates();

        $this->assertFalse($result['is_valid']);

        $outdatedWarnings = array_filter($result['warnings'], fn ($w) => $w['type'] === 'potentially_outdated');
        $this->assertNotEmpty($outdatedWarnings);
    }

    /** @test */
    public function test_validate_bpjs_rates_falls_back_to_updated_at_for_staleness(): void
    {
        $this->seedSinglePeriodRates([
            'effective_date' => Carbon::now()->subMonths(14)->toDateString(),
            'valid_until' => null,
        ]);

        $service = app(TaxCalculationService::class);
        $result = $service->validateBpjsRates();

        $this->assertFalse($result['is_valid']);

        $outdatedWarnings = array_filter($result['warnings'], fn ($w) => $w['type'] === 'potentially_outdated');
        $this->assertNotEmpty($outdatedWarnings);
    }

    /** @test */
    public function test_validate_bpjs_rates_detects_missing_components(): void
    {
        $this->seedSinglePeriodRates();

        BpjsRate::where('component', 'jkm')->delete();

        $service = app(TaxCalculationService::class);
        $result = $service->validateBpjsRates();

        $this->assertFalse($result['is_valid']);

        $missingWarnings = array_filter($result['warnings'], fn ($w) => $w['type'] === 'missing_component');
        $this->assertNotEmpty($missingWarnings);

        $jkmMissing = array_filter($missingWarnings, fn ($w) => $w['component'] === 'jkm');
        $this->assertNotEmpty($jkmMissing);
    }

    /** @test */
    public function test_validate_bpjs_rates_returns_rate_details(): void
    {
        $this->seedSinglePeriodRates();

        $service = app(TaxCalculationService::class);
        $result = $service->validateBpjsRates();

        $this->assertArrayHasKey('rates', $result);
        $this->assertArrayHasKey('jp', $result['rates']);
        $this->assertArrayHasKey('bpjs_kesehatan', $result['rates']);

        $jpRate = $result['rates']['jp'];
        $this->assertArrayHasKey('effective_date', $jpRate);
        $this->assertArrayHasKey('valid_until', $jpRate);
        $this->assertArrayHasKey('is_expired', $jpRate);
        $this->assertArrayHasKey('is_potentially_outdated', $jpRate);
        $this->assertFalse($jpRate['is_expired']);
        $this->assertFalse($jpRate['is_potentially_outdated']);
    }

    /** @test */
    public function test_bpjs_cap_warnings_for_salary_below_all_caps(): void
    {
        $this->seedSinglePeriodRates();

        $service = app(TaxCalculationService::class);
        $result = $service->getBpjsCapWarnings(8_000_000);

        $this->assertEquals(8_000_000, $result['gross_salary']);
        $this->assertNotEmpty($result['caps_applied']);

        foreach ($result['caps_applied'] as $cap) {
            $this->assertFalse($cap['salary_exceeds_cap']);
            $this->assertEquals(0, $cap['excess_amount']);
        }
    }

    /** @test */
    public function test_bpjs_cap_warnings_for_salary_above_jp_cap(): void
    {
        $this->seedSinglePeriodRates();

        $service = app(TaxCalculationService::class);
        $result = $service->getBpjsCapWarnings(15_000_000);

        $jpCap = collect($result['caps_applied'])->firstWhere('component', 'jp');
        $this->assertNotNull($jpCap);
        $this->assertTrue($jpCap['salary_exceeds_cap']);
        $this->assertEquals(10_042_300, $jpCap['cap_amount']);
        $this->assertEqualsWithDelta(4_957_700, $jpCap['excess_amount'], 0.01);
        $this->assertEquals(10_042_300, $jpCap['capped_base']);
    }

    /** @test */
    public function test_bpjs_cap_warnings_for_salary_above_all_caps(): void
    {
        $this->seedSinglePeriodRates();

        $service = app(TaxCalculationService::class);
        $result = $service->getBpjsCapWarnings(20_000_000);

        $capsExceeded = collect($result['caps_applied'])->filter(fn ($c) => $c['salary_exceeds_cap']);
        $this->assertCount(2, $capsExceeded);

        $jpCap = $capsExceeded->firstWhere('component', 'jp');
        $kesCap = $capsExceeded->firstWhere('component', 'bpjs_kesehatan');

        $this->assertNotNull($jpCap);
        $this->assertNotNull($kesCap);
        $this->assertEquals(10_042_300, $jpCap['capped_base']);
        $this->assertEquals(12_000_000, $kesCap['capped_base']);
    }

    /** @test */
    public function test_bpjs_cap_warnings_only_includes_capped_components(): void
    {
        $this->seedSinglePeriodRates();

        $service = app(TaxCalculationService::class);
        $result = $service->getBpjsCapWarnings(8_000_000);

        $components = collect($result['caps_applied'])->pluck('component')->all();
        $this->assertContains('jp', $components);
        $this->assertContains('bpjs_kesehatan', $components);
        $this->assertNotContains('jht', $components);
        $this->assertNotContains('jkk', $components);
        $this->assertNotContains('jkm', $components);
    }

    /** @test */
    public function test_bpjs_validation_endpoint_returns_success(): void
    {
        $this->seedSinglePeriodRates();

        $user = $this->actingAsRole('finance');

        $response = $this->getJson('/api/v1/payroll-settings/bpjs-validation');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_valid', true)
            ->assertJsonPath('data.warnings', []);
    }

    /** @test */
    public function test_bpjs_validation_endpoint_returns_warnings_for_expired_rates(): void
    {
        $this->seedSinglePeriodRates();

        BpjsRate::where('component', 'jp')->update([
            'valid_until' => Carbon::now()->subMonth()->toDateString(),
        ]);

        $user = $this->actingAsRole('finance');

        $response = $this->getJson('/api/v1/payroll-settings/bpjs-validation');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.is_valid', false);

        $warnings = $response->json('data.warnings');
        $this->assertNotEmpty($warnings);
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
