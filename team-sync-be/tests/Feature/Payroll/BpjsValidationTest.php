<?php

namespace Tests\Feature\Payroll;

use App\Models\BpjsRate;
use App\Models\User;
use App\Services\Payroll\TaxCalculationService;
use Carbon\Carbon;
use Database\Seeders\BpjsRateSeeder;
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

/**
 * Tests for BPJS rate validation and cap warnings (#7).
 */
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
            BpjsRateSeeder::class,
            PtkpAmountSeeder::class,
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // validateBpjsRates()
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function test_validate_bpjs_rates_returns_valid_when_rates_are_current(): void
    {
        // Set all rates with recent effective_date
        BpjsRate::query()->update([
            'effective_date' => Carbon::now()->subMonths(3)->toDateString(),
            'valid_until' => Carbon::now()->addYear()->toDateString(),
        ]);

        $service = app(TaxCalculationService::class);
        $result = $service->validateBpjsRates();

        $this->assertTrue($result['is_valid']);
        $this->assertEmpty($result['warnings']);
        $this->assertNotEmpty($result['rates']);
    }

    /** @test */
    public function test_validate_bpjs_rates_detects_expired_rates(): void
    {
        // Set JP rate as expired
        BpjsRate::where('component', 'jp')->update([
            'effective_date' => Carbon::now()->subYears(2)->toDateString(),
            'valid_until' => Carbon::now()->subMonth()->toDateString(),
        ]);

        // Keep others current
        BpjsRate::where('component', '!=', 'jp')->update([
            'effective_date' => Carbon::now()->subMonths(3)->toDateString(),
            'valid_until' => Carbon::now()->addYear()->toDateString(),
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
        // Set all rates with effective_date > 12 months ago, no valid_until
        BpjsRate::query()->update([
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
        // No effective_date or valid_until, but updated_at is old
        BpjsRate::query()->update([
            'effective_date' => null,
            'valid_until' => null,
            'updated_at' => Carbon::now()->subMonths(14),
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
        // Delete one component
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
        BpjsRate::query()->update([
            'effective_date' => Carbon::now()->subMonths(3)->toDateString(),
            'valid_until' => Carbon::now()->addYear()->toDateString(),
        ]);

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

    // ─────────────────────────────────────────────────────────────────────────
    // getBpjsCapWarnings()
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function test_bpjs_cap_warnings_for_salary_below_all_caps(): void
    {
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
        $service = app(TaxCalculationService::class);
        // JP cap is 10_042_300
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
        $service = app(TaxCalculationService::class);
        // Above both JP (10_042_300) and Kesehatan (12_000_000) caps
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
        $service = app(TaxCalculationService::class);
        $result = $service->getBpjsCapWarnings(8_000_000);

        // Only JP and bpjs_kesehatan have caps; JHT, JKK, JKM do not
        $components = collect($result['caps_applied'])->pluck('component')->all();
        $this->assertContains('jp', $components);
        $this->assertContains('bpjs_kesehatan', $components);
        $this->assertNotContains('jht', $components);
        $this->assertNotContains('jkk', $components);
        $this->assertNotContains('jkm', $components);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // API endpoint: GET /payroll-settings/bpjs-validation
    // ─────────────────────────────────────────────────────────────────────────

    /** @test */
    public function test_bpjs_validation_endpoint_returns_success(): void
    {
        BpjsRate::query()->update([
            'effective_date' => Carbon::now()->subMonths(3)->toDateString(),
            'valid_until' => Carbon::now()->addYear()->toDateString(),
        ]);

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
        BpjsRate::where('component', 'jp')->update([
            'effective_date' => Carbon::now()->subYears(2)->toDateString(),
            'valid_until' => Carbon::now()->subMonth()->toDateString(),
        ]);

        BpjsRate::where('component', '!=', 'jp')->update([
            'effective_date' => Carbon::now()->subMonths(3)->toDateString(),
            'valid_until' => Carbon::now()->addYear()->toDateString(),
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
