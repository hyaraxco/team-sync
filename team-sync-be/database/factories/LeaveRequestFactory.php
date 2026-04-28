<?php

namespace Database\Factories;

use App\Enums\LeaveType;
use App\Models\LeaveRequest;
use App\Models\StaffMemberProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveRequest>
 */
class LeaveRequestFactory extends Factory
{
    protected $model = LeaveRequest::class;

    public function definition(): array
    {
        $startDate = fake()->dateTimeBetween('-1 month', '+1 month');
        $totalDays = fake()->numberBetween(1, 7);
        $endDate = (clone $startDate)->modify('+' . ($totalDays - 1) . ' days');
        $status = fake()->randomElement(['pending', 'approved', 'rejected']);
        $proofReviewStatus = fake()->optional(0.5)->randomElement(['approved', 'rejected']);
        $hasProof = fake()->boolean(40);

        return [
            'staff_member_id' => StaffMemberProfile::factory(),
            'leave_type' => fake()->randomElement(array_column(LeaveType::cases(), 'value')),
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'total_days' => $totalDays,
            'reason' => fake()->optional()->sentence(),
            'emergency_contact' => fake()->optional()->phoneNumber(),
            'proof_file_path' => $hasProof ? fake()->filePath() : null,
            'proof_file_name' => $hasProof ? fake()->word() . '.pdf' : null,
            'proof_mime_type' => $hasProof ? fake()->randomElement(['application/pdf', 'image/jpeg', 'image/png']) : null,
            'proof_size_kb' => $hasProof ? fake()->numberBetween(10, 5120) : null,
            'proof_uploaded_at' => $hasProof ? fake()->dateTimeBetween('-1 month', 'now') : null,
            'proof_review_status' => $proofReviewStatus,
            'status' => $status,
            'approved_by' => $status === 'approved' ? StaffMemberProfile::factory() : null,
            'proof_reviewed_by' => $proofReviewStatus ? StaffMemberProfile::factory() : null,
            'proof_reviewed_at' => $proofReviewStatus ? fake()->dateTimeBetween('-1 month', 'now') : null,
            'proof_review_notes' => $proofReviewStatus ? fake()->optional()->sentence() : null,
        ];
    }
}
