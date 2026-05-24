# Attendance Pagination Consistency + Staff Tab Merge

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Two-track redesign — (1) standardize pagination wrappers, loading states, and badge usage across 7 admin attendance views (low risk, surface-level consistency), (2) merge 3 staff-member attendance pages into a single tabbed view to mirror the admin `AttendanceList.vue` pattern (medium risk, structural refactor).

**Architecture:** Phases 1–5 are CSS/component swaps inside existing files — no route or structural changes. Phase 6 introduces a tab shell at `MyAttendance.vue` that embeds `MyOvertime.vue` + `HybridSchedules.vue` as children via an `embedded` prop, removes 2 staff routes, and prunes the sidebar.

**Tech Stack:** Vue 3 Composition API (`<script setup>`), Tailwind CSS v3, Pinia stores, Lucide Vue Next, Vitest.

---

## Background & Audit Summary

### Pagination wrapper inconsistencies (7 admin views)

Canonical pattern from `AttendanceRecordList.vue:221`:
```vue
<div class="p-4 border-t border-brand-border bg-brand-border/10">
    <Pagination ... />
</div>
```

Current state (5 of 7 views deviate):

| File | Current wrapper |
|---|---|
| `AttendanceRecordList.vue:221` | Canonical (reference) |
| `OvertimeManagement.vue:256` | `border-t px-4 py-3` (no bg tint) |
| `HybridScheduleList.vue:442` | `mt-6` only |
| `LeaveRequestTab.vue:190` | Bare — no wrapper |
| `AttendanceCorrectionTab.vue:180` | Bare — no wrapper |
| `LeaveRequestList.vue:537` | No wrapper, directly inside card |
| `AttendanceCorrectionList.vue:233` | No wrapper, directly inside card |

### Loading state inconsistencies

- `AttendanceRecordList.vue` / `LeaveRequestList.vue` — spinner with `animate-spin`
- `AttendanceCorrectionTab.vue` / `LeaveRequestTab.vue` — plain `<p>Loading...</p>` text

### StatusBadge bug

`AttendanceRecordList.vue:161` passes `type="leave-type"` for attendance status values (`present`, `late`, `absent`, `half_day`). `StatusBadge.vue` has no `attendance-status` type — values fall through to the unknown fallback (purple).

### LeaveRequestList calendar inline nav

`LeaveRequestList.vue:549-568` has hand-rolled prev/next/today buttons that duplicate `DatePagination.vue` logic. The list view of the same file already uses `DatePagination` correctly.

### HybridScheduleList table missing card wrapper

Both tables (Schedules + Exceptions) sit bare inside the page. Other admin views use `<div class="bg-white rounded-2xl border border-brand-border overflow-hidden">` as the card shell.

### Staff-member attendance — fragmented routes, dead route

3 separate routes for related attendance functionality:
- `staffMember.attendance.my-attendances` → `MyAttendance.vue` (1310 lines, already has tab system)
- `staffMember.attendance.my-overtime` → `MyOvertime.vue` (326 lines)
- `staffMember.attendance.my-hybrid-schedule` → `HybridSchedules.vue` (233 lines) — **no sidebar link, dead route**

Admin equivalent (`AttendanceList.vue`) already uses a tab shell pattern that consolidates Records / Leave / Corrections / Overtime / Hybrid into one view via embedded child components.

---

## File Structure

**Admin attendance views (Phases 1–5):**
- `team-sync-fe/src/views/admin/attendance/AttendanceRecordList.vue`
- `team-sync-fe/src/views/admin/attendance/AttendanceCorrectionList.vue`
- `team-sync-fe/src/views/admin/attendance/AttendanceCorrectionTab.vue`
- `team-sync-fe/src/views/admin/attendance/LeaveRequestList.vue`
- `team-sync-fe/src/views/admin/attendance/LeaveRequestTab.vue`
- `team-sync-fe/src/views/admin/attendance/HybridScheduleList.vue`
- `team-sync-fe/src/views/admin/attendance/OvertimeManagement.vue`

**Staff-member views (Phase 6):**
- `team-sync-fe/src/views/staff-member/MyAttendance.vue` — tab shell host
- `team-sync-fe/src/views/staff-member/MyOvertime.vue` — refactor to accept `embedded` prop
- `team-sync-fe/src/views/staff-member/HybridSchedules.vue` — refactor to accept `embedded` prop

**Shared components:**
- `team-sync-fe/src/components/common/Pagination.vue` (no changes — wrapper-only fixes)
- `team-sync-fe/src/components/common/StatusBadge.vue` — add `attendance-status` type
- `team-sync-fe/src/components/admin/attendance/DatePagination.vue` (no changes)
- `team-sync-fe/src/utils/badgeUtils.js` — add `getAttendanceStatusBadgeClass()`

