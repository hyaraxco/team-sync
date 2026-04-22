# Employee to StaffMember Hard Cutover Implementation Plan

> **Execution:** Use the **executing-plans** skill to execute this plan in single-flow mode.

**Goal:** Complete a hard cutover rename from `Employee` to `StaffMember` across backend, frontend, RBAC role/permissions, tests, and docs, with no compatibility aliases.

**Architecture:** This implementation is split into 4 PRs to control risk: (1) DB + backend core contract rename, (2) backend propagation + RBAC hardening, (3) frontend + e2e contract alignment, and (4) docs + stabilization sweep. We will add characterization tests first, then perform mechanical rename + targeted logic updates, then run full verification gates.

**Tech Stack:** Laravel 12 / PHP 8.2, Spatie Laravel Permission v6, Vue 3 + Pinia + Vue Router, Vitest, Playwright.

**Design Reference:** `docs/plans/2026-04-21-employee-to-staff-member-rename-design.md`

---

## PR Strategy (Approved)

1. **PR-1: DB + Backend Core Contracts**
2. **PR-2: Backend Propagation + RBAC Hardening**
3. **PR-3: Frontend + E2E Contract Alignment**
4. **PR-4: Docs + Stabilization + Final Sweep**

---

### Task 1: Create dedicated worktree + baseline verification

**Files:**
- Modify: *(none, setup task)*
- Test: `team-sync-be/composer.json`, `team-sync-fe/package.json`

**Step 1: Create dedicated worktree**

Run:
```bash
git worktree add ../team-sync-staff-cutover -b feat/staff-member-hard-cutover
```
Expected: new worktree directory created and branch checked out.

**Step 2: Verify backend baseline passes**

Run:
```bash
composer test
```
Workdir: `team-sync-be/`
Expected: existing test baseline passes.

**Step 3: Verify frontend baseline passes**

Run:
```bash
bun run test:guards
```
Workdir: `team-sync-fe/`
Expected: guard tests pass on baseline.

**Step 4: Commit (tracking baseline only, optional notes file)**

```bash
git add -A
git commit -m "chore: establish baseline for staff-member cutover"
```

---

### Task 2: Add failing characterization tests for new contract

**Files:**
- Create: `team-sync-be/tests/Feature/StaffMember/StaffMemberContractTest.php`
- Create: `team-sync-fe/src/tests/router/staffMemberRouteContract.test.js`
- Test: `team-sync-be/tests/Feature/Employee/EmployeeProfileEndpointTest.php`
- Test: `team-sync-fe/src/tests/router/featureGuardMatrix.test.js`

**Step 1: Write failing backend contract test**

```php
<?php

namespace Tests\Feature\StaffMember;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffMemberContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_members_endpoints_exist(): void
    {
        $this->getJson('/api/v1/staff-members')->assertStatus(401);
        $this->getJson('/api/v1/staff-members/statistics')->assertStatus(401);
    }
}
```

**Step 2: Write failing frontend route contract test**

```js
import { describe, expect, it } from "vitest";
import { appRoutes } from "@/router/index";

const allNames = [];
const walk = (routes) => {
  for (const r of routes) {
    if (r?.name) allNames.push(r.name);
    if (Array.isArray(r?.children)) walk(r.children);
  }
};

describe("staff member route contract", () => {
  it("contains admin.staffMembers route namespace", () => {
    walk(appRoutes);
    expect(allNames).toContain("admin.staffMembers");
  });
});
```

**Step 3: Run tests to verify they fail**

Run:
```bash
php artisan test tests/Feature/StaffMember/StaffMemberContractTest.php
```
Expected: FAIL (routes not found yet).

Run:
```bash
bun run vitest run src/tests/router/staffMemberRouteContract.test.js
```
Expected: FAIL (route namespace not present yet).

**Step 4: Commit failing tests**

```bash
git add team-sync-be/tests/Feature/StaffMember/StaffMemberContractTest.php team-sync-fe/src/tests/router/staffMemberRouteContract.test.js
git commit -m "test(contract): add failing staff-member route contracts"
```

---

### Task 3: Implement DB migration for table/column + RBAC rename

**Files:**
- Create: `team-sync-be/database/migrations/2026_04_21_010000_rename_employee_domain_to_staff_member.php`
- Modify: `team-sync-be/database/seeders/RoleSeeder.php`
- Modify: `team-sync-be/database/seeders/PermissionSeeder.php`
- Modify: `team-sync-be/database/seeders/RolePermissionSeeder.php`

