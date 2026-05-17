# Phase 6 — Test Coverage Gap Closure (BE + FE)

**Status:** IN PROGRESS
**Date:** 2026-05-17
**Predecessor:** Phase 5 — `2026-05-17-fe-audit-phase-5-focus-states.md` (PR #34)
**Follows workflow:** root [`AGENTS.md`](../../AGENTS.md), [`team-sync-be/AGENTS.md`](../../team-sync-be/AGENTS.md), [`team-sync-fe/AGENTS.md`](../../team-sync-fe/AGENTS.md)

---

## Context

Coverage audit (m0450) identified pre-existing test gaps not introduced by recent feature work. Goal of this phase: close high/medium-confidence gaps so every shipped controller, notification, and user-facing view has a baseline test.

Recent feature commits all included tests. **No new test debt.** This phase is hardening only.

---

## Scope

### Backend gaps (5 items)

| Target | Test home | Pattern |
|---|---|---|
| `OptionController` (14 endpoints) | new `tests/Feature/Options/OptionControllerTest.php` | Permission guard + smoke `200 OK + data shape` per endpoint |
| `AnalyticsExportController::exportExcel` | new `tests/Feature/Analytics/AnalyticsExportControllerTest.php` | Permission guard + content-type + filename |
| `AnalyticsExportController::exportPdf` | same file | Permission guard + content-type + filename |
| `AttendanceCorrectionNeedsApproval` | extend `tests/Unit/Services/EmailServiceTest.php` | `Notification::assertSentTo(...class)` pattern (matches existing `AttendanceCorrectionApproved` test at line 732) |
| `AttendanceCorrectionRejected` | same file | same pattern |
| `TeamLeadChanged` | same file | same pattern (call via `EmailService::sendTeamLeadChangedNotification`) |

### Frontend gaps (4 medium-risk views)

| View | Test home | Pattern |
|---|---|---|
| `views/admin/payroll/PayrollComparison.vue` | new `src/tests/admin/payroll/PayrollComparison.smoke.test.js` | Mount with payroll store mock, permission gate, render assertions |
| `views/admin/payroll/PayrollApprovalMatrix.vue` | new `src/tests/admin/payroll/PayrollApprovalMatrix.smoke.test.js` | same |
| `views/staff-member/MyOvertime.vue` | new `src/tests/staff-member/MyOvertime.smoke.test.js` | Mount with overtime store mock, list rendering |
| `views/setup/SetupWizard.vue` | new `src/tests/views/setup/SetupWizard.smoke.test.js` | Mount with setup store mock, step navigation |

**Out of scope** (intentionally deferred — low risk/no logic):
- `UpgradePlan.vue` — static marketing page, only `toast.info()` placeholder
- `VerifyEmailResult.vue` — minimal logic, query string handling only
- `NotFound.vue` — pure JSX, no behavior
- `OptionController` is a thin wrapper — repository-level tests already exist for `OptionRepository`. We add **route-level smoke** only (auth + 200), not exhaustive payload validation.
- 3 partially-covered notifications (`AttendanceMismatchRequiresReview`, `AttendanceMismatchStatusChanged`, `ProjectTaskCollaborationUpdated`, `ProjectTaskStatusChanged`) — already covered indirectly via service-layer mocks. Adding direct `assertSentTo` is low ROI.

---

## Architecture & Conventions

Follow existing patterns. No new abstractions.

### Backend (per `team-sync-be/AGENTS.md`)
- Pest tests in `tests/Feature/` (integration) and `tests/Unit/` (unit)
- 4-space indentation
- Use `RefreshDatabase` + license seeding (`ActivatesLicense` trait + `RoleSeeder`/`PermissionSeeder`/`RolePermissionSeeder`)
- Use `Sanctum::actingAs(...)` or existing `actingAsRole(...)` helper
- `Notification::fake()` before service calls, `Notification::assertSentTo(...)` after
- Run via `composer test` (auto-clears config) — never `./vendor/bin/pest` directly

### Frontend (per `team-sync-fe/AGENTS.md`)
- Vitest + jsdom in `src/tests/{role}/...`
- 4-space indentation, ES Modules
- Mock the Pinia store, the `useToast` composable, vue-router (`createRouter`/`createWebHistory`/`useRouter`/`useRoute`), and `permissionHelper` — match existing `PayrollDashboard.smoke.test.js` template
- Stub heavy children (`MainCard`, `StatsCard`, `ModalWrapper`, `VueApexCharts`, `RouterLink`, `Alert`, `StatusBadge`)
- Follow naming: `{ViewName}.smoke.test.js`
- Run via `bun run test` (Vitest)

---

## Phase split / PR strategy

Two independent PRs (matches Phases 1–5 cadence; CI pipelines split BE/FE):

| PR | Branch | Scope |
|---|---|---|
| **PR-A: BE** | `feat/test-coverage-be` | OptionController + AnalyticsExport tests + 3 notification assertions |
| **PR-B: FE** | `feat/test-coverage-fe` | 4 view smoke tests |

Each PR: branch from `main`, squash to 1 commit (`chore(be): close controller + notification test gaps`, `chore(fe): close view smoke test gaps`), push, wait CI, rebase & merge.

---

## File Structure

### PR-A (BE)
- **Create:** `team-sync-be/tests/Feature/Options/OptionControllerTest.php`
- **Create:** `team-sync-be/tests/Feature/Analytics/AnalyticsExportControllerTest.php`
- **Modify:** `team-sync-be/tests/Unit/Services/EmailServiceTest.php` — append 3 new `it(...)` blocks (one per notification class)

### PR-B (FE)
- **Create:** `team-sync-fe/src/tests/admin/payroll/PayrollComparison.smoke.test.js`
- **Create:** `team-sync-fe/src/tests/admin/payroll/PayrollApprovalMatrix.smoke.test.js`
- **Create:** `team-sync-fe/src/tests/staff-member/MyOvertime.smoke.test.js`
- **Create:** `team-sync-fe/src/tests/views/setup/SetupWizard.smoke.test.js`

---

## Tasks

### PR-A: Backend test coverage

- [ ] **A1. OptionController feature test** (new file: `tests/Feature/Options/OptionControllerTest.php`)
  - Setup: `RefreshDatabase`, seed Roles/Permissions, `activateTestLicense()`, `Sanctum::actingAs($user)` (any authenticated user — option endpoints have no permission requirement, just `auth:sanctum`)
  - One test per endpoint (14 total) following structure:
    ```php
    public function test_returns_department_options_for_authenticated_user(): void
    {
        $this->actingAsRole('staff');
        $this->getJson('/api/v1/options/departments')
            ->assertOk()
            ->assertJsonStructure(['success', 'message', 'data']);
    }
    ```
  - One test for unauthenticated: `assertUnauthorized()` on `options/departments`
  - Asserts: response shape only (we trust `OptionRepository` correctness — already unit-tested elsewhere)

- [ ] **A2. AnalyticsExportController feature test** (new file: `tests/Feature/Analytics/AnalyticsExportControllerTest.php`)
  - Setup: same as above, plus assign `analytics-export` permission to a user
  - 6 tests:
    1. Unauthenticated → `assertUnauthorized()` on `/api/v1/analytics/export/excel`
    2. User without `analytics-export` permission → `assertForbidden()`
    3. Excel export with `tab=executive` → status 200, header `Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet`, filename starts with `analytics-executive-`
    4. Excel export with invalid tab (`tab=foo`) → still 200 with empty workbook (matches default branch in match expression)
    5. PDF export with `tab=workforce` → status 200, header `Content-Type: application/pdf`, filename ends with `.pdf`
    6. Excel export with mocked repository throwing → 500 with `success:false` payload
  - Mock `AnalyticsRepositoryInterface` for tests 3–6 to avoid heavy data setup; bind a stub via `$this->app->bind(AnalyticsRepositoryInterface::class, fn() => $stub)`

- [ ] **A3. Three new notification assertions in `EmailServiceTest.php`**

  Append 3 `it(...)` blocks at end of file before the final closing brace. Pattern matches existing `sendAttendanceCorrectionApprovedNotification` test (lines 727-752):

  ```php
  /*
  |--------------------------------------------------------------------------
  | sendAttendanceCorrectionNeedsApprovalNotification
  |--------------------------------------------------------------------------
  */
  it('sends attendance correction needs approval notification to approver', function () {
      // setup: staff requester + manager approver + correction record
      // call: $this->service->sendAttendanceCorrectionNeedsApprovalNotification($correction)
      // assert: Notification::assertSentTo($manager, AttendanceCorrectionNeedsApproval::class)
  });
  ```

  Same shape for `AttendanceCorrectionRejected` and `TeamLeadChanged`.

  For `TeamLeadChanged`: call `$this->service->sendTeamLeadChangedNotification($team, $oldLeadUser, $newLeadUser)` per `EmailService.php:397`.

- [ ] **A4. Run `composer test`**
  - Expected: all 1481 existing tests still pass + new tests added (≈ 14 + 6 + 3 = 23 new tests = 1504 total)
  - Run from `team-sync-be/`

- [ ] **A5. Run Pint formatter**
  - `./vendor/bin/pint`

- [ ] **A6. Squash commit + push + create PR**
  - Branch: `feat/test-coverage-be` off `main`
  - Commit message: `chore(be): close controller + notification test gaps`
  - PR title: `chore(be): close controller + notification test gaps`
  - Body: list closed gaps from this plan; reference Phase 6 audit

### PR-B: Frontend test coverage

- [ ] **B1. PayrollComparison smoke test** (new file: `src/tests/admin/payroll/PayrollComparison.smoke.test.js`)
  - Read `src/views/admin/payroll/PayrollComparison.vue` first to identify imports/stores/permissions
  - Mock: `@/stores/payroll` (relevant actions/state), `@/helpers/permissionHelper` (`can`), `@/composables/useToast`, `pinia` (storeToRefs), `vue-router` (`useRouter`, `useRoute`, `createRouter`, `createWebHistory`)
  - Stubs: `MainCard`, `StatsCard`, `RouterLink`, `Alert`, `StatusBadge`, `VueApexCharts`, `ModalWrapper` (whatever the view uses)
  - Tests: ≥3 — render guard for permission, store action invoked on mount, basic UI text rendering

- [ ] **B2. PayrollApprovalMatrix smoke test** (new file: `src/tests/admin/payroll/PayrollApprovalMatrix.smoke.test.js`)
  - Same approach as B1 — read view, mock store + helpers + router, ≥3 tests

- [ ] **B3. MyOvertime smoke test** (new file: `src/tests/staff-member/MyOvertime.smoke.test.js`)
  - Read `src/views/staff-member/MyOvertime.vue`
  - Mock: `@/stores/overtime`, helpers, router, composables
  - Tests: ≥3 — overtime list rendering, empty state, store fetch on mount

- [ ] **B4. SetupWizard smoke test** (new file: `src/tests/views/setup/SetupWizard.smoke.test.js`)
  - Read `src/views/setup/SetupWizard.vue`
  - Mock: `@/stores/setup`, router, composables
  - Tests: ≥3 — step indicator, navigation between steps, terminal state submission

- [ ] **B5. Run `bun run test`**
  - Expected: all 991 existing pass + new tests added (≈ 12 new tests = ~1003 total)

- [ ] **B6. Run `bun run build`** — verify nothing broke

- [ ] **B7. Squash commit + push + create PR**
  - Branch: `feat/test-coverage-fe` off `main`
  - Commit message: `chore(fe): close view smoke test gaps`
  - PR title: `chore(fe): close view smoke test gaps`

---

## Verification per AGENTS.md "verify semua test suites"

| Suite | Command | When |
|---|---|---|
| BE Pest | `composer test` | PR-A only (no FE changes) |
| FE Vitest | `bun run test` | PR-B only |
| FE Build | `bun run build` | PR-B only |
| FE a11y | bundled in CI `fe-tests` job | PR-B (CI) |
| FE Screenshots | CI `pr-screenshots` | PR-B (CI) |
| E2E | `bun run e2e` | Skip — pure unit/feature tests, no behavior change |

CI gates per `.github/workflows/`:
- `be-tests.yml` blocks merge until `composer test` passes (PR-A)
- `fe-tests.yml` blocks merge until `bun run test` + `bun run build` + `bun run test:a11y` pass (PR-B)
- `pr-screenshots.yml` runs on FE changes (PR-B)

---

## Acceptance Criteria

- [ ] BE: `composer test` exit 0 with ≥ 1504 tests
- [ ] FE: `bun run test` exit 0 with ≥ 1003 tests
- [ ] FE: `bun run build` succeeds
- [ ] PR-A merged to main
- [ ] PR-B merged to main
- [ ] Plan archived to `docs/plans/archive/2026-05-17-fe-be-test-coverage-gap.md` with status COMPLETED + PR refs

---

## Out of Scope (Future)

- 5 untested "Enhanced" analytics components (`WorkforceAnalyticsEnhanced`, etc.) — large surface, deserves own plan
- Reusable form primitives (`Input`, `Select`, `TextArea`, `ConfirmationModal`) — high test value but not the gap surfaced in audit
- 4 partially-covered notifications — indirect coverage acceptable
- Loose components without tests (~20 dashboard widgets) — backlog item

---

## Status Updates

- 2026-05-17: Plan created. Audit (m0450) identified gaps. Skipping party-mode per AGENTS.md (mechanical test addition, not a design decision).