**Routing & navigation:**
- `team-sync-fe/src/router/attendance.js` — remove 2 staff routes
- `team-sync-fe/src/components/admin/Sidebar.vue` — remove "My Overtime" link (PERSONAL section)
- `team-sync-fe/src/components/admin/Header.vue` — remove stale title map entries if present

**Tests:**
- `team-sync-fe/src/tests/admin/attendance/AttendanceRecordList.smoke.test.js`
- `team-sync-fe/src/tests/admin/attendance/HybridScheduleList.smoke.test.js`
- `team-sync-fe/src/tests/admin/attendance/OvertimeManagement.smoke.test.js`
- `team-sync-fe/src/tests/admin/attendance/LeaveRequestList.smoke.test.js`
- `team-sync-fe/src/tests/staff-member/MyAttendance.smoke.test.js` (new or extended)
- `team-sync-fe/src/tests/router/` — route guard tests for removed staff routes

**Out of scope:**
- Mobile card views for admin tables (separate concern)
- Staff-side card vs admin-side table split (intentional design)
- Staff skeleton vs admin spinner loading (intentional, card-shaped layouts use skeleton)
- BE changes — none required

---

## Phase 1 — HybridScheduleList Table Wrapper

**Goal:** Both tables (Schedules + Exceptions) get the same `rounded-2xl` card shell as `OvertimeManagement.vue`.

- [ ] Read `team-sync-fe/src/views/admin/attendance/OvertimeManagement.vue` and `HybridScheduleList.vue` to confirm current structure
- [ ] Wrap Schedules tab `<table>` (around line 234) in `<div class="bg-white rounded-2xl border border-brand-border overflow-hidden">`
- [ ] Wrap Exceptions tab `<table>` (around line 307) in the same wrapper
- [ ] Verify `EmptyState` placement still renders inside the card when no rows exist
- [ ] Run `bun run test -- HybridScheduleList` and visually verify both tabs

---

## Phase 2 — Pagination Wrapper Standardization

**Goal:** All `<Pagination>` usages share identical wrapper: `<div class="p-4 border-t border-brand-border bg-brand-border/10">`.

- [ ] `OvertimeManagement.vue:256` — change wrapper from `border-t px-4 py-3` to canonical
- [ ] `HybridScheduleList.vue:442` — change wrapper from `mt-6` to canonical (apply to both tabs if both paginate)
- [ ] `LeaveRequestTab.vue:190` — wrap bare `<Pagination>` with canonical wrapper
- [ ] `AttendanceCorrectionTab.vue:180` — wrap bare `<Pagination>` with canonical wrapper
- [ ] `LeaveRequestList.vue:537` — wrap with canonical wrapper (place inside card, after table)
- [ ] `AttendanceCorrectionList.vue:233` — wrap with canonical wrapper
- [ ] Run `bun run test -- attendance` to ensure no smoke tests break on DOM structure assertions

---

## Phase 3 — Loading State Consistency

**Goal:** Replace plain text loading with spinner pattern matching `AttendanceRecordList.vue`.

Reference spinner pattern:
```vue
<tr v-if="loading">
    <td :colspan="N" class="text-center py-12">
        <div class="inline-flex items-center gap-2 text-brand-light">
            <div class="w-4 h-4 border-2 border-brand-primary border-t-transparent rounded-full animate-spin"></div>
            Loading...
        </div>
    </td>
</tr>
```

- [ ] `LeaveRequestTab.vue` — replace `<p class="text-center py-12">Loading...</p>` with spinner pattern (adjust `colspan` to match table columns)
- [ ] `AttendanceCorrectionTab.vue` — same replacement
- [ ] Verify alignment and centering in both views
- [ ] Run `bun run test -- LeaveRequest AttendanceCorrection`

---

## Phase 4 — `attendance-status` Badge Type

**Goal:** Add a dedicated badge type for attendance statuses with a restrained palette (no rainbow), and fix the bug at `AttendanceRecordList.vue:161`.

### 4.1 — Add badge handler

- [ ] Open `team-sync-fe/src/utils/badgeUtils.js`
- [ ] Add `getAttendanceStatusBadgeClass(status)` mapping:
  - `present` → neutral default (e.g. `bg-brand-border/30 text-brand-dark border border-brand-border`)
  - `late` → desaturated amber (e.g. `bg-warning-50 text-warning-700 border border-warning-200`)
  - `absent` → muted danger (e.g. `bg-danger-50 text-danger-700 border border-danger-200`)
  - `half_day` → cool slate (e.g. `bg-slate-50 text-slate-700 border border-slate-200`)
  - `sick_leave` / `annual_leave` — delegate to `getLeaveTypeBadgeClass` for parity
  - default fallback → same as `present` (neutral)
- [ ] Keep palette to 3–4 distinct tones max; reuse existing semantic colors (`success`, `warning`, `danger`) defined in `tailwind.config.js`

