<?php

namespace Tests\Feature\Performance;

use App\Models\PerformanceReviewCycle;
use App\Models\PerformanceReviewSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\ActivatesLicense;
use Tests\TestCase;

class PerformanceTopsisControllerTest extends TestCase
{
    use ActivatesLicense, RefreshDatabase;

    private User $hrAdmin;

    private User $employee;

    private PerformanceReviewCycle $completedCycle;

    private PerformanceReviewCycle $activeCycle;

    private PerformanceReviewSection $sectionC1;

    private PerformanceReviewSection $sectionC2;

    private PerformanceReviewSection $sectionC3;

    private PerformanceReviewSection $sectionC4;

    private PerformanceReviewSection $sectionC5;

    protected function setUp(): void
    {
        parent::setUp();

        // Run seeders required for full data
        $this->artisan('db:seed', ['--class' => 'RoleSeeder']);
        $this->artisan('db:seed', ['--class' => 'PermissionSeeder']);
        $this->artisan('db:seed', ['--class' => 'RolePermissionSeeder']);
        $this->artisan('db:seed', ['--class' => 'PerformanceReviewSectionSeeder']);
        $this->artisan('db:seed', ['--class' => 'ManagerSeeder']);
        $this->artisan('db:seed', ['--class' => 'EmployeeSeeder']);
        $this->artisan('db:seed', ['--class' => 'HrSeeder']);
        $this->artisan('db:seed', ['--class' => 'FinanceSeeder']);
        $this->artisan('db:seed', ['--class' => 'PerformanceDataSeeder']);
        $this->activateTestLicense();

        // Give HR permission
        $hrRole = Role::findByName('hr', 'sanctum');
        $permission = Permission::firstOrCreate(['name' => 'performance.cycles.view', 'guard_name' => 'sanctum']);
        $hrRole->givePermissionTo($permission);
        $permissionRank = Permission::firstOrCreate(['name' => 'performance.topsis.view', 'guard_name' => 'sanctum']);
        $hrRole->givePermissionTo($permissionRank);

        $this->hrAdmin = User::where('email', 'tasyia@teamsync.com')->first();
        $this->employee = User::where('email', 'agung@teamsync.com')->first();

        // Q4 2025 is completed, Q1 2026 is active
        $this->completedCycle = PerformanceReviewCycle::where('name', 'Q4 2025 Performance Review')->first();
        $this->activeCycle = PerformanceReviewCycle::where('name', 'Q1 2026 Performance Review')->first();
    }

    public function test_hr_can_fetch_topsis_ranking_for_completed_cycle()
    {
        Sanctum::actingAs($this->hrAdmin);

        $response = $this->getJson("/api/v1/performance/cycles/{$this->completedCycle->id}/topsis-ranking");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'cycle_id',
                'cycle_name',
                'cycle_status',
                'total_candidates',
                'ranking',
                'weights',
                'ideal_positive',
                'ideal_negative',
            ],
        ]);

        $this->assertGreaterThanOrEqual(1, $response->json('data.total_candidates'));
        $this->assertNotEmpty($response->json('data.ranking'));
    }

    public function test_topsis_endpoint_returns_422_empty_state_when_no_completed_reviews()
    {
        Sanctum::actingAs($this->hrAdmin);

        // Fetch ranking for active cycle which has NO completed reviews
        $response = $this->getJson("/api/v1/performance/cycles/{$this->activeCycle->id}/topsis-ranking");

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'data' => [
                'total_completed' => 0,
                'ranking' => [],
            ],
        ]);
    }

    public function test_topsis_endpoint_returns_404_for_missing_cycle()
    {
        Sanctum::actingAs($this->hrAdmin);

        $response = $this->getJson('/api/v1/performance/cycles/99999/topsis-ranking');

        $response->assertStatus(404);
        $response->assertJson([
            'success' => false,
            'message' => 'Review cycle tidak ditemukan',
        ]);
    }

    public function test_topsis_endpoint_applies_custom_weights()
    {
        Sanctum::actingAs($this->hrAdmin);

        $response = $this->getJson("/api/v1/performance/cycles/{$this->completedCycle->id}/topsis-ranking?w_avg_manager_rating=0.25&w_final_rating=0.15&w_avg_goal_completion=0.15&w_goal_completion_ratio=0.10&w_positive_feedback_count=0.15&w_attendance_quality=0.10&w_task_completion_quality=0.10");
        $response->assertStatus(200);
        $this->assertArrayHasKey('avg_manager_rating', $response->json('data.weights'));
        $this->assertEquals(0.25, $response->json('data.weights.avg_manager_rating'));
        $this->assertArrayHasKey('final_rating', $response->json('data.weights'));
        $this->assertArrayHasKey('avg_goal_completion', $response->json('data.weights'));
        $this->assertArrayHasKey('goal_completion_ratio', $response->json('data.weights'));
        $this->assertArrayHasKey('positive_feedback_count', $response->json('data.weights'));
        $this->assertArrayHasKey('attendance_quality', $response->json('data.weights'));
        $this->assertArrayHasKey('task_completion_quality', $response->json('data.weights'));
    }

    public function test_employee_cannot_access_topsis_ranking()
    {
        Sanctum::actingAs($this->employee);

        $response = $this->getJson("/api/v1/performance/cycles/{$this->completedCycle->id}/topsis-ranking");

        $this->assertTrue(in_array($response->status(), [403, 401]));
    }
}
