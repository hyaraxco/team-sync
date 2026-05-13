<?php

use App\Models\JobInformation;
use App\Models\LeaveEntitlement;
use App\Models\LeaveRequest;
use App\Models\StaffMemberProfile;
use App\Services\Attendance\LeaveEntitlementValidator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->service = new LeaveEntitlementValidator;

    Role::create(['name' => 'staff', 'guard_name' => 'sanctum']);
});

// ─── Helpers ────────────────────────────────────────────────────────────────

/**
 * Create a full_time employee with JobInformation and return the profile.
 */
function createValidatorTestEmployee(): StaffMemberProfile
{
    $profile = StaffMemberProfile::factory()->create();

    JobInformation::factory()->active()->fullTime()->create([
        'staff_member_id' => $profile->id,
    ]);

    return $profile->fresh(['jobInformation']);
}

/**
 * Create a leave request for a given employee, dates, and type, with optional overrides.
 */
function createLeaveRequest(
    StaffMemberProfile $employee,
    string $leaveType = 'annual_leave',
    ?string $startDate = null,
    ?string $endDate = null,
    array $overrides = [],
): LeaveRequest {
    $startDate ??= Carbon::now()->startOfWeek()->addDay()->toDateString(); // Monday
    $endDate ??= Carbon::now()->startOfWeek()->addDays(3)->toDateString(); // Wednesday (3 days: Mon, Tue, Wed)

    return LeaveRequest::create(array_merge([
        'staff_member_id' => $employee->id,
        'leave_type' => $leaveType,
        'start_date' => $startDate,
        'end_date' => $endDate,
        'total_days' => Carbon::parse($startDate)->diffInDays(Carbon::parse($endDate)) + 1,
        'status' => 'pending',
    ], $overrides));
}

/**
 * Create a LeaveEntitlement for the given employee and leave type.
 */
function createEntitlement(
    string $employmentType = 'full_time',
    string $leaveType = 'annual_leave',
    array $overrides = [],
): LeaveEntitlement {
    return LeaveEntitlement::create(array_merge([
        'employment_type' => $employmentType,
        'leave_type' => $leaveType,
        'is_eligible' => true,
        'is_paid' => true,
        'quota_scope' => 'annual',
        'quota_days' => 12,
        'requires_attachment' => false,
        'requires_reason' => false,
        'allowed_mime_types' => null,
        'max_attachment_size_kb' => null,
    ], $overrides));
}

// ─── 1. Valid leave request ─────────────────────────────────────────────────

it('returns valid for a well-formed leave request', function () {
    $employee = createValidatorTestEmployee();

    createEntitlement('full_time', 'annual_leave');

    // Monday to Wednesday — 3 working days
    $monday = Carbon::now()->startOfWeek()->addDay()->toDateString();
    $wednesday = Carbon::now()->startOfWeek()->addDays(3)->toDateString();

    $leaveRequest = createLeaveRequest($employee, 'annual_leave', $monday, $wednesday);

    $result = $this->service->validate($leaveRequest);

    expect($result['valid'])->toBeTrue();
    expect($result['errors'])->toBeEmpty();
});

// ─── 2. Missing employment type ─────────────────────────────────────────────

it('returns error when employment type is missing', function () {
    $profile = StaffMemberProfile::factory()->create();
    // No JobInformation created — employment_type resolves to null

    $leaveRequest = createLeaveRequest($profile, 'annual_leave');

    $result = $this->service->validate($leaveRequest);

    expect($result['valid'])->toBeFalse();
    expect($result['errors'])->toContain('employee_employment_type_missing');
});

// ─── 3. No entitlement found ────────────────────────────────────────────────

it('returns error when no entitlement is found for the leave type', function () {
    $employee = createValidatorTestEmployee();

    // No entitlement created — resolveEntitlement returns null

    $monday = Carbon::now()->startOfWeek()->addDay()->toDateString();
    $wednesday = Carbon::now()->startOfWeek()->addDays(3)->toDateString();
    $leaveRequest = createLeaveRequest($employee, 'annual_leave', $monday, $wednesday);

    $result = $this->service->validate($leaveRequest);

    expect($result['valid'])->toBeFalse();
    expect($result['errors'])->toContain('leave_type_not_eligible');
});