### 4.2 — Wire up StatusBadge

- [ ] Open `team-sync-fe/src/components/common/StatusBadge.vue`
- [ ] Add `attendance-status` to the `typeMap` so `<StatusBadge type="attendance-status" :value="status" />` resolves to the new handler

### 4.3 — Fix the bug

- [ ] `AttendanceRecordList.vue:161` — change `type="leave-type"` to `type="attendance-status"`
- [ ] Audit other attendance views for the same bug (`grep -rn 'type="leave-type"' src/views/admin/attendance` — only fix where the value is an attendance status, not a leave type)

### 4.4 — Tests

- [ ] Add unit test in `src/tests/utils/badgeUtils.test.js` covering all 6 attendance statuses + unknown fallback
- [ ] Update `AttendanceRecordList.smoke.test.js` if it asserts on badge classes

---

## Phase 5 — LeaveRequestList Calendar → DatePagination

**Goal:** Replace the inline prev/next/today buttons in the calendar view with the existing `<DatePagination>` component.

- [ ] `LeaveRequestList.vue:549-568` — remove the inline `<button>` cluster
- [ ] Replace with `<DatePagination v-model="dateRange" :loading="loading" @update:modelValue="handleDateChange" />` (or whatever binding the list view uses)
- [ ] Verify `currentMonth` ref (line 65) stays in sync with `dateRange`. If they're separate refs, consolidate or add a watcher
- [ ] Confirm month-picker UX matches what the list view shows
- [ ] Run `bun run test -- LeaveRequestList` and manually verify calendar navigation

---

## Phase 6 — Staff "My Attendance" Tab Merge

**Goal:** Single staff entry point for attendance. `MyAttendance.vue` becomes a tab shell hosting Overview, Corrections, Overtime, and (conditionally) Hybrid Schedule. Remove the standalone routes and the "My Overtime" sidebar link.

### 6.1 — Refactor child views to support `embedded` prop

- [ ] `MyOvertime.vue`:
  - Add `defineProps({ embedded: { type: Boolean, default: false } })`
  - Wrap top-level page header/title in `<template v-if="!embedded">` (or remove entirely when embedded)
  - Keep all data fetching, pagination, filters, and store wiring intact
  - Remove outer `<Layout>` / page padding wrappers when embedded; let parent provide chrome

- [ ] `HybridSchedules.vue`:
  - Add `defineProps({ embedded: { type: Boolean, default: false } })`
  - Same pattern: hide standalone page header when embedded
  - Keep both columns (Base Schedule + Overrides) intact
  - Keep override modal trigger working

### 6.2 — Component reuse fixes inside `HybridSchedules.vue`

- [ ] Replace raw status `<span>` badges with `<StatusBadge type="leave-status" :value="override.status" />`
- [ ] Replace inline `<p>` empty states with `<EmptyState>` component (use existing copy)
- [ ] Keep skeleton `animate-pulse` loading (appropriate for card layout — do NOT replace with spinner)

### 6.3 — Expand `MyAttendance.vue` tab system

- [ ] Open `MyAttendance.vue:226-241` (`sections` computed)
- [ ] Add `Timer` and `MapPin` icon imports from `lucide-vue-next`
- [ ] Add `useHybridScheduleStore` (or compute `isHybrid` from auth store as already done)
- [ ] Extend `sections` computed:
  ```js
  const sections = computed(() => {
      const items = [
          { id: 'overview', label: 'Overview', icon: CalendarDays, isVisible: true },
          { id: 'corrections', label: 'Corrections', icon: Clock, isVisible: canCreateCorrection.value },
          { id: 'overtime', label: 'Overtime', icon: Timer, isVisible: true },
          { id: 'hybrid', label: 'Hybrid Schedule', icon: MapPin, isVisible: isHybrid.value },
      ];
      return items.filter(s => s.isVisible);
  });
  ```
- [ ] Import `MyOvertime` and `HybridSchedules` as components
- [ ] Add tab panels:
  ```vue
  <section v-else-if="activeSection === 'overtime'">
      <MyOvertime embedded />
  </section>
  <section v-else-if="activeSection === 'hybrid'">
      <HybridSchedules embedded />
  </section>
  ```
- [ ] Verify tab grid layout adjusts gracefully from 2 → 3 → 4 columns based on `sections.length`

### 6.4 — Optional URL sync (`?tab=` query)

- [ ] Add a watcher on `activeSection` that updates `router.replace({ query: { ...route.query, tab: activeSection.value } })`
- [ ] On mount, if `route.query.tab` matches a visible section id, set `activeSection.value` to it
- [ ] This makes the page deep-linkable and survives reloads

### 6.5 — Remove standalone routes

