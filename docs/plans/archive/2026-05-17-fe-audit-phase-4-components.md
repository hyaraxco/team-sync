# FE Visual + UX Audit — Phase 4: Components & Leftover Views

**Status:** COMPLETED — PR #33 merged (rebase)
**Date:** 2026-05-17
**Predecessor:** [`2026-05-17-fe-visual-ux-audit.md`](../archive/2026-05-17-fe-visual-ux-audit.md) (Phases 1–3, PRs #30 #31 #32 merged)
**Authoritative spec:** [`team-sync-fe/docs/design-system.md`](../../../team-sync-fe/docs/design-system.md)

---

## Context

Phases 1–3 audited `team-sync-fe/src/views/**` (67 view files) against `design-system.md`. The components directory (`src/components/**`) was out of scope and contains regressions of the same anti-patterns. Additionally, 4 view-level files were touched during the audit but their `<script setup lang="ts">` violations were missed.

This Phase 4 closes the gap: components + leftover view fixups.

## Goals

- Zero `<script setup lang="ts">` across `src/` (JS-only project per `AGENTS.md` — "DO NOT create TypeScript files").
- Zero arbitrary `border-[#…]`, `text-[#…]`, `bg-[#…]`, `rounded-[Npx]` in components.
- Flatten degenerate gradients (same color from/to) — keep only intentional decorative ones.
- Keep behaviour, copy, and DOM structure unchanged. CSS-only mechanical migration.

## Non-Goals

- No new components, no API changes, no test rewrites.
- Do not touch animation `<style>` blocks that define `@keyframes` (e.g. ToastContainer, LeaveRequestSuccessModal) — these animate logic and are safe.
- Do not touch tooltip style blocks that compose pseudo-elements (e.g. `SidebarTooltip` arrow `::before`) — Tailwind cannot express ::before arrow geometry cleanly.
- Do not refactor `NotificationPanel` style block (gradients + radial overlays + box-shadow are intentional design effects, not anti-patterns).

## Scope Inventory

### Anti-pattern findings (from grep audit on main `ed719ac`)

**A. `lang="ts"` violations — 22 files**

```
4 in views/admin/ (touched in Phase 1/2 but lang attr missed):
- src/views/admin/Notifications.vue
- src/views/admin/staff-member/StaffMemberCreate.vue
- src/views/admin/staff-member/StaffMemberDetail.vue
- src/views/admin/staff-member/StaffMemberEdit.vue

18 in components/ (never audited):
- src/components/admin/dashboard/EmployeeStatistics.vue
- src/components/admin/dashboard/LatestEmployees.vue
- src/components/admin/dashboard/LatestTeams.vue
- src/components/admin/dashboard/QuickActions.vue
- src/components/admin/dashboard/SearchSection.vue
- src/components/admin/dashboard/Statistics.vue
- src/components/admin/project/list/Statistics.vue
- src/components/admin/staff-member/create/ErrorModal.vue
- src/components/admin/staff-member/create/RightSidebar.vue
- src/components/admin/staff-member/create/RightSidebarStep2.vue
- src/components/admin/staff-member/create/RightSidebarStep3.vue
- src/components/admin/staff-member/create/Stepper.vue
- src/components/admin/staff-member/create/steps/Step1PersonalInfo.vue
- src/components/admin/staff-member/create/steps/Step2JobInfo.vue
- src/components/admin/staff-member/create/steps/Step3EmergencyContact.vue
- src/components/admin/staff-member/create/steps/Step4Preview.vue
- src/components/admin/staff-member/list/Statistics.vue
- src/components/admin/team/Statistic.vue
```

**B. `rounded-[Npx]` arbitrary radii — 3 occurrences in 2 files**

```
- src/components/admin/project/list/CardList.vue:95   rounded-[6px]   → rounded-md
- src/components/admin/analytics/MetricCard.vue:59    rounded-[6px]   → rounded-md
- src/components/admin/analytics/MetricCard.vue:86    rounded-[26px]  → rounded-3xl  (closest token; 1.5rem = 24px vs 26px — visually equivalent)
```

**C. Hex colors in components — 23 files (counts)**

| File | Count | Notes |
|---|---|---|
| `admin/NotificationPanel.vue` | 25 | Heaviest — status helpers + skeletons + body text |
| `admin/dashboard/EmployeeStatistics.vue` | 5 | |
| `admin/project/detail/TaskDetailModal.vue` | 4 | `hover:bg-[#0a42b3]` ×4 |
| `common/ConfirmationModal.vue` | 3 | |
| `admin/team/detail/Header.vue` | 2 | |
| `admin/staff-member/list/CardList.vue` | 2 | |
| `admin/Header.vue` | 2 | |
| Others (1 each, 16 files) | 16 | mostly `border-[#2151A0]` btn redundant + `hover:bg-[#0a42b3]` |

**D. Gradients in components — 14 files (25 occurrences)**

Audit per-file: flatten degenerate, retain decorative.

**E. Style blocks — 6 files**

| File | Action |
|---|---|
| `admin/NotificationPanel.vue` | KEEP — intentional radial overlay + box-shadow design |
| `admin/meeting/MeetingCreateModal.vue` | KEEP scrollbar; consider migrating `.blue-gradient` to gradient utility (or token-based gradient) |
| `common/ModalWrapper.vue` | KEEP — `::-webkit-scrollbar` cannot be expressed in Tailwind |
| `common/ToastContainer.vue` | KEEP — `@keyframes toast-shrink` |
| `staff-member/attendance/LeaveRequestSuccessModal.vue` | KEEP — `@keyframes scale-in/bounce-in` |
| `ui/SidebarTooltip.vue` | KEEP — `::before` arrow geometry |

No style blocks need migration this phase. Document them as "intentional CSS escape hatches" in design-system.md follow-up.

### Out of Scope (by design)

- `src/components/admin/Sidebar.vue` 4 gradients — collapsed/expanded brand panel decoration. Audit visually if any is degenerate, otherwise keep.

---

## Approach

Mirror Phases 1–3:
1. Single branch `feat/fe-audit-tier-4-components` off `main`.
2. Three parallel `@fixer` batches for parallelism.
3. Mechanical hex/gradient/radius/lang migration only.
4. After fixes: `bun run test` (must stay 991/991), `bun run build`, push, PR.
5. Archive plan when PR merged.

### Token Mapping Reference

```
# Existing tailwind tokens (from PR #26 / design-system.md)
brand-dark    #0C1C3C          ⇐ gray-900-ish
brand-primary #0C51D9          ⇐ primary-700
primary-50  #eff6ff   primary-100 #dbeafe   primary-200 #bfdbfe   primary-700 #1d4ed8   primary-800 #1e40af
success-50  #ecfdf5   success-700 #047857
danger-50   #fef2f2   danger-600  #dc2626   danger-700  #b91c1c
warning-50  #fffbeb   warning-700 #b45309

# Common mappings for this audit
#0a42b3, #093d9e, #0a44b8     → primary-800 (hover state of brand-primary)
#2151A0                       → primary-700 (slightly darker than brand-primary; redundant on .btn-primary; remove if it accompanies bg-brand-primary, otherwise primary-700)
#16A34A                       → success-600
#7C3AED                       → purple-600
#EA580C                       → orange-600
#2563EB                       → primary-600
#F97316                       → orange-500
#EAF8EE                       → success-50
#F0ECFF                       → purple-50
#FFF4E8                       → orange-50
#EAF0FF                       → primary-50
#FFF1E8                       → orange-50 (collapse with above)
#EEF4FF                       → primary-50 (collapse with above)
#E4EBF9, #C9DBFF              → primary-100
#5D6882                       → slate-500
#334155                       → slate-700
#D6E3FD, #E3ECFF              → primary-100
#64748B                       → slate-500
#4B5563                       → gray-600
#E1ECFF                       → primary-100
#F7FAFF                       → primary-50/40
```

---

## Execution

### Batch I — `lang="ts"` removal (22 files)

**Owner:** parallel fixer agent #1
**Files:** all 22 listed above (Section A).

Mechanical change: `<script setup lang="ts">` → `<script setup>`. No other edits.

Verify after edit:
```bash
rg -l 'script setup lang' src/        # must print nothing
bun run test                          # must still pass 991/991
```

Note: do not silently remove `: SomeType` annotations or generics — none exist (already verified per Phase 3 fix on `VerifyEmailResult`/`StaffMemberSuccess`). If found, log them, do not strip, return early.

### Batch J — `NotificationPanel.vue` overhaul (heaviest hex file)

**Owner:** parallel fixer agent #2
**File:** `src/components/admin/NotificationPanel.vue`

**Replacements:**
- Status helpers (lines 144–186): `bg-[#…]` and `text-[#…]` → token equivalents per mapping table.
- Header/body text colors: `text-[#5D6882]` → `text-slate-500`, `text-[#334155]` → `text-slate-700`, `text-[#64748B]` → `text-slate-500`, `text-[#4B5563]` → `text-gray-600`.
- Skeletons: `bg-[#D6E3FD]` and `bg-[#E3ECFF]` → `bg-primary-100`.
- Borders: `border-[#E4EBF9]` → `border-primary-100`.
- Buttons: `disabled:bg-[#E1ECFF]` → `disabled:bg-primary-100`.
- Empty hover state: `hover:bg-[#F7FAFF]` → `hover:bg-primary-50/40`.

**Style block:** keep. Radial overlay + drop shadow are intentional design effects; design-system explicitly permits `<style>` for visual effects beyond Tailwind.

Verify:
```bash
rg 'border-\[#|text-\[#|bg-\[#|hover:.*\[#|focus:.*\[#|ring-\[#|divide-\[#' src/components/admin/NotificationPanel.vue
# must print nothing (template-only)
```

### Batch K — Component sweep (everything else)

**Owner:** parallel fixer agent #3
**Files (~22):**
- `src/components/admin/dashboard/EmployeeStatistics.vue` (5 hex)
- `src/components/admin/project/detail/TaskDetailModal.vue` (4 hex — all `hover:bg-[#0a42b3]` → `hover:bg-primary-800`)
- `src/components/admin/project/detail/TaskCreateModal.vue` (1 hex)
- `src/components/admin/project/detail/TaskBoard.vue` (1 hex)
- `src/components/common/ConfirmationModal.vue` (3 hex + 2 gradients)
- `src/components/admin/team/detail/Header.vue` (2 hex + 1 gradient)
- `src/components/admin/team/Statistic.vue` (1 hex)
- `src/components/admin/team/Pagination.vue` (1 hex)
- `src/components/admin/team/CardList.vue` (1 gradient)
- `src/components/admin/staff-member/list/CardList.vue` (2 hex)
- `src/components/admin/staff-member/list/Statistics.vue` (1 hex)
- `src/components/admin/staff-member/create/ErrorModal.vue` (1 hex + 1 gradient)
- `src/components/admin/staff-member/create/Stepper.vue` (2 gradients)
- `src/components/admin/staff-member/create/steps/Step2JobInfo.vue` (2 gradients)
- `src/components/admin/staff-member/create/steps/Step4Preview.vue` (1 gradient)
- `src/components/admin/Header.vue` (2 hex)
- `src/components/admin/Sidebar.vue` (1 hex + 4 gradients — keep brand panel decorative gradient if non-degenerate; flatten any same-from-to)
- `src/components/admin/payroll/Pagination.vue` (1 hex)
- `src/components/admin/meeting/MeetingCreateModal.vue` (1 hex + style block; keep scrollbar; if `.blue-gradient` is used, leave it — gradient is intentional)
- `src/components/admin/dashboard/QuickActions.vue` (1 hex)
- `src/components/admin/dashboard/LatestEmployees.vue` (1 hex)
- `src/components/admin/dashboard/LatestTeams.vue` (1 gradient)
- `src/components/admin/dashboard/TodayAttendanceOverview.vue` (2 gradients)
- `src/components/admin/dashboard/TeamPulseOverview.vue` (1 gradient)
- `src/components/admin/analytics/MetricCard.vue` (rounded-[6px], rounded-[26px], 1 gradient)
- `src/components/admin/project/list/CardList.vue` (rounded-[6px], 1 gradient)
- `src/components/staff-member/attendance/LeaveRequestSuccessModal.vue` (1 hex + 2 gradients; keep keyframes)
- `src/components/common/SearchFilter.vue` (1 hex)
- `src/components/common/MainCard.vue` (1 hex)
- `src/components/common/form/TextArea.vue` (1 hex)
- `src/components/common/form/Select.vue` (1 hex)

**Per-file rules:**
1. Replace hex with closest token from mapping table.
2. Flatten degenerate gradients (identical or near-identical from/to).
3. Keep non-degenerate decorative gradients (Sidebar brand panel, ConfirmationModal danger gradient if any, Stepper progress gradient if any).
4. `rounded-[6px]` → `rounded-md`. `rounded-[26px]` → `rounded-3xl`.
5. Don't touch any `<style>` blocks beyond what Batch I/J cover.

Verify after batch:
```bash
rg -l 'border-\[#|text-\[#|bg-\[#|hover:.*\[#|focus:.*\[#|ring-\[#|divide-\[#' src/components/
# must print nothing OR only files with documented intentional escape hatches
rg 'rounded-\[' src/components/      # must print nothing
bun run test                          # 991/991
bun run build
```

---

## Verification

After all 3 batches:

```bash
# 1. lang=ts gone everywhere
rg -l 'script setup lang' src/        # ZERO

# 2. No arbitrary tailwind values in components
rg 'border-\[#|text-\[#|bg-\[#|rounded-\[' src/components/ | grep -v node_modules
# Allowed exceptions: NONE expected

# 3. Tests + build
bun run test     # 991/991 PASS
bun run build    # ok

# 4. Visual regression (best-effort, screenshots CI on PR)
```

If a batch leaves residual hex in a file, re-dispatch a single-file fixer to close it. Don't ship partial.

---

## Rollout

1. Branch from main: `feat/fe-audit-tier-4-components`
2. Three commits (one per batch) for review readability:
   - `chore(fe): remove lang="ts" from views and components`
   - `chore(fe): NotificationPanel token migration`
   - `chore(fe): component-layer visual audit`
3. Push, open PR. CI must be green (fe-tests, screenshots).
4. After merge: archive this plan to `docs/plans/archive/`.

## Risks

- **Color drift:** mapping `#16A34A` → `success-600` differs by a few RGB points. Acceptable per design-system spec.
- **Gradient flattening:** if I misidentify a "degenerate" gradient that the designer intended (e.g. subtle shadow effect via gradient), the visual changes very slightly. Mitigation: gradient is only flagged degenerate if `from-X to-X` literally identical, never near-identical.
- **`lang="ts"` removal hides type errors:** there are no real types in any file (verified via Phase 3 — files were JS-with-mistaken-attr, not real TS). If batch I finds real type annotations, abort and ask user.

## Out-of-Scope Followups (not this PR)

- Style block normalization (NotificationPanel design effects, MeetingCreateModal `.blue-gradient`).
- Sidebar 4 gradients audit (visual review needed before flattening).
- Possible new design tokens for repeated `slate-500`/`gray-600` text patterns (centralization).
