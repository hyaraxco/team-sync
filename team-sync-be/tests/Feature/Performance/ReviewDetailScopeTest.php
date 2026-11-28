<?php

use App\Models\PerformanceReview;
use App\Models\PerformanceReviewCycle;
use App\Models\StaffMemberProfile;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\ActivatesLicense;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class, ActivatesLicense::class);

it('limits manager review detail to own or assigned reviews while retaining HR and superadmin access', function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RolePermissionSeeder::class);
    $this->activateTestLicense();

    $manager = User::factory()->create();
    $manager->syncRoles(['manager']);
    $managerProfile = StaffMemberProfile::factory()->create(['user_id' => $manager->id]);
    $employee = StaffMemberProfile::factory()->create();
    $otherReviewer = StaffMemberProfile::factory()->create();
    $cycle = PerformanceReviewCycle::factory()->active()->create();
    $unrelated = PerformanceReview::create([
        'cycle_id' => $cycle->id,
        'staff_member_id' => $employee->id,
        'reviewer_id' => $otherReviewer->id,
        'status' => 'pending_manager',
    ]);
    $own = PerformanceReview::create([
        'cycle_id' => $cycle->id,
        'staff_member_id' => $managerProfile->id,
        'reviewer_id' => $otherReviewer->id,
        'status' => 'pending_manager',
    ]);

    Sanctum::actingAs($manager);
    $this->getJson("/api/v1/performance/reviews/{$unrelated->id}")->assertForbidden();
    $this->getJson("/api/v1/performance/reviews/{$own->id}")
        ->assertOk()->assertJsonPath('data.id', $own->id);

    // Team reviews are scoped by reviewer_id, not by permission alone.
    $unrelated->update(['reviewer_id' => $managerProfile->id]);
    $this->getJson("/api/v1/performance/reviews/{$unrelated->id}")
        ->assertOk()->assertJsonPath('data.id', $unrelated->id);
    $unrelated->update(['reviewer_id' => $otherReviewer->id]);
    $this->getJson("/api/v1/performance/reviews/{$unrelated->id}")->assertForbidden();

    foreach (['hr', 'superadmin'] as $role) {
        $administrator = User::factory()->create();
        $administrator->syncRoles([$role]);
        StaffMemberProfile::factory()->create(['user_id' => $administrator->id]);
        Sanctum::actingAs($administrator);
        $this->getJson("/api/v1/performance/reviews/{$unrelated->id}")
            ->assertOk()->assertJsonPath('data.id', $unrelated->id);
    }
});