// ─── 4. Entitlement with is_eligible = false ────────────────────────────────

it('returns error when entitlement exists but is_eligible is false', function () {
    $employee = createValidatorTestEmployee();

    createEntitlement('full_time', 'annual_leave', [
        'is_eligible' => false,
    ]);

    $monday = Carbon::now()->startOfWeek()->addDay()->toDateString();
    $wednesday = Carbon::now()->startOfWeek()->addDays(3)->toDateString();
    $leaveRequest = createLeaveRequest($employee, 'annual_leave', $monday, $wednesday);

    $result = $this->service->validate($leaveRequest);

    expect($result['valid'])->toBeFalse();
    expect($result['errors'])->toContain('leave_type_not_eligible');
});

// ─── 5. Emergency leave without reason ──────────────────────────────────────

it('returns error for emergency leave without a reason', function () {
    $employee = createValidatorTestEmployee();

    createEntitlement('full_time', 'emergency_leave', [
        'quota_scope' => 'unlimited',
    ]);

    $monday = Carbon::now()->startOfWeek()->addDay()->toDateString();
    $wednesday = Carbon::now()->startOfWeek()->addDays(3)->toDateString();
    $leaveRequest = createLeaveRequest($employee, 'emergency_leave', $monday, $wednesday, [
        'reason' => null,
    ]);

    $result = $this->service->validate($leaveRequest);

    expect($result['valid'])->toBeFalse();
    expect($result['errors'])->toContain('emergency_leave_reason_required');
});

// ─── 6. Emergency leave with whitespace-only reason ─────────────────────────

it('returns error for emergency leave with a whitespace-only reason', function () {
    $employee = createValidatorTestEmployee();

    createEntitlement('full_time', 'emergency_leave', [
        'quota_scope' => 'unlimited',
    ]);

    $monday = Carbon::now()->startOfWeek()->addDay()->toDateString();
    $wednesday = Carbon::now()->startOfWeek()->addDays(3)->toDateString();
    $leaveRequest = createLeaveRequest($employee, 'emergency_leave', $monday, $wednesday, [
        'reason' => '   ',
    ]);

    $result = $this->service->validate($leaveRequest);

    expect($result['valid'])->toBeFalse();
    expect($result['errors'])->toContain('emergency_leave_reason_required');
});

// ─── 7. Sick leave without proof attachment ─────────────────────────────────

it('returns error for sick leave without a proof attachment', function () {
    $employee = createValidatorTestEmployee();

    createEntitlement('full_time', 'sick_leave', [
        'requires_attachment' => true,
        'quota_scope' => 'unlimited',
        'allowed_mime_types' => ['application/pdf', 'image/jpeg'],
        'max_attachment_size_kb' => 5120,
    ]);

    $monday = Carbon::now()->startOfWeek()->addDay()->toDateString();
    $wednesday = Carbon::now()->startOfWeek()->addDays(3)->toDateString();
    $leaveRequest = createLeaveRequest($employee, 'sick_leave', $monday, $wednesday, [
        'proof_file_path' => null,
        'proof_file_name' => null,
        'proof_mime_type' => null,
        'proof_size_kb' => null,
        'proof_uploaded_at' => null,
    ]);

    $result = $this->service->validate($leaveRequest);

    expect($result['valid'])->toBeFalse();
    expect($result['errors'])->toContain('sick_leave_proof_required');
});

// ─── 8. Sick leave with proof but not approved ──────────────────────────────

it('returns error for sick leave with proof that is not approved', function () {
    $employee = createValidatorTestEmployee();

    createEntitlement('full_time', 'sick_leave', [
        'requires_attachment' => true,
        'quota_scope' => 'unlimited',
        'allowed_mime_types' => ['application/pdf', 'image/jpeg'],
        'max_attachment_size_kb' => 5120,
    ]);

    $monday = Carbon::now()->startOfWeek()->addDay()->toDateString();
    $wednesday = Carbon::now()->startOfWeek()->addDays(3)->toDateString();
    $leaveRequest = createLeaveRequest($employee, 'sick_leave', $monday, $wednesday, [
        'proof_file_path' => 'proofs/sick-cert.pdf',
        'proof_file_name' => 'sick-cert.pdf',
        'proof_mime_type' => 'application/pdf',
        'proof_size_kb' => 256,
        'proof_uploaded_at' => Carbon::now()->toDateTimeString(),
        'proof_review_status' => 'pending',
    ]);

    $result = $this->service->validate($leaveRequest);

    expect($result['valid'])->toBeFalse();
    expect($result['errors'])->toContain('sick_leave_proof_not_approved');
});

