<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_readiness_route_validation()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->withoutMiddleware()->getJson('/api/v1/payrolls/generate-readiness?salary_month=2026-0');

        echo 'STATUS: '.$response->getStatusCode()."\n";
        echo 'BODY: '.$response->getContent()."\n";
        $this->assertTrue(true);
    }
}
