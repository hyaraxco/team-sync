<?php

namespace Tests\Feature\Payroll;

use App\Jobs\GeneratePayrollJob;
use App\Models\User;
use App\Services\Payroll\PayrollGenerationService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\ActivatesLicense;
use Tests\TestCase;

class PayrollGenerationServiceWiringTest extends TestCase
{
    use ActivatesLicense;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->activateTestLicense();
        $this->seed([RoleSeeder::class, PermissionSeeder::class, RolePermissionSeeder::class]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_payroll_generation_service_is_resolvable_from_container(): void
    {
        $service = app(PayrollGenerationService::class);

        $this->assertInstanceOf(PayrollGenerationService::class, $service);
    }

    public function test_generate_endpoint_dispatches_job_via_service(): void
    {
        Queue::fake();

        $this->actingAsFinance();

        $salaryMonth = now()->subMonths(2)->format('Y-m');

        $response = $this->postJson('/api/v1/payrolls/generate', [
            'salary_month' => $salaryMonth,
        ]);

        // Either 200 (job dispatched) or 422 (readiness failed — no attendance data)
        $this->assertContains($response->status(), [200, 422]);
        $response->assertJsonStructure(['success', 'message']);

        if ($response->status() === 200) {
            Queue::assertPushed(GeneratePayrollJob::class);
        } else {
            Queue::assertNotPushed(GeneratePayrollJob::class);
        }
    }

    public function test_generate_readiness_endpoint_returns_200_via_service(): void
    {
        $this->actingAsFinance();

        $salaryMonth = now()->subMonth()->format('Y-m');

        $this->getJson("/api/v1/payrolls/generate-readiness?salary_month={$salaryMonth}")
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'message', 'data' => ['can_generate']]);
    }

    public function test_readiness_dashboard_endpoint_returns_200_via_service(): void
    {
        $this->actingAsFinance();

        $salaryMonth = now()->subMonth()->format('Y-m');

        $this->getJson("/api/v1/payrolls/readiness-dashboard?salary_month={$salaryMonth}")
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'message', 'data']);
    }

    public function test_readiness_team_summary_endpoint_returns_200_via_service(): void
    {
        $this->actingAsFinance();

        $salaryMonth = now()->subMonth()->format('Y-m');

        $this->getJson("/api/v1/payrolls/readiness-dashboard/team-summary?salary_month={$salaryMonth}")
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['success', 'message', 'data']);
    }

    private function actingAsFinance(): User
    {
        $user = User::factory()->create();
        $user->assignRole('finance');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Sanctum::actingAs($user);

        return $user;
    }
}
