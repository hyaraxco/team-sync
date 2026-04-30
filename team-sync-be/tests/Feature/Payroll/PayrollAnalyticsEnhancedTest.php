<?php

namespace Tests\Feature\Payroll;

use App\Models\Payroll;
use App\Models\PayrollDetail;
use App\Models\StaffMemberProfile;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PayrollAnalyticsEnhancedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Cache::flush();
    }

    public function test_analytics_returns_enhanced_metrics(): void
    {
        $this->actingAsRole('finance');
        $this->seedPayrollData();

        $response = $this->getJson('/api/v1/payrolls/analytics?months=6');

        $response->assertOk();

        $data = $response->json('data');

        // Verify new fields exist
        $this->assertArrayHasKey('average_salary_trend', $data);
        $this->assertArrayHasKey('total_deductions_trend', $data);
        $this->assertArrayHasKey('headcount_vs_payroll_growth', $data);
        $this->assertArrayHasKey('bpjs_contribution_trend', $data);
        $this->assertArrayHasKey('top_deduction_reasons', $data);

        // Verify top_deduction_reasons structure
        $this->assertIsArray($data['top_deduction_reasons']);
        if (count($data['top_deduction_reasons']) > 0) {
            $firstReason = $data['top_deduction_reasons'][0];
            $this->assertArrayHasKey('reason', $firstReason);
            $this->assertArrayHasKey('days', $firstReason);
        }

        // Verify trends include BPJS data
        if (count($data['trends']) > 0) {
            $firstTrend = $data['trends'][0];
            $this->assertArrayHasKey('bpjs_employee_total', $firstTrend);
            $this->assertArrayHasKey('bpjs_employer_total', $firstTrend);
            $this->assertArrayHasKey('bpjs_combined_total', $firstTrend);
        }
    }

    public function test_analytics_permission_guard(): void
    {
        // Create user without payroll-statistics permission
        $user = User::factory()->create();
        $role = Role::findByName('staff', 'sanctum');
        $user->assignRole($role);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/payrolls/analytics?months=6')
            ->assertStatus(403);
    }

    public function test_analytics_returns_correct_deduction_reason_values(): void
    {
        $this->actingAsRole('finance');
        $this->seedPayrollData();

        $response = $this->getJson('/api/v1/payrolls/analytics?months=6');

        $response->assertOk();

        $reasons = $response->json('data.top_deduction_reasons');

        // Should have 3 reason types
        $this->assertCount(3, $reasons);

        $reasonTypes = array_column($reasons, 'reason');
        $this->assertContains('absent', $reasonTypes);
        $this->assertContains('half_day', $reasonTypes);
        $this->assertContains('unpaid_leave', $reasonTypes);

        // Should be sorted by days descending
        $days = array_column($reasons, 'days');
        $this->assertGreaterThanOrEqual($days[1], $days[0]);
        $this->assertGreaterThanOrEqual($days[2], $days[1]);
    }

    public function test_average_salary_trend_has_correct_structure(): void
    {
        $this->actingAsRole('finance');
        $this->seedPayrollData();

        $response = $this->getJson('/api/v1/payrolls/analytics?months=6');

        $response->assertOk();

        $trend = $response->json('data.average_salary_trend');
        $this->assertIsArray($trend);

        if (count($trend) > 0) {
            $this->assertArrayHasKey('salary_month', $trend[0]);
            $this->assertArrayHasKey('label', $trend[0]);
            $this->assertArrayHasKey('average_salary', $trend[0]);
        }
    }

    private function actingAsRole(string $roleName): User
    {
        $user = User::factory()->create();
        $role = Role::findByName($roleName, 'sanctum');
        $user->assignRole($role);

        Sanctum::actingAs($user);

        return $user;
    }

    private function seedPayrollData(): void
    {
        $user = User::factory()->create([
            'email' => 'analytics-employee+' . uniqid() . '@teamsync.com',
        ]);

        $staffMember = StaffMemberProfile::withoutSyncingToSearch(function () use ($user) {
            return StaffMemberProfile::factory()->for($user)->create();
        });

        // Use fixed past dates to avoid unique constraint conflicts with "now"
        $baseDate = Carbon::create(2025, 1, 1);

        // Create 3 months of payroll data
        for ($i = 0; $i < 3; $i++) {
            $month = $baseDate->copy()->addMonths($i)->startOfMonth();

            $payroll = Payroll::create([
                'salary_month' => $month->format('Y-m-d'),
                'status' => 'paid',
                'payment_date' => $month->copy()->addDays(25)->format('Y-m-d'),
                'correction_count' => 0,
            ]);

            PayrollDetail::create([
                'payroll_id' => $payroll->id,
                'staff_member_id' => $staffMember->id,
                'original_salary' => 10000000,
                'final_salary' => 9500000,
                'deduction_amount' => 500000,
                'attended_days' => 20,
                'present_days' => 18,
                'late_days' => 2,
                'half_day_count' => 1,
                'paid_leave_days' => 0,
                'unpaid_leave_days' => 1,
                'sick_days' => 1,
                'absent_days' => 2,
                'bpjs_tk_employee' => 200000,
                'bpjs_tk_employer' => 370000,
                'bpjs_kes_employee' => 100000,
                'bpjs_kes_employer' => 400000,
                'pph21_amount' => 150000,
                'notes' => 'Analytics test data',
            ]);
        }
    }
}
