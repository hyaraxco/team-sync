# Data Privacy Resource Exposure Implementation Plan

> **Execution:** Use the **executing-plans** skill to execute this plan in single-flow mode.

**Goal:** Implement conditional exposure of sensitive data in StaffMemberProfileResource and JobInformationResource based on user permissions and profile ownership.

**Architecture:** Use Laravel API Resource `$this->when()` to check if the authenticated user has 'staff-member-edit' (or 'payroll-list' for salary) OR is viewing their own profile.

**Tech Stack:** Laravel 10+, PHP 8.2+

---

### Task 1: Update StaffMemberProfileResource

**Files:**
- Modify: `team-sync-be/app/Http/Resources/StaffMemberProfileResource.php`
- Test: `team-sync-be/tests/Feature/StaffMember/StaffMemberProfileEndpointTest.php`

**Step 1: Write the failing tests**

In `team-sync-be/tests/Feature/StaffMember/StaffMemberProfileEndpointTest.php`, add two tests for `GET /api/staff-members/{id}`:
1. "it hides sensitive data for regular staff viewing other profiles"
2. "it exposes sensitive data for users viewing their own profile"
3. "it exposes sensitive data for users with staff-member-edit permission"

**Step 2: Implement the minimal code**

In `team-sync-be/app/Http/Resources/StaffMemberProfileResource.php`:

```php
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isOwnProfile = $user && $user->staffMemberProfile && $user->staffMemberProfile->id === $this->id;
        $canEdit = $user && $user->can('staff-member-edit');
        $canSeeSensitive = $isOwnProfile || $canEdit;

        return [
            'id' => $this->id,
            'user' => new UserResource($this->whenLoaded('user')),
            'code' => $this->code,
            'identity_number' => $this->when($canSeeSensitive, $this->identity_number),
            'npwp' => $this->when($canSeeSensitive, $this->npwp),
            'bpjs_ketenagakerjaan' => $this->when($canSeeSensitive, $this->bpjs_ketenagakerjaan),
            'bpjs_kesehatan' => $this->when($canSeeSensitive, $this->bpjs_kesehatan),
            'ptkp_status' => $this->when($canSeeSensitive, $this->ptkp_status),
            'phone' => $this->phone,
            'date_of_birth' => $this->date_of_birth?->format('Y-m-d'),
            'gender' => $this->gender,
            'religion' => $this->religion,
            'marital_status' => $this->marital_status,
            'blood_type' => $this->blood_type,
            'place_of_birth' => $this->place_of_birth,
            'address' => $this->address,
            'city' => $this->city,
            'postal_code' => $this->postal_code,

            'job_information' => new JobInformationResource($this->whenLoaded('jobInformation')),
            'bank_information' => $this->when($canSeeSensitive, new BankInformationResource($this->whenLoaded('bankInformation'))),
            'emergency_contacts' => $this->when($canSeeSensitive, EmergencyContactResource::collection($this->whenLoaded('emergencyContacts'))),
            'team' => new TeamResource($this->whenLoaded('team')),

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
```

**Step 3: Run the tests**

Run: `php artisan test --filter=StaffMemberProfileEndpointTest`
Expected: PASS

**Step 4: Commit**

```bash
git add app/Http/Resources/StaffMemberProfileResource.php tests/Feature/StaffMember/StaffMemberProfileEndpointTest.php
git commit -m "feat(api): hide sensitive profile data from unauthorized users (GAP-005)"
```

---

### Task 2: Update JobInformationResource

**Files:**
- Modify: `team-sync-be/app/Http/Resources/JobInformationResource.php`
- Test: `team-sync-be/tests/Feature/StaffMember/StaffMemberProfileEndpointTest.php`

**Step 1: Write the failing tests**

In `team-sync-be/tests/Feature/StaffMember/StaffMemberProfileEndpointTest.php`, add tests:
1. "it hides monthly_salary for regular staff viewing other profiles"
2. "it exposes monthly_salary for users with payroll-list permission"

**Step 2: Implement the minimal code**

In `team-sync-be/app/Http/Resources/JobInformationResource.php`:

```php
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isOwnProfile = $user && $user->staffMemberProfile && $user->staffMemberProfile->id === $this->staff_member_profile_id;
        $canEdit = $user && $user->can('staff-member-edit');
        $canSeePayroll = $user && $user->can('payroll-list');
        $canSeeSensitive = $isOwnProfile || $canEdit || $canSeePayroll;

        return [
            'id' => $this->id,
            'job_title' => $this->job_title,
            'team' => new TeamResource($this->whenLoaded('team')),
            'status' => $this->status,
            'employment_type' => $this->employment_type,
            'work_location' => $this->work_location,
            'start_date' => $this->start_date,
            'monthly_salary' => $this->when($canSeeSensitive, $this->monthly_salary),
        ];
    }
```

**Step 3: Run the tests**

Run: `php artisan test --filter=StaffMemberProfileEndpointTest`
Expected: PASS

**Step 4: Commit**

```bash
git add app/Http/Resources/JobInformationResource.php tests/Feature/StaffMember/StaffMemberProfileEndpointTest.php
git commit -m "feat(api): hide monthly_salary from unauthorized users (GAP-006)"
```
