# Implementation Gap Closure Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `subagent-driven-development` (recommended) or `executing-plans` to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Close highest-value unimplemented/incomplete areas found after 2026-05-17 UI/UX/test pass.

**Architecture:** Start with verified backend contract cleanup, then address largest legacy validation debt, then run narrow FE/design-system hardening. All implementation must follow root + sub-repo `AGENTS.md`: Controller → Service → Repository → Interface, JsonResource responses, FormRequest validation, Pinia-only API calls.

**Tech Stack:** Laravel 12 + Pest + Pint; Vue 3 + Pinia + Vitest + Bun; Tailwind design system.

---

## Discovery Summary

### Critical / High-Value Gaps

1. **BE attendance admin controllers return raw JSON**
   - `team-sync-be/app/Http/Controllers/HolidayCalendarController.php`
   - `team-sync-be/app/Http/Controllers/AttendancePeriodController.php`
   - `team-sync-be/app/Http/Controllers/HybridWorkScheduleController.php`
   - Missing Resources:
      - `AttendancePeriodResource`
      - `HolidayCalendarResource`
      - `HybridWorkScheduleResource`

2. **PayrollController largest backend debt**
   - `team-sync-be/app/Http/Controllers/PayrollController.php`
   - ~870 lines, direct `PayrollRepositoryInterface` injection, many inline `$request->validate()` calls.
   - Next action should be staged: FormRequests first, then PayrollService extraction.

3. **FE smoke/a11y/design-system debt remains but discovery changed after party-mode review**
   - Policy mismatch Pinia actions already exist in `team-sync-fe/src/stores/attendance.js`; do not re-implement.
   - Project smoke tests already exist; audit before adding coverage.
   - Skip-nav links already exist in `Admin.vue` and `Auth.vue`.
   - Common form primitives already support `aria-describedby`; remaining gap is inline forms/views not using primitives.
   - Real design-system issues found: `Input.vue` uses `rounded-xl` instead of input `rounded-2xl`; `TextArea.vue` contains inline color styles.

4. **Staff member ID rename audit needed**
   - Product/DB terminology is now `staff_member_id`, not `employee_id` / “Employee ID”.
   - Task 1 Resources preserve `staff_member_id` fields where applicable.
   - Follow-up scan found likely live FE leftovers:
      - `team-sync-fe/src/views/staff-member/StaffMemberProfile.vue` copy: “Employee ID”
      - `team-sync-fe/src/views/staff-member/PayslipDetail.vue` copy: “Employee ID”
      - `team-sync-fe/src/views/admin/attendance/OvertimeManagement.vue` copy: “Employee ID”
      - `team-sync-fe/src/views/admin/performance/ReviewDetail.vue` code references `review.value?.employee_id`
      - `team-sync-fe/src/tests/admin/attendance/OvertimeManagement.smoke.test.js` expects “Employee ID”
   - Legacy/archive docs and rename migrations still contain `employee_id`; do not edit old migrations. Treat docs/archive as historical unless user requests docs cleanup.

---

## Workload Order

### Task 1: Add Resources for Raw Attendance Admin Responses — COMPLETED 2026-05-18

**Files:**
- Created: `team-sync-be/app/Http/Resources/AttendancePeriodResource.php`
- Created: `team-sync-be/app/Http/Resources/HolidayCalendarResource.php`
- Created: `team-sync-be/app/Http/Resources/HybridWorkScheduleResource.php`
- Modified: `team-sync-be/app/Http/Controllers/AttendancePeriodController.php`
- Modified: `team-sync-be/app/Http/Controllers/HolidayCalendarController.php`
- Modified: `team-sync-be/app/Http/Controllers/HybridWorkScheduleController.php`
- Modified tests:
  - `team-sync-be/tests/Feature/AttendancePeriodTest.php`
  - `team-sync-be/tests/Feature/HolidayCalendarTest.php`
  - `team-sync-be/tests/Feature/HybridWorkScheduleTest.php`

