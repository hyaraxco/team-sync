<?php

namespace Tests\Feature\Performance;

use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\ActivatesLicense;
use Tests\TestCase;

class FeedbackMiddlewareTest extends TestCase
{
    use ActivatesLicense, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->activateTestLicense();

        $this->seed([
            RoleSeeder::class,
            PermissionSeeder::class,
            RolePermissionSeeder::class,
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_user_without_performance_menu_gets_403_on_received_feedback(): void
    {
        $this->actingAsUserWithoutFeedbackPermissions();

        $this->getJson('/api/v1/performance/feedback/received')
            ->assertForbidden();
    }

    public function test_user_without_performance_menu_gets_403_on_show_feedback(): void
    {
        $this->actingAsUserWithoutFeedbackPermissions();

        $this->getJson('/api/v1/performance/feedback/1')
            ->assertForbidden();
    }

    public function test_user_without_performance_menu_gets_403_on_acknowledge_feedback(): void
    {
        $this->actingAsUserWithoutFeedbackPermissions();

        $this->postJson('/api/v1/performance/feedback/1/acknowledge')
            ->assertForbidden();
    }

    private function actingAsUserWithoutFeedbackPermissions(): void
    {
        $user = User::factory()->create();
        // No roles, no permissions — should be blocked by performance-menu middleware
        Sanctum::actingAs($user);
    }
}