// ─── 9. Sick leave with approved proof passes ───────────────────────────────

it('passes validation for sick leave with approved proof', function () {
    $employee = createValidatorTestEmployee();

    createEntitlement('full_time', 'sick_leave', [
        'requires_attachment' => true,
        'quota_scope' => 'unlimited',
        'allowed_mime_types' => ['application/pdf', 'image/jpeg'],
        'max_attachment_size_kb' => 5120,
    ]);

    $monday = Carbon::now()->startOfWeek()->addDay()->toDateString();
    $wednesday = Carbon::now()->startOfWeek()->addDays(3)->toDateString();
    $leaveRequest = createLeaveRequest($employee, 'sick_leave', $monday, $wednesday, [
        'proof_file_path' => 'proofs/sick-cert.pdf',
        'proof_file_name' => 'sick-cert.pdf',
        'proof_mime_type' => 'application/pdf',
        'proof_size_kb' => 256,
        'proof_uploaded_at' => Carbon::now()->toDateTimeString(),
        'proof_review_status' => 'approved',
    ]);

    $result = $this->service->validate($leaveRequest);

    expect($result['valid'])->toBeTrue();
    expect($result['errors'])->toBeEmpty();
});

// ─── 10. Sick leave with invalid mime type ──────────────────────────────────

it('returns error for sick leave with an invalid mime type', function () {
    $employee = createValidatorTestEmployee();

    createEntitlement('full_time', 'sick_leave', [
        'requires_attachment' => true,
        'quota_scope' => 'unlimited',
        'allowed_mime_types' => ['application/pdf'],
        'max_attachment_size_kb' => 5120,
    ]);

    $monday = Carbon::now()->startOfWeek()->addDay()->toDateString();
    $wednesday = Carbon::now()->startOfWeek()->addDays(3)->toDateString();
    $leaveRequest = createLeaveRequest($employee, 'sick_leave', $monday, $wednesday, [
        'proof_file_path' => 'proofs/sick-cert.pdf',
        'proof_file_name' => 'sick-cert.pdf',
        'proof_mime_type' => 'image/png',
        'proof_size_kb' => 256,
        'proof_uploaded_at' => Carbon::now()->toDateTimeString(),
        'proof_review_status' => 'approved',
    ]);

    $result = $this->service->validate($leaveRequest);

    expect($result['valid'])->toBeFalse();
    expect($result['errors'])->toContain('sick_leave_invalid_mime_type');
});

// ─── 11. Sick leave with oversized attachment ───────────────────────────────

it('returns error for sick leave with attachment exceeding max size', function () {
    $employee = createValidatorTestEmployee();

    createEntitlement('full_time', 'sick_leave', [
        'requires_attachment' => true,
        'quota_scope' => 'unlimited',
        'allowed_mime_types' => ['application/pdf'],
        'max_attachment_size_kb' => 2048,
    ]);

    $monday = Carbon::now()->startOfWeek()->addDay()->toDateString();
    $wednesday = Carbon::now()->startOfWeek()->addDays(3)->toDateString();
    $leaveRequest = createLeaveRequest($employee, 'sick_leave', $monday, $wednesday, [
        'proof_file_path' => 'proofs/sick-cert.pdf',
        'proof_file_name' => 'sick-cert.pdf',
        'proof_mime_type' => 'application/pdf',
        'proof_size_kb' => 4096,
        'proof_uploaded_at' => Carbon::now()->toDateTimeString(),
        'proof_review_status' => 'approved',
    ]);

    $result = $this->service->validate($leaveRequest);

    expect($result['valid'])->toBeFalse();
    expect($result['errors'])->toContain('sick_leave_attachment_too_large');
});

