<?php

namespace Tests\Feature\Performance;

use App\Models\PerformanceReviewSection;
use App\Models\PerformanceReviewTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PerformanceReviewTemplateControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRolesAndPermissions();
        $this->seedSections();
    }

    private function seedRolesAndPermissions(): void
    {
        Permission::create(['name' => 'review-cycle-manage', 'guard_name' => 'sanctum']);

        Role::create(['name' => 'HR', 'guard_name' => 'sanctum'])
            ->givePermissionTo(['review-cycle-manage']);

        Role::create(['name' => 'Employee', 'guard_name' => 'sanctum']);
    }

    private function seedSections(): void
    {
        PerformanceReviewSection::create([
            'name' => 'Technical Skills',
            'description' => 'Technical proficiency',
            'weight' => 25.00,
            'topsis_category' => 'kpi',
            'order' => 1,
            'is_active' => true,
        ]);

        PerformanceReviewSection::create([
            'name' => 'Leadership',
            'description' => 'Leadership capabilities',
            'weight' => 25.00,
            'topsis_category' => 'competency',
            'order' => 2,
            'is_active' => true,
        ]);
    }

    private function actingAsRole(string $roleName): User
    {
        $user = User::factory()->create();
        $role = Role::findByName($roleName, 'sanctum');
        $user->assignRole($role);
        Sanctum::actingAs($user);

        return $user;
    }

    // ── Index ────────────────────────────────────────────────────────

    public function test_hr_can_list_templates(): void
    {
        $this->actingAsRole('HR');

        PerformanceReviewTemplate::create([
            'name' => 'Staff Template',
            'is_active' => true,
            'is_default' => true,
        ]);

        $response = $this->getJson('/api/v1/performance/templates');

        $response->assertOk()
            ->assertJsonStructure(['success', 'message', 'data'])
            ->assertJsonCount(1, 'data');
    }

    // ── Store ────────────────────────────────────────────────────────

    public function test_hr_can_create_template(): void
    {
        $this->actingAsRole('HR');
        $sections = PerformanceReviewSection::all();

        $payload = [
            'name' => 'New Template',
            'description' => 'A test template',
            'is_active' => true,
            'is_default' => false,
            'sections' => [
                ['id' => $sections[0]->id, 'weight' => 60],
                ['id' => $sections[1]->id, 'weight' => 40],
            ],
        ];

        $response = $this->postJson('/api/v1/performance/templates', $payload);

        $response->assertCreated()
            ->assertJsonFragment(['name' => 'New Template']);

        $this->assertDatabaseHas('performance_review_templates', ['name' => 'New Template']);
    }

    public function test_create_validates_required_fields(): void
    {
        $this->actingAsRole('HR');

        $response = $this->postJson('/api/v1/performance/templates', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'sections']);
    }

    public function test_create_validates_section_exists(): void
    {
        $this->actingAsRole('HR');

        $response = $this->postJson('/api/v1/performance/templates', [
            'name' => 'Invalid Section',
            'sections' => [
                ['id' => 99999, 'weight' => 100],
            ],
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['sections.0.id']);
    }

    public function test_setting_default_unsets_previous_default(): void
    {
        $this->actingAsRole('HR');
        $sections = PerformanceReviewSection::all();

        $existing = PerformanceReviewTemplate::create([
            'name' => 'Old Default',
            'is_active' => true,
            'is_default' => true,
        ]);

        $payload = [
            'name' => 'New Default',
            'is_default' => true,
            'sections' => [
                ['id' => $sections[0]->id, 'weight' => 50],
                ['id' => $sections[1]->id, 'weight' => 50],
            ],
        ];

        $response = $this->postJson('/api/v1/performance/templates', $payload);
        $response->assertCreated();

        $this->assertFalse($existing->fresh()->is_default);
    }

    // ── Show ─────────────────────────────────────────────────────────

    public function test_hr_can_view_template(): void
    {
        $this->actingAsRole('HR');
        $template = PerformanceReviewTemplate::create([
            'name' => 'View Test',
            'is_active' => true,
            'is_default' => false,
        ]);

        $response = $this->getJson("/api/v1/performance/templates/{$template->id}");

        $response->assertOk()
            ->assertJsonFragment(['name' => 'View Test']);
    }

    public function test_show_returns_404_for_missing(): void
    {
        $this->actingAsRole('HR');

        $response = $this->getJson('/api/v1/performance/templates/99999');

        $response->assertNotFound();
    }

    // ── Update ───────────────────────────────────────────────────────

    public function test_hr_can_update_template(): void
    {
        $this->actingAsRole('HR');
        $sections = PerformanceReviewSection::all();

        $template = PerformanceReviewTemplate::create([
            'name' => 'Before Update',
            'is_active' => true,
            'is_default' => false,
        ]);
        $template->sections()->attach($sections[0]->id, ['weight' => 100]);

        $response = $this->putJson("/api/v1/performance/templates/{$template->id}", [
            'name' => 'After Update',
            'sections' => [
                ['id' => $sections[0]->id, 'weight' => 60],
                ['id' => $sections[1]->id, 'weight' => 40],
            ],
        ]);

        $response->assertOk()
            ->assertJsonFragment(['name' => 'After Update']);

        $this->assertEquals(2, $template->fresh()->sections()->count());
    }

    // ── Destroy ──────────────────────────────────────────────────────

    public function test_hr_can_delete_template(): void
    {
        $this->actingAsRole('HR');
        $template = PerformanceReviewTemplate::create([
            'name' => 'Delete Me',
            'is_active' => true,
            'is_default' => false,
        ]);

        $response = $this->deleteJson("/api/v1/performance/templates/{$template->id}");

        $response->assertOk();
        $this->assertSoftDeleted('performance_review_templates', ['id' => $template->id]);
    }

    // ── Authorization ────────────────────────────────────────────────

    public function test_employee_cannot_access_templates(): void
    {
        $this->actingAsRole('Employee');

        $response = $this->getJson('/api/v1/performance/templates');

        $response->assertForbidden();
    }

    public function test_unauthenticated_cannot_access_templates(): void
    {
        $response = $this->getJson('/api/v1/performance/templates');

        $response->assertUnauthorized();
    }
}
