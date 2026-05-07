<?php

namespace App\Services;

use App\Interfaces\LicenseRepositoryInterface;
use App\Models\License;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use RuntimeException;

class LicenseService
{
    public function __construct(
        private readonly LicenseRepositoryInterface $licenseRepository
    ) {}

    public function getAll(): Collection
    {
        return $this->licenseRepository->getAll();
    }

    public function getById(int $id): License
    {
        return $this->licenseRepository->getById($id);
    }

    public function getActive(): ?License
    {
        return $this->licenseRepository->getActive();
    }

    public function validateLicense(string $licenseKey): array
    {
        $payload = $this->decodePayload($licenseKey);

        $this->assertRequiredFields($payload);
        $this->verifySignature($payload);

        $issuedAt = CarbonImmutable::parse($payload['issued_at']);
        $expiresAt = CarbonImmutable::parse($payload['expires_at']);

        if ($issuedAt->isFuture()) {
            throw new \InvalidArgumentException('License is not active yet.');
        }

        if ($expiresAt->isPast()) {
            throw new \InvalidArgumentException('License has expired.');
        }

        if (! is_array($payload['features'])) {
            throw new \InvalidArgumentException('License features must be an array.');
        }

        $payload['max_users'] = (int) $payload['max_users'];
        if ($payload['max_users'] < 1) {
            throw new \InvalidArgumentException('License max_users must be at least 1.');
        }

        return $payload;
    }

    public function activateLicense(string $licenseKey, array $companyDetails = []): License
    {
        $payload = $this->validateLicense($licenseKey);

        $this->licenseRepository->deactivateAll();

        return $this->licenseRepository->create([
            'license_key' => $licenseKey,
            'license_hash' => hash('sha256', $licenseKey),
            'company_name' => $companyDetails['company_name'] ?? $payload['company_name'],
            'contact_email' => $companyDetails['contact_email'] ?? $payload['contact_email'],
            'issued_at' => CarbonImmutable::parse($payload['issued_at'])->toDateString(),
            'expires_at' => CarbonImmutable::parse($payload['expires_at'])->toDateString(),
            'is_active' => true,
            'features' => array_values($payload['features']),
            'max_users' => $payload['max_users'],
            'current_users' => 0,
            'activated_at' => now(),
            'last_validated_at' => now(),
            'signature' => $payload['signature'],
        ]);
    }

    public function updateLicense(int $id, array $data): License
    {
        $license = $this->licenseRepository->getById($id);

        if (array_key_exists('current_users', $data) && $data['current_users'] > ($data['max_users'] ?? $license->max_users)) {
            throw new \InvalidArgumentException('Current users cannot exceed max users.');
        }

        return $this->licenseRepository->update($license, $data);
    }

    public function deactivateLicense(int $id): void
    {
        $license = $this->licenseRepository->getById($id);

        $this->licenseRepository->update($license, ['is_active' => false]);
    }

    public function isFeatureEnabled(string $feature): bool
    {
        $license = $this->getActive();

        return $license !== null && in_array($feature, $license->features ?? [], true);
    }

    public function verifyLicenseKey(string $licenseKey): array
    {
        $payload = $this->validateLicense($licenseKey);

        return [
            'valid' => true,
            'company_name' => $payload['company_name'],
            'contact_email' => $payload['contact_email'],
            'issued_at' => $payload['issued_at'],
            'expires_at' => $payload['expires_at'],
            'features' => array_values($payload['features']),
            'max_users' => $payload['max_users'],
            'license_hash' => hash('sha256', $licenseKey),
        ];
    }

    private function decodePayload(string $licenseKey): array
    {
        $decoded = base64_decode($licenseKey, true);

        if ($decoded === false) {
            throw new \InvalidArgumentException('Invalid license key format.');
        }

        $payload = json_decode($decoded, true);

        if (! is_array($payload) || json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Invalid license payload JSON.');
        }

        return $payload;
    }

    private function assertRequiredFields(array $payload): void
    {
        foreach (['company_name', 'contact_email', 'issued_at', 'expires_at', 'features', 'max_users', 'signature'] as $field) {
            if (! array_key_exists($field, $payload) || $payload[$field] === null || $payload[$field] === '') {
                throw new \InvalidArgumentException("License field '{$field}' is required.");
            }
        }
    }

    private function verifySignature(array $payload): void
    {
        $signature = base64_decode((string) $payload['signature'], true);

        if ($signature === false) {
            throw new \InvalidArgumentException('License signature is malformed.');
        }

        $publicKey = $this->getConfiguredPublicKey();
        $canonicalPayload = $this->canonicalJson(Arr::except($payload, ['signature']));
        $verified = openssl_verify($canonicalPayload, $signature, $publicKey, OPENSSL_ALGO_SHA256);

        if ($verified !== 1) {
            throw new \InvalidArgumentException('Invalid license signature.');
        }
    }

    private function getConfiguredPublicKey(): mixed
    {
        $configuredKey = trim(str_replace('\\n', PHP_EOL, (string) config('license.public_key')));

        if ($configuredKey !== '') {
            $publicKey = openssl_pkey_get_public($configuredKey);

            if ($publicKey !== false) {
                return $publicKey;
            }
        }

        $path = config('license.public_key_path');
        if (is_string($path) && $path !== '' && is_readable($path)) {
            $publicKey = openssl_pkey_get_public((string) file_get_contents($path));

            if ($publicKey !== false) {
                return $publicKey;
            }
        }

        throw new RuntimeException('License public key is not configured.');
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
