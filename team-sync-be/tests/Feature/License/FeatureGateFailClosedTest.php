<?php

namespace Tests\Feature\License;

use App\Models\License;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\ActivatesLicense;
use Tests\TestCase;

class FeatureGateFailClosedTest extends TestCase
{
    use ActivatesLicense, RefreshDatabase;

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

    // ─────────────────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function actingAsRole(string $roleName): User
    {
        $user = User::factory()->create();
        $role = Role::findByName($roleName, 'sanctum');
        $user->assignRole($role);

        Sanctum::actingAs($user);

        return $user;
    }

    private function createExpiredLicense(array $features): License
    {
        return License::query()->create([
            'license_key' => base64_encode(json_encode([
                'company_name' => 'PT Expired License',
                'contact_email' => 'expired@teamsync.test',
                'issued_at' => '2019-01-01T00:00:00+00:00',
                'expires_at' => '2020-01-01T00:00:00+00:00',
                'features' => array_values($features),
                'max_users' => 999,
                'signature' => base64_encode('test-signature'),
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
            'license_hash' => hash('sha256', 'expired-license-'.implode('|', $features)),
            'company_name' => 'PT Expired License',
            'contact_email' => 'expired@teamsync.test',
            'issued_at' => '2019-01-01',
            'expires_at' => '2020-01-01',
            'is_active' => true,
            'features' => array_values($features),
            'max_users' => 999,
            'current_users' => 0,
            'activated_at' => now(),
            'last_validated_at' => now(),
            'signature' => base64_encode('test-signature'),
        ]);
    }

    private function assertFeatureBlocked(string $method, string $uri, string $feature): void
    {
        $response = $this->{$method}($uri);

        $response->assertStatus(403)
            ->assertJsonPath('message', "Feature '{$feature}' is not enabled for the active license.");
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 1. NO LICENSE EXISTS — fail-closed (403 for all gated routes)
    // ═════════════════════════════════════════════════════════════════════════

    public function test_leave_route_returns_403_when_no_license_exists(): void
    {
        $this->actingAsRole('staff');

        $this->assertFeatureBlocked('getJson', '/api/v1/my-leave-requests', 'leave');
    }

    public function test_overtime_route_returns_403_when_no_license_exists(): void
    {
        $this->actingAsRole('hr');

        $this->assertFeatureBlocked('getJson', '/api/v1/overtime', 'overtime');
    }

    public function test_thr_route_returns_403_when_no_license_exists(): void
    {
        $this->actingAsRole('hr');

        $this->assertFeatureBlocked('getJson', '/api/v1/thr', 'thr');
    }

    public function test_payroll_route_returns_403_when_no_license_exists(): void
    {
        $this->actingAsRole('finance');

        $this->assertFeatureBlocked('getJson', '/api/v1/payrolls/all/paginated', 'payroll');
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 2. EXPIRED LICENSE — fail-closed (403 even though is_active = true)
    // ═════════════════════════════════════════════════════════════════════════

    public function test_leave_route_returns_403_when_license_is_expired(): void
    {
        $this->createExpiredLicense(['leave', 'overtime', 'thr', 'payroll']);
        $this->actingAsRole('staff');

        $this->assertFeatureBlocked('getJson', '/api/v1/my-leave-requests', 'leave');
    }

    public function test_overtime_route_returns_403_when_license_is_expired(): void
    {
        $this->createExpiredLicense(['leave', 'overtime', 'thr', 'payroll']);
        $this->actingAsRole('hr');

        $this->assertFeatureBlocked('getJson', '/api/v1/overtime', 'overtime');
    }

    public function test_thr_route_returns_403_when_license_is_expired(): void
    {
        $this->createExpiredLicense(['leave', 'overtime', 'thr', 'payroll']);
        $this->actingAsRole('hr');

        $this->assertFeatureBlocked('getJson', '/api/v1/thr', 'thr');
    }

    public function test_payroll_route_returns_403_when_license_is_expired(): void
    {
        $this->createExpiredLicense(['leave', 'overtime', 'thr', 'payroll']);
        $this->actingAsRole('finance');

        $this->assertFeatureBlocked('getJson', '/api/v1/payrolls/all/paginated', 'payroll');
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 3. LEAVE feature gate (currently untested)
    // ═════════════════════════════════════════════════════════════════════════

    public function test_leave_route_is_accessible_when_leave_feature_is_enabled(): void
    {
        $this->activateTestLicense(['leave']);
        $this->actingAsRole('staff');

        $response = $this->getJson('/api/v1/my-leave-requests');

        $this->assertNotSame(403, $response->status());
        $this->assertNotSame(
            "Feature 'leave' is not enabled for the active license.",
            $response->json('message')
        );
    }

    public function test_leave_route_is_blocked_when_leave_feature_is_missing(): void
    {
        $this->activateTestLicense(['payroll', 'attendance']);
        $this->actingAsRole('staff');

        $this->assertFeatureBlocked('getJson', '/api/v1/my-leave-requests', 'leave');
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 4. OVERTIME feature gate (currently untested)
    // ═════════════════════════════════════════════════════════════════════════

    public function test_overtime_route_is_accessible_when_overtime_feature_is_enabled(): void
    {
        $this->activateTestLicense(['overtime']);
        $this->actingAsRole('hr');

        $response = $this->getJson('/api/v1/overtime');

        $this->assertNotSame(403, $response->status());
        $this->assertNotSame(
            "Feature 'overtime' is not enabled for the active license.",
            $response->json('message')
        );
    }

    public function test_overtime_route_is_blocked_when_overtime_feature_is_missing(): void
    {
        $this->activateTestLicense(['payroll', 'attendance']);
        $this->actingAsRole('hr');

        $this->assertFeatureBlocked('getJson', '/api/v1/overtime', 'overtime');
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 5. THR feature gate (currently untested)
    // ═════════════════════════════════════════════════════════════════════════

    public function test_thr_route_is_accessible_when_thr_feature_is_enabled(): void
    {
        $this->activateTestLicense(['thr']);
        $this->actingAsRole('hr');

        $response = $this->getJson('/api/v1/thr');

        $this->assertNotSame(403, $response->status());
        $this->assertNotSame(
            "Feature 'thr' is not enabled for the active license.",
            $response->json('message')
        );
    }

    public function test_thr_route_is_blocked_when_thr_feature_is_missing(): void
    {
        $this->activateTestLicense(['payroll', 'attendance']);
        $this->actingAsRole('hr');

        $this->assertFeatureBlocked('getJson', '/api/v1/thr', 'thr');
    }

    // ═════════════════════════════════════════════════════════════════════════
    // 6. PAYROLL feature gate — no-license & expired complement existing test
    // ═════════════════════════════════════════════════════════════════════════

    public function test_payroll_route_is_accessible_when_payroll_feature_is_enabled(): void
    {
        $this->activateTestLicense(['payroll']);
        $this->actingAsRole('finance');

        $response = $this->getJson('/api/v1/payrolls/all/paginated');

        $this->assertNotSame(403, $response->status());
        $this->assertNotSame(
            "Feature 'payroll' is not enabled for the active license.",
            $response->json('message')
        );
    }
}
