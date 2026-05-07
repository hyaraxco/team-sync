# Team Sync Role Matrix Fix Plan

## Scope Note

- Repo already has many large unrelated changes/deletions outside this task (`.agent`, `.agents`, `_bmad`, many backend/frontend files). Do not clean, revert, or include them in this PRD task.
- `TODO.md` and `docs/project-context.md` were already untracked at session start; this update only tracks the final PRD handoff.
- Role dashboard/sidebar/settings alignment is now finalized as product requirements in `docs/requirements/2026-05-07-final-role-dashboard-sidebar-settings-prd.md` and should be implemented as a separate planned task because it touches RBAC, route guards, sidebar, dashboard widgets, backend scoping, analytics, settings, and tests.

## P0 — Security / Data Integrity

- [x] Force attendance check-in to authenticated staff profile; reject users without staff profile.
- [x] Force leave create to authenticated staff profile and initial `pending` status.
- [x] Fix performance feedback giver identity from `User.id` to `StaffMemberProfile.id` and ownership checks.
- [x] Add/adjust backend regression tests for forged attendance/leave and feedback ownership.

## P1 — Role Flow / FE-BE Contracts

- [x] Align finance payroll readiness/generation permission with intended payroll operations.
- [x] Fix meeting FE route guard vs backend list permission mismatch.
- [x] Fix leave/overtime Pinia pagination parsing for `PaginateResource`.
- [x] Fix notification retry button, notification `body` schema, and action URLs.
- [x] Ensure meeting queue handling is documented/configured.

## P2 — Hardening / UX Consistency

- [x] Hide payroll settings link unless user has `payroll-statistics`.
- [x] Guard/validate leave approve/reject flows with pending-state and entitlement checks.
- [x] Normalize/update role matrix tests where touched.
- [x] Run targeted backend and frontend tests.

## Deferred / Needs Product Decision

- [x] Decide whether THR needs stricter separation of duties (`thr-generate` vs `thr-approve` vs `thr-process`) — superseded by final strict least-privilege PRD: Finance owns THR generate/approve/process/finalize; HR is read-only readiness/context only if needed.
- [x] Add payroll adjustment approval audit columns (`approved_by`, `approved_at`) via migration after reviewing SQL.
- [x] Decide whether payroll PDF export should stay first-payslip export or become true bulk PDF/ZIP export — selected bulk ZIP for Finance/HR.
- [x] Scope analytics/dashboard/staff directory exposure by role if company-wide data is not intended for staff/finance/manager — finalized as a strict least-privilege PRD in `docs/requirements/2026-05-07-final-role-dashboard-sidebar-settings-prd.md`; earlier requirements capture was pruned during docs cleanup.

## Next Separate Implementation Task

- [x] Create an implementation plan from the final PRD before editing code. — Phase 0 output: `docs/plans/on_going/2026-05-07-role-dashboard-sidebar-settings-implementation-plan.md`.
- [x] Inventory current permissions, route guards, sidebar visibility, dashboard API calls, analytics endpoints, settings sections, and staff directory resources against the PRD matrix.
- [x] Resolve Phase 1 product decisions:
  - HR payroll readiness: YES (read-only, `payroll-readiness-view`).
  - Finance staff lookup: NO (payroll detail resource cukup).
  - Manager staff directory: DEFERRED (tanpa team-scoped API proper).
- [x] Phase 1A — Permission vocabulary & role seeders:
  - Added `payroll-readiness-view` permission.
  - Rewrote `RolePermissionSeeder`: Manager explicit allowlist, HR no payroll ops, Finance owns payroll/THR, staff self-service only.
  - Updated `PayrollController` middleware: readiness dashboard accepts `payroll-readiness-view`.
  - Updated 34 tests to match new role matrix. All 730 tests pass.
- [x] Update backend seeders/middleware/resources first, with role matrix and forbidden-access tests.
  - Added `analytics-hr-view`, `analytics-finance-view`, `analytics-performance-view`, `analytics-project-view` permissions.
  - Added `dashboard-hr-view` permission for company-wide dashboard stats.
  - Split AnalyticsController middleware by audience (HR/Finance/Manager/Performance/Project).
  - Scoped DashboardController: company stats → HR only, self-stats → all, team pulse → manager.
  - Added `tests/Feature/RoleForbiddenAccessTest.php` (20 test methods).
  - Updated `tests/Unit/RolePermissionMatrixTest.php` with audience permission tests.
  - Updated `tests/Feature/Analytics/AnalyticsEndpointGapTest.php` for Finance payroll endpoints.
  - All 762 tests pass.
- [x] Update frontend sidebar/router/dashboard/settings/analytics second, with role visibility tests.
  - Dashboard.vue: Staff → EmployeeStatistics only, Finance → PayrollAnalytics, Manager → TeamPulse + EmployeeStats, HR/Superadmin → full company-wide (gated by `dashboard-hr-view`).
  - AnalyticsDashboard.vue: Tabs gated by `analytics-hr-view`, `analytics-finance-view`, `analytics-performance-view`, `analytics-project-view`. Default tab auto-selects per role.
  - Sidebar/Router/Settings: Already correctly permission-gated, no changes needed.
  - Updated Dashboard smoke test to match new role-based rendering.
  - All 618 frontend tests pass.
- [x] Run focused backend, frontend, and E2E role-navigation verification.
  - Backend: 762 tests pass (3197 assertions).
  - Frontend: 618 tests pass (108 test files).
  - Role forbidden-access tests verify all boundaries.
  - Dashboard/Analytics smoke tests verify role-based rendering.

## Docs Cleanup Plan

- [x] Keep canonical requirement docs in `docs/requirements/` and do not merge them into historical plans.
- [x] Keep testing/runbook docs in `docs/testing/` and reference docs in `docs/references/`.
- [x] Move loose root planning markdown into `docs/plans/archive/` when duplicated or already represented by an on-going/archive doc.
- [x] Move `docs/plans/on_going/*` docs to `docs/plans/archive/` when their content says resolved, implemented, walkthrough/handover, audit completed, or superseded.
- [x] Keep genuinely not-yet-implemented/backlog/spec docs in `docs/plans/on_going/`.
- [x] Prune generated/duplicate/unrelated artifact folders after user selected recommended cleanup.