- [x] Read `team-sync-be/AGENTS.md`.
- [x] Inspect existing Resource naming/shape patterns.
- [x] Run baseline: `php artisan config:clear --ansi && php artisan test --filter="AttendancePeriod|HolidayCalendar|HybridWorkSchedule"`.
- [x] Inspect FE stores/views that consume these endpoints before changing response shape.
- [x] Write failing Feature tests asserting JSON resource date fields for index/show/store/update endpoints.
- [x] Implement Resource classes with stable API fields used by FE.
- [x] Replace raw model payloads with Resource / Resource-transformed paginator collections where appropriate.
- [x] Keep status codes/messages compatible.
- [x] Run targeted BE tests.
- [x] Run `composer test`.
- [x] Run `./vendor/bin/pint`.

**Implementation notes:**
- Resource date fields now emit `Y-m-d` strings for FE compatibility instead of raw Laravel ISO datetimes.
- Existing top-level JSON contract (`success`, `message`, `data`) preserved.
- Paginator envelope preserved as `data.data`, `data.current_page`, `data.total`, etc.; only items are resource-transformed.
- `HybridWorkScheduleController::mySchedule()` now returns `null` data when no schedule exists, preserving previous semantic.

**Verification evidence:**
- RED: targeted tests failed on raw ISO date strings before Resources.
- GREEN: `php artisan config:clear --ansi && php artisan test tests/Feature/AttendancePeriodTest.php tests/Feature/HolidayCalendarTest.php tests/Feature/HybridWorkScheduleTest.php` → 15 warnings, 78 assertions, 0 failures.
- FULL BE: `composer test` → 99 passed, 1405 warnings, 5520 assertions, 0 failures.
- Format: `./vendor/bin/pint` → pass.

**Acceptance:** No raw model response in those controllers; Resource tests pass; FE contract preserved.

### Task 2: Extract Payroll FormRequests First

**Files:**
- Modify: `team-sync-be/app/Http/Controllers/PayrollController.php`
- Create FormRequests under: `team-sync-be/app/Http/Requests/Payroll/`
- Test: existing payroll Feature tests; add coverage for validation failures where missing.

- [ ] Read `team-sync-be/AGENTS.md` and payroll domain rules in root `AGENTS.md`.
- [ ] Run baseline payroll tests and record pass count: `composer test -- --filter=Payroll` or `php artisan config:clear --ansi && php artisan test --filter=Payroll`.
- [ ] Inventory every `$request->validate()` in `PayrollController.php`.
- [ ] Group validations by endpoint; avoid premature service refactor in same task.
- [ ] Create dedicated FormRequest classes with `authorize(): bool` returning true when route permission middleware owns access.
- [ ] Replace inline validations with typed FormRequest injection.
- [ ] Preserve validated key names and date/month formats exactly.
- [ ] Run targeted payroll Feature tests.
- [ ] Run `composer test` if targeted suite passes.
- [ ] Run `./vendor/bin/pint`.

**Acceptance:** `PayrollController.php` has no inline `$request->validate()`; behavior unchanged.

### Task 3: Plan PayrollService Extraction Separately

**Files:**
- Create follow-up plan in `docs/plans/on_going/` after Task 2 passes.
- Scope likely files:
  - `team-sync-be/app/Services/Payroll/PayrollService.php` or existing equivalent
  - `team-sync-be/app/Http/Controllers/PayrollController.php`
  - `team-sync-be/app/Repositories/PayrollRepository.php`
  - `team-sync-be/app/Interfaces/PayrollRepositoryInterface.php`

- [ ] Ask @oracle for architecture review before extraction.
- [ ] Identify controller methods that are pure orchestration vs business rules.
- [ ] Move business logic to service in small batches.
- [ ] Preserve state-machine guards: locked attendance before generation, reconciliation before paid.
- [ ] Add/adjust tests per moved behavior.

**Acceptance:** Separate reviewed plan exists before implementation; no big-bang payroll refactor.

**Progress update (2026-05-20):** PR 1/4 `PayrollAnalyticsService` merged on `main`. PR 2/4 `PayrollGenerationService` implemented on branch `chore/payroll-generation-service` with thin delegation only: new `PayrollGenerationService`, `GeneratePayrollJob` method-injection swap, and `PayrollController` wiring for `generate`, `generateReadiness`, `readinessDashboard`, `readinessTeamSummary`. `PayrollRepositoryInterface` remains unchanged. Pending: push branch, open PR, wait for CI/review.

