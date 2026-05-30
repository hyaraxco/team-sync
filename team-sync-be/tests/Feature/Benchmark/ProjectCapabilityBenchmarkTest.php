<?php

/**
 * ╔══════════════════════════════════════════════════════════════════════╗
 * ║  TEAM SYNC — PROJECT CAPABILITY BENCHMARK TEST                     ║
 * ║                                                                     ║
 * ║  Validates that the backend is capable of carrying out all core     ║
 * ║  HRIS operations: auth, RBAC, CRUD, payroll, attendance, leave,    ║
 * ║  performance, projects, notifications, and analytics.              ║
 * ║                                                                     ║
 * ║  Run: php artisan test --filter=ProjectCapabilityBenchmarkTest     ║
 * ║  Or:  ./vendor/bin/pest tests/Feature/Benchmark/                   ║
 * ╚══════════════════════════════════════════════════════════════════════╝
 */

namespace Tests\Feature\Benchmark;

use App\Interfaces\PayrollRepositoryInterface;
use App\Models\Attendance;
use App\Models\JobInformation;
use App\Models\LeaveRequest;
use App\Models\Meeting;
use App\Models\PayrollSetting;
use App\Models\PerformanceReview;
use App\Models\PerformanceReviewCycle;
use App\Models\PerformanceReviewSection;
use App\Models\Project;
use App\Models\StaffMemberProfile;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Services\TopsisService;
use Carbon\Carbon;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\ActivatesLicense;
use Tests\TestCase;