**Step 1: Write migration skeleton with explicit rename map**

Create migration that does all of the following in `up()`:
- rename table: `employee_profiles` → `staff_member_profiles`
- rename role row: `roles.name = employee` → `staff`
- rename permission rows prefix: `employee-` → `staff-member-`
- rename FK columns `employee_id` → `staff_member_id` in tables:
  - `job_information`
  - `bank_information`
  - `emergency_contacts`
  - `team_members`
  - `attendances`
  - `leave_requests`
  - `payroll_details`
  - `hybrid_work_schedules`
  - `hybrid_schedule_overrides`
  - `attendance_policy_mismatches`
  - `payroll_adjustments`
  - `payroll_notification_deliveries`
  - `attendance_corrections`
  - `performance_reviews`
  - `performance_goals`
  - `performance_feedback`
  - `project_task_comments`
  - `project_task_attachments`

Include explicit `down()` to reverse all renames.

**Step 2: Update seeders to canonical names**

- `RoleSeeder.php`: role `employee` → `staff`
- `PermissionSeeder.php`: permission namespace key `employee` → `staff-member`
- `RolePermissionSeeder.php`: references `employee-*` → `staff-member-*`, `$employee` role variable → `$staff`

**Step 3: Run migration in test env**

Run:
```bash
php artisan migrate:fresh --seed
```
Workdir: `team-sync-be/`
Expected: migration + seeders complete with no SQL error.

**Step 4: Commit migration + seeder updates**

```bash
git add team-sync-be/database/migrations/2026_04_21_010000_rename_employee_domain_to_staff_member.php team-sync-be/database/seeders/RoleSeeder.php team-sync-be/database/seeders/PermissionSeeder.php team-sync-be/database/seeders/RolePermissionSeeder.php
git commit -m "feat(rbac): rename employee domain keys to staff-member and role to staff"
```

---

### Task 4: Rename backend core profile classes/files

**Files:**
- Rename: `team-sync-be/app/Models/EmployeeProfile.php` → `team-sync-be/app/Models/StaffMemberProfile.php`
- Rename: `team-sync-be/app/Http/Controllers/EmployeeProfileController.php` → `team-sync-be/app/Http/Controllers/StaffMemberProfileController.php`
- Rename: `team-sync-be/app/Repositories/EmployeeProfileRepository.php` → `team-sync-be/app/Repositories/StaffMemberProfileRepository.php`
- Rename: `team-sync-be/app/Interfaces/EmployeeProfileRepositoryInterface.php` → `team-sync-be/app/Interfaces/StaffMemberProfileRepositoryInterface.php`
- Rename: `team-sync-be/app/Http/Resources/EmployeeProfileResource.php` → `team-sync-be/app/Http/Resources/StaffMemberProfileResource.php`
- Rename: `team-sync-be/app/Http/Requests/EmployeeProfileStoreRequest.php` → `team-sync-be/app/Http/Requests/StaffMemberProfileStoreRequest.php`
- Rename: `team-sync-be/app/Http/Requests/EmployeeProfileUpdateRequest.php` → `team-sync-be/app/Http/Requests/StaffMemberProfileUpdateRequest.php`
- Rename: `team-sync-be/app/DTOs/EmployeeProfileDto.php` → `team-sync-be/app/DTOs/StaffMemberProfileDto.php`
- Modify: `team-sync-be/app/Providers/RepositoryServiceProvider.php`
- Modify: `team-sync-be/tests/Unit/DTOs/EmployeeProfileDtoTest.php` (rename class + imports)

**Step 1: Rename files/classes and fix namespaces/imports**

Apply class renames consistently:
- `EmployeeProfile` → `StaffMemberProfile`
- `EmployeeProfileController` → `StaffMemberProfileController`
- `EmployeeProfileRepositoryInterface` → `StaffMemberProfileRepositoryInterface`
- `EmployeeProfileRepository` → `StaffMemberProfileRepository`
- `EmployeeProfileResource` → `StaffMemberProfileResource`
- `EmployeeProfileStoreRequest` → `StaffMemberProfileStoreRequest`
- `EmployeeProfileUpdateRequest` → `StaffMemberProfileUpdateRequest`
- `EmployeeProfileDto` → `StaffMemberProfileDto`

**Step 2: Update DI bindings**