### Task 4: Audit and Strengthen Project View Smoke Tests

**Files:**
- Test: existing `team-sync-fe/src/**/ProjectList.smoke.test.js`
- Test: existing `team-sync-fe/src/**/ProjectCreate.smoke.test.js`
- Test: existing `team-sync-fe/src/**/ProjectEdit.smoke.test.js`
- Test: existing `team-sync-fe/src/**/ProjectDetail.smoke.test.js`

- [ ] Read existing project tests and router/store mocks.
- [ ] Audit existing smoke coverage before writing new tests.
- [ ] Add only missing high-value assertions: loading, empty/error states, primary actions.
- [ ] Mock Pinia stores; do not hit network.
- [ ] Use existing test helper patterns.
- [ ] Run targeted tests.
- [ ] Run `bun run test`.

**Acceptance:** Existing project smoke tests cover render + primary UX states without redundant cases.

### Task 5: Focused FE Accessibility and Design-System Micro-Pass

**Files:**
- Modify: `team-sync-fe/src/components/common/form/Input.vue`
- Modify: `team-sync-fe/src/components/common/form/TextArea.vue`
- Modify highest-traffic inline forms discovered by audit only if they do not use common form primitives.
- Test: add/update component/view tests for form semantics if existing coverage is missing.

- [ ] Load `accessibility` and `design-taste-frontend` before edits.
- [ ] Do not modify skip links unless tests prove regression; `Admin.vue` and `Auth.vue` already have them.
- [ ] Change `Input.vue` input radius from `rounded-xl` to design-system `rounded-2xl`.
- [ ] Convert `TextArea.vue` inline color styles to Tailwind/design tokens.
- [ ] Audit inline forms; apply `aria-describedby` only to touched fields with visible error messages.
- [ ] Add `aria-live` for dynamic status if already touching affected views.
- [ ] Verify `bun run test:a11y` exists. If yes, run build then `bun run test:a11y`; otherwise run targeted Vitest + `bun run build`.

**Acceptance:** No broad churn; common form primitives match design-system tokens; touched inline forms have explicit error semantics.

**Implementation status (2026-05-18):**
- ✅ `Input.vue` radius: `rounded-xl` → `rounded-2xl` (SHA `4ed1ee7`)
- ✅ `Input.vue` added `max` prop for native forwarding (SHA `6d66158`)
- ✅ `TextArea.vue` inline color styles → Tailwind tokens (`text-gray-600`, `bg-white`, `text-red-600`, `text-sm`, `font-semibold`, `font-normal`) (SHA `7ac8e7f`)
- ✅ `AnimatedValue.vue` added `tabular-nums` class (SHA `abede64`)
- ✅ Financial views (`MyPayslips.vue`, `PayslipDetail.vue`, `ThrManagement.vue`) — `tabular-nums` on all currency/number displays (SHA `8d5e77b`)
- ✅ `PolicyMismatches.vue` dev copy → user-friendly error message (SHA `a0bf53b`)
- ✅ `AttendanceSettings.vue` migrated 13 inputs + 2 selects across 3 modals to common Input/Select primitives. Time inputs kept native (picker chrome). Checkboxes deferred. Numeric coercion added at submit time. (SHA `e948a17`, `0ca1270`)
- ⏭️ Step 8 (manual visual check) skipped — non-interactive worker
- Tests: 139 files, 1025 tests, 0 failures. Build passes.
- Branch: `chore/accessibility-micro-pass`

### Task 6: Staff Member ID Terminology Audit

**Files:**
- Inspect/modify: `team-sync-fe/src/views/staff-member/StaffMemberProfile.vue`
- Inspect/modify: `team-sync-fe/src/views/staff-member/PayslipDetail.vue`
- Inspect/modify: `team-sync-fe/src/views/admin/attendance/OvertimeManagement.vue`
- Inspect/modify: `team-sync-fe/src/views/admin/performance/ReviewDetail.vue`
- Update tests expecting "Employee ID".

