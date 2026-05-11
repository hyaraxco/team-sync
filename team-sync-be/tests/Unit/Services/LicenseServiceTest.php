<?php

namespace Tests\Unit\Services;

use App\Interfaces\LicenseRepositoryInterface;
use App\Models\License;
use App\Services\LicenseService;
use Tests\TestCase;

class LicenseServiceTest extends TestCase
{
    private LicenseService $service;

    private $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = $this->createMock(LicenseRepositoryInterface::class);
        $this->service = new LicenseService($this->repository);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // isFeatureEnabled
    // ─────────────────────────────────────────────────────────────────────────

    public function test_is_feature_enabled_returns_true_when_feature_exists(): void
    {
        $license = new License(['features' => ['attendance', 'leave', 'payroll']]);
        $this->repository->method('getActive')->willReturn($license);

        $this->assertTrue($this->service->isFeatureEnabled('attendance'));
        $this->assertTrue($this->service->isFeatureEnabled('payroll'));
    }

    public function test_is_feature_enabled_returns_false_when_feature_missing(): void
    {
        $license = new License(['features' => ['attendance', 'leave']]);
        $this->repository->method('getActive')->willReturn($license);

        $this->assertFalse($this->service->isFeatureEnabled('payroll'));
    }

    public function test_is_feature_enabled_returns_false_when_no_active_license(): void
    {
        $this->repository->method('getActive')->willReturn(null);

        $this->assertFalse($this->service->isFeatureEnabled('attendance'));
    }

    public function test_is_feature_enabled_returns_false_when_features_null(): void
    {
        $license = new License(['features' => null]);
        $this->repository->method('getActive')->willReturn($license);

        $this->assertFalse($this->service->isFeatureEnabled('attendance'));
    }

    // ─────────────────────────────────────────────────────────────────────────
    // getActive
    // ─────────────────────────────────────────────────────────────────────────

    public function test_get_active_returns_license(): void
    {
        $license = new License(['is_active' => true]);
        $this->repository->method('getActive')->willReturn($license);

        $result = $this->service->getActive();

        $this->assertInstanceOf(License::class, $result);
    }

    public function test_get_active_returns_null_when_no_license(): void
    {
        $this->repository->method('getActive')->willReturn(null);

        $result = $this->service->getActive();

        $this->assertNull($result);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // validateLicense
    // ─────────────────────────────────────────────────────────────────────────

    public function test_validate_license_rejects_invalid_base64(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid license key format');

        $this->service->validateLicense('not-valid-base64!!!');
    }

    public function test_validate_license_rejects_invalid_json(): void
    {
        $key = base64_encode('not valid json');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid license payload JSON');

        $this->service->validateLicense($key);
    }

    public function test_validate_license_rejects_missing_required_fields(): void
    {
        $payload = ['company_name' => 'Test'];
        $key = base64_encode(json_encode($payload));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('required');

        $this->service->validateLicense($key);
    }
}