// ─── 12. Leave period with zero working days ────────────────────────────────

it('returns error when leave period has no working days', function () {
    $employee = createValidatorTestEmployee();

    createEntitlement('full_time', 'annual_leave');

    // Saturday to Sunday — full_time works Mon-Fri, so 0 working days
    $nextSaturday = Carbon::now()->next(Carbon::SATURDAY)->toDateString();
    $nextSunday = Carbon::now()->next(Carbon::SATURDAY)->addDay()->toDateString();

    $leaveRequest = createLeaveRequest($employee, 'annual_leave', $nextSaturday, $nextSunday);

    $result = $this->service->validate($leaveRequest);

    expect($result['valid'])->toBeFalse();
    expect($result['errors'])->toContain('leave_has_no_working_days');
});

// ─── 13. Per-occurrence quota exceeded ──────────────────────────────────────

it('returns error when per_occurrence quota is exceeded', function () {
    $employee = createValidatorTestEmployee();

    createEntitlement('full_time', 'personal_leave', [
        'quota_scope' => 'per_occurrence',
        'quota_days' => 3,
    ]);

    // Monday to Friday — 5 working days, exceeds quota of 3
    $monday = Carbon::now()->startOfWeek()->addDay()->toDateString();
    $friday = Carbon::now()->startOfWeek()->addDays(5)->toDateString();

    $leaveRequest = createLeaveRequest($employee, 'personal_leave', $monday, $friday);

    $result = $this->service->validate($leaveRequest);

    expect($result['valid'])->toBeFalse();
    expect($result['errors'])->toContain('quota_exceeded_per_occurrence');
});

// ─── 14. Per-occurrence quota within limit ──────────────────────────────────

it('passes when per_occurrence quota is within limit', function () {
    $employee = createValidatorTestEmployee();

    createEntitlement('full_time', 'personal_leave', [
        'quota_scope' => 'per_occurrence',
        'quota_days' => 5,
    ]);

    // Monday to Wednesday — 3 working days, within quota of 5
    $monday = Carbon::now()->startOfWeek()->addDay()->toDateString();
    $wednesday = Carbon::now()->startOfWeek()->addDays(3)->toDateString();

    $leaveRequest = createLeaveRequest($employee, 'personal_leave', $monday, $wednesday);

    $result = $this->service->validate($leaveRequest);

    expect($result['valid'])->toBeTrue();
    expect($result['errors'])->toBeEmpty();
});

// ─── 15. Annual quota exceeded ──────────────────────────────────────────────

it('returns error when annual quota is exceeded', function () {
    $employee = createValidatorTestEmployee();

    createEntitlement('full_time', 'annual_leave', [
        'quota_scope' => 'annual',
        'quota_days' => 5,
    ]);

    // Create an existing approved leave (2 working days) in the same year
    // Pick a Monday-Tuesday in a week that is fully in the past
    $pastMonday = Carbon::now()->subWeek()->startOfWeek()->addDay()->toDateString();
    $pastTuesday = Carbon::now()->subWeek()->startOfWeek()->addDays(2)->toDateString();

    createLeaveRequest($employee, 'annual_leave', $pastMonday, $pastTuesday, [
        'status' => 'approved',
    ]);

    // Request 4 working days — total would be 2 (used) + 4 (new) = 6 > 5 (quota)
    $monday = Carbon::now()->startOfWeek()->addDay()->toDateString();
    $thursday = Carbon::now()->startOfWeek()->addDays(4)->toDateString();

    $leaveRequest = createLeaveRequest($employee, 'annual_leave', $monday, $thursday);

    $result = $this->service->validate($leaveRequest);

    expect($result['valid'])->toBeFalse();
    expect($result['errors'])->toContain('quota_exceeded_annual');
});

// ─── 16. Annual quota within limit ──────────────────────────────────────────

