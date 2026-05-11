<?php

namespace App\Services\Attendance;

use App\Models\LeaveEntitlement;
use App\Models\LeaveRequest;
use App\Models\StaffMemberProfile;
use App\Support\AttendanceHelper;
use Carbon\Carbon;
use Carbon\CarbonInterface;

class LeaveEntitlementValidator
{
    public function validate(LeaveRequest $leaveRequest, CarbonInterface|string|null $asOfDate = null): array
    {
        $errors = [];

        $employmentType = $this->resolveEmploymentType($leaveRequest);
        if ($employmentType === null) {
            return [
                'valid' => false,
                'errors' => ['employee_employment_type_missing'],
                'is_paid_leave' => false,
            ];
        }

        $leaveType = AttendanceHelper::leaveTypeValue($leaveRequest);
        $entitlement = $this->resolveEntitlement($employmentType, $leaveType);

        if (! $entitlement || ! $entitlement->is_eligible) {
            return [
                'valid' => false,
                'errors' => ['leave_type_not_eligible'],
                'is_paid_leave' => false,
            ];
        }

        if ($leaveType === 'emergency_leave' && trim((string) $leaveRequest->reason) === '') {
            $errors[] = 'emergency_leave_reason_required';
        }

        if ($leaveType === 'sick_leave') {
            $sickProofErrors = $this->validateSickLeaveProof($leaveRequest, $entitlement);
            if (! empty($sickProofErrors)) {
                $errors = array_merge($errors, $sickProofErrors);
            }
        }

        $scheduledWeekdays = AttendanceHelper::resolveScheduledWeekdays($employmentType);
        $requestWorkingDays = AttendanceHelper::countWorkingLeaveDays(
            $employmentType,
            Carbon::parse($leaveRequest->start_date)->startOfDay(),
            Carbon::parse($leaveRequest->end_date)->endOfDay(),
            $scheduledWeekdays
        );

        if ($requestWorkingDays <= 0) {
            $errors[] = 'leave_has_no_working_days';
        }

        if (! $this->quotaIsValidForLeave(
            $employmentType,
            $leaveRequest,
            $entitlement,
            $requestWorkingDays,
            $asOfDate
        )) {
            $errors[] = $entitlement->quota_scope === 'annual'
                ? 'quota_exceeded_annual'
                : 'quota_exceeded_per_occurrence';
        }

        return [
            'valid' => empty($errors),
            'errors' => array_values(array_unique($errors)),
            'is_paid_leave' => (bool) $entitlement->is_paid,
            'request_working_days' => $requestWorkingDays,
        ];
    }

    private function resolveEmploymentType(LeaveRequest $leaveRequest): ?string
    {
        $employee = $this->resolveEmployee($leaveRequest);
        $employmentType = (string) ($employee?->jobInformation?->employment_type ?? '');

        if ($employmentType === '') {
            return null;
        }

        return AttendanceHelper::normalizeEmploymentType($employmentType);
    }

    private function resolveEmployee(LeaveRequest $leaveRequest): ?StaffMemberProfile
    {
        if ($leaveRequest->relationLoaded('staffMember') && $leaveRequest->staffMember) {
            $employee = $leaveRequest->staffMember;
            if (! $employee->relationLoaded('jobInformation')) {
                $employee->load('jobInformation');
            }

            return $employee;
        }

        return StaffMemberProfile::query()
            ->with('jobInformation')
            ->find($leaveRequest->staff_member_id);
    }

    private function validateSickLeaveProof(LeaveRequest $leaveRequest, LeaveEntitlement $entitlement): array
    {
        if (! $entitlement->requires_attachment) {
            return [];
        }

        $errors = [];

        if (! $this->hasSickProofAttachment($leaveRequest)) {
            $errors[] = 'sick_leave_proof_required';

            return $errors;
        }

        if (! $this->isSickProofApproved($leaveRequest)) {
            $errors[] = 'sick_leave_proof_not_approved';
        }

        $allowedMimeTypes = $entitlement->allowed_mime_types;
        if (is_array($allowedMimeTypes) && ! in_array((string) $leaveRequest->proof_mime_type, $allowedMimeTypes, true)) {
            $errors[] = 'sick_leave_invalid_mime_type';
        }

        if ($entitlement->max_attachment_size_kb !== null
            && (int) $leaveRequest->proof_size_kb > (int) $entitlement->max_attachment_size_kb) {
            $errors[] = 'sick_leave_attachment_too_large';
        }

        return $errors;
    }

    private function hasSickProofAttachment(LeaveRequest $leaveRequest): bool
    {
        return $leaveRequest->proof_file_path !== null
            && $leaveRequest->proof_file_name !== null
            && $leaveRequest->proof_mime_type !== null
            && $leaveRequest->proof_size_kb !== null
            && $leaveRequest->proof_uploaded_at !== null;
    }

    private function isSickProofApproved(LeaveRequest $leaveRequest): bool
    {
        return $leaveRequest->proof_review_status === 'approved';
    }

    private function quotaIsValidForLeave(
        string $employmentType,
        LeaveRequest $leaveRequest,
        LeaveEntitlement $entitlement,
        int $requestWorkingDays,
        CarbonInterface|string|null $asOfDate = null
    ): bool {
        if ($requestWorkingDays <= 0) {
            return false;
        }

        if ($entitlement->quota_scope === null || in_array($entitlement->quota_scope, ['unlimited', 'unpaid'], true)) {
            return true;
        }

        if ($entitlement->quota_days === null) {
            return false;
        }

        $quotaDays = (float) $entitlement->quota_days;

        if ($entitlement->quota_scope === 'per_occurrence') {
            return $requestWorkingDays <= $quotaDays;
        }

        if ($entitlement->quota_scope !== 'annual') {
            return true;
        }

        $targetDate = $asOfDate ? Carbon::parse($asOfDate)->startOfDay() : Carbon::parse($leaveRequest->start_date)->startOfDay();
        $yearStart = $targetDate->copy()->startOfYear()->toDateString();
        $yearEnd = $targetDate->copy()->endOfYear()->toDateString();
        $leaveType = AttendanceHelper::leaveTypeValue($leaveRequest);
        $scheduledWeekdays = AttendanceHelper::resolveScheduledWeekdays($employmentType);

        $approvedLeavesInYear = LeaveRequest::query()
            ->where('staff_member_id', $leaveRequest->staff_member_id)
            ->where('status', 'approved')
            ->where('id', '!=', $leaveRequest->id)
            ->where('leave_type', $leaveType)
            ->whereDate('start_date', '<=', $yearEnd)
            ->whereDate('end_date', '>=', $yearStart)
            ->get();

        $usedDays = 0;

        /** @var LeaveRequest $approvedLeave */
        foreach ($approvedLeavesInYear as $approvedLeave) {
            $usedDays += AttendanceHelper::countWorkingLeaveDays(
                $employmentType,
                Carbon::parse($approvedLeave->start_date)->startOfDay(),
                Carbon::parse($approvedLeave->end_date)->endOfDay(),
                $scheduledWeekdays
            );
        }

        return ($usedDays + $requestWorkingDays) <= $quotaDays;
    }

    private function resolveEntitlement(string $employmentType, string $leaveType): ?LeaveEntitlement
    {
        return LeaveEntitlement::query()
            ->where('employment_type', $employmentType)
            ->where('leave_type', $leaveType)
            ->first();
    }
}
