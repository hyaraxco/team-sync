# GAP-005 & GAP-006: Data Privacy Resource Exposure Design

**Date**: 2026-04-22

## Background
Currently, `StaffMemberProfileResource` and `JobInformationResource` expose sensitive data such as identity numbers, NPWP, BPJS, bank information, emergency contacts, and monthly salaries to anyone who can view the staff member profile list or detail.

## Goal
Restrict the exposure of sensitive data in the API response conditionally based on the user's role and whether the profile belongs to the authenticated user.

## Access Rules (Business Logic)

### 1. General Sensitive Data (`StaffMemberProfileResource`)
Fields to protect: `identity_number`, `npwp`, `bpjs_ketenagakerjaan`, `bpjs_kesehatan`, `ptkp_status`, `bank_information`, `emergency_contacts`.

**Who can see this data?**
- The user themselves (Own Profile).
- Users with the `staff-member-edit` permission (HR/Admin).

### 2. Salary Data (`JobInformationResource`)
Field to protect: `monthly_salary`.

**Who can see this data?**
- The user themselves (Own Profile).
- Users with the `staff-member-edit` permission (HR/Admin).
- Users with the `payroll-list` permission (Finance).

## Implementation Approach
We will utilize Laravel's API Resource `$this->when()` method to conditionally include fields in the JSON response.

### Implementation Example
```php
// In StaffMemberProfileResource
$isOwnProfile = $request->user()?->staffMemberProfile?->id === $this->id;
$canEdit = $request->user()?->can('staff-member-edit');
$canSeeSensitive = $isOwnProfile || $canEdit;

return [
    ...
    'npwp' => $this->when($canSeeSensitive, $this->npwp),
    ...
];
```

```php
// In JobInformationResource
$isOwnProfile = $request->user()?->staffMemberProfile?->id === $this->staff_member_profile_id; // Check relation
$canEdit = $request->user()?->can('staff-member-edit');
$canSeePayroll = $request->user()?->can('payroll-list');
$canSeeSensitive = $isOwnProfile || $canEdit || $canSeePayroll;

return [
    ...
    'monthly_salary' => $this->when($canSeeSensitive, $this->monthly_salary),
    ...
];
```

This ensures zero frontend changes are required while preventing data leaks at the API boundary.
