<?php

namespace Database\Seeders;

use App\Models\LeaveEntitlement;
use Illuminate\Database\Seeder;

class LeaveEntitlementSeeder extends Seeder
{
    private const SICK_LEAVE_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
    ];

    /**
     * Seed the application's leave entitlements.
     */
    public function run(): void
    {
        $entitlements = [
            ['employment_type' => 'full_time', 'leave_type' => 'annual_leave', 'is_eligible' => true, 'is_paid' => true, 'quota_scope' => 'annual', 'quota_days' => 12.00, 'carry_over_max_days' => 5, 'requires_attachment' => false, 'requires_reason' => true, 'allowed_mime_types' => null, 'max_attachment_size_kb' => null],
            ['employment_type' => 'full_time', 'leave_type' => 'sick_leave', 'is_eligible' => true, 'is_paid' => true, 'quota_scope' => 'unlimited', 'quota_days' => null, 'carry_over_max_days' => null, 'requires_attachment' => true, 'requires_reason' => true, 'allowed_mime_types' => self::SICK_LEAVE_MIME_TYPES, 'max_attachment_size_kb' => 5120],
            ['employment_type' => 'full_time', 'leave_type' => 'personal_leave', 'is_eligible' => true, 'is_paid' => false, 'quota_scope' => 'unpaid', 'quota_days' => null, 'carry_over_max_days' => null, 'requires_attachment' => false, 'requires_reason' => true, 'allowed_mime_types' => null, 'max_attachment_size_kb' => null],
            ['employment_type' => 'full_time', 'leave_type' => 'maternity_leave', 'is_eligible' => true, 'is_paid' => true, 'quota_scope' => 'annual', 'quota_days' => 90.00, 'carry_over_max_days' => null, 'requires_attachment' => false, 'requires_reason' => true, 'allowed_mime_types' => null, 'max_attachment_size_kb' => null],
            ['employment_type' => 'full_time', 'leave_type' => 'paternity_leave', 'is_eligible' => true, 'is_paid' => true, 'quota_scope' => 'annual', 'quota_days' => 2.00, 'carry_over_max_days' => null, 'requires_attachment' => false, 'requires_reason' => true, 'allowed_mime_types' => null, 'max_attachment_size_kb' => null],
            ['employment_type' => 'full_time', 'leave_type' => 'compassionate_leave', 'is_eligible' => true, 'is_paid' => true, 'quota_scope' => 'per_occurrence', 'quota_days' => 3.00, 'carry_over_max_days' => null, 'requires_attachment' => false, 'requires_reason' => true, 'allowed_mime_types' => null, 'max_attachment_size_kb' => null],
            ['employment_type' => 'full_time', 'leave_type' => 'emergency_leave', 'is_eligible' => true, 'is_paid' => true, 'quota_scope' => 'annual', 'quota_days' => 2.00, 'carry_over_max_days' => null, 'requires_attachment' => false, 'requires_reason' => true, 'allowed_mime_types' => null, 'max_attachment_size_kb' => null],
            ['employment_type' => 'contract', 'leave_type' => 'annual_leave', 'is_eligible' => true, 'is_paid' => true, 'quota_scope' => 'annual', 'quota_days' => 6.00, 'carry_over_max_days' => 5, 'requires_attachment' => false, 'requires_reason' => true, 'allowed_mime_types' => null, 'max_attachment_size_kb' => null],
            ['employment_type' => 'contract', 'leave_type' => 'sick_leave', 'is_eligible' => true, 'is_paid' => true, 'quota_scope' => 'unlimited', 'quota_days' => null, 'carry_over_max_days' => null, 'requires_attachment' => true, 'requires_reason' => true, 'allowed_mime_types' => self::SICK_LEAVE_MIME_TYPES, 'max_attachment_size_kb' => 5120],
            ['employment_type' => 'contract', 'leave_type' => 'personal_leave', 'is_eligible' => true, 'is_paid' => false, 'quota_scope' => 'unpaid', 'quota_days' => null, 'carry_over_max_days' => null, 'requires_attachment' => false, 'requires_reason' => true, 'allowed_mime_types' => null, 'max_attachment_size_kb' => null],
            ['employment_type' => 'contract', 'leave_type' => 'maternity_leave', 'is_eligible' => true, 'is_paid' => true, 'quota_scope' => 'annual', 'quota_days' => 90.00, 'carry_over_max_days' => null, 'requires_attachment' => false, 'requires_reason' => true, 'allowed_mime_types' => null, 'max_attachment_size_kb' => null],
            ['employment_type' => 'contract', 'leave_type' => 'paternity_leave', 'is_eligible' => true, 'is_paid' => true, 'quota_scope' => 'annual', 'quota_days' => 2.00, 'carry_over_max_days' => null, 'requires_attachment' => false, 'requires_reason' => true, 'allowed_mime_types' => null, 'max_attachment_size_kb' => null],
            ['employment_type' => 'contract', 'leave_type' => 'compassionate_leave', 'is_eligible' => true, 'is_paid' => true, 'quota_scope' => 'per_occurrence', 'quota_days' => 3.00, 'carry_over_max_days' => null, 'requires_attachment' => false, 'requires_reason' => true, 'allowed_mime_types' => null, 'max_attachment_size_kb' => null],
            ['employment_type' => 'contract', 'leave_type' => 'emergency_leave', 'is_eligible' => true, 'is_paid' => true, 'quota_scope' => 'annual', 'quota_days' => 2.00, 'carry_over_max_days' => null, 'requires_attachment' => false, 'requires_reason' => true, 'allowed_mime_types' => null, 'max_attachment_size_kb' => null],
            ['employment_type' => 'intern', 'leave_type' => 'annual_leave', 'is_eligible' => true, 'is_paid' => true, 'quota_scope' => 'annual', 'quota_days' => 6.00, 'carry_over_max_days' => 5, 'requires_attachment' => false, 'requires_reason' => true, 'allowed_mime_types' => null, 'max_attachment_size_kb' => null],
            ['employment_type' => 'intern', 'leave_type' => 'sick_leave', 'is_eligible' => true, 'is_paid' => true, 'quota_scope' => 'unlimited', 'quota_days' => null, 'carry_over_max_days' => null, 'requires_attachment' => true, 'requires_reason' => true, 'allowed_mime_types' => self::SICK_LEAVE_MIME_TYPES, 'max_attachment_size_kb' => 5120],
            ['employment_type' => 'intern', 'leave_type' => 'personal_leave', 'is_eligible' => true, 'is_paid' => false, 'quota_scope' => 'unpaid', 'quota_days' => null, 'carry_over_max_days' => null, 'requires_attachment' => false, 'requires_reason' => true, 'allowed_mime_types' => null, 'max_attachment_size_kb' => null],
            ['employment_type' => 'intern', 'leave_type' => 'maternity_leave', 'is_eligible' => false, 'is_paid' => false, 'quota_scope' => 'annual', 'quota_days' => 0.00, 'carry_over_max_days' => null, 'requires_attachment' => false, 'requires_reason' => true, 'allowed_mime_types' => null, 'max_attachment_size_kb' => null],
            ['employment_type' => 'intern', 'leave_type' => 'paternity_leave', 'is_eligible' => false, 'is_paid' => false, 'quota_scope' => 'annual', 'quota_days' => 0.00, 'carry_over_max_days' => null, 'requires_attachment' => false, 'requires_reason' => true, 'allowed_mime_types' => null, 'max_attachment_size_kb' => null],
            ['employment_type' => 'intern', 'leave_type' => 'compassionate_leave', 'is_eligible' => true, 'is_paid' => true, 'quota_scope' => 'per_occurrence', 'quota_days' => 2.00, 'carry_over_max_days' => null, 'requires_attachment' => false, 'requires_reason' => true, 'allowed_mime_types' => null, 'max_attachment_size_kb' => null],
            ['employment_type' => 'intern', 'leave_type' => 'emergency_leave', 'is_eligible' => true, 'is_paid' => true, 'quota_scope' => 'annual', 'quota_days' => 1.00, 'carry_over_max_days' => null, 'requires_attachment' => false, 'requires_reason' => true, 'allowed_mime_types' => null, 'max_attachment_size_kb' => null],
            ['employment_type' => 'part_time', 'leave_type' => 'annual_leave', 'is_eligible' => true, 'is_paid' => true, 'quota_scope' => 'annual', 'quota_days' => 7.00, 'carry_over_max_days' => 5, 'requires_attachment' => false, 'requires_reason' => true, 'allowed_mime_types' => null, 'max_attachment_size_kb' => null],
            ['employment_type' => 'part_time', 'leave_type' => 'sick_leave', 'is_eligible' => true, 'is_paid' => true, 'quota_scope' => 'unlimited', 'quota_days' => null, 'carry_over_max_days' => null, 'requires_attachment' => true, 'requires_reason' => true, 'allowed_mime_types' => self::SICK_LEAVE_MIME_TYPES, 'max_attachment_size_kb' => 5120],
            ['employment_type' => 'part_time', 'leave_type' => 'personal_leave', 'is_eligible' => true, 'is_paid' => false, 'quota_scope' => 'unpaid', 'quota_days' => null, 'carry_over_max_days' => null, 'requires_attachment' => false, 'requires_reason' => true, 'allowed_mime_types' => null, 'max_attachment_size_kb' => null],
            ['employment_type' => 'part_time', 'leave_type' => 'maternity_leave', 'is_eligible' => true, 'is_paid' => true, 'quota_scope' => 'annual', 'quota_days' => 90.00, 'carry_over_max_days' => null, 'requires_attachment' => false, 'requires_reason' => true, 'allowed_mime_types' => null, 'max_attachment_size_kb' => null],
            ['employment_type' => 'part_time', 'leave_type' => 'paternity_leave', 'is_eligible' => true, 'is_paid' => true, 'quota_scope' => 'annual', 'quota_days' => 2.00, 'carry_over_max_days' => null, 'requires_attachment' => false, 'requires_reason' => true, 'allowed_mime_types' => null, 'max_attachment_size_kb' => null],
            ['employment_type' => 'part_time', 'leave_type' => 'compassionate_leave', 'is_eligible' => true, 'is_paid' => true, 'quota_scope' => 'per_occurrence', 'quota_days' => 2.00, 'carry_over_max_days' => null, 'requires_attachment' => false, 'requires_reason' => true, 'allowed_mime_types' => null, 'max_attachment_size_kb' => null],
            ['employment_type' => 'part_time', 'leave_type' => 'emergency_leave', 'is_eligible' => true, 'is_paid' => true, 'quota_scope' => 'annual', 'quota_days' => 1.00, 'carry_over_max_days' => null, 'requires_attachment' => false, 'requires_reason' => true, 'allowed_mime_types' => null, 'max_attachment_size_kb' => null],
        ];

        foreach ($entitlements as $entitlement) {
            LeaveEntitlement::query()->updateOrCreate(
                [
                    'employment_type' => $entitlement['employment_type'],
                    'leave_type' => $entitlement['leave_type'],
                ],
                $entitlement
            );
        }
    }
}
