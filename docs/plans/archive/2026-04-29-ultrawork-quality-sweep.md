# Ultrawork Quality Sweep Plan

> Date: 2026-04-29
> Status: EXECUTED
> Source: Metis Plan Consultant

## Scope

Complete all remaining test coverage gaps and security hardening identified by codebase scan.

## Wave 1-2: Store Unit Tests + BE HasMiddleware

- [x] staffMember.test.js
- [x] project.test.js
- [x] auth.test.js
- [x] leaveRequest.test.js
- [x] task.test.js
- [x] performanceGoal.test.js
- [x] performanceFeedback.test.js
- [x] analytics.test.js
- [x] dashboard.test.js
- [x] notifications.test.js
- [x] attendanceCorrection.test.js
- [x] attendancePeriod.test.js
- [x] holidayCalendar.test.js
- [x] hybridSchedule.test.js
- [x] option.test.js
- [x] HasMiddleware: AttendancePeriodController
- [x] HasMiddleware: HolidayCalendarController
- [x] HasMiddleware: HybridWorkScheduleController

## Wave 3-5: View Smoke Tests

- [x] StaffMemberList.smoke.test.js
- [x] StaffMemberDetail.smoke.test.js
- [x] StaffMemberEdit.smoke.test.js
- [x] TeamList.smoke.test.js
- [x] TeamCreate.smoke.test.js
- [x] TeamEdit.smoke.test.js
- [x] ProjectList.smoke.test.js
- [x] Dashboard.smoke.test.js
- [x] ProjectCreate.smoke.test.js
- [x] ProjectEdit.smoke.test.js
- [x] AttendanceList.smoke.test.js
- [x] AttendanceRecordList.smoke.test.js
- [x] ReviewCycleDetail.smoke.test.js
- [x] TeamReviews.smoke.test.js
- [x] AnalyticsDashboard.smoke.test.js
- [x] Login.smoke.test.js
- [x] ForgotPassword.smoke.test.js
- [x] ResetPassword.smoke.test.js
- [x] StaffMemberSuccess.smoke.test.js
- [x] StaffMemberProfileEdit.smoke.test.js

## Skip List

- NotFound.vue (17 lines, zero logic)
- VerifyEmailResult.vue (44 lines, trivial redirect)
- NotificationController HasMiddleware (user-scoped, no admin actions)
- OptionController HasMiddleware (read-only enums, would break dropdowns)
- HybridScheduleOverrideController HasMiddleware (already route-guarded)

## Bug Fixes (reactive)

- [x] HR forbidden on review detail (BE auth expansion)
- [x] Assign reviewer modal clipping (Teleport to body)
- [x] Self-review prevention in reviewer dropdown
- [x] Staff assignee visibility guard in TaskDetailModal

## QA Scenarios

### Wave 1-2: Store Tests + HasMiddleware
- `cd team-sync-fe && bun run test --run -- src/stores/__tests__/` → all 21 store test files pass
- `cd team-sync-be && docker compose exec -T web php artisan test --filter="HasMiddleware|Attendance|Holiday|Hybrid"` → 0 failures
- Verify: `grep -c "implements HasMiddleware" team-sync-be/app/Http/Controllers/AttendancePeriodController.php` → 1
- Verify: `grep -c "implements HasMiddleware" team-sync-be/app/Http/Controllers/HolidayCalendarController.php` → 1
- Verify: `grep -c "implements HasMiddleware" team-sync-be/app/Http/Controllers/HybridWorkScheduleController.php` → 1

### Wave 3-5: View Smoke Tests
- `cd team-sync-fe && bun run test --run -- src/tests/admin/ src/tests/auth/ src/tests/staff-member/` → all 20 new smoke test files pass
- Each test verifies: renders without crashing, calls fetch on mount, one interaction

### Bug Fixes
- HR forbidden: `curl -H "Authorization: Bearer {hr_token}" http://localhost:8000/api/v1/performance/reviews/7` → 200 (not 403)
- Assign reviewer modal: open modal from Review Cycle detail → modal renders above table, not clipped
- Self-review: reviewer dropdown excludes the reviewee from options
- Staff assignee: login as staff → open task detail → no "Change assignee" or X button visible

### Global Verification
- `cd team-sync-fe && bun run test --run` → 92 files, 497 tests, 0 failures
- `cd team-sync-be && docker compose exec -T web php artisan test` → 429 tests, 0 failures
- `cd team-sync-fe && bun run e2e` → 15+ passed (known flaky: notification queue timing, payroll queue timing)
- Store test coverage: 21/21 (100%)
- View smoke coverage: 54/56 (96%)
