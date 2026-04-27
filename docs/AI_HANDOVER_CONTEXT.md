# AI Context Handover: Payroll, Attendance & Performance Notifications

**Date:** 2026-04-28
**Context:** This file was generated to transfer context to a new AI Agent/IDE session. It summarizes all recent structural changes, closed gaps, and the current state of the `team-sync` application.

**Dear AI Agent reading this:** Please read this entire document to understand the latest architectural state, recently modified files, and business logic decisions made before your session started.

---

## 1. Project Current State
- **Backend Stack:** Laravel 10, Pest/PHPUnit, Sanctum, Spatie Permissions.
- **Frontend Stack:** Vue 3 (Composition API), Pinia, Vue Router.
- **Test Integrity:** 100% Passing. (436 BE tests, 211 FE tests).

## 2. Recently Completed Scope (Phase A - F)
We just completed a massive alignment between the Backend, Frontend, and the Business Specs. 

### A. Payroll & Attendance Parity
- **Missing Controllers Implemented:** `AttendancePolicyController`, `LeaveEntitlementController`, `PayrollAdjustmentController`.
- **Frontend Wiring:** `AttendanceSettings.vue` now uses live endpoints instead of hardcoded mocks.
- **Period Lifecycle:** `AttendancePeriodController` now strictly enforces state transitions (`open` -> `review` -> `locked`).

### B. Analytics Module
- Added 7 new endpoints to `AnalyticsController` for HR dashboards (e.g., Workforce Demographics, Leave Turnaround Time).
- Data fetching logic is encapsulated in `AnalyticsRepository`.

### C. Performance Notifications (DB-Only)
- All performance notifications are strictly **Database Channel Only** (no emails).
- We created 7 Notification Classes in `team-sync-be/app/Notifications/Performance/`:
  1. `FeedbackReceived`
  2. `GoalAssigned`
  3. `GoalDeadlineApproaching` (Triggered via Console Command `NotifyGoalDeadlines`)
  4. `ReviewCycleStarted`
  5. `ReviewSubmittedForManager`
  6. `ReviewSubmittedForCalibration`
  7. `ReviewCalibrated`
  8. `GoalProgressUpdated` (Newly created).

### D. Notification Wiring & Deep Links
- All notification `action_url` paths strictly map to Frontend Vue Router paths under the `/admin/...` prefix (e.g., `/admin/performance/goals/{id}`).
- Dispatches are wired directly inside the controllers:
  - `PerformanceReviewCycleController::generateReviews()`
  - `PerformanceReviewController::submitSelfAssessment()`, `submitManagerAssessment()`, `calibrateReview()`
  - `PerformanceGoalController::addProgressUpdate()`

## 3. Key Files to Know (Recently Modified)
If you need to work on related features, these are the files we just touched:
- **Routes:** `team-sync-be/routes/api.php`
- **Controllers:** 
  - `team-sync-be/app/Http/Controllers/PerformanceReviewController.php`
  - `team-sync-be/app/Http/Controllers/PerformanceGoalController.php`
  - `team-sync-be/app/Http/Controllers/AnalyticsController.php`
- **Tests:** 
  - `team-sync-be/tests/Feature/Performance/PerformanceNotificationTest.php`
  - `team-sync-be/tests/Feature/Analytics/AnalyticsEndpointGapTest.php`

## 4. Documentation Audit Resolution
All previous "gaps" between BE and FE documentation have been resolved. 
- Old gap documents like `payroll-attendance-frontend-gap.md` were moved to `docs/plans/archive/`.
- The active plans are in `docs/plans/on_going/`.

## 5. Instructions for the Next Task
1. You can trust that the current `main` branch is stable and fully tested.
2. If working on the Frontend, assume all endpoints mentioned above now exist and work.
3. If creating new features, follow the established pattern: `Controller` -> `Repository` -> `Feature Test` (TDD required).
