<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\MinimalPayrollE2ESeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\ActivatesLicense;
use Tests\TestCase;

/**
 * Phase 1B — Forbidden-access tests.
 *
 * Verifies that roles cannot access endpoints outside their PRD scope:
 * - Staff: self-service only, no admin analytics/dashboard/staff directory
 * - Manager: no staff directory, no payroll analytics, no HR analytics
 * - Finance: no staff directory, no HR analytics, no HR dashboard stats
 * - HR: no payroll operations (create/process), no finance analytics
 */
class RoleForbiddenAccessTest extends TestCase
{
    use ActivatesLicense, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MinimalPayrollE2ESeeder::class);
        $this->activateTestLicense();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function actingAsEmail(string $email): User
    {
        $user = User::where('email', $email)->firstOrFail();
        Sanctum::actingAs($user);

        return $user;
    }

    // ─── Staff forbidden access ──────────────────────────────────────────────────

    public function test_staff_cannot_access_staff_directory(): void
    {
        $this->actingAsEmail('agung@teamsync.com');

        $this->getJson('/api/v1/staff-members')->assertForbidden();
        $this->getJson('/api/v1/staff-members/all/paginated')->assertForbidden();
        $this->getJson('/api/v1/staff-members/statistics')->assertForbidden();
    }

    public function test_staff_cannot_access_company_dashboard_statistics(): void
    {
        $this->actingAsEmail('agung@teamsync.com');

        $this->getJson('/api/v1/dashboard/statistics')->assertForbidden();
        $this->getJson('/api/v1/dashboard/today-attendance-overview')->assertForbidden();
    }

    public function test_staff_can_access_own_employee_statistics(): void
    {
        $this->actingAsEmail('agung@teamsync.com');

        // Permission check passes (not 403); may be 200 or 500 depending on data availability
        $response = $this->getJson('/api/v1/dashboard/my-statistics');
        $this->assertNotEquals(403, $response->status(), 'Staff should not be forbidden from own statistics');
    }

    public function test_staff_cannot_access_analytics(): void
    {
        $this->actingAsEmail('agung@teamsync.com');

        $this->getJson('/api/v1/analytics/executive-summary')->assertForbidden();
        $this->getJson('/api/v1/analytics/workforce')->assertForbidden();
        $this->getJson('/api/v1/analytics/payroll')->assertForbidden();
        $this->getJson('/api/v1/analytics/attendance')->assertForbidden();
        $this->getJson('/api/v1/analytics/leave')->assertForbidden();
        $this->getJson('/api/v1/analytics/projects')->assertForbidden();
    }

    public function test_staff_cannot_access_team_pulse(): void
    {
        $this->actingAsEmail('agung@teamsync.com');

        $this->getJson('/api/v1/dashboard/team-pulse')->assertForbidden();
    }

    // ─── Manager forbidden access ────────────────────────────────────────────────

    public function test_manager_cannot_access_staff_directory(): void
    {
        $this->actingAsEmail('yudhis@teamsync.com');

        $this->getJson('/api/v1/staff-members')->assertForbidden();
        $this->getJson('/api/v1/staff-members/all/paginated')->assertForbidden();
        $this->getJson('/api/v1/staff-members/statistics')->assertForbidden();
    }

    public function test_manager_cannot_access_company_dashboard_statistics(): void
    {
        $this->actingAsEmail('yudhis@teamsync.com');

        $this->getJson('/api/v1/dashboard/statistics')->assertForbidden();
        $this->getJson('/api/v1/dashboard/today-attendance-overview')->assertForbidden();
    }

    public function test_manager_cannot_access_hr_analytics(): void
    {
        $this->actingAsEmail('yudhis@teamsync.com');

        $this->getJson('/api/v1/analytics/workforce')->assertForbidden();
        $this->getJson('/api/v1/analytics/attendance')->assertForbidden();
        $this->getJson('/api/v1/analytics/leave')->assertForbidden();
    }

    public function test_manager_cannot_access_payroll_analytics(): void
    {
        $this->actingAsEmail('yudhis@teamsync.com');

        $this->getJson('/api/v1/analytics/payroll')->assertForbidden();
        $this->getJson('/api/v1/analytics/payroll/cost-trends')->assertForbidden();
        $this->getJson('/api/v1/analytics/payroll/salary-distribution')->assertForbidden();
    }

    public function test_manager_can_access_performance_analytics(): void
    {
        $this->actingAsEmail('yudhis@teamsync.com');

        // Permission check passes (not 403); may be 200 or 500 depending on data
        $r1 = $this->getJson('/api/v1/analytics/performance/team-summary?team_id=1');
        $this->assertNotEquals(403, $r1->status(), 'Manager should access team performance analytics');

        $r2 = $this->getJson('/api/v1/analytics/performance/goal-completion-rate');
        $this->assertNotEquals(403, $r2->status(), 'Manager should access goal completion rate');
    }

    public function test_manager_can_access_project_analytics(): void
    {
        $this->actingAsEmail('yudhis@teamsync.com');

        $r = $this->getJson('/api/v1/analytics/projects');
        $this->assertNotEquals(403, $r->status(), 'Manager should access project analytics');
    }

    public function test_manager_can_access_team_pulse(): void
    {
        $this->actingAsEmail('yudhis@teamsync.com');

        $r = $this->getJson('/api/v1/dashboard/team-pulse');
        $this->assertNotEquals(403, $r->status(), 'Manager should access team pulse');
    }

    // ─── Finance forbidden access ────────────────────────────────────────────────

    public function test_finance_cannot_access_staff_directory(): void
    {
        $this->actingAsEmail('dwimeta@teamsync.com');

        $this->getJson('/api/v1/staff-members')->assertForbidden();
        $this->getJson('/api/v1/staff-members/all/paginated')->assertForbidden();
        $this->getJson('/api/v1/staff-members/statistics')->assertForbidden();
    }

    public function test_finance_cannot_access_company_dashboard_statistics(): void
    {
        $this->actingAsEmail('dwimeta@teamsync.com');

        $this->getJson('/api/v1/dashboard/statistics')->assertForbidden();
        $this->getJson('/api/v1/dashboard/today-attendance-overview')->assertForbidden();
    }

    public function test_finance_cannot_access_hr_analytics(): void
    {
        $this->actingAsEmail('dwimeta@teamsync.com');

        $this->getJson('/api/v1/analytics/workforce')->assertForbidden();
        $this->getJson('/api/v1/analytics/attendance')->assertForbidden();
        $this->getJson('/api/v1/analytics/leave')->assertForbidden();
    }

    public function test_finance_can_access_payroll_analytics(): void
    {
        $this->actingAsEmail('dwimeta@teamsync.com');

        // Permission check passes (not 403); may be 200 or 500 depending on data
        $r1 = $this->getJson('/api/v1/analytics/payroll');
        $this->assertNotEquals(403, $r1->status(), 'Finance should access payroll analytics');

        $r2 = $this->getJson('/api/v1/analytics/payroll/cost-trends');
        $this->assertNotEquals(403, $r2->status(), 'Finance should access payroll cost trends');

        $r3 = $this->getJson('/api/v1/analytics/payroll/salary-distribution');
        $this->assertNotEquals(403, $r3->status(), 'Finance should access salary distribution');
    }

    public function test_finance_cannot_access_performance_analytics(): void
    {
        $this->actingAsEmail('dwimeta@teamsync.com');

        $this->getJson('/api/v1/analytics/performance/team-summary?team_id=1')->assertForbidden();
        $this->getJson('/api/v1/analytics/performance/company-summary')->assertForbidden();
    }

    public function test_finance_cannot_access_project_analytics(): void
    {
        $this->actingAsEmail('dwimeta@teamsync.com');

        $this->getJson('/api/v1/analytics/projects')->assertForbidden();
    }

    // ─── HR forbidden access ─────────────────────────────────────────────────────

    public function test_hr_cannot_access_payroll_analytics(): void
    {
        $this->actingAsEmail('tasyia@teamsync.com');

        $this->getJson('/api/v1/analytics/payroll')->assertForbidden();
        $this->getJson('/api/v1/analytics/payroll/cost-trends')->assertForbidden();
        $this->getJson('/api/v1/analytics/payroll/salary-distribution')->assertForbidden();
    }

    public function test_hr_can_access_hr_analytics(): void
    {
        $this->actingAsEmail('tasyia@teamsync.com');

        // Permission check passes (not 403); may be 200 or 500 depending on data
        $r1 = $this->getJson('/api/v1/analytics/workforce');
        $this->assertNotEquals(403, $r1->status(), 'HR should access workforce analytics');

        $r2 = $this->getJson('/api/v1/analytics/attendance');
        $this->assertNotEquals(403, $r2->status(), 'HR should access attendance analytics');

        $r3 = $this->getJson('/api/v1/analytics/leave');
        $this->assertNotEquals(403, $r3->status(), 'HR should access leave analytics');
    }

    public function test_hr_can_access_company_dashboard_statistics(): void
    {
        $this->actingAsEmail('tasyia@teamsync.com');

        $r1 = $this->getJson('/api/v1/dashboard/statistics');
        $this->assertNotEquals(403, $r1->status(), 'HR should access company dashboard statistics');

        $r2 = $this->getJson('/api/v1/dashboard/today-attendance-overview');
        $this->assertNotEquals(403, $r2->status(), 'HR should access today attendance overview');
    }

    public function test_hr_can_access_staff_directory(): void
    {
        $this->actingAsEmail('tasyia@teamsync.com');

        $r1 = $this->getJson('/api/v1/staff-members');
        $this->assertNotEquals(403, $r1->status(), 'HR should access staff directory');

        $r2 = $this->getJson('/api/v1/staff-members/statistics');
        $this->assertNotEquals(403, $r2->status(), 'HR should access staff statistics');
    }

    public function test_hr_can_access_performance_analytics(): void
    {
        $this->actingAsEmail('tasyia@teamsync.com');

        $r1 = $this->getJson('/api/v1/analytics/performance/company-summary');
        $this->assertNotEquals(403, $r1->status(), 'HR should access company performance summary');

        $r2 = $this->getJson('/api/v1/analytics/performance/goal-completion-rate');
        $this->assertNotEquals(403, $r2->status(), 'HR should access goal completion rate');
    }

    public function test_hr_cannot_create_payroll(): void
    {
        $this->actingAsEmail('tasyia@teamsync.com');

        // Payroll create route uses POST /api/v1/payrolls/generate
        $response = $this->postJson('/api/v1/payrolls/generate', [
            'salary_month' => now()->format('Y-m'),
            'payroll_date' => now()->toDateString(),
        ]);
        // HR should be forbidden from payroll generation (no payroll-create permission)
        $this->assertTrue(
            in_array($response->status(), [403, 405]),
            'HR should not be able to create/generate payroll'
        );
    }
}