it('passes when annual quota is within limit counting existing approved leaves', function () {
    $employee = createValidatorTestEmployee();

    createEntitlement('full_time', 'annual_leave', [
        'quota_scope' => 'annual',
        'quota_days' => 10,
    ]);

    // Create an existing approved leave (2 working days)
    $pastMonday = Carbon::now()->subWeek()->startOfWeek()->addDay()->toDateString();
    $pastTuesday = Carbon::now()->subWeek()->startOfWeek()->addDays(2)->toDateString();

    createLeaveRequest($employee, 'annual_leave', $pastMonday, $pastTuesday, [
        'status' => 'approved',
    ]);

    // Request 3 working days — total 2 + 3 = 5 ≤ 10
    $monday = Carbon::now()->startOfWeek()->addDay()->toDateString();
    $wednesday = Carbon::now()->startOfWeek()->addDays(3)->toDateString();

    $leaveRequest = createLeaveRequest($employee, 'annual_leave', $monday, $wednesday);

    $result = $this->service->validate($leaveRequest);

    expect($result['valid'])->toBeTrue();
    expect($result['errors'])->toBeEmpty();
});

// ─── 17. Unlimited quota always passes ──────────────────────────────────────

it('passes quota validation when quota_scope is unlimited', function () {
    $employee = createValidatorTestEmployee();

    createEntitlement('full_time', 'annual_leave', [
        'quota_scope' => 'unlimited',
        'quota_days' => null,
    ]);

    // Request 10 working days (2 weeks)
    $monday = Carbon::now()->startOfWeek()->addDay()->toDateString();
    $friday = Carbon::now()->startOfWeek()->addDays(9)->toDateString();

    $leaveRequest = createLeaveRequest($employee, 'annual_leave', $monday, $friday);

    $result = $this->service->validate($leaveRequest);

    expect($result['valid'])->toBeTrue();
    expect($result['errors'])->toBeEmpty();
});

// ─── 18. Unpaid leave always passes quota check ─────────────────────────────

it('passes quota validation when quota_scope is unpaid', function () {
    $employee = createValidatorTestEmployee();

    createEntitlement('full_time', 'personal_leave', [
        'quota_scope' => 'unpaid',
        'quota_days' => null,
    ]);

    $monday = Carbon::now()->startOfWeek()->addDay()->toDateString();
    $wednesday = Carbon::now()->startOfWeek()->addDays(3)->toDateString();

    $leaveRequest = createLeaveRequest($employee, 'personal_leave', $monday, $wednesday);

    $result = $this->service->validate($leaveRequest);

    expect($result['valid'])->toBeTrue();
    expect($result['errors'])->toBeEmpty();
});

// ─── 19. Unpaid leave passes even with no quota_days set ────────────────────

it('passes quota validation when quota_scope is null (no scope configured)', function () {
    $employee = createValidatorTestEmployee();

    createEntitlement('full_time', 'personal_leave', [
        'quota_scope' => null,
        'quota_days' => null,
    ]);

    $monday = Carbon::now()->startOfWeek()->addDay()->toDateString();
    $wednesday = Carbon::now()->startOfWeek()->addDays(3)->toDateString();

    $leaveRequest = createLeaveRequest($employee, 'personal_leave', $monday, $wednesday);

    $result = $this->service->validate($leaveRequest);

    expect($result['valid'])->toBeTrue();
    expect($result['errors'])->toBeEmpty();
});

// ─── 20. Returns is_paid_leave from entitlement ─────────────────────────────

it('returns is_paid_leave from the entitlement', function () {
    $employee = createValidatorTestEmployee();

    createEntitlement('full_time', 'annual_leave', [
        'is_paid' => true,
    ]);

    $monday = Carbon::now()->startOfWeek()->addDay()->toDateString();
    $wednesday = Carbon::now()->startOfWeek()->addDays(3)->toDateString();
    $leaveRequest = createLeaveRequest($employee, 'annual_leave', $monday, $wednesday);

    $result = $this->service->validate($leaveRequest);

    expect($result['is_paid_leave'])->toBeTrue();
});

// ─── 21. Returns request_working_days in result ─────────────────────────────

it('returns request_working_days in the result', function () {
    $employee = createValidatorTestEmployee();

    createEntitlement('full_time', 'annual_leave');

    // Monday to Wednesday — 3 working days
    $monday = Carbon::now()->startOfWeek()->addDay()->toDateString();
    $wednesday = Carbon::now()->startOfWeek()->addDays(3)->toDateString();
    $leaveRequest = createLeaveRequest($employee, 'annual_leave', $monday, $wednesday);

    $result = $this->service->validate($leaveRequest);

    expect($result['request_working_days'])->toBeGreaterThan(0);
});

