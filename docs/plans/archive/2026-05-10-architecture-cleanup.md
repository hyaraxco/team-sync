# Architecture Cleanup Plan

> **Status:** PENDING

**Goal:** Fix architecture violations found in audit — Eloquent in controllers, inline validation, duplicated logic, unfiltered request data.

**Tech Stack:** Laravel 12, PHP 8.2, Pest 4

---

### Task 1: Move Eloquent Calls from Controllers to Repositories

**Violations (19 instances):**

| Controller | Lines | Issue |
|-----------|-------|-------|
| `ProjectTaskController.php` | 278, 317, 405 | `ProjectTaskComment::where()`, `ProjectTaskAttachment::where()` |
| `ProjectController.php` | 141, 168, 215 | `Project::findOrFail()` |
| `StaffMemberProfileController.php` | 133, 141 | `User::query()`, `StaffMemberProfile::query()` |
| `PayrollController.php` | 504, 537 | `Payroll::findOrFail()` |
| `PerformanceReviewCycleController.php` | 80, 85, 90 | `StaffMemberProfile`, `PerformanceReviewTemplate` |
| `PerformanceReviewController.php` | 135, 237, 267 | `User::role()`, `PerformanceGoal`, `PerformanceFeedback` |
| `HolidayCalendarController.php` | 35 | `HolidayCalendar::create()` |
| `HybridScheduleOverrideController.php` | 25 | `HybridScheduleOverride::create()` (already fixed in security hardening) |
| `SetupController.php` | 134 | `User::create()` |

**Approach:** For each violation, extract the Eloquent call to the corresponding repository method. If no repository exists, create one.

- [ ] Move `ProjectTaskComment` and `ProjectTaskAttachment` queries to `ProjectTaskRepository`
- [ ] Move `Project::findOrFail()` to `ProjectRepository`
- [ ] Move `User::query()` and `StaffMemberProfile::query()` to `StaffMemberProfileRepository`
- [ ] Move `Payroll::findOrFail()` to `PayrollRepository`
- [ ] Move performance-related queries to their respective repositories
- [ ] Move `HolidayCalendar::create()` to `HolidayCalendarRepository`
- [ ] Verify `SetupController` — `User::create()` may be acceptable for bootstrap

---

### Task 2: Convert Inline Validation to FormRequest Classes

**Violations (28 instances):**

| Controller | Count |
|-----------|-------|
| `PayrollController.php` | 14 |
| `HybridScheduleOverrideController.php` | 2 (fixed) |
| `PerformanceReviewTemplateController.php` | 2 |
| `TeamController.php` | 1 |
| `ThrPayrollController.php` | 1 |
| `PayrollAdjustmentController.php` | 1 |
| `PayrollSettingController.php` | 1 |
| `AttendancePeriodController.php` | 1 |
| `PayslipController.php` | 1 |
| `ProjectTaskController.php` | 1 |
| `ProjectController.php` | 1 |
| `MeetingController.php` | 2 |

**Approach:** Create FormRequest classes for each inline validation. Follow existing pattern in `app/Http/Requests/`.

- [ ] Create FormRequest for each PayrollController inline validation
- [ ] Create FormRequest for remaining controllers
- [ ] Replace `$request->validate([...])` with FormRequest type hints

---

### Task 3: Extract Shared `isProjectMember()` Logic

**Duplicated in 3 places:**
- `app/Http/Middleware/EnsureProjectMembership.php`
- `app/Policies/ProjectPolicy.php`
- `app/Policies/ProjectTaskPolicy.php`

**Approach:** Extract to `app/Services/ProjectMembershipService.php` or a trait. All three locations call the same logic.

- [ ] Create `ProjectMembershipService` with `isMember(User $user, Project $project): bool`
- [ ] Update middleware, ProjectPolicy, ProjectTaskPolicy to use the service

---

### Task 4: Filter `$request->all()` Before Passing to Repositories

**Files:**
- `PerformanceFeedbackController.php:42,50`
- `PerformanceGoalController.php:49,56`
- `PerformanceReviewController.php:58,65,151`
- `PerformanceReviewCycleController.php:35`

**Approach:** Create FormRequest classes with explicit filter rules, or use `$request->only([...])` with whitelisted keys.

- [ ] Create `PerformanceFeedbackFilterRequest`
- [ ] Create `PerformanceGoalFilterRequest`
- [ ] Create `PerformanceReviewFilterRequest`
- [ ] Create `PerformanceReviewCycleFilterRequest`