- [ ] Open `team-sync-fe/src/router/attendance.js`
- [ ] Remove `staffMember.attendance.my-overtime` route definition
- [ ] Remove `staffMember.attendance.my-hybrid-schedule` route definition
- [ ] Keep `staffMember.attendance.my-attendances` and `staffMember.attendance.clock`
- [ ] Search the codebase for any `router.push({ name: 'staffMember.attendance.my-overtime' })` or `router.push({ name: 'staffMember.attendance.my-hybrid-schedule' })` calls and replace with `my-attendances` (optionally with `?tab=` query)

### 6.6 — Sidebar cleanup

- [ ] Open `team-sync-fe/src/components/admin/Sidebar.vue`
- [ ] PERSONAL section — remove the "My Overtime" link (around lines 614–686)
- [ ] Update the active-route check on "My Attendance" link if needed (it should now also be active for the removed routes' old paths if any redirect is added — but per user direction we are not redirecting)
- [ ] Open `Header.vue` — remove or update the title map entry for `staffMember.attendance.my-overtime` (and `my-hybrid-schedule` if present)

### 6.7 — Tests

- [ ] Update or add `src/tests/staff-member/MyAttendance.smoke.test.js`:
  - Renders Overview tab by default
  - Shows Corrections tab when permission granted
  - Shows Overtime tab unconditionally
  - Shows Hybrid Schedule tab only when `work_location === 'hybrid'`
  - Switching tabs renders correct child component
- [ ] Update or add `src/tests/staff-member/MyOvertime.test.js`:
  - Hides standalone page header when `embedded` prop is true
- [ ] Remove or update tests targeting the deleted routes (search for `my-overtime` and `my-hybrid-schedule` route names in test files)
- [ ] Update `src/tests/router/` route guard tests if they reference removed routes

### 6.8 — Sanity sweep

- [ ] `bun run test` — full unit suite
- [ ] `bun run e2e:prepare:be && bun run e2e -- attendance` — E2E for staff attendance flows
- [ ] Manual smoke: log in as staff with `work_location=office` → no Hybrid tab visible
- [ ] Manual smoke: log in as staff with `work_location=hybrid` → Hybrid tab visible
- [ ] Manual smoke: log in as staff without `attendance-correction-create` permission → no Corrections tab
- [ ] Manual smoke: deep-link `?tab=overtime` opens Overtime tab directly

---

## Risk Matrix

| Phase | Risk | Mitigation |
|---|---|---|
| 1 | Low — visual wrapper only | Snapshot test + visual check |
| 2 | Low — same component, new wrapper | Smoke tests catch DOM regressions |
| 3 | Low — template change only | Smoke tests cover loading state |
| 4 | Low — additive type, isolated bug fix | Unit test for badge handler |
| 5 | Low — component swap | E2E covers calendar nav |
| 6 | Medium — route removal + sidebar + embedding | Test all 4 tab states + permission gates + work_location branches |

---

## Execution Order Recommendations

**Option A — single PR, two commits (preferred):**
1. Commit 1: Phases 1–5 (`chore(fe): standardize attendance pagination, loading, badge types`)
2. Commit 2: Phase 6 (`feat(fe): merge staff attendance into single tabbed view`)
3. Squash into one commit before merge per AGENTS.md workflow

**Option B — two PRs:**
1. PR 1: Phases 1–5 — quick review, low risk, can merge fast
2. PR 2: Phase 6 — bigger review, covers route + sidebar changes

User chose Option A pattern with separate phases for safety.

---

## Acceptance Criteria

- All 7 admin attendance views show identical pagination wrapper styling
- Both bare-loading views show the spinner pattern instead of plain text
- `AttendanceRecordList.vue` attendance status badges render with attendance-status palette (no purple fallback)
- `LeaveRequestList.vue` calendar uses `<DatePagination>` (no inline buttons)
- `HybridScheduleList.vue` tables sit inside `rounded-2xl` card wrapper
- Staff sidebar shows only "My Attendance" link in PERSONAL section
- Staff `/attendance/my-overtime` and `/attendance/my-hybrid-schedule` URLs return 404 (route removed)
- Staff `/attendance/my-attendances` shows tabs: Overview, Corrections (if permitted), Overtime, Hybrid (if hybrid worker)
- `bun run test` — all 981+ tests pass
- `bun run e2e -- attendance` — all attendance E2E specs pass
- No `console.error` warnings in dev mode

---

## Out of Scope (Explicit)

- Adding mobile card view to other admin tables — separate plan
- Staff skeleton loader unification with admin spinner — intentional design split
- Admin/staff table-vs-card pattern split — intentional design split
- BE changes — none needed
- Performance/accessibility audit beyond regression — covered by separate plans

---

## Status

- **Created:** 2026-05-23
- **Status:** COMPLETED
- **Owner:** Antigravity
- **Estimated effort:** Phases 1–5 ≈ 1–2 hours; Phase 6 ≈ 3–4 hours including tests