// ─── 22. Deducts self from annual quota count ──────────────────────────────

it('excludes the current leave request from annual quota counting', function () {
    $employee = createValidatorTestEmployee();

    createEntitlement('full_time', 'annual_leave', [
        'quota_scope' => 'annual',
        'quota_days' => 3,
    ]);

    // Monday to Wednesday — 3 working days (the request itself)
    $monday = Carbon::now()->startOfWeek()->addDay()->toDateString();
    $wednesday = Carbon::now()->startOfWeek()->addDays(3)->toDateString();
    $leaveRequest = createLeaveRequest($employee, 'annual_leave', $monday, $wednesday);

    // No other approved leaves exist — usedDays = 0, request = 3, quota = 3 → valid
    $result = $this->service->validate($leaveRequest);

    expect($result['valid'])->toBeTrue();
    expect($result['errors'])->toBeEmpty();
});

// ─── 23. Multiple sick leave proof errors ───────────────────────────────────

it('returns multiple sick leave proof errors when proof is missing and invalid', function () {
    $employee = createValidatorTestEmployee();

    createEntitlement('full_time', 'sick_leave', [
        'requires_attachment' => true,
        'quota_scope' => 'unlimited',
        'allowed_mime_types' => ['application/pdf'],
        'max_attachment_size_kb' => 2048,
    ]);

    $monday = Carbon::now()->startOfWeek()->addDay()->toDateString();
    $wednesday = Carbon::now()->startOfWeek()->addDays(3)->toDateString();

    // No proof at all
    $leaveRequest = createLeaveRequest($employee, 'sick_leave', $monday, $wednesday);

    $result = $this->service->validate($leaveRequest);

    // Should have sick_leave_proof_required (early return in validateSickLeaveProof)
    expect($result['valid'])->toBeFalse();
    expect($result['errors'])->toContain('sick_leave_proof_required');
    // Should NOT have other proof errors because hasSickProofAttachment returns false → early return
    expect($result['errors'])->not->toContain('sick_leave_proof_not_approved');
});

// ─── 24. Annual quota counts approved leaves in same year only ──────────────

it('only counts approved leaves within the same year for annual quota', function () {
    $employee = createValidatorTestEmployee();

    createEntitlement('full_time', 'annual_leave', [
        'quota_scope' => 'annual',
        'quota_days' => 2,
    ]);

    // Create an approved leave from last year (should not count)
    $lastYearMonday = Carbon::now()->subYear()->startOfYear()->addDays(1)->toDateString();
    $lastYearTuesday = Carbon::now()->subYear()->startOfYear()->addDays(2)->toDateString();

    // Make sure these are actually last year
    $lastYearMondayDate = Carbon::parse($lastYearMonday);
    if ($lastYearMondayDate->year === Carbon::now()->year) {
        $lastYearMonday = Carbon::now()->subYears(2)->startOfYear()->addDay()->toDateString();
        $lastYearTuesday = Carbon::now()->subYears(2)->startOfYear()->addDays(2)->toDateString();
    }

    createLeaveRequest($employee, 'annual_leave', $lastYearMonday, $lastYearTuesday, [
        'status' => 'approved',
    ]);

    // Request 2 working days this year — should be valid since last year's leaves don't count
    $monday = Carbon::now()->startOfWeek()->addDay()->toDateString();
    $tuesday = Carbon::now()->startOfWeek()->addDays(2)->toDateString();

    $leaveRequest = createLeaveRequest($employee, 'annual_leave', $monday, $tuesday);

    $result = $this->service->validate($leaveRequest);

    expect($result['valid'])->toBeTrue();
    expect($result['errors'])->toBeEmpty();
});

// ─── 25. Sick leave without requires_attachment skips proof checks ───────────

