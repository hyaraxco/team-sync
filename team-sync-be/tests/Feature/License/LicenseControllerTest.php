<?php

namespace Tests\Feature\License;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class LicenseControllerTest extends TestCase
{
    use RefreshDatabase;

    private string $privateKeyPem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,
        ]);

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

    public function test_unauthenticated_user_cannot_access_license_endpoints(): void
    {
        $this->getJson('/api/v1/licenses')->assertUnauthorized();
        $this->postJson('/api/v1/licenses/verify', ['license_key' => 'invalid'])->assertUnauthorized();
    }

    public function test_non_superadmin_user_cannot_manage_licenses(): void
    {
        $staff = User::factory()->create();
        $staff->assignRole(Role::findByName('staff', 'sanctum'));

        Sanctum::actingAs($staff);

        $this->getJson('/api/v1/licenses')->assertForbidden();
        $this->postJson('/api/v1/licenses/verify', ['license_key' => $this->makeSignedLicenseKey()])->assertForbidden();
    }

    public function test_superadmin_can_verify_and_activate_license(): void
    {
        $superadmin = User::factory()->create();
        $superadmin->assignRole(Role::findByName('superadmin', 'sanctum'));

        Sanctum::actingAs($superadmin);

        $licenseKey = $this->makeSignedLicenseKey();

        $this->postJson('/api/v1/licenses/verify', [
            'license_key' => $licenseKey,
        ])
            ->assertOk()
            ->assertJsonPath('data.valid', true)
            ->assertJsonPath('data.company_name', 'PT Team Sync Nusantara');

        $this->postJson('/api/v1/licenses', [
            'license_key' => $licenseKey,
        ])
            ->assertCreated()
            ->assertJsonPath('data.company_name', 'PT Team Sync Nusantara')
            ->assertJsonPath('data.max_users', 150)
            ->assertJsonPath('data.is_active', true);

        $this->getJson('/api/v1/licenses/current')
            ->assertOk()
            ->assertJsonPath('data.company_name', 'PT Team Sync Nusantara');

        $this->assertDatabaseHas('licenses', [
            'company_name' => 'PT Team Sync Nusantara',
            'is_active' => true,
        ]);
    }

    public function test_verify_rejects_invalid_signature(): void
    {
        $superadmin = User::factory()->create();
        $superadmin->assignRole(Role::findByName('superadmin', 'sanctum'));

        Sanctum::actingAs($superadmin);

        $licenseKey = $this->makeSignedLicenseKey([
            'signature' => base64_encode('tampered-signature'),
        ], false);

        $this->postJson('/api/v1/licenses/verify', [
            'license_key' => $licenseKey,
        ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);
    }

    private function makeSignedLicenseKey(array $overrides = [], bool $sign = true): string
    {
        $payload = array_merge([
            'company_name' => 'PT Team Sync Nusantara',
            'contact_email' => 'admin@teamsync.test',
            'issued_at' => now()->subDay()->toIso8601String(),
            'expires_at' => now()->addYear()->toIso8601String(),
            'features' => ['attendance', 'payroll', 'performance'],
            'max_users' => 150,
        ], $overrides);

        unset($payload['signature']);

        if ($sign) {
            openssl_sign($this->canonicalJson($payload), $signature, $this->privateKeyPem, OPENSSL_ALGO_SHA256);
            $payload['signature'] = base64_encode($signature);
        } elseif (array_key_exists('signature', $overrides)) {
            $payload['signature'] = $overrides['signature'];
        }

        return base64_encode(json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
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
