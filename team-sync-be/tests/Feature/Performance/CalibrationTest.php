<?php

use App\Models\PerformanceReview;
use App\Models\PerformanceReviewCycle;
use App\Models\PerformanceReviewResponse;
use App\Models\PerformanceReviewSection;
use App\Models\EmployeeProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    Permission::firstOrCreate(['name' => 'review-calibrate', 'guard_name' => 'sanctum']);
    $role = Role::firstOrCreate(['name' => 'HR', 'guard_name' => 'sanctum']);
    $role->givePermissionTo('review-calibrate');
});

function actingAsHR() {
    $user = User::factory()->create();
    $employee = EmployeeProfile::factory()->create(['user_id' => $user->id]);
    $role = Role::findByName('HR', 'sanctum');
    $user->assignRole($role);
    Sanctum::actingAs($user);
    return ['user' => $user, 'employee' => $employee];
}

function createReviewForCalibration($employeeId = null, $status = 'pending_calibration', $reviewerId = null) {
    $cycle = PerformanceReviewCycle::factory()->create();
    $employee = $employeeId ? EmployeeProfile::find($employeeId) : EmployeeProfile::factory()->create();
    $reviewer = $reviewerId ? EmployeeProfile::find($reviewerId) : EmployeeProfile::factory()->create();
    
    return PerformanceReview::create([
        'cycle_id' => $cycle->id,
        'employee_id' => $employee->id,
        'reviewer_id' => $reviewer->id,
        'status' => $status,
    ]);
}

it('hr can calibrate another employee review', function () {
    $hr = actingAsHR();
    $review = createReviewForCalibration();

    $response = $this->postJson("/api/v1/performance/reviews/{$review->id}/calibrate", [
        'responses' => []
    ]);

    $response->assertOk()
        ->assertJsonFragment(['status' => 'completed']);
        
    $this->assertDatabaseHas('performance_reviews', [
        'id' => $review->id,
        'status' => 'completed',
        'calibrated_by' => $hr['user']->id,
    ]);
});

it('hr cannot calibrate their own review', function () {
    $hr = actingAsHR();
    $review = createReviewForCalibration($hr['employee']->id);

    $response = $this->postJson("/api/v1/performance/reviews/{$review->id}/calibrate", [
        'responses' => []
    ]);

    $response->assertForbidden();
});

it('calibration auto-calculates final_rating', function () {
    actingAsHR();
    $review = createReviewForCalibration();
    
    $section1 = PerformanceReviewSection::create(['name' => 'S1', 'weight' => 60, 'order' => 1, 'is_active' => true]);
    $section2 = PerformanceReviewSection::create(['name' => 'S2', 'weight' => 40, 'order' => 2, 'is_active' => true]);

    $response = $this->postJson("/api/v1/performance/reviews/{$review->id}/calibrate", [
        'responses' => [
            ['section_id' => $section1->id, 'rating' => 4.0],
            ['section_id' => $section2->id, 'rating' => 3.0],
        ]
    ]);

    $response->assertOk();
    
    $this->assertDatabaseHas('performance_reviews', [
        'id' => $review->id,
        'final_rating' => 3.6,
    ]);
});

it('calibration auto-derives final_rating_label', function () {
    actingAsHR();
    $review = createReviewForCalibration();
    
    $section1 = PerformanceReviewSection::create(['name' => 'S1', 'weight' => 100, 'order' => 1, 'is_active' => true]);

    $response = $this->postJson("/api/v1/performance/reviews/{$review->id}/calibrate", [
        'responses' => [
            ['section_id' => $section1->id, 'rating' => 4.5],
        ]
    ]);

    $response->assertOk();
    
    $this->assertDatabaseHas('performance_reviews', [
        'id' => $review->id,
        'final_rating_label' => 'Outstanding',
    ]);
});

it('pending calibration endpoint returns only pending_calibration reviews', function () {
    actingAsHR();
    
    $pendingReview = createReviewForCalibration(null, 'pending_calibration');
    $completedReview = createReviewForCalibration(null, 'completed');
    $draftReview = createReviewForCalibration(null, 'draft');

    $response = $this->getJson('/api/v1/performance/reviews/pending-calibration');

    $response->assertOk()
        ->assertJsonFragment(['id' => $pendingReview->id])
        ->assertJsonMissing(['id' => $completedReview->id])
        ->assertJsonMissing(['id' => $draftReview->id]);
});

it('pending calibration endpoint excludes hr own review', function () {
    $hr = actingAsHR();
    
    $otherReview = createReviewForCalibration(null, 'pending_calibration');
    $hrReview = createReviewForCalibration($hr['employee']->id, 'pending_calibration');

    $response = $this->getJson('/api/v1/performance/reviews/pending-calibration');

    $response->assertOk()
        ->assertJsonFragment(['id' => $otherReview->id])
        ->assertJsonMissing(['id' => $hrReview->id]);
});

it('calibration context returns cross-manager stats', function () {
    actingAsHR();
    
    $cycle = PerformanceReviewCycle::factory()->create();
    $section = PerformanceReviewSection::create(['name' => 'S1', 'weight' => 100, 'order' => 1, 'is_active' => true]);
    
    $user1 = User::factory()->create(['name' => 'Manager One']);
    $manager1 = EmployeeProfile::factory()->create(['user_id' => $user1->id]);
    
    $user2 = User::factory()->create(['name' => 'Manager Two']);
    $manager2 = EmployeeProfile::factory()->create(['user_id' => $user2->id]);
    
    $review1 = PerformanceReview::create([
        'cycle_id' => $cycle->id,
        'employee_id' => EmployeeProfile::factory()->create()->id,
        'reviewer_id' => $manager1->id,
        'status' => 'pending_calibration',
        'manager_assessment_submitted_at' => now(),
    ]);
    PerformanceReviewResponse::create(['review_id' => $review1->id, 'section_id' => $section->id, 'manager_rating' => 4.0]);
    
    $review2 = PerformanceReview::create([
        'cycle_id' => $cycle->id,
        'employee_id' => EmployeeProfile::factory()->create()->id,
        'reviewer_id' => $manager1->id,
        'status' => 'pending_calibration',
        'manager_assessment_submitted_at' => now(),
    ]);
    PerformanceReviewResponse::create(['review_id' => $review2->id, 'section_id' => $section->id, 'manager_rating' => 3.0]);
    
    $review3 = PerformanceReview::create([
        'cycle_id' => $cycle->id,
        'employee_id' => EmployeeProfile::factory()->create()->id,
        'reviewer_id' => $manager2->id,
        'status' => 'pending_calibration',
        'manager_assessment_submitted_at' => now(),
    ]);
    PerformanceReviewResponse::create(['review_id' => $review3->id, 'section_id' => $section->id, 'manager_rating' => 5.0]);

    $response = $this->getJson("/api/v1/performance/reviews/{$review1->id}/calibration-context");

    $response->assertOk()
        ->assertJsonFragment([
            'manager_id' => $manager1->id,
            'review_count' => 2,
            'avg_rating' => 3.5,
        ])
        ->assertJsonFragment([
            'manager_id' => $manager2->id,
            'review_count' => 1,
            'avg_rating' => 5.0,
        ]);
});
