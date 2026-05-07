<?php

namespace Tests\Concerns;

use App\Models\License;
use Carbon\CarbonImmutable;

trait ActivatesLicense
{
    protected function activateTestLicense(array $features = ['attendance', 'leave', 'payroll', 'analytics', 'performance', 'overtime', 'thr']): License
    {
        License::query()->delete();

        $issuedAt = CarbonImmutable::parse('2000-01-01T00:00:00+00:00');
        $expiresAt = CarbonImmutable::parse('2100-01-01T00:00:00+00:00');

        return License::query()->create([
            'license_key' => base64_encode(json_encode([
                'company_name' => 'PT Test License',
                'contact_email' => 'qa@teamsync.test',
                'issued_at' => $issuedAt->toIso8601String(),
                'expires_at' => $expiresAt->toIso8601String(),
                'features' => array_values($features),
                'max_users' => 999,
                'signature' => base64_encode('test-signature'),
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
            'license_hash' => hash('sha256', 'test-license-'.implode('|', $features)),
            'company_name' => 'PT Test License',
            'contact_email' => 'qa@teamsync.test',
            'issued_at' => $issuedAt->toDateString(),
            'expires_at' => $expiresAt->toDateString(),
            'is_active' => true,
            'features' => array_values($features),
            'max_users' => 999,
            'current_users' => 0,
            'activated_at' => now(),
            'last_validated_at' => now(),
            'signature' => base64_encode('test-signature'),
        ]);
    }
}
