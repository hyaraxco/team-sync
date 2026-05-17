# FE Visual + UX Audit Plan

> **Status:** COMPLETED
> **Created:** 2026-05-17
> **Completed:** 2026-05-17
> **Owner:** AI agents (Pi/Claude/Codex/OpenCode)
> **Scope:** All 67 Vue views in `team-sync-fe/src/views/`
> **Strategy:** Tier-based pass + static code audit + one PR per phase
> **Outcome:** PR #30 (Tier-1) + #31 (Tier-2) + #32 (Tier-3) — all merged

**Goal:** Bring all 67 frontend views into compliance with `team-sync-fe/docs/design-system.md` and apply UX heuristic / UX-law improvements without changing behavior.

**Architecture:** Static code audit against design-system.md contracts. No runtime browser walkthrough except for spot-checks. Findings grouped by severity tier. Each phase ships independently as its own PR so reviews stay manageable.

**Tech stack:** Vue 3 Composition API, Tailwind CSS 3, design tokens defined in `team-sync-fe/tailwind.config.js`, components in `team-sync-fe/src/components/common/`.

---

## Audit Findings (Static Pass)

Counts from `rg` over `team-sync-fe/src/views/` on 2026-05-17:

| Anti-pattern | Count | Notes |
|---|---|---|
| `border-[#...]` arbitrary borders | 76 | Mostly redundant `border-[#2151A0]` on `.btn-primary` |
| `text-[#...]` arbitrary text colors | 45 | Status badges + notification color helpers should use `StatusBadge` / brand tokens |
| `bg-[#...]` arbitrary backgrounds | 38 | Half are status badges (TeamDetail, StaffMemberTeam, Notifications) |
| `bg-gradient-to-*` | 29 | 10+ are degenerate `from-brand-primary to-brand-primary` (no actual gradient) |
| `rounded-[Npx]` arbitrary radii | 7 | Includes broken `rounded-[#12px]` in PayrollDetail.vue:1015 (will not render) |
| `<style>` blocks in views | 3 | PayslipDetail, HybridSchedules, AttendanceSettings |
| `alert()` calls | 1 | UpgradePlan.vue:76 — placeholder, replace with toast |
| `hover:border-2`, `dark:`, `shadow-[…]`, `h-screen` | 0 | Clean from PR #26 |
| `aria-label` usage | 12 sites only | Underapplied — many icon-only buttons missing labels |
| `tabular-nums` | 0 | Missing on financial / time / numeric tables |

### Critical Bugs Found

1. **`PayrollDetail.vue:1015`** — `rounded-[#12px]` is not valid Tailwind syntax (the `#` prefix breaks it). Currently rendering with no border-radius. Fix: `rounded-xl`.
2. **`UpgradePlan.vue:76`** — uses native `alert()` for "Payment gateway integration coming soon!" — replace with toast.

### File Size Hotspots

| View | Lines | Tier |
|---|---|---|
| `admin/payroll/PayrollDetail.vue` | 2316 | T1 |
| `admin/performance/ReviewDetail.vue` | 1588 | T2 |
| `staff-member/MyAttendance.vue` | 1426 | T1 |
| `admin/payroll/PayrollSettings.vue` | 939 | T2 |
| `admin/payroll/PayrollReadiness.vue` | 898 | T2 |
| `admin/project/ProjectEdit.vue` | 894 | T2 |
| `admin/project/ProjectCreate.vue` | 888 | T2 |
| `admin/attendance/AttendanceSettings.vue` | 884 | T2 |
| `admin/payroll/PayrollDashboard.vue` | 794 | T1 |

These are not refactored in this audit (out of scope), but flagged so the audit doesn't expand into structural rework.

---

## Tier Definitions

### Tier 1 — High-Traffic (Daily Use)

User-facing views employees and admins hit every day. Deep audit: visual tokens + UX heuristics + UX laws + accessibility.