In `RepositoryServiceProvider.php` update import and bind call:
- `EmployeeProfileRepositoryInterface::class` → `StaffMemberProfileRepositoryInterface::class`
- `EmployeeProfileRepository::class` → `StaffMemberProfileRepository::class`

**Step 3: Run targeted backend tests**

Run:
```bash
php artisan test tests/Unit/DTOs/EmployeeProfileDtoTest.php tests/Feature/Employee/EmployeeProfileEndpointTest.php
```
Expected: initially FAIL on stale names, then PASS after updating tests in Task 6.

**Step 4: Commit core class renames**

```bash
git add team-sync-be/app/Models team-sync-be/app/Http/Controllers team-sync-be/app/Repositories team-sync-be/app/Interfaces team-sync-be/app/Http/Resources team-sync-be/app/Http/Requests team-sync-be/app/DTOs team-sync-be/app/Providers/RepositoryServiceProvider.php team-sync-be/tests/Unit/DTOs/EmployeeProfileDtoTest.php
git commit -m "refactor(be): rename EmployeeProfile core classes to StaffMemberProfile"
```

---

### Task 5: Update backend API route contracts + controller middleware permissions

**Files:**
- Modify: `team-sync-be/routes/api.php`
- Modify: `team-sync-be/app/Http/Controllers/StaffMemberProfileController.php`
- Modify: `team-sync-be/tests/Feature/Employee/EmployeeProfileEndpointTest.php` (move/rename to StaffMember namespace)

**Step 1: Update route URIs**

In `routes/api.php`:
- `employees/statistics` → `staff-members/statistics`
- `employees/{id}/performance-statistics` → `staff-members/{id}/performance-statistics`
- `employees/all/paginated` → `staff-members/all/paginated`
- `employees/check-availability` → `staff-members/check-availability`
- `Route::apiResource('employees', ...)` → `Route::apiResource('staff-members', ...)`
- switch controller import to `StaffMemberProfileController`

**Step 2: Update controller permission middleware**

In `StaffMemberProfileController`:
- `employee-list|employee-create|employee-edit|employee-delete`
  → `staff-member-list|staff-member-create|staff-member-edit|staff-member-delete`
- all single permission strings follow same rename.

**Step 3: Update endpoint tests**

- Rename test file to `team-sync-be/tests/Feature/StaffMember/StaffMemberEndpointTest.php`
- Update assertions to use `/api/v1/staff-members...` for admin endpoints.
- Keep `/api/v1/my-profile` and `/api/v1/my-team` unchanged unless explicitly renamed in implementation.

**Step 4: Run tests and verify pass**

Run:
```bash
php artisan test tests/Feature/StaffMember/StaffMemberContractTest.php tests/Feature/StaffMember/StaffMemberEndpointTest.php
```
Expected: PASS.

**Step 5: Commit route contract updates**

```bash
git add team-sync-be/routes/api.php team-sync-be/app/Http/Controllers/StaffMemberProfileController.php team-sync-be/tests/Feature/StaffMember
git commit -m "feat(api): switch employee endpoints and permissions to staff-member"
```

---

### Task 6: Propagate backend relation + FK renames across models/resources/requests/repositories/services

**Files (high-impact, modify):**
- `team-sync-be/app/Models/User.php`
- `team-sync-be/app/Models/JobInformation.php`
- `team-sync-be/app/Models/BankInformation.php`
- `team-sync-be/app/Models/EmergencyContact.php`
- `team-sync-be/app/Models/TeamMember.php`
- `team-sync-be/app/Models/Attendance.php`
- `team-sync-be/app/Models/AttendanceCorrection.php`
- `team-sync-be/app/Models/LeaveRequest.php`
- `team-sync-be/app/Models/PayrollDetail.php`
- `team-sync-be/app/Models/HybridWorkSchedule.php`
- `team-sync-be/app/Models/HybridScheduleOverride.php`
- `team-sync-be/app/Models/AttendancePolicyMismatch.php`
- `team-sync-be/app/Models/PayrollAdjustment.php`
- `team-sync-be/app/Models/PayrollNotificationDelivery.php`
- `team-sync-be/app/Models/PerformanceReview.php`
- `team-sync-be/app/Models/PerformanceGoal.php`
- `team-sync-be/app/Models/PerformanceFeedback.php`
- `team-sync-be/app/Models/ProjectTaskComment.php`
- `team-sync-be/app/Models/ProjectTaskAttachment.php`
- `team-sync-be/app/Repositories/*.php` (all employee_id/employeeProfile usages)
- `team-sync-be/app/Http/Resources/*.php` (payload keys)
- `team-sync-be/app/Http/Requests/**/*.php` (validation keys)
- `team-sync-be/app/DTOs/**/*.php` (field names)
- `team-sync-be/app/Services/**/*.php`
- `team-sync-be/app/Notifications/*.php`

