<?php

namespace Tests\Feature\Analytics;

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

class AnalyticsEndpointGapTest extends TestCase
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

        $this->activateTestLicense();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function actingAsHr(): User
    {
        $user = User::factory()->create();
        $role = Role::findByName('hr', 'sanctum');
        $user->assignRole($role);
        Sanctum::actingAs($user);

        return $user;
    }

    private function actingAsFinance(): User
    {
        $user = User::factory()->create();
        $role = Role::findByName('finance', 'sanctum');
        $user->assignRole($role);
        Sanctum::actingAs($user);

        return $user;
    }

    /**
     * Helper: assert the route exists, the caller is authorized, and the endpoint
     * answers without a server error.
     *
     * Historically this helper accepted HTTP 500 because several analytics queries
     * use MySQL-only SQL (DATE_FORMAT, TIMESTAMPDIFF) that fails on the SQLite test
     * database. That made the suite unable to detect a genuine 500 - a real
     * arithmetic or column bug looked identical to the known dialect gap.
     *
     * Endpoints listed in MySQL_ONLY_ENDPOINTS are still expected to 500 here; they
     * are asserted as such explicitly, so the unsupported state is visible instead
     * of silent. Any other 500 fails the test.
     */
    private const MYSQL_ONLY_ENDPOINTS = [
        '/api/v1/analytics/attendance/correction-frequency',
        '/api/v1/analytics/leave/approval-turnaround',
        '/api/v1/analytics/payroll/cost-per-employee',
        '/api/v1/analytics/payroll/processing-time',
    ];

    private function assertAnalyticsRouteAccessible(string $uri, string $role = 'hr'): void
    {
        if ($role === 'finance') {
            $this->actingAsFinance();
        } else {
            $this->actingAsHr();
        }

        $response = $this->getJson($uri);
        $status = $response->status();

        // Route must exist (not 404) and user must be authorized (not 401/403)
        $this->assertNotEquals(404, $status, "Route {$uri} should exist (got 404)");
        $this->assertNotEquals(401, $status, "Route {$uri} should not require re-auth (got 401)");
        $this->assertNotEquals(403, $status, "Route {$uri} should be accessible to {$role} (got 403)");

        if (in_array($uri, self::MYSQL_ONLY_ENDPOINTS, true)) {
            // Known dialect gap on SQLite. Pin it so that fixing the query makes this
            // test fail loudly (and the endpoint gets moved to the strict path).
            $this->assertSame(
                500,
                $status,
                "{$uri} is listed as MySQL-only. If it now returns {$status} on SQLite, "
                .'remove it from MySQL_ONLY_ENDPOINTS and assert its payload instead.'
            );

            return;
        }

        $this->assertSame(
            200,
            $status,
            "{$uri} should return 200 for {$role}; a 500 here is a real failure, not the "
            .'known MySQL/SQLite dialect gap.'
        );
        $response->assertJsonStructure(['success', 'message', 'data'])
            ->assertJson(['success' => true]);
    }

    // ─── GAP-4a: Workforce Demographics ─────────────────────────────

    public function test_workforce_demographics_endpoint_exists_and_returns_data(): void
    {
        $this->actingAsHr();

        // This endpoint doesn't use MySQL-specific functions, so it should return 200
        $response = $this->getJson('/api/v1/analytics/workforce/demographics');

        $response->assertOk()
            ->assertJsonStructure(['success', 'message', 'data'])
            ->assertJson(['success' => true]);
    }

    // ─── GAP-4b: Attendance Correction Frequency ────────────────────

    public function test_attendance_correction_frequency_endpoint_exists(): void
    {
        $this->assertAnalyticsRouteAccessible('/api/v1/analytics/attendance/correction-frequency');
    }

    // ─── GAP-4c: Leave Approval Turnaround ──────────────────────────

    public function test_leave_approval_turnaround_endpoint_exists(): void
    {
        $this->assertAnalyticsRouteAccessible('/api/v1/analytics/leave/approval-turnaround');
    }

    // ─── GAP-4d: Leave Type Distribution ────────────────────────────

    public function test_leave_type_distribution_endpoint_exists_and_returns_data(): void
    {
        $this->actingAsHr();

        // This endpoint doesn't use MySQL-specific functions, so it should return 200
        $response = $this->getJson('/api/v1/analytics/leave/type-distribution');

        $response->assertOk()
            ->assertJsonStructure(['success', 'message', 'data'])
            ->assertJson(['success' => true]);
    }

    // ─── GAP-4e: Payroll Cost Per Employee ──────────────────────────

    public function test_payroll_cost_per_employee_endpoint_exists(): void
    {
        // Payroll analytics requires analytics-finance-view (Finance role)
        $this->assertAnalyticsRouteAccessible('/api/v1/analytics/payroll/cost-per-employee', 'finance');
    }

    // ─── GAP-4f: Payroll Processing Time ────────────────────────────

    public function test_payroll_processing_time_endpoint_exists(): void
    {
        // Payroll analytics requires analytics-finance-view (Finance role)
        $this->assertAnalyticsRouteAccessible('/api/v1/analytics/payroll/processing-time', 'finance');
    }

    // ─── GAP-4g: Project Resource Utilization ───────────────────────

    public function test_project_resource_utilization_endpoint_exists(): void
    {
        $this->assertAnalyticsRouteAccessible('/api/v1/analytics/project/resource-utilization');
    }

    // ─── Permission Guard: Unauthenticated ──────────────────────────

    public function test_unauthenticated_user_cannot_access_demographics(): void
    {
        $this->getJson('/api/v1/analytics/workforce/demographics')
            ->assertUnauthorized();
    }

    public function test_unauthenticated_user_cannot_access_correction_frequency(): void
    {
        $this->getJson('/api/v1/analytics/attendance/correction-frequency')
            ->assertUnauthorized();
    }

    public function test_unauthenticated_user_cannot_access_leave_turnaround(): void
    {
        $this->getJson('/api/v1/analytics/leave/approval-turnaround')
            ->assertUnauthorized();
    }

    public function test_unauthenticated_user_cannot_access_type_distribution(): void
    {
        $this->getJson('/api/v1/analytics/leave/type-distribution')
            ->assertUnauthorized();
    }

    public function test_unauthenticated_user_cannot_access_cost_per_employee(): void
    {
        $this->getJson('/api/v1/analytics/payroll/cost-per-employee')
            ->assertUnauthorized();
    }

    public function test_unauthenticated_user_cannot_access_processing_time(): void
    {
        $this->getJson('/api/v1/analytics/payroll/processing-time')
            ->assertUnauthorized();
    }

    public function test_unauthenticated_user_cannot_access_resource_utilization(): void
    {
        $this->getJson('/api/v1/analytics/project/resource-utilization')
            ->assertUnauthorized();
    }
}