- `auth/Login.vue` ✓ (already done in PR #27)
- `auth/ForgotPassword.vue` ✓ (already done in PR #27)
- `auth/ResetPassword.vue` ✓ (already done in PR #27)
- `auth/VerifyEmailResult.vue`
- `admin/Dashboard.vue`
- `admin/Notifications.vue`
- `admin/analytics/AnalyticsDashboard.vue`
- `admin/attendance/AttendanceList.vue`
- `admin/payroll/PayrollDashboard.vue`
- `admin/payroll/PayrollDetail.vue` (CRITICAL: has the broken-radius bug)
- `admin/UpgradePlan.vue` (CRITICAL: has the alert() bug)
- `staff-member/MyAttendance.vue`
- `staff-member/StaffMemberProfile.vue`
- `staff-member/StaffMemberTeam.vue`
- `staff-member/MyPayslips.vue`
- `staff-member/PayslipDetail.vue`

**14 views in scope for Phase 1.**

### Tier 2 — Forms + Settings + Performance (Frequent but Specialized)

Configuration screens, CRUD forms, performance review module, payroll settings. Medium-pass: token compliance + obvious UX wins (button consistency, spacing, label clarity).

- `admin/Settings.vue`
- `admin/attendance/AttendanceCorrectionList.vue`
- `admin/attendance/AttendancePeriods.vue`
- `admin/attendance/AttendanceRecordList.vue`
- `admin/attendance/AttendanceSettings.vue`
- `admin/attendance/HolidayCalendar.vue`
- `admin/attendance/HybridScheduleList.vue`
- `admin/attendance/LeaveRequestList.vue`
- `admin/attendance/OvertimeManagement.vue`
- `admin/attendance/PolicyMismatches.vue`
- `admin/meeting/MeetingList.vue`
- `admin/payroll/AdjustmentQueue.vue`
- `admin/payroll/ApprovalMatrix.vue`
- `admin/payroll/Comparison.vue`
- `admin/payroll/Create.vue`
- `admin/payroll/Readiness.vue`
- `admin/payroll/Settings.vue`
- `admin/payroll/ThrManagement.vue`
- `admin/performance/*` (14 views)
- `admin/project/*` (4 views)
- `admin/staff-member/*` (5 views)
- `admin/team/*` (4 views)
- `staff-member/HybridSchedules.vue`
- `staff-member/MyOvertime.vue`
- `staff-member/StaffMemberProfileEdit.vue`

**~46 views in scope for Phase 2.**

### Tier 3 — Edge / Low-Traffic

Setup wizard (one-time), error pages, success splashes. Light sweep: token-only fix where trivial, no UX rework.

- `setup/SetupWizard.vue`
- `admin/staff-member/StaffMemberSuccess.vue`
- `auth/VerifyEmailResult.vue` (if not already in T1)
- `NotFound.vue`

**4 views in scope for Phase 3.**

---

## Audit Checklist (Per View)

Each view gets evaluated against this checklist. The phase-level commits should reference the checklist items they fix.

### Visual / Token Compliance

- [ ] No `border-[#...]`, `bg-[#...]`, `text-[#...]` — use brand tokens (`brand-primary`, `brand-border`, `brand-dark`, `success`, `danger`, `warning`)
- [ ] No `rounded-[Npx]` — use `rounded-md`, `rounded-lg`, `rounded-xl`, `rounded-2xl`
- [ ] No `shadow-[...]` — use `shadow-sm`, `shadow-md`, `shadow-lg`, `blue-btn-shadow`
- [ ] No `bg-gradient-to-*` for solid colors — only use gradients when there's a real color stop transition
- [ ] No `<style scoped>` blocks for CSS that should live in `input.css`
- [ ] No `dark:` classes (project does not support dark mode currently)
- [ ] No `h-screen` — use `min-h-[100dvh]`
- [ ] Status badges use the `StatusBadge` component, not inline `bg-[#...] text-[#...]` pills
- [ ] Buttons follow design-system: `.btn-primary` already encodes border + shadow + gradient — no need for redundant `border border-[#2151A0] blue-gradient blue-btn-shadow`
- [ ] Financial / time / numeric tables use `tabular-nums`

### UX Heuristic Pass (Nielsen 10 + UX Laws)

- [ ] **Visibility of system status** — every async action shows loading state (spinner / skeleton / disabled button)
- [ ] **Match real world** — labels in user language (no `staff_member_id` exposed)
- [ ] **User control** — destructive actions are confirmed via `ConfirmationModal` (not `window.confirm`)
- [ ] **Consistency** — same action styled the same way across views (Edit button = ghost, Delete = danger ghost, Save = primary)
- [ ] **Error prevention** — required fields validated before submit, can't double-submit
- [ ] **Recognition over recall** — filters retain selection, breadcrumbs show path
- [ ] **Flexibility** — keyboard navigation (Tab/Enter/Esc) works
- [ ] **Aesthetic / minimalist** — no extraneous decoration, no nested cards-in-cards-in-cards
- [ ] **Help users recover from errors** — error states show actionable next step
- [ ] **Help / documentation** — tooltips on icon-only buttons (every icon button has `title` or `aria-label`)
- [ ] **Hick's Law** — no more than ~7 primary actions per page
- [ ] **Fitts's Law** — clickable icons are at least 32×32px hit target
- [ ] **Miller's Law** — list rows under 7 fields visible at once on mobile
- [ ] **Jakob's Law** — common patterns (table sort, search, paginate) work as expected

### Accessibility Pass (WCAG 2.1 AA)

- [ ] Icon-only buttons have `aria-label`
- [ ] Form inputs have associated `<label>`
- [ ] Color is not sole indicator (status badges include text)
- [ ] Focus rings visible on all interactive elements
- [ ] `prefers-reduced-motion` respected on animations
- [ ] Heading hierarchy is sequential (h1 → h2 → h3, no skips)
- [ ] Alt text on meaningful images, empty alt on decorative

---

## Phase 1 — Tier 1 Deep Audit

**Branch:** `feat/fe-audit-tier-1`
**PR title:** `chore(fe): tier-1 visual + UX audit`
**Estimated commits:** 14 (one per view)

### Task 1.1 — `auth/VerifyEmailResult.vue`

**Files:**
- Modify: `team-sync-fe/src/views/auth/VerifyEmailResult.vue`

**Steps:**
- [ ] Read file, run audit checklist mentally
- [ ] Fix all token violations inline (token + UX + a11y)
- [ ] `bun run test` — must stay green
- [ ] Commit

### Task 1.2 — `admin/Dashboard.vue`

Same template as 1.1. Pay extra attention to:
- Stats card consistency (use `StatsCard` component)
- Numeric values use `tabular-nums`
- Headings hierarchy

### Task 1.3 — `admin/Notifications.vue`

**Critical fixes:**
- 6× `bg-[#...] text-[#...]` color helpers in `iconColor` and `bgColor` computed — refactor to use brand tokens (`bg-success/10 text-success`, etc.)
- 12× `bg-[#F8FAFC]`, `bg-[#EFF5FF]`, `border-[#D5E2FB]`, `border-[#E7ECF4]`, `border-[#EEF2F8]` — replace with `bg-gray-50`, `bg-brand-primary/10`, `border-brand-border`, `border-gray-100`
- `text-[#16A34A]`, `text-[#7C3AED]`, etc. → `text-success`, `text-purple-600` (or token)
- `text-[#334155]`, `text-[#64748B]` → `text-brand-dark`, `text-gray-500`

### Task 1.4 — `admin/analytics/AnalyticsDashboard.vue`

**Critical fixes:**
- `bg-[#0a44b8]` → `bg-brand-primary/90` (line 284)
- Tab pill `border-[#2151A0]` redundant with `blue-gradient` — remove

### Task 1.5 — `admin/attendance/AttendanceList.vue`

**Critical fixes:**
- `border-[#0B1042]` on `.main-card` (line 204) — this is a dark-mode card, keep but verify in design-system.md
- Verify `EmptyState` usage on empty leave + corrections lists

### Task 1.6 — `admin/payroll/PayrollDashboard.vue`

**Critical fixes:**
- 4× redundant `border border-[#2151A0]` on `.btn-primary` — remove
- Single-color gradient on icon container — flatten to `bg-brand-primary`
- Numeric metrics get `tabular-nums`

### Task 1.7 — `admin/payroll/PayrollDetail.vue` (CRITICAL — file is 2316 lines)

**Critical fixes:**
- **BUG: `rounded-[#12px]` (line 1015) — broken syntax, replace with `rounded-xl`**
- 5× tab pill `border-[#2151A0]` — already `.blue-gradient`, remove redundant border
- 5× action button `border-[#2151A0]` redundant with `.btn-primary`
- Currency / IDR amounts use `tabular-nums`
- Approval / lock destructive actions confirmed via `ConfirmationModal`

### Task 1.8 — `admin/UpgradePlan.vue` (CRITICAL — has alert() bug)

**Critical fixes:**
- **BUG: line 76 `alert("Payment gateway integration coming soon!")` — replace with `toast.info('Payment gateway integration coming soon')`**
- 2× `bg-[#1e1e1e]` → `bg-brand-dark`
- `hover:bg-[#093d9e]` → `hover:bg-brand-primary/90`
- Active scale animation respects `prefers-reduced-motion`

### Task 1.9 — `staff-member/MyAttendance.vue` (1426 lines)

**Critical fixes:**
- 6× redundant `border-[#2151A0]` on action buttons + tab pills
- 3× single-color `bg-gradient-to-br from-brand-primary to-brand-primary` icon containers — flatten
- `text-[#EE2A3B]` → `text-danger`
- Verify `EmptyState` usage on all 3 empty branches (correct count)

### Task 1.10 — `staff-member/StaffMemberProfile.vue`

**Critical fixes:**
- `border-[#2151A0]` redundant with `.btn-primary` (×2)
- `from-brand-primary to-brand-primary` flatten

### Task 1.11 — `staff-member/StaffMemberTeam.vue`

**Critical fixes:**
- All 6 status badge `bg-[#...] text-[#...]` color helpers → `StatusBadge` component
- `border-[#2151A0]` redundant on tab pills (×3)
- Decorative blob gradients are intentional, keep

### Task 1.12 — `staff-member/MyPayslips.vue`

**Critical fixes:**
- `bg-gradient-to-r from-blue-600 to-blue-700` on action button → `.btn-primary`

### Task 1.13 — `staff-member/PayslipDetail.vue`

**Critical fixes:**
- Remove `<style scoped>` block (line 524) — move print CSS to `input.css` under `@media print`
- 2× `bg-gradient-to-br from-blue-600 to-blue-700` → check if intentional or flatten
- IDR amounts `tabular-nums`

### Task 1.14 — Phase 1 wrap-up

- [ ] `bun run test` — 991/991 pass
- [ ] `bun run build` — succeeds
- [ ] Push, create PR
- [ ] Wait for CI green + approval, rebase + merge, delete branch

---

## Phase 2 — Tier 2 Medium Pass

**Branch:** `feat/fe-audit-tier-2`
**PR title:** `chore(fe): tier-2 visual + UX audit (forms, settings, performance)`
**Prerequisite:** Phase 1 merged.

For ~46 views, focus on:
1. Token compliance only (no deep UX rework)
2. Replace redundant `border-[#2151A0]` on `.btn-primary` (single biggest issue)
3. Status badges → `StatusBadge` component
4. Single-color gradient flattening
5. Remove `<style>` blocks in `staff-member/HybridSchedules.vue` and `admin/attendance/AttendanceSettings.vue`

**Strategy per view:**
- [ ] Open file
- [ ] Run sed-style fix on redundant `border border-[#2151A0]` patterns
- [ ] Replace `bg-[#...]` and `text-[#...]` color helpers with tokens
- [ ] Validate via `bun run test`

This phase batches commits more aggressively (3-5 views per commit) since the changes are mechanical.

---

## Phase 3 — Tier 3 Light Sweep

**Branch:** `feat/fe-audit-tier-3`
**PR title:** `chore(fe): tier-3 visual audit (edge views)`
**Prerequisite:** Phase 2 merged.

Trivial token fixes only:
- `setup/SetupWizard.vue`: `bg-gradient-to-br from-slate-50 via-white to-blue-50` is intentional aurora, keep
- `admin/staff-member/StaffMemberSuccess.vue`: 1 redundant border, fix
- `NotFound.vue`: review

Single commit. Fast PR.

---

## Verification (Each Phase)

Run before pushing:

```bash
cd team-sync-fe
bun run test           # 991/991 expected
bun run build          # must succeed
rg -c 'border-\[#2151A0\]' src/views/  # should decrease
rg -c 'rounded-\[#' src/views/         # should be 0 after Phase 1
```

Manual spot-check (browser): pull up Login + Dashboard + PayrollDetail at 375px / 768px / 1440px — no horizontal scroll, all interactive elements have visible focus.

---

## Out of Scope

- File-size refactors (PayrollDetail.vue, ReviewDetail.vue, MyAttendance.vue) — flagged for separate plan
- Component library extraction (turning inline patterns into shared components)
- Performance optimization (code splitting, image optimization)
- i18n / RTL support
- Dark mode

---

## Self-Review Checklist (For Plan Author)

- [x] Spec coverage: each finding ties to a task
- [x] No placeholders / TBD
- [x] Tasks reference real file paths
- [x] Verification command per phase
- [x] Out-of-scope list explicit
- [x] Phase boundary = PR boundary
