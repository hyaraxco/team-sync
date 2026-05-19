<?php

namespace Tests\Feature\Payroll;

use App\Services\Payroll\PayrollAnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollAnalyticsServiceWiringTest extends TestCase
{
    use RefreshDatabase;

    public function test_payroll_analytics_service_is_resolvable_from_container(): void
    {
        $service = app(PayrollAnalyticsService::class);

        $this->assertInstanceOf(PayrollAnalyticsService::class, $service);
    }
}