**Step 1: Rename relationship methods and FK keys**

Minimum required updates:
- `employeeProfile()` → `staffMemberProfile()`
- `employee_id` fields in code → `staff_member_id`
- `EmployeeProfile::class` → `StaffMemberProfile::class`

**Step 2: Update serialized payload keys**

In API resources and DTOs:
- `employee` key → `staff_member`
- `employee_id` key → `staff_member_id`

**Step 3: Run static checks by grep**

Run:
```bash
grep -R "employeeProfile\|employee_id\|EmployeeProfile::class" app
```
Expected: no runtime references remain (except intentionally historical comments).

**Step 4: Commit propagation batch**

```bash
git add team-sync-be/app/Models team-sync-be/app/Repositories team-sync-be/app/Http/Resources team-sync-be/app/Http/Requests team-sync-be/app/DTOs team-sync-be/app/Services team-sync-be/app/Notifications
git commit -m "refactor(be): propagate staff-member relation and payload naming"
```

---

### Task 7: Update backend role checks + seeders/factories/tests to `staff`

**Files:**
- Modify: `team-sync-be/app/Http/Middleware/EnsureProjectMembership.php`
- Modify: `team-sync-be/app/Services/EmailService.php`
- Modify: `team-sync-be/app/Repositories/ProjectRepository.php`
- Modify: `team-sync-be/app/Repositories/ProjectTaskRepository.php`
- Modify: `team-sync-be/app/Repositories/AttendanceCorrectionRepository.php`
- Modify: `team-sync-be/database/factories/StaffMemberProfileFactory.php` *(renamed from EmployeeProfileFactory)*
- Modify: `team-sync-be/database/seeders/EmployeeSeeder.php` *(or renamed seeder file if chosen)*
- Modify: `team-sync-be/database/seeders/MobileDevelopmentDummySeeder.php`
- Modify tests:
  - `team-sync-be/tests/Feature/Leave/LeaveRequestProofUploadTest.php`
  - `team-sync-be/tests/Feature/Leave/LeaveRequestPeriodGuardTest.php`
  - `team-sync-be/tests/Feature/Attendance/AttendanceCorrectionGuardTest.php`
  - `team-sync-be/tests/Feature/Notification/ProjectTeamAttendanceNotificationsTest.php`
  - `team-sync-be/tests/Feature/Performance/CalibrationTest.php`
  - `team-sync-be/tests/Unit/Helpers/PerformanceRatingHelperTest.php`

**Step 1: Replace runtime role checks**

- `hasRole('employee')` → `hasRole('staff')`
- `assignRole('employee')` → `assignRole('staff')`
- `syncRoles(['employee'])` → `syncRoles(['staff'])`

**Step 2: Update seed/test role fixtures**

- `Role::firstOrCreate(['name' => 'employee'...])` → `'staff'`
- `Role::findByName('employee', 'sanctum')` → `'staff'`

**Step 3: Run targeted backend role tests**

Run:
```bash
php artisan test tests/Feature/Leave/LeaveRequestProofUploadTest.php tests/Feature/Attendance/AttendanceCorrectionGuardTest.php tests/Feature/Performance/CalibrationTest.php
```
Expected: PASS.

**Step 4: Commit backend RBAC hardening**

```bash
git add team-sync-be/app/Http/Middleware/EnsureProjectMembership.php team-sync-be/app/Services/EmailService.php team-sync-be/app/Repositories/ProjectRepository.php team-sync-be/app/Repositories/ProjectTaskRepository.php team-sync-be/app/Repositories/AttendanceCorrectionRepository.php team-sync-be/database/factories team-sync-be/database/seeders team-sync-be/tests/Feature team-sync-be/tests/Unit
git commit -m "refactor(be-rbac): replace employee role checks with staff"
```

---

### Task 8: Run full backend verification gate (PR-2 close)

**Files:**
- Modify: *(none, verification task)*
- Test: backend full suite

**Step 1: Run migration fresh + seed**

Run:
```bash
php artisan migrate:fresh --seed
```
Expected: PASS.

