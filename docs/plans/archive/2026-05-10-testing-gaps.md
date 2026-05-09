# Testing Gaps Plan

> **Status:** PENDING

**Goal:** Close testing gaps found in audit — CI workflow for Pest, unit tests for critical services, FormRequest validation tests.

**Tech Stack:** Pest 4, PHPUnit, GitHub Actions

---

### Task 1: Add CI Workflow for Backend Tests (Pest)

**Current:** No GitHub Actions workflow runs `composer test`. Only frontend tests have CI.

**Approach:** Create `.github/workflows/be-tests.yml` that runs Pest on push/PR.

- [ ] Create workflow file
- [ ] Configure MySQL service container (or SQLite :memory:)
- [ ] Run migrations and seeders
- [ ] Run `composer test`

---

### Task 2: Add Unit Tests for Critical Services

**Untested services (16 of 25):**

| Service | Priority |
|---------|----------|
| `Payroll/OvertimeCalculationService` | HIGH |
| `Payroll/ThrCalculationService` | HIGH |
| `Attendance/AttendancePeriodService` | HIGH |
| `Attendance/AttendancePolicyMismatchLifecycleService` | MEDIUM |
| `Performance/FeedbackService` | MEDIUM |
| `Performance/GoalService` | MEDIUM |
| `Performance/PerformanceReviewService` | MEDIUM |
| `Performance/ReviewCycleService` | MEDIUM |
| `PayslipPdfService` | MEDIUM |
| `EmailService` | LOW |
| `LicenseService` | LOW |
| `MeetingService` | LOW |
| `OvertimeService` | LOW |
| `ThrService` | LOW |
| `PayrollActivityLogger` | LOW |
| `Analytics/DailyMetricsCalculator` | LOW |

**Approach:** Focus on HIGH priority first. Test edge cases and error handling.

- [ ] `OvertimeCalculationService` — test hours cap, negative values, concurrent requests
- [ ] `ThrCalculationService` — test proration, eligibility, tax calculation
- [ ] `AttendancePeriodService` — test lifecycle transitions, locking behavior
- [ ] MEDIUM priority services
- [ ] LOW priority services

---

### Task 3: Add Validation Tests for FormRequest Classes

**Current:** 0 of 73 FormRequest classes have dedicated validation tests.

**Approach:** Create parameterized tests that verify required fields, type constraints, and edge cases.

- [ ] Test `StaffMemberProfileStoreRequest` — required fields, valid enums
- [ ] Test `ProjectStoreRequest` — required fields, image validation
- [ ] Test `LeaveRequestStoreRequest` — date validation, balance check
- [ ] Test remaining FormRequest classes

---

### Task 4: Add Missing Frontend Tests

**Gaps:**
- `thr` store — no unit test (1 of 25 stores)
- 7 views — no smoke test
- 5 composables — no unit test

**Approach:** Follow existing test patterns in `src/tests/` and `src/stores/__tests__/`.

- [ ] Create `stores/__tests__/thr.test.js`
- [ ] Add smoke tests for 7 untested views
- [ ] Add unit tests for composables

---

### Task 5: Clean Up Unused Dependencies

**Files:**
- `team-sync-be/composer.json` — `teamtnt/laravel-scout-tntsearch-driver` unused
- `team-sync-be/app/Traits/` — empty directory

**Approach:** Remove unused package, delete empty directory.

- [ ] Remove `teamtnt/laravel-scout-tntsearch-driver` from composer.json
- [ ] Delete empty `Traits/` directory
- [ ] Verify no references to removed package
