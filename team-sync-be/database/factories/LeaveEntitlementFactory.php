<?php

namespace Database\Factories;

use App\Enums\LeaveType;
use App\Models\LeaveEntitlement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LeaveEntitlement>
 */
class LeaveEntitlementFactory extends Factory
{
    protected $model = LeaveEntitlement::class;

    public function definition(): array
    {
        $quotaScope = fake()->randomElement(['annual', 'per_occurrence', 'unlimited', 'unpaid']);
        $leaveType = fake()->randomElement(array_column(LeaveType::cases(), 'value'));
        $requiresAttachment = $leaveType === LeaveType::SICK_LEAVE->value;

        return [
            'employment_type' => fake()->randomElement(['full_time', 'contract', 'intern', 'part_time']),
            'leave_type' => $leaveType,
            'is_eligible' => fake()->boolean(90),
            'is_paid' => $quotaScope !== 'unpaid',
            'quota_scope' => $quotaScope,
            'quota_days' => in_array($quotaScope, ['annual', 'per_occurrence'], true)
                ? fake()->randomFloat(2, 1, 90)
                : null,
            'carry_over_max_days' => $quotaScope === 'annual' ? fake()->numberBetween(0, 10) : null,
            'requires_attachment' => $requiresAttachment,
            'requires_reason' => fake()->boolean(85),
            'allowed_mime_types' => $requiresAttachment
                ? fake()->randomElement([
                    ['application/pdf'],
                    ['application/pdf', 'image/jpeg', 'image/png'],
                ])
                : null,
            'max_attachment_size_kb' => $requiresAttachment ? fake()->randomElement([2048, 5120, 10240]) : null,
        ];
    }
}