**Step 2: Run full backend tests**

Run:
```bash
composer test
```
Expected: PASS.

**Step 3: Commit any last test fixes**

```bash
git add team-sync-be
git commit -m "test(be): align full backend suite with staff-member cutover"
```

---

### Task 9: Rename frontend store/router/layout and route contract

**Files:**
- Rename: `team-sync-fe/src/stores/employee.js` → `team-sync-fe/src/stores/staffMember.js`
- Rename: `team-sync-fe/src/router/employee.js` → `team-sync-fe/src/router/staffMember.js`
- Rename: `team-sync-fe/src/layouts/EmployeeCreateLayout.vue` → `team-sync-fe/src/layouts/StaffMemberCreateLayout.vue`
- Modify: `team-sync-fe/src/router/index.js`
- Modify: `team-sync-fe/src/router/payroll.js`
- Modify: `team-sync-fe/src/router/attendance.js`

**Step 1: Rename store API surface**

In new `staffMember.js`:
- `useEmployeeStore` → `useStaffMemberStore`
- `defineStore("employee")` → `defineStore("staffMember")`
- endpoints `/employees...` → `/staff-members...`
- method names `fetchEmployees*` → `fetchStaffMembers*` etc.

**Step 2: Rename router module and route names/paths**

In new `staffMember.js`:
- `path: 'employees'` → `path: 'staff-members'`
- `name: 'admin.employees'` → `name: 'admin.staffMembers'`
- `requiredPermission: 'employee-*'` → `requiredPermission: 'staff-member-*'`

In `index.js`:
- import `staffMemberRoutes` from `./staffMember`
- route paths `/admin/employees/...` → `/admin/staff-members/...`
- route names `admin.employees.*` → `admin.staffMembers.*`

**Step 3: Update references from payroll/attendance router files**

- update view import paths once renamed in Task 10.

**Step 4: Run failing contract tests then fix to pass**

Run:
```bash
bun run vitest run src/tests/router/staffMemberRouteContract.test.js src/tests/router/featureGuardMatrix.test.js
```
Expected: PASS after route updates.

**Step 5: Commit frontend route/store contract changes**

```bash
git add team-sync-fe/src/stores team-sync-fe/src/router team-sync-fe/src/layouts
git commit -m "refactor(fe): switch employee store/routes to staff-member contract"
```

---

### Task 10: Rename frontend views/components directories and imports

**Files:**
- Rename dir: `team-sync-fe/src/views/admin/employee/` → `team-sync-fe/src/views/admin/staff-member/`
- Rename dir: `team-sync-fe/src/views/employee/` → `team-sync-fe/src/views/staff-member/`
- Rename dir: `team-sync-fe/src/components/admin/employee/` → `team-sync-fe/src/components/admin/staff-member/`
- Rename dir: `team-sync-fe/src/components/employee/` → `team-sync-fe/src/components/staff-member/`
- Modify import callers (examples):
  - `team-sync-fe/src/views/admin/project/ProjectCreate.vue`
  - `team-sync-fe/src/views/admin/project/ProjectEdit.vue`
  - `team-sync-fe/src/views/admin/team/TeamCreate.vue`
  - `team-sync-fe/src/views/admin/team/TeamEdit.vue`
  - `team-sync-fe/src/views/admin/team/TeamDetail.vue`
  - `team-sync-fe/src/components/admin/dashboard/LatestEmployees.vue`
  - `team-sync-fe/src/components/admin/dashboard/EmployeeStatistics.vue`
  - `team-sync-fe/src/components/admin/project/detail/TaskDetailModal.vue`

**Step 1: Rename files and directories physically**

Keep kebab-case for directories and PascalCase for Vue filenames.

**Step 2: Update imports globally**

Replace:
- `@/views/admin/employee/` → `@/views/admin/staff-member/`
- `@/views/employee/` → `@/views/staff-member/`
- `@/components/admin/employee/` → `@/components/admin/staff-member/`
- `@/components/employee/` → `@/components/staff-member/`
- `@/stores/employee` → `@/stores/staffMember`

**Step 3: Run quick unit pass**

Run:
```bash
bun run test:guards
```
Expected: PASS.

**Step 4: Commit file-system rename batch**

```bash
git add team-sync-fe/src/views team-sync-fe/src/components team-sync-fe/src/tests team-sync-fe/src/router team-sync-fe/src/stores
git commit -m "refactor(fe): rename employee view/component namespaces to staff-member"
```

