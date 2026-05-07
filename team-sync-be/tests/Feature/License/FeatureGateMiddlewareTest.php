<?php

namespace Tests\Feature\License;

use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\MinimalPayrollE2ESeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class FeatureGateMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private string $privateKeyPem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MinimalPayrollE2ESeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $keys = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        openssl_pkey_export($keys, $privateKeyPem);
        $details = openssl_pkey_get_details($keys);

        $this->privateKeyPem = $privateKeyPem;
        config()->set('license.public_key', $details['key']);
    }

    public function test_payroll_route_is_blocked_when_payroll_feature_is_missing(): void
    {
        $this->activateLicense(['attendance', 'performance']);
        Sanctum::actingAs(User::where('email', 'dwimeta@teamsync.com')->firstOrFail());

        $this->getJson('/api/v1/payrolls/all/paginated')
            ->assertStatus(403)
            ->assertJsonPath('message', "Feature 'payroll' is not enabled for the active license.");
    }

    public function test_analytics_route_is_blocked_when_analytics_feature_is_missing(): void
    {
        $this->activateLicense(['attendance', 'payroll']);
        Sanctum::actingAs(User::where('email', 'tasyia@teamsync.com')->firstOrFail());

        $this->getJson('/api/v1/analytics/workforce/demographics')
            ->assertStatus(403)
            ->assertJsonPath('message', "Feature 'analytics' is not enabled for the active license.");
    }

    public function test_performance_route_is_accessible_when_feature_is_enabled(): void
    {
        $this->activateLicense(['performance']);
        Sanctum::actingAs(User::where('email', 'tasyia@teamsync.com')->firstOrFail());

        $response = $this->getJson('/api/v1/performance/cycles/1/topsis-ranking');

        $this->assertNotSame(403, $response->status());
        $this->assertNotSame("Feature 'performance' is not enabled for the active license.", $response->json('message'));
    }

    private function activateLicense(array $features): void
    {
        $payload = [
            'company_name' => 'PT Team Sync Nusantara',
            'contact_email' => 'admin@teamsync.test',
            'issued_at' => CarbonImmutable::now()->subDay()->toIso8601String(),
            'expires_at' => CarbonImmutable::now()->addYear()->toIso8601String(),
            'features' => $features,
            'max_users' => 150,
        ];

        openssl_sign($this->canonicalJson($payload), $signature, $this->privateKeyPem, OPENSSL_ALGO_SHA256);
        $payload['signature'] = base64_encode($signature);

        $superadmin = User::factory()->create();
        $superadmin->assignRole('superadmin');
        Sanctum::actingAs($superadmin);

        $this->postJson('/api/v1/licenses', [
            'license_key' => base64_encode(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
        ])->assertCreated();
    }

    private function canonicalJson(array $payload): string
    {
        return json_encode($this->sortRecursive($payload), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    private function sortRecursive(array $value): array
    {
        if (array_is_list($value)) {
            return array_map(fn ($item) => is_array($item) ? $this->sortRecursive($item) : $item, $value);
        }

        ksort($value);

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = $this->sortRecursive($item);
            }
        }

        return $value;
    }
}