- [x] `ReviewDetail.vue` `employee_id` code reference fixed (see implementation notes below).
- [x] Load `systematic-debugging` before changing `employee_id` references; distinguish display copy from API/DB field usage.
- [x] Confirm backend payload shape for each touched view/resource.
- [x] Replace user-facing "Employee ID" with "Staff Member ID" where it refers to DB/staff profile identifier.
- [x] Replace live `employee_id` code references only when backend payload already exposes `staff_member_id`.
- [x] Do not edit historical migrations; add new migrations only if DB schema still contains live `employee_id` columns after latest rename migration.
- [x] Run targeted FE tests and any affected BE tests.

**Implementation notes (2026-05-18 — ReviewDetail.vue fix):**
- Root cause: `ReviewDetail.vue:167` used `review.value?.employee_id` in `canCalibrate` computed. Backend `PerformanceReview` model has `staff_member_id` column (no `employee_id`). Controller returns raw model (no Resource). API payload emits `staff_member_id`.
- Bug impact: `review.value?.employee_id` always `undefined`, so `currentEmployeeId.value !== undefined` always `true` — the HR self-calibration guard was completely bypassed. Any HR user could calibrate their own review.
- Fix: Changed `review.value?.employee_id` → `review.value?.staff_member_id` at line 167.
- Added 2 targeted tests: HR-as-reviewee blocked, HR-as-non-reviewee allowed.
- Display copy fixes: `StaffMemberProfile.vue`, `PayslipDetail.vue`, and `OvertimeManagement.vue` now say "Staff Member ID". `OvertimeManagement.smoke.test.js` expectation updated.
- Backend API/copy fixes: `PayslipPdfService`, `ThrPayrollDetailResource`, `OvertimeRecordResource`, team add/remove FormRequest validation attributes, seed command table header, and `HybridScheduleResolverTest` description now use staff-member/code terminology.
- Remaining `employee_id` in FE: none. `employee_profile` references are auth API relation names, not part of rename.
- Remaining backend app residues are comment-only historical/conceptual references: `AnalyticsRepository.php` PHPDoc and `RecalculatePayrollTaxCommand.php` PHPDoc. Old migrations intentionally unchanged.
- Verification: `bun run test` → 134 files, 1012 tests, 0 failures. `composer test` → 99 passed, 1405 warnings, 5520 assertions, 0 failures. `./vendor/bin/pint` → pass.

**Acceptance:** Live UI/code uses staff-member terminology consistently without breaking historical migration rollback logic.

---

## Party Mode Review Notes (2026-05-18)

### Participants

- 🏗️ Arsitek — Plan executable after small revisions; backend sequencing sound, avoid Resource response-shape breakage.
- 🧪 Fitri — Revise before executing; stale FE discovery made Tasks 1 and 5 already done.
- 🎨 Eka — Revise FE scope; skip-nav and form primitive claims were partially false; real issues are Input radius and TextArea inline styles.

### Decision

**What to do:** Revise plan, then execute Task 1 (BE Resources) first. Do not implement stale policy-mismatch actions or duplicate project smoke tests.

### Key Risks + Mitigations

| Risk | Likelihood | Mitigation |
|------|------------|------------|
| Resource wrapping breaks FE contract | Medium | Inspect FE consumers before Resource implementation; run relevant FE tests if response shape changes |
| Payroll FormRequest extraction changes validation behavior | Medium | Baseline payroll tests; keep `authorize(): true`; preserve keys/formats |
| FE work duplicates existing tests/skip-links | High | Audit before edits; only strengthen gaps found |

---

## Verification Checklist

- [x] FE changes: `bun run test`
- [ ] FE build/a11y where applicable: `bun run build`, `bun run test:a11y`
- [x] FE changes: `bun run test` → 134 files, 1012 tests, 0 failures
- [x] BE changes: `composer test`
- [x] BE formatting: `./vendor/bin/pint`
- [x] No API calls added to Vue components.
- [x] No raw model responses for changed BE endpoints.
- [x] No old migrations modified.
- [x] Plan updated with actual implementation notes before PR.

---

## Notes / Non-Goals

- Payment gateway in `UpgradePlan.vue` is future SaaS Phase 2B work, not part of this gap-closure pass.
- Setup wizard disabled in router appears intentional for cloud-hosted development; document separately before changing.
- PayrollService extraction must not be bundled with FormRequest cleanup.