---

### Task 11: Update frontend RBAC checks (`employee` role and `employee-*` permissions)

**Files:**
- Modify: `team-sync-fe/src/components/admin/project/detail/TaskBoard.vue`
- Modify: `team-sync-fe/src/components/admin/project/detail/TaskDetailModal.vue`
- Modify: `team-sync-fe/src/views/admin/performance/ReviewDetail.vue`
- Modify: `team-sync-fe/src/components/admin/Sidebar.vue`
- Modify: `team-sync-fe/src/components/admin/dashboard/QuickActions.vue`
- Modify: `team-sync-fe/src/views/admin/staff-member/EmployeeList.vue` *(renamed path)*
- Modify tests:
  - `team-sync-fe/src/tests/router/featureGuardMatrix.test.js`
  - `team-sync-fe/src/tests/router/payrollRoleAccess.test.js`
  - `team-sync-fe/src/tests/router/routerGuard.integration.test.js`
  - `team-sync-fe/src/tests/admin/components/Sidebar.smoke.test.js`
  - `team-sync-fe/src/tests/admin/dashboard/QuickActions.smoke.test.js`
  - `team-sync-fe/src/tests/admin/project/TaskBoard.smoke.test.js`
  - `team-sync-fe/src/tests/admin/project/TaskDetailModal.smoke.test.js`

**Step 1: Replace role checks**

- `hasRole("employee")` → `hasRole("staff")`
- role fixture `roles: [{ name: "employee" }]` → `roles: [{ name: "staff" }]`

**Step 2: Replace permission strings**

- `employee-menu` → `staff-member-menu`
- `employee-list` → `staff-member-list`
- `employee-create` → `staff-member-create`
- `employee-edit` → `staff-member-edit`
- `employee-delete` → `staff-member-delete`

**Step 3: Update admin route names in tests/components**

- `admin.employees*` → `admin.staffMembers*`
- `/admin/employees` → `/admin/staff-members`

**Step 4: Run router + component smoke tests**

Run:
```bash
bun run vitest run src/tests/router/featureGuardMatrix.test.js src/tests/router/routerGuard.integration.test.js src/tests/admin/components/Sidebar.smoke.test.js src/tests/admin/dashboard/QuickActions.smoke.test.js src/tests/admin/project/TaskBoard.smoke.test.js src/tests/admin/project/TaskDetailModal.smoke.test.js
```
Expected: PASS.

**Step 5: Commit frontend RBAC updates**

```bash
git add team-sync-fe/src/components team-sync-fe/src/views team-sync-fe/src/tests
git commit -m "refactor(fe-rbac): rename employee role/permission checks to staff"
```

---

### Task 12: Update E2E helpers/specs and package scripts

**Files:**
- Modify: `team-sync-fe/e2e/helpers/auth.ts`
- Rename: `team-sync-fe/e2e/notification-employee-task.spec.ts` → `team-sync-fe/e2e/notification-staff-task.spec.ts`
- Modify: `team-sync-fe/e2e/payroll-roles.spec.ts`
- Modify: `team-sync-fe/package.json`

**Step 1: Update e2e role credential key**

In `auth.ts`:
- `employee: { ... }` → `staff: { ... }`
- update `RoleName` usage accordingly.

**Step 2: Update e2e spec role logins**

- `loginAsRole(page, "employee")` → `loginAsRole(page, "staff")`

**Step 3: Update package test script paths**

`test:guards` should reference renamed test directory/files (staff-member paths).

**Step 4: Run e2e auth smoke + guard script**

Run:
```bash
bun run test:guards
```
Expected: PASS.

Run (optional fast e2e smoke):
```bash
bun run e2e:ui -- --grep "payroll roles"
```
Expected: PASS for targeted scenario.

**Step 5: Commit e2e alignment**

```bash
git add team-sync-fe/e2e team-sync-fe/package.json
git commit -m "test(e2e): switch employee role fixtures to staff"
```

---

### Task 13: Update docs and naming references

