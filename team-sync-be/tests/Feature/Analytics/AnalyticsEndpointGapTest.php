<?php

namespace Tests\Feature\Analytics;

use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\LeaveRequest;
use App\Models\Payroll;
use App\Models\PayrollApproval;
use App\Models\PayrollApprovalPolicy;
use App\Models\PayrollDetail;
use App\Models\StaffMemberProfile;
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
        $this->travelTo(now()->setDate(2026, 11, 23)->startOfDay());

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

    private function assertAnalyticsRouteAccessible(string $uri, string $role = 'hr'): void
    {
        if ($role === 'finance') {
            $this->actingAsFinance();
        } else {
            $this->actingAsHr();
        }

        $this->getJson($uri)->assertOk()
            ->assertJsonStructure(['success', 'message', 'data'])
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

    public function test_attendance_correction_frequency_returns_counts_and_approval_rate(): void
    {
        $member = StaffMemberProfile::factory()->create();
        foreach (['approved', 'approved', 'rejected', 'pending'] as $index => $status) {
            $attendance = Attendance::factory()->create([
                'staff_member_id' => $member->id,
                'date' => '2026-11-'.(10 + $index),
            ]);
            AttendanceCorrection::factory()->create([
                'attendance_id' => $attendance->id,
                'staff_member_id' => $member->id,
                'status' => $status,
                'created_at' => '2026-11-23 23:59:59',
            ]);
        }
        $this->actingAsHr();

        $this->getJson('/api/v1/analytics/attendance/correction-frequency?period=1m')
            ->assertOk()->assertJsonPath('success', true)
            ->assertJsonPath('data.correction_trend', [[
                'month' => 'Nov 2026', 'total' => 4, 'approved' => 2,
                'rejected' => 1, 'pending' => 1, 'approval_rate' => 50,
            ]])
            ->assertJsonPath('data.top_requesters', [[
                'employee_name' => $member->user->name,
                'employee_code' => $member->code, 'correction_count' => 4,
            ]]);
    }

    // ─── GAP-4c: Leave Approval Turnaround ──────────────────────────

    public function test_leave_approval_turnaround_returns_whole_hour_statistics(): void
    {
        $member = StaffMemberProfile::factory()->create();
        foreach (['approved' => '2026-11-10 11:59:59', 'rejected' => '2026-11-10 14:05:00', 'pending' => '2026-11-20 10:00:00'] as $status => $updatedAt) {
            LeaveRequest::factory()->create([
                'staff_member_id' => $member->id, 'status' => $status,
                'start_date' => '2026-11-15', 'end_date' => '2026-11-15', 'total_days' => 1,
                'created_at' => '2026-11-10 10:00:00', 'updated_at' => $updatedAt,
            ]);
        }
        $this->actingAsHr();

        $this->getJson('/api/v1/analytics/leave/approval-turnaround?period=1m')
            ->assertOk()->assertJsonPath('success', true)
            ->assertJsonPath('data.turnaround_by_month', [[
                'month' => 'Nov 2026', 'avg_hours' => 2.5,
                'min_hours' => 1, 'max_hours' => 4, 'total_processed' => 2,
            ]]);
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

    public function test_payroll_cost_per_employee_returns_exact_cost_and_distinct_headcount(): void
    {
        $members = StaffMemberProfile::factory()->count(3)->create();
        foreach (['2026-11-01' => 'approved', '2026-11-02' => 'paid', '2026-11-03' => 'pending'] as $date => $status) {
            $payroll = Payroll::factory()->create([
                'salary_month' => $date, 'status' => $status, 'attendance_period_id' => null,
            ]);
            foreach ($members as $index => $member) {
                $salary = $status === 'pending' ? 90000000 : ($status === 'paid' ? 1 : [3000000, 3000000, 4000001][$index]);
                PayrollDetail::create([
                    'payroll_id' => $payroll->id, 'staff_member_id' => $member->id,
                    'original_salary' => $salary, 'final_salary' => $salary,
                ]);
            }
        }
        $this->actingAsFinance();

        $this->getJson('/api/v1/analytics/payroll/cost-per-employee?period=1m')
            ->assertOk()->assertJsonPath('success', true)
            ->assertJsonPath('data.cost_per_employee_trend', [[
                'month' => 'Nov 2026', 'total_cost' => 10000004,
                'employee_count' => 3, 'cost_per_employee' => 3333334.67,
            ]]);
    }

    // ─── GAP-4f: Payroll Processing Time ────────────────────────────

    public function test_payroll_processing_time_counts_each_payroll_at_its_final_approved_step(): void
    {
        $policies = [];
        foreach ([1, 2] as $order) {
            $policies[] = PayrollApprovalPolicy::create([
                'name' => 'Approval '.$order, 'min_amount' => 0,
                'required_role' => 'finance', 'approval_order' => $order,
            ]);
        }
        foreach ([
            ['2026-11-01', 'approved', '2026-11-10 11:59:59'],
            ['2026-11-02', 'paid', '2026-11-10 14:05:00'],
            ['2026-11-03', 'pending', '2026-11-20 10:00:00'],
            ['2026-11-04', 'approved', null],
            ['2026-08-01', 'approved', '2026-11-20 10:00:00'],
        ] as [$date, $status, $approvedAt]) {
            $payroll = Payroll::factory()->create([
                'salary_month' => $date, 'status' => $status, 'attendance_period_id' => null,
                'created_at' => '2026-11-10 10:00:00',
            ]);
            PayrollApproval::create([
                'payroll_id' => $payroll->id, 'policy_id' => $policies[0]->id,
                'status' => 'approved', 'approved_at' => $approvedAt,
            ]);
            if ($date === '2026-11-02') {
                PayrollApproval::create([
                    'payroll_id' => $payroll->id, 'policy_id' => $policies[1]->id,
                    'status' => 'approved', 'approved_at' => '2026-11-10 10:30:00',
                ]);
            }
            if ($date === '2026-11-01') {
                PayrollApproval::create([
                    'payroll_id' => $payroll->id, 'policy_id' => $policies[1]->id,
                    'status' => 'rejected', 'approved_at' => '2026-11-20 10:00:00',
                ]);
            }
        }
        $this->actingAsFinance();

        $this->getJson('/api/v1/analytics/payroll/processing-time?period=1m')
            ->assertOk()->assertJsonPath('success', true)
            ->assertJsonPath('data.processing_time_trend', [[
                'month' => 'Nov 2026', 'avg_hours_to_approve' => 2.5, 'total_payrolls' => 2,
            ]]);
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
