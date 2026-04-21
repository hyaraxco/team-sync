<?php

namespace Database\Seeders;

use App\Models\PerformanceReview;
use App\Models\PerformanceReviewCycle;
use App\Models\PerformanceReviewResponse;
use App\Models\PerformanceReviewSection;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class PerformanceDataSeeder extends Seeder
{
    public function run(): void
    {
        // Get existing users
        $manager = User::where('email', 'yudhis@teamsync.com')->first();
        $employee = User::where('email', 'agung@teamsync.com')->first();
        $hr = User::where('email', 'tasyia@teamsync.com')->first();

        if (! $manager || ! $employee || ! $hr) {
            $this->command->warn('Required users not found. Run ManagerSeeder, EmployeeSeeder, and HrSeeder first.');

            return;
        }

        $managerProfile = $manager->staffMemberProfile;
        $staffMemberProfile = $employee->staffMemberProfile;
        $hrProfile = $hr->staffMemberProfile;

        if (! $managerProfile || ! $staffMemberProfile || ! $hrProfile) {
            $this->command->warn('Employee profiles not found for required users.');

            return;
        }

        $sections = PerformanceReviewSection::where('is_active', true)->orderBy('order')->get();

        if ($sections->isEmpty()) {
            $this->command->warn('No active review sections found. Run PerformanceReviewSectionSeeder first.');

            return;
        }

        // Create one active review cycle
        $cycle = PerformanceReviewCycle::updateOrCreate(
            ['name' => 'Q1 2026 Performance Review'],
            [
                'cycle_type' => 'quarterly',
                'start_date' => '2026-01-01',
                'end_date' => '2026-06-30',
                'review_period_start' => '2026-01-01',
                'review_period_end' => '2026-03-31',
                'status' => 'active',
                'self_assessment_deadline' => '2026-04-30',
                'manager_assessment_deadline' => '2026-05-15',
                'calibration_deadline' => '2026-05-31',
                'created_by' => $hr->id,
            ]
        );

        // Review 1: Employee Agung - pending_self (employee can test self-assessment)
        $review1 = PerformanceReview::updateOrCreate(
            ['cycle_id' => $cycle->id, 'staff_member_id' => $staffMemberProfile->id],
            [
                'reviewer_id' => $managerProfile->id,
                'status' => 'pending_self',
            ]
        );

        // Review 2: Manager Yudhis - pending_manager (has self-assessment filled)
        $review2 = PerformanceReview::updateOrCreate(
            ['cycle_id' => $cycle->id, 'staff_member_id' => $managerProfile->id],
            [
                'reviewer_id' => $managerProfile->id,
                'status' => 'pending_manager',
                'self_assessment_submitted_at' => Carbon::now()->subDays(5),
            ]
        );

        // Seed self-assessment responses for review 2
        foreach ($sections as $section) {
            PerformanceReviewResponse::updateOrCreate(
                ['review_id' => $review2->id, 'section_id' => $section->id],
                [
                    'self_rating' => rand(3, 5),
                    'self_comments' => 'Self-assessment for '.$section->name.'. I have demonstrated strong performance in this area.',
                ]
            );
        }

        // Review 3: HR Tasyia - pending_calibration (has self + manager assessment)
        $review3 = PerformanceReview::updateOrCreate(
            ['cycle_id' => $cycle->id, 'staff_member_id' => $hrProfile->id],
            [
                'reviewer_id' => $managerProfile->id,
                'status' => 'pending_calibration',
                'self_assessment_submitted_at' => Carbon::now()->subDays(10),
                'manager_assessment_submitted_at' => Carbon::now()->subDays(3),
                'final_rating' => 3.80,
            ]
        );

        // Seed both self and manager responses for review 3
        foreach ($sections as $section) {
            PerformanceReviewResponse::updateOrCreate(
                ['review_id' => $review3->id, 'section_id' => $section->id],
                [
                    'self_rating' => rand(3, 5),
                    'self_comments' => 'Self-assessment for '.$section->name.'.',
                    'manager_rating' => rand(3, 4),
                    'manager_comments' => 'Manager review for '.$section->name.'. Good performance overall.',
                ]
            );
        }

        // Create a second completed cycle for history
        $completedCycle = PerformanceReviewCycle::updateOrCreate(
            ['name' => 'Q4 2025 Performance Review'],
            [
                'cycle_type' => 'quarterly',
                'start_date' => '2025-10-01',
                'end_date' => '2025-12-31',
                'review_period_start' => '2025-10-01',
                'review_period_end' => '2025-12-31',
                'status' => 'completed',
                'self_assessment_deadline' => '2026-01-15',
                'manager_assessment_deadline' => '2026-01-31',
                'calibration_deadline' => '2026-02-15',
                'created_by' => $hr->id,
            ]
        );

        // Review 4: Employee Agung - completed (all data filled)
        $review4 = PerformanceReview::updateOrCreate(
            ['cycle_id' => $completedCycle->id, 'staff_member_id' => $staffMemberProfile->id],
            [
                'reviewer_id' => $managerProfile->id,
                'status' => 'completed',
                'self_assessment_submitted_at' => Carbon::parse('2026-01-10'),
                'manager_assessment_submitted_at' => Carbon::parse('2026-01-25'),
                'final_rating' => 4.20,
                'final_rating_label' => 'Exceeds Expectations',
                'calibrated_at' => Carbon::parse('2026-02-10'),
                'calibrated_by' => $hr->id,
                'completed_at' => Carbon::parse('2026-02-10'),
            ]
        );

        // Seed all responses for completed review 4
        foreach ($sections as $section) {
            $selfRating = rand(3, 5);
            $managerRating = rand(3, 5);
            PerformanceReviewResponse::updateOrCreate(
                ['review_id' => $review4->id, 'section_id' => $section->id],
                [
                    'self_rating' => $selfRating,
                    'self_comments' => 'Self-assessment for '.$section->name.'. I believe I performed well.',
                    'manager_rating' => $managerRating,
                    'manager_comments' => 'Manager assessment for '.$section->name.'. Solid performance.',
                    'final_rating' => round(($selfRating + $managerRating) / 2),
                ]
            );
        }

        $this->command->info('Performance data seeded successfully:');
        $this->command->info("- Cycle: {$cycle->name} (active) with 3 reviews");
        $this->command->info("- Cycle: {$completedCycle->name} (completed) with 1 review");
    }
}