**Files (modify, minimum set):**
- `docs/README.md`
- `docs/employee/README.md` *(rename folder optional: `docs/staff-member/README.md`)*
- `docs/attendance/README.md`
- `docs/analytics/SPEC.md`
- `docs/payroll/README.md`
- `docs/payroll/payroll-phase-2-backlog-plan.md`
- `docs/payroll/payroll-phase-3-plan.md`
- `docs/payroll/payroll-role-e2e-qa.md`
- `docs/payroll_attendance/SPEC.md`
- `docs/performance-management/SPEC.md`
- `docs/performance-management/performance-analytics-implementation-plan.md`
- `docs/plans/2026-04-20-analytics-refactor.md`
- `docs/plans/2026-04-20-finance-dashboard-design.md`
- `docs/plans/2026-04-20-finance-dashboard-plan.md`
- `docs/plans/2026-04-20-payroll-analytics-fix-plan.md`
- `docs/plans/2026-04-20-ui-standardization-plan.md`
- `docs/plans/2026-04-21-calibration-system-overhaul.md`
- `docs/plans/2026-04-21-rbac-e2e-audit.md`
- `docs/plans/2026-04-21-employee-to-staff-member-rename-design.md` *(historical note can remain title, but body updated to final decisions)*

**Step 1: Replace terminology in docs**

Replace textual terms:
- Employee / employee → Staff Member / staff member
- employee role → staff role
- employee-* permissions → staff-member-* permissions

**Step 2: Keep historical migration filenames untouched**

Do not rewrite old migration file names in docs where they represent historical facts.

**Step 3: Run docs grep check**

Run:
```bash
grep -R "employee\|Employee" docs
```
Expected: only intentional historical references (if any) remain.

**Step 4: Commit docs update**

```bash
git add docs
git commit -m "docs: align terminology to staff-member hard cutover"
```

---

### Task 14: Final repository-wide sweep + full verification gates

**Files:**
- Modify: *(none, verification task)*

**Step 1: Backend no-regression checks**

Run:
```bash
php artisan migrate:fresh --seed
composer test
```
Workdir: `team-sync-be/`
Expected: all green.

**Step 2: Frontend no-regression checks**

Run:
```bash
bun run test
bun run build
```
Workdir: `team-sync-fe/`
Expected: all green, build output generated.

**Step 3: Residual token sweep**

Run:
```bash
grep -R "\bemployee\b\|EmployeeProfile\|employee_id\|admin\.employees\|/admin/employees\|hasRole(\"employee\")" team-sync-be team-sync-fe
```
Expected: no runtime-code references; only allowed historical strings in old migrations/tests docs if explicitly accepted.

**Step 4: Manual QA checklist**

Verify manually:
1. Login as `staff` user → self-service pages load.
2. HR/Manager open `/admin/staff-members` list/create/edit/detail.
3. Project task transitions still enforce role checks for `staff`.
4. Performance review self-assessment works for `staff` role.
5. Payroll access matrix unchanged functionally except renamed permission strings.

**Step 5: Commit final stabilization fixes**

```bash
git add -A
git commit -m "chore: finalize staff-member hard cutover verification fixes"
```

---

## PR Packaging Instructions

### PR-1 (DB + Backend Core Contracts)
Include commits from Tasks 2-5.

Suggested PR title:
`feat(be): introduce staff-member core domain and API contracts`

### PR-2 (Backend Propagation + RBAC Hardening)
Include commits from Tasks 6-8.

Suggested PR title:
`refactor(be): propagate staff-member relations and staff RBAC checks`

### PR-3 (Frontend + E2E Contract Alignment)
Include commits from Tasks 9-12.

Suggested PR title:
`refactor(fe): migrate employee UI/routes/tests to staff-member contract`

### PR-4 (Docs + Stabilization)
Include commits from Tasks 13-14.

Suggested PR title:
`docs/chore: finalize staff-member terminology and stability sweep`

---

## Acceptance Criteria (Binary)

1. `RoleSeeder` and DB data no longer use role name `employee`; canonical role is `staff`.
2. Permission namespace `employee-*` is replaced by `staff-member-*` in backend + frontend guards/tests.
3. Backend staff-member API endpoints are live at `/api/v1/staff-members*`.
4. Frontend admin routes are live at `/admin/staff-members*` with route names `admin.staffMembers*`.
5. Core profile classes and repository contracts use `StaffMemberProfile*` naming.
6. Backend tests pass (`composer test`).
7. Frontend tests/build pass (`bun run test`, `bun run build`).
8. No critical runtime references to old employee naming remain in application code.

---

Plan complete and saved to `docs/plans/2026-04-21-employee-to-staff-member-rename-implementation-plan.md`.
Next step: use the **executing-plans** skill to execute this plan task-by-task in single-flow mode.