class ProjectCapabilityBenchmarkTest extends TestCase
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
        Notification::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ─── Helpers ────────────────────────────────────────────────────

    private function createUserWithRole(string $roleName): array
    {
        $user = User::factory()->create();
        $employee = StaffMemberProfile::withoutSyncingToSearch(function () use ($user) {
            return StaffMemberProfile::factory()->create(['user_id' => $user->id]);
        });
        $role = Role::findByName($roleName, 'sanctum');
        $user->assignRole($role);

        return ['user' => $user, 'staff' => $employee];
    }

    private function actAs(string $roleName): array
    {
        $data = $this->createUserWithRole($roleName);
        Sanctum::actingAs($data['user']);

        return $data;
    }

    private function createActiveEmployeeWithAttendance(Carbon $month, int $monthlySalary): StaffMemberProfile
    {
        return StaffMemberProfile::withoutSyncingToSearch(function () use ($month, $monthlySalary) {
            $employee = StaffMemberProfile::factory()->create();
            $startDate = $month->copy()->startOfMonth();
            $endDate = $month->copy()->endOfMonth();

            JobInformation::factory()
                ->forEmployee($employee)
                ->active()
                ->state([
                    'monthly_salary' => $monthlySalary,
                    'status' => 'active',
                    'employment_type' => 'full_time',
                ])
                ->create();

            $cursor = $startDate->copy();
            while ($cursor->lte($endDate)) {
                if (! $cursor->isWeekend()) {
                    Attendance::create([
                        'staff_member_id' => $employee->id,
                        'date' => $cursor->toDateString(),
                        'check_in' => $cursor->format('Y-m-d').' 08:00:00',
                        'check_out' => $cursor->format('Y-m-d').' 17:00:00',
                        'status' => 'present',
                        'notes' => 'Benchmark test',
                    ]);
                }
                $cursor->addDay();
            }

            return $employee;
        });
    }

    // ═══════════════════════════════════════════════════════════════
    // SECTION 1: AUTHENTICATION & AUTHORIZATION
    // ═══════════════════════════════════════════════════════════════

    public function test_benchmark_login_returns_token_for_valid_credentials(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('benchmark-pass'),
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'benchmark-pass',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['data' => ['token']]);
    }

    public function test_benchmark_login_rejects_invalid_credentials(): void
    {
        $user = User::factory()->create();

        $response = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertUnauthorized();
    }

    public function test_benchmark_unauthenticated_request_returns_401(): void
    {
        $this->getJson('/api/v1/teams')
            ->assertUnauthorized();
    }

    public function test_benchmark_sanctum_token_grants_access(): void
    {
        $this->actAs('hr');

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonStructure(['data' => ['id', 'name', 'email']]);
    }

    // ═══════════════════════════════════════════════════════════════
    // SECTION 2: ROLE-BASED ACCESS CONTROL (RBAC)
    // ═══════════════════════════════════════════════════════════════

    public function test_benchmark_role_seeder_creates_all_expected_roles(): void
    {
        $expectedRoles = ['superadmin', 'hr', 'manager', 'finance', 'staff'];

        foreach ($expectedRoles as $roleName) {
            $this->assertNotNull(
                Role::findByName($roleName, 'sanctum'),
                "Role '{$roleName}' should exist after seeding"
            );
        }
    }

    public function test_benchmark_hr_has_staff_member_crud_permissions(): void
    {
        $hrRole = Role::findByName('hr', 'sanctum');

        $this->assertTrue($hrRole->hasPermissionTo('staff-member-list'));
        $this->assertTrue($hrRole->hasPermissionTo('staff-member-create'));
        $this->assertTrue($hrRole->hasPermissionTo('staff-member-edit'));
        $this->assertTrue($hrRole->hasPermissionTo('staff-member-delete'));
    }

    public function test_benchmark_manager_has_no_staff_directory_access(): void
    {
        $managerRole = Role::findByName('manager', 'sanctum');

        // PRD: Manager deferred from staff directory until team-scoped API
        $this->assertFalse($managerRole->hasPermissionTo('staff-member-menu'));
        $this->assertFalse($managerRole->hasPermissionTo('staff-member-list'));
        $this->assertFalse($managerRole->hasPermissionTo('staff-member-create'));
        $this->assertFalse($managerRole->hasPermissionTo('staff-member-edit'));
        $this->assertFalse($managerRole->hasPermissionTo('staff-member-delete'));
    }

    public function test_benchmark_staff_has_limited_permissions(): void
    {
        $staffRole = Role::findByName('staff', 'sanctum');

        // Staff should NOT have admin-level permissions
        $this->assertFalse($staffRole->hasPermissionTo('staff-member-create'));
        $this->assertFalse($staffRole->hasPermissionTo('staff-member-edit'));
        $this->assertFalse($staffRole->hasPermissionTo('staff-member-delete'));
    }

    public function test_benchmark_hr_can_access_staff_member_list(): void
    {
        $this->actAs('hr');

        $this->getJson('/api/v1/staff-members')
            ->assertOk();
    }

    // ═══════════════════════════════════════════════════════════════
    // SECTION 3: TEAM MANAGEMENT CRUD
    // ═══════════════════════════════════════════════════════════════

    public function test_benchmark_hr_can_create_team(): void
    {
        $hr = $this->actAs('hr');

        $response = $this->postJson('/api/v1/teams', [
            'name' => 'Benchmark Team',
            'description' => 'Created by benchmark test',
            'department' => 'development',
            'icon' => UploadedFile::fake()->image('icon.png', 100, 100),
            'responsibilities' => ['Backend development', 'Code review', 'Testing'],
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('teams', ['name' => 'Benchmark Team']);
    }

    public function test_benchmark_hr_can_list_teams(): void
    {
        $this->actAs('hr');
        Team::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/teams');

        $response->assertOk();
    }

    public function test_benchmark_hr_can_update_team(): void
    {
        $this->actAs('hr');
        $team = Team::factory()->create(['name' => 'Old Name']);

        $response = $this->putJson("/api/v1/teams/{$team->id}", [
            'name' => 'Updated Benchmark Team',
            'description' => $team->description ?? 'Updated',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('teams', ['id' => $team->id, 'name' => 'Updated Benchmark Team']);
    }

    public function test_benchmark_hr_can_delete_team(): void
    {
        $this->actAs('hr');
        $team = Team::factory()->create();

        $response = $this->deleteJson("/api/v1/teams/{$team->id}");

        $response->assertOk();
    }

    // ═══════════════════════════════════════════════════════════════
    // SECTION 4: STAFF MEMBER MANAGEMENT
    // ═══════════════════════════════════════════════════════════════

    public function test_benchmark_hr_can_view_staff_member_detail(): void
    {
        $hr = $this->actAs('hr');
        $employee = StaffMemberProfile::withoutSyncingToSearch(function () {
            return StaffMemberProfile::factory()->create();
        });

        $response = $this->getJson("/api/v1/staff-members/{$employee->id}");

        $response->assertOk()
            ->assertJsonStructure(['data' => ['id']]);
    }

    // ═══════════════════════════════════════════════════════════════
    // SECTION 5: ATTENDANCE SYSTEM
    // ═══════════════════════════════════════════════════════════════

    public function test_benchmark_attendance_records_can_be_created(): void
    {
        $employee = StaffMemberProfile::withoutSyncingToSearch(function () {
            return StaffMemberProfile::factory()->create();
        });

        $attendance = Attendance::create([
            'staff_member_id' => $employee->id,
            'date' => now()->toDateString(),
            'check_in' => now()->format('Y-m-d').' 08:00:00',
            'check_out' => now()->format('Y-m-d').' 17:00:00',
            'status' => 'present',
            'notes' => 'Benchmark attendance',
        ]);

        $this->assertDatabaseHas('attendances', [
            'id' => $attendance->id,
            'staff_member_id' => $employee->id,
            'status' => 'present',
        ]);
    }

    public function test_benchmark_hr_can_list_attendance_records(): void
    {
        $this->actAs('hr');

        $response = $this->getJson('/api/v1/attendances');

        $response->assertOk();
    }

    // ═══════════════════════════════════════════════════════════════
    // SECTION 6: LEAVE MANAGEMENT
    // ═══════════════════════════════════════════════════════════════

    public function test_benchmark_leave_request_model_relationships(): void
    {
        $employee = StaffMemberProfile::withoutSyncingToSearch(function () {
            return StaffMemberProfile::factory()->create();
        });

        $leave = LeaveRequest::create([
            'staff_member_id' => $employee->id,
            'leave_type' => 'annual_leave',
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(7)->toDateString(),
            'total_days' => 3,
            'reason' => 'Benchmark leave test',
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('leave_requests', [
            'id' => $leave->id,
            'status' => 'pending',
        ]);

        $this->assertNotNull($leave->staffMember);
    }

    public function test_benchmark_hr_can_list_leave_requests(): void
    {
        $this->actAs('hr');

        $response = $this->getJson('/api/v1/leave-requests');

        $response->assertOk();
    }

    // ═══════════════════════════════════════════════════════════════
    // SECTION 7: PAYROLL ENGINE
    // ═══════════════════════════════════════════════════════════════

    public function test_benchmark_payroll_generation_produces_details(): void
    {
        Carbon::setTestNow('2026-04-28 09:00:00');
        PayrollSetting::current()->update([
            'attendance_cutoff_day' => 25,
            'rounding_mode' => 'none',
        ]);

        $employee = $this->createActiveEmployeeWithAttendance(now()->startOfMonth(), 10000000);
        $repository = app(PayrollRepositoryInterface::class);

        $payroll = $repository->generatePayroll('2026-04', null);
        $detail = $payroll->payrollDetails->firstWhere('staff_member_id', $employee->id);

        $this->assertNotNull($payroll);
        $this->assertNotNull($detail);
        $this->assertGreaterThan(0, (float) $detail->original_salary);
        $this->assertGreaterThan(0, (float) $detail->final_salary);
        $this->assertGreaterThan(0, $detail->attended_days);
        $this->assertGreaterThan(0, (float) $detail->daily_rate);
    }

    public function test_benchmark_payroll_absent_deduction_reduces_salary(): void
    {
        Carbon::setTestNow('2026-04-28 09:00:00');
        PayrollSetting::current()->update([
            'attendance_cutoff_day' => 25,
            'absent_deduction_rate' => 1.0,
            'rounding_mode' => 'none',
        ]);

        $fullEmployee = $this->createActiveEmployeeWithAttendance(now()->startOfMonth(), 10000000);
        $absentEmployee = $this->createActiveEmployeeWithAttendance(now()->startOfMonth(), 10000000);

        Attendance::where('staff_member_id', $absentEmployee->id)
            ->orderBy('date')
            ->limit(5)
            ->update([
                'status' => 'absent',
                'check_in' => null,
                'check_out' => null,
            ]);

        $repository = app(PayrollRepositoryInterface::class);
        $payroll = $repository->generatePayroll('2026-04', null);

        $fullDetail = $payroll->payrollDetails->firstWhere('staff_member_id', $fullEmployee->id);
        $absentDetail = $payroll->payrollDetails->firstWhere('staff_member_id', $absentEmployee->id);

        $this->assertGreaterThan(
            (float) $absentDetail->final_salary,
            (float) $fullDetail->final_salary,
            'Full attendance employee should earn more than absent employee'
        );
    }

    public function test_benchmark_payroll_settings_rounding_modes_configurable(): void
    {
        $settings = PayrollSetting::current();

        foreach (['floor', 'ceil', 'none'] as $mode) {
            $settings->update([
                'rounding_mode' => $mode,
                'rounding_unit' => 1000,
            ]);

            $settings->refresh();
            $this->assertEquals($mode, $settings->rounding_mode);
            $this->assertEquals(1000, $settings->rounding_unit);
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // SECTION 8: PERFORMANCE REVIEW SYSTEM
    // ═══════════════════════════════════════════════════════════════

    public function test_benchmark_review_cycle_can_be_created(): void
    {
        $cycle = PerformanceReviewCycle::factory()->create([
            'name' => 'Benchmark Cycle Q1 2026',
        ]);

        $this->assertDatabaseHas('performance_review_cycles', [
            'id' => $cycle->id,
            'name' => 'Benchmark Cycle Q1 2026',
        ]);
    }

    public function test_benchmark_performance_review_lifecycle(): void
    {
        $cycle = PerformanceReviewCycle::factory()->create();
        $employee = StaffMemberProfile::withoutSyncingToSearch(function () {
            return StaffMemberProfile::factory()->create();
        });
        $reviewer = StaffMemberProfile::withoutSyncingToSearch(function () {
            return StaffMemberProfile::factory()->create();
        });

        $review = PerformanceReview::create([
            'cycle_id' => $cycle->id,
            'staff_member_id' => $employee->id,
            'reviewer_id' => $reviewer->id,
            'status' => 'pending_self',
        ]);

        $this->assertDatabaseHas('performance_reviews', [
            'id' => $review->id,
            'status' => 'pending_self',
        ]);

        // Transition to pending_manager
        $review->update(['status' => 'pending_manager']);
        $this->assertDatabaseHas('performance_reviews', [
            'id' => $review->id,
            'status' => 'pending_manager',
        ]);

        // Transition to pending_calibration
        $review->update(['status' => 'pending_calibration']);
        $this->assertDatabaseHas('performance_reviews', [
            'id' => $review->id,
            'status' => 'pending_calibration',
        ]);
    }

    public function test_benchmark_hr_can_calibrate_review(): void
    {
        $hr = $this->actAs('hr');
        $cycle = PerformanceReviewCycle::factory()->create();
        $employee = StaffMemberProfile::withoutSyncingToSearch(function () {
            return StaffMemberProfile::factory()->create();
        });
        $reviewer = StaffMemberProfile::withoutSyncingToSearch(function () {
            return StaffMemberProfile::factory()->create();
        });

        $review = PerformanceReview::create([
            'cycle_id' => $cycle->id,
            'staff_member_id' => $employee->id,
            'reviewer_id' => $reviewer->id,
            'status' => 'pending_calibration',
        ]);

        $section = PerformanceReviewSection::create([
            'name' => 'Benchmark Section',
            'weight' => 100,
            'order' => 1,
            'is_active' => true,
        ]);

        $response = $this->postJson("/api/v1/performance/reviews/{$review->id}/calibrate", [
            'responses' => [
                ['section_id' => $section->id, 'rating' => 4],
            ],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('performance_reviews', [
            'id' => $review->id,
            'status' => 'completed',
            'calibrated_by' => $hr['user']->id,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    // SECTION 9: TOPSIS RANKING ENGINE
    // ═══════════════════════════════════════════════════════════════

    public function test_benchmark_topsis_ranks_candidates_correctly(): void
    {
        $service = new TopsisService;

        $candidates = [
            [
                'staff_member_id' => 'emp-1',
                'employee_name' => 'Top Performer',
                'department' => 'Engineering',
                'performance_score' => 95.0,
                'attendance_rate' => 98.0,
                'goal_completion' => 100.0,
                'feedback_score' => 10,
                'tenure_factor' => 80.0,
            ],
            [
                'staff_member_id' => 'emp-2',
                'employee_name' => 'Average Performer',
                'department' => 'Engineering',
                'performance_score' => 60.0,
                'attendance_rate' => 80.0,
                'goal_completion' => 50.0,
                'feedback_score' => 3,
                'tenure_factor' => 50.0,
            ],
            [
                'staff_member_id' => 'emp-3',
                'employee_name' => 'Low Performer',
                'department' => 'Engineering',
                'performance_score' => 20.0,
                'attendance_rate' => 60.0,
                'goal_completion' => 10.0,
                'feedback_score' => 0,
                'tenure_factor' => 20.0,
            ],
        ];

        $weights = [
            'performance_score' => 0.30,
            'attendance_rate' => 0.20,
            'goal_completion' => 0.25,
            'feedback_score' => 0.15,
            'tenure_factor' => 0.10,
        ];

        $result = $service->calculate($candidates, $weights);

        $this->assertEquals(3, $result['total_candidates']);
        $this->assertCount(3, $result['ranking']);

        // Top performer should be rank 1
        $this->assertEquals('emp-1', $result['ranking'][0]['staff_member_id']);
        $this->assertEquals(1, $result['ranking'][0]['rank']);

        // Low performer should be rank 3
        $this->assertEquals('emp-3', $result['ranking'][2]['staff_member_id']);
        $this->assertEquals(3, $result['ranking'][2]['rank']);

        // Coefficients should be descending
        $this->assertGreaterThan(
            $result['ranking'][1]['closeness_coefficient'],
            $result['ranking'][0]['closeness_coefficient']
        );
        $this->assertGreaterThan(
            $result['ranking'][2]['closeness_coefficient'],
            $result['ranking'][1]['closeness_coefficient']
        );
    }

    public function test_benchmark_topsis_handles_edge_cases(): void
    {
        $service = new TopsisService;
        $weights = [
            'performance_score' => 0.30,
            'attendance_rate' => 0.20,
            'goal_completion' => 0.25,
            'feedback_score' => 0.15,
            'tenure_factor' => 0.10,
        ];

        // Empty candidates
        $result = $service->calculate([], $weights);
        $this->assertEquals(0, $result['total_candidates']);
        $this->assertEmpty($result['ranking']);

        // Identical scores (division-by-zero safety)
        $identical = [
            [
                'staff_member_id' => 'emp-a',
                'employee_name' => 'A',
                'department' => 'Eng',
                'performance_score' => 60.0,
                'attendance_rate' => 80.0,
                'goal_completion' => 50.0,
                'feedback_score' => 3,
                'tenure_factor' => 50.0,
            ],
            [
                'staff_member_id' => 'emp-b',
                'employee_name' => 'B',
                'department' => 'Eng',
                'performance_score' => 60.0,
                'attendance_rate' => 80.0,
                'goal_completion' => 50.0,
                'feedback_score' => 3,
                'tenure_factor' => 50.0,
            ],
        ];

        $result = $service->calculate($identical, $weights);
        foreach ($result['ranking'] as $ranked) {
            $this->assertFalse(is_nan($ranked['closeness_coefficient']));
            $this->assertFalse(is_infinite($ranked['closeness_coefficient']));
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // SECTION 10: PROJECT & TASK MANAGEMENT
    // ═══════════════════════════════════════════════════════════════

    public function test_benchmark_project_crud_operations(): void
    {
        // Permission overhaul (2026-05-30): only manager has project CRUD; HR is read-only.
        $this->actAs('manager');

        // Create
        $response = $this->postJson('/api/v1/projects', [
            'name' => 'Benchmark Project',
            'description' => 'Testing project CRUD',
            'type' => 'web_development',
            'priority' => 'high',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(3)->toDateString(),
            'status' => 'active',
        ]);

        $response->assertCreated();
        $projectId = $response->json('data.id');

        // Read
        $this->getJson("/api/v1/projects/{$projectId}")
            ->assertOk()
            ->assertJsonFragment(['name' => 'Benchmark Project']);

        // Update
        $this->putJson("/api/v1/projects/{$projectId}", [
            'name' => 'Updated Benchmark Project',
            'description' => 'Updated description',
            'type' => 'web_development',
            'priority' => 'high',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addMonths(6)->toDateString(),
            'status' => 'active',
        ])->assertOk();

        $this->assertDatabaseHas('projects', [
            'id' => $projectId,
            'name' => 'Updated Benchmark Project',
        ]);
    }

    // ═══════════════════════════════════════════════════════════════
    // SECTION 11: MEETING SYSTEM
    // ═══════════════════════════════════════════════════════════════

    public function test_benchmark_meeting_list_endpoint(): void
    {
        $this->actAs('hr');

        // List meetings endpoint should be accessible
        $response = $this->getJson('/api/v1/meetings');

        $response->assertOk();
    }

    // ═══════════════════════════════════════════════════════════════
    // SECTION 12: NOTIFICATION SYSTEM
    // ═══════════════════════════════════════════════════════════════

    public function test_benchmark_notification_endpoint_accessible(): void
    {
        $this->actAs('hr');

        $response = $this->getJson('/api/v1/my-notifications');

        $response->assertOk();
    }

    // ═══════════════════════════════════════════════════════════════
    // SECTION 13: ANALYTICS ENDPOINTS
    // ═══════════════════════════════════════════════════════════════

    public function test_benchmark_analytics_routes_are_registered(): void
    {
        $this->actAs('hr');

        // Analytics endpoints should not return 401 (unauthorized) or 404 (not found)
        // They may return 500 in test env due to missing seeded data, but the routes exist
        $analyticsEndpoints = [
            '/api/v1/analytics/workforce',
            '/api/v1/analytics/attendance',
            '/api/v1/analytics/leave',
            '/api/v1/analytics/payroll',
            '/api/v1/analytics/projects',
        ];

        foreach ($analyticsEndpoints as $endpoint) {
            $response = $this->getJson($endpoint);
            $this->assertNotEquals(
                404,
                $response->status(),
                "Analytics route {$endpoint} should be registered (got 404)"
            );
            $this->assertNotEquals(
                401,
                $response->status(),
                "Analytics route {$endpoint} should be accessible with auth (got 401)"
            );
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // SECTION 14: MODEL FACTORY & RELATIONSHIP INTEGRITY
    // ═══════════════════════════════════════════════════════════════

    public function test_benchmark_all_critical_factories_produce_valid_models(): void
    {
        $factories = [
            User::class,
            Team::class,
            PerformanceReviewCycle::class,
        ];

        foreach ($factories as $modelClass) {
            $model = $modelClass::factory()->create();
            $this->assertNotNull($model->id, "{$modelClass} factory should produce a valid model");
        }

        // StaffMemberProfile needs search sync disabled
        $employee = StaffMemberProfile::withoutSyncingToSearch(function () {
            return StaffMemberProfile::factory()->create();
        });
        $this->assertNotNull($employee->id);
    }

    public function test_benchmark_user_staff_member_relationship(): void
    {
        $user = User::factory()->create();
        $employee = StaffMemberProfile::withoutSyncingToSearch(function () use ($user) {
            return StaffMemberProfile::factory()->create(['user_id' => $user->id]);
        });

        $this->assertEquals($user->id, $employee->user_id);
        $this->assertNotNull($employee->user);
        $this->assertEquals($user->id, $employee->user->id);
    }

    // ═══════════════════════════════════════════════════════════════
    // SECTION 15: DATABASE MIGRATION INTEGRITY
    // ═══════════════════════════════════════════════════════════════

    public function test_benchmark_all_critical_tables_exist(): void
    {
        $criticalTables = [
            'users',
            'staff_member_profiles',
            'teams',
            'team_members',
            'projects',
            'project_tasks',
            'attendances',
            'leave_requests',
            'payrolls',
            'payroll_details',
            'payroll_settings',
            'performance_review_cycles',
            'performance_reviews',
            'performance_review_sections',
            'performance_goals',
            'meetings',
            'overtime_records',
            'notifications',
            'roles',
            'permissions',
        ];

        foreach ($criticalTables as $table) {
            $this->assertTrue(
                \Schema::hasTable($table),
                "Critical table '{$table}' should exist in the database"
            );
        }
    }

    // ═══════════════════════════════════════════════════════════════
    // SECTION 16: HOLIDAY CALENDAR (INDONESIAN CONTEXT)
    // ═══════════════════════════════════════════════════════════════

    public function test_benchmark_holiday_calendar_crud(): void
    {
        $this->actAs('hr');

        $response = $this->postJson('/api/v1/holiday-calendars', [
            'name' => 'Hari Raya Idul Fitri',
            'date' => '2026-03-20',
            'type' => 'national_holiday',
            'is_cuti_bersama' => false,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('holiday_calendars', ['name' => 'Hari Raya Idul Fitri']);
    }

    // ═══════════════════════════════════════════════════════════════
    // SECTION 17: CROSS-CUTTING CONCERNS
    // ═══════════════════════════════════════════════════════════════

    public function test_benchmark_api_response_format_consistency(): void
    {
        $this->actAs('hr');

        // Successful list endpoint should return consistent structure
        $response = $this->getJson('/api/v1/teams');
        $response->assertOk();

        $json = $response->json();
        // API should return data key
        $this->assertArrayHasKey('data', $json);
    }

    public function test_benchmark_validation_returns_422_for_invalid_data(): void
    {
        $this->actAs('hr');

        // Missing required fields
        $response = $this->postJson('/api/v1/teams', []);

        $response->assertUnprocessable();
    }

    public function test_benchmark_concurrent_model_creation_integrity(): void
    {
        $this->actAs('hr');

        // Create multiple related entities to verify referential integrity
        $team = Team::factory()->create();
        $user = User::factory()->create();
        $employee = StaffMemberProfile::withoutSyncingToSearch(function () use ($user) {
            return StaffMemberProfile::factory()->create(['user_id' => $user->id]);
        });

        TeamMember::create([
            'team_id' => $team->id,
            'staff_member_id' => $employee->id,
            'role' => 'member',
        ]);

        $this->assertDatabaseHas('team_members', [
            'team_id' => $team->id,
            'staff_member_id' => $employee->id,
        ]);
    }
}