it('skips sick leave proof checks when entitlement does not require attachment', function () {
    $employee = createValidatorTestEmployee();

    createEntitlement('full_time', 'sick_leave', [
        'requires_attachment' => false,
        'quota_scope' => 'unlimited',
    ]);

    $monday = Carbon::now()->startOfWeek()->addDay()->toDateString();
    $wednesday = Carbon::now()->startOfWeek()->addDays(3)->toDateString();
    $leaveRequest = createLeaveRequest($employee, 'sick_leave', $monday, $wednesday);

    $result = $this->service->validate($leaveRequest);

    expect($result['valid'])->toBeTrue();
    expect($result['errors'])->toBeEmpty();
});

// ─── 26. Emergency leave with valid reason passes ───────────────────────────

it('passes validation for emergency leave with a valid reason', function () {
    $employee = createValidatorTestEmployee();

    createEntitlement('full_time', 'emergency_leave', [
        'quota_scope' => 'unlimited',
    ]);

    $monday = Carbon::now()->startOfWeek()->addDay()->toDateString();
    $wednesday = Carbon::now()->startOfWeek()->addDays(3)->toDateString();
    $leaveRequest = createLeaveRequest($employee, 'emergency_leave', $monday, $wednesday, [
        'reason' => 'Family emergency at home',
    ]);

    $result = $this->service->validate($leaveRequest);

    expect($result['valid'])->toBeTrue();
    expect($result['errors'])->toBeEmpty();
});

// ─── 27. Per-occurrence quota with null quota_days returns error ────────────

it('returns error when per_occurrence entitlement has null quota_days', function () {
    $employee = createValidatorTestEmployee();

    createEntitlement('full_time', 'personal_leave', [
        'quota_scope' => 'per_occurrence',
        'quota_days' => null,
    ]);

    $monday = Carbon::now()->startOfWeek()->addDay()->toDateString();
    $wednesday = Carbon::now()->startOfWeek()->addDays(3)->toDateString();
    $leaveRequest = createLeaveRequest($employee, 'personal_leave', $monday, $wednesday);

    $result = $this->service->validate($leaveRequest);

    // quota_days is null → quotaIsValidForLeave returns false (line 169-171)
    expect($result['valid'])->toBeFalse();
    expect($result['errors'])->toContain('quota_exceeded_per_occurrence');
});

// ─── 28. Annual quota with null quota_days returns error ────────────────────

it('returns error when annual entitlement has null quota_days', function () {
    $employee = createValidatorTestEmployee();

    createEntitlement('full_time', 'annual_leave', [
        'quota_scope' => 'annual',
        'quota_days' => null,
    ]);

    $monday = Carbon::now()->startOfWeek()->addDay()->toDateString();
    $wednesday = Carbon::now()->startOfWeek()->addDays(3)->toDateString();
    $leaveRequest = createLeaveRequest($employee, 'annual_leave', $monday, $wednesday);

    $result = $this->service->validate($leaveRequest);

    // quota_days is null → quotaIsValidForLeave returns false
    expect($result['valid'])->toBeFalse();
    expect($result['errors'])->toContain('quota_exceeded_annual');
});

// ─── 29. Annual quota only counts same leave type ───────────────────────────

it('only counts leaves of the same type for annual quota', function () {
    $employee = createValidatorTestEmployee();

    createEntitlement('full_time', 'annual_leave', [
        'quota_scope' => 'annual',
        'quota_days' => 2,
    ]);

    // Create approved personal_leave (different type, should not count)
    $pastMonday = Carbon::now()->subWeek()->startOfWeek()->addDay()->toDateString();
    $pastWednesday = Carbon::now()->subWeek()->startOfWeek()->addDays(3)->toDateString();

    createLeaveRequest($employee, 'personal_leave', $pastMonday, $pastWednesday, [
        'status' => 'approved',
    ]);

    // Request 2 working days of annual_leave — should be valid
    $monday = Carbon::now()->startOfWeek()->addDay()->toDateString();
    $tuesday = Carbon::now()->startOfWeek()->addDays(2)->toDateString();

    $leaveRequest = createLeaveRequest($employee, 'annual_leave', $monday, $tuesday);

    $result = $this->service->validate($leaveRequest);

    expect($result['valid'])->toBeTrue();
    expect($result['errors'])->toBeEmpty();
});
