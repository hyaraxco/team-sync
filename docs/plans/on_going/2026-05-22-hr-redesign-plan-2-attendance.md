# HR Admin Redesign — Plan 2: Attendance Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Redesign all 10 HR attendance routes to converge on baseline shared components, remove duplicate page titles, and complete page-level dark mode consistency using the Plan 1 foundation.

**Architecture:** Keep existing attendance route/view structure intact. Use Plan 1 shared components (`StatsCard`, `MainCard`, `SearchFilter`, `EmptyState`, `NotificationPanel`, `Header`) as the foundation, then standardize each attendance page in small batches: overview/stats first, settings/forms second, list/table pages third, and calendar/schedule pages last. Favor token cleanup and baseline convergence over refactors.

**Tech Stack:** Vue 3 Composition API, Tailwind CSS v3, CSS custom properties, Pinia stores, Lucide Vue Next, Vitest

---

## File Structure

**Attendance views in scope:**
- `team-sync-fe/src/views/admin/attendance/AttendanceList.vue` — attendance overview/dashboard, nav shortcuts, KPI cards, summary sections
- `team-sync-fe/src/views/admin/attendance/AttendanceSettings.vue` — attendance rules/settings form page
- `team-sync-fe/src/views/admin/attendance/AttendancePeriods.vue` — attendance period management list + modal
- `team-sync-fe/src/views/admin/attendance/PolicyMismatches.vue` — mismatch review table
- `team-sync-fe/src/views/admin/attendance/AttendanceCorrectionList.vue` — corrections approval list
- `team-sync-fe/src/views/admin/attendance/AttendanceRecordList.vue` — detailed attendance record list
- `team-sync-fe/src/views/admin/attendance/LeaveRequestList.vue` — leave request review list
- `team-sync-fe/src/views/admin/attendance/HolidayCalendar.vue` — holiday list/calendar management
- `team-sync-fe/src/views/admin/attendance/HybridScheduleList.vue` — hybrid schedule management
- `team-sync-fe/src/views/admin/attendance/OvertimeManagement.vue` — overtime dashboard/list/modals

**Shared dependencies already used by attendance pages:**
- `team-sync-fe/src/components/common/StatsCard.vue`
- `team-sync-fe/src/components/common/MainCard.vue`
- `team-sync-fe/src/components/common/SearchFilter.vue`
- `team-sync-fe/src/components/common/EmptyState.vue`
- `team-sync-fe/src/components/common/StatusBadge.vue`
- `team-sync-fe/src/components/common/ModalWrapper.vue`
- `team-sync-fe/src/components/common/ConfirmationModal.vue`
- `team-sync-fe/src/components/admin/team/Pagination.vue`
- `team-sync-fe/src/composables/useSearchFilter.js`
- `team-sync-fe/src/composables/useConfirmAction.js`

**Existing tests to update/extend:**
- `team-sync-fe/src/tests/admin/attendance/AttendanceList.smoke.test.js`
- `team-sync-fe/src/tests/admin/attendance/AttendanceSettings.smoke.test.js`
- `team-sync-fe/src/tests/admin/attendance/AttendanceSettings.test.js`
- `team-sync-fe/src/tests/admin/attendance/AttendancePeriods.test.js`
- `team-sync-fe/src/tests/admin/attendance/AttendanceCorrectionList.smoke.test.js`
- `team-sync-fe/src/tests/admin/attendance/AttendanceRecordList.smoke.test.js`
- `team-sync-fe/src/tests/admin/attendance/LeaveRequestList.smoke.test.js`
- `team-sync-fe/src/tests/admin/attendance/HolidayCalendar.smoke.test.js`
- `team-sync-fe/src/tests/admin/attendance/HybridScheduleList.smoke.test.js`
- `team-sync-fe/src/tests/admin/attendance/OvertimeManagement.smoke.test.js`
- `team-sync-fe/src/tests/admin/attendance/PolicyMismatches.smoke.test.js`
- `team-sync-fe/src/tests/admin/attendance/PolicyMismatches.test.js`

---

## Attendance Scope Map

**Routes → views:**
- `/admin/attendances` → `AttendanceList.vue`
- `/admin/attendance-settings` → `AttendanceSettings.vue`
- `/admin/attendance-periods` → `AttendancePeriods.vue`
- `/admin/attendance-policy-mismatches` → `PolicyMismatches.vue`
- `/admin/attendance-corrections` → `AttendanceCorrectionList.vue`
- `/admin/attendance-records` → `AttendanceRecordList.vue`
- `/admin/leave-requests` → `LeaveRequestList.vue`
- `/admin/holiday-calendar` → `HolidayCalendar.vue`
- `/admin/hybrid-schedules` → `HybridScheduleList.vue`
- `/admin/overtime` → `OvertimeManagement.vue`

**High-debt pages by audit:**
- `OvertimeManagement.vue` → heaviest token debt (`text-gray-*` 41, `border-gray-*` 7)
- `HybridScheduleList.vue` → heavy token debt (`text-gray-*` 26, `border-gray-*` 14)
- `AttendanceSettings.vue` → form-heavy token debt (`text-gray-*` 9, `border-gray-*` 8)
- `AttendanceList.vue` → duplicate headings, mixed stats patterns, custom shortcut cards
- `PolicyMismatches.vue` → inline empty state, duplicate `h1`, raw table shell

**Cross-cutting attendance rules for this plan:**
- Remove local page `h1/h2` where Header now owns title/subtitle
- Prefer `StatsCard.vue` for KPI metrics; reserve `MainCard` stat mode for one hero metric max
- Prefer `SearchFilter.vue` for searchable lists already using filters
- Prefer `EmptyState.vue` for all empty screens/sections
- Keep semantic accent colors for statuses only
- Replace `bg-white`, `text-gray-*`, `border-gray-*` with token-based styles or compatible baseline classes
- Keep functionality and store interactions unchanged

---

### Task 1: Standardize Attendance Overview (`AttendanceList.vue`)

**Files:**
- Modify: `team-sync-fe/src/views/admin/attendance/AttendanceList.vue`
- Test: `team-sync-fe/src/tests/admin/attendance/AttendanceList.smoke.test.js`

- [ ] **Step 1: Write failing smoke assertions for standardized overview**

Add or update `team-sync-fe/src/tests/admin/attendance/AttendanceList.smoke.test.js` with assertions for:

```javascript
it('does not render duplicate local h1 title', () => {
    const wrapper = factory()
    expect(wrapper.find('h1').exists()).toBe(false)
})

it('renders StatsCard components for KPI metrics', () => {
    const wrapper = factory()
    expect(wrapper.findComponent({ name: 'StatsCard' }).exists()).toBe(true)
})

it('keeps empty states rendered through EmptyState component', () => {
    const wrapper = factory({ recentAttendance: [], pendingApprovals: [] })
    expect(wrapper.findComponent({ name: 'EmptyState' }).exists()).toBe(true)
})
```

- [ ] **Step 2: Run test to verify it fails**

Run:
```bash
cd team-sync-fe
bun run test src/tests/admin/attendance/AttendanceList.smoke.test.js
```

Expected: FAIL because local `h1` still exists and overview still mixes custom heading/stat layout.

- [ ] **Step 3: Read current overview structure**

Run:
```bash
cd team-sync-fe
rtk grep -n "<h1\|StatsCard\|main-card\|EmptyState" src/views/admin/attendance/AttendanceList.vue
```

Expected: local `h1`, existing `StatsCard` usage, custom overview sections.

- [ ] **Step 4: Remove duplicate headings and standardize KPI section**

In `team-sync-fe/src/views/admin/attendance/AttendanceList.vue`:
- Remove local `<h1>` / extra page-heading wrapper.
- Keep existing stats data logic.
- Convert KPI row so all metrics use `StatsCard` except one hero metric if justified.
- Keep shortcut/action cards but wrap in baseline shells:

```vue
<section class="space-y-6">
    <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
        <StatsCard title="Today present" :value="todayPresent" iconName="UserCheck" colorScheme="blue" />
        <StatsCard title="Late arrivals" :value="lateCount" iconName="Clock3" colorScheme="orange" />
        <StatsCard title="On leave" :value="leaveCount" iconName="CalendarClock" colorScheme="purple" />
        <StatsCard title="Pending actions" :value="pendingCount" iconName="AlertCircle" colorScheme="red" />
    </div>
</section>
```

- [ ] **Step 5: Standardize shortcut cards and section shells**

Use token-compatible shell classes for overview action cards:

```vue
<div class="rounded-2xl border border-brand-border p-5 shadow-sm transition-all duration-200 hover:ring-2 hover:ring-brand-primary/20" :style="{ background: 'var(--color-surface)' }">
```

Replace raw `bg-white` and remove duplicated section titles where Header already covers page title.

- [ ] **Step 6: Run test to verify it passes**

Run:
```bash
cd team-sync-fe
bun run test src/tests/admin/attendance/AttendanceList.smoke.test.js
```

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
cd team-sync-fe
git add src/views/admin/attendance/AttendanceList.vue src/tests/admin/attendance/AttendanceList.smoke.test.js
git commit -m "feat: standardize attendance overview layout"
```

---

### Task 2: Standardize Attendance Settings + Periods forms/lists

**Files:**
- Modify: `team-sync-fe/src/views/admin/attendance/AttendanceSettings.vue`
- Modify: `team-sync-fe/src/views/admin/attendance/AttendancePeriods.vue`
- Test: `team-sync-fe/src/tests/admin/attendance/AttendanceSettings.smoke.test.js`
- Test: `team-sync-fe/src/tests/admin/attendance/AttendanceSettings.test.js`
- Test: `team-sync-fe/src/tests/admin/attendance/AttendancePeriods.test.js`

- [ ] **Step 1: Write failing tests for duplicate headers and tokenized shells**

Add assertions:

```javascript
expect(wrapper.find('h1').exists()).toBe(false)
expect(wrapper.html()).toContain('var(--color-surface)')
```

for both settings and periods test files where page shell is rendered.

- [ ] **Step 2: Run tests to verify they fail**

Run:
```bash
cd team-sync-fe
bun run test src/tests/admin/attendance/AttendanceSettings.smoke.test.js src/tests/admin/attendance/AttendanceSettings.test.js src/tests/admin/attendance/AttendancePeriods.test.js
```

Expected: FAIL due to local `h1` and hardcoded `bg-white`.

- [ ] **Step 3: Read current settings/periods shells**

Run:
```bash
cd team-sync-fe
rtk grep -n "<h1\|bg-white\|border-gray\|text-gray" src/views/admin/attendance/AttendanceSettings.vue src/views/admin/attendance/AttendancePeriods.vue
```

Expected: local page headings + hardcoded card/form colors.

- [ ] **Step 4: Standardize `AttendanceSettings.vue`**

Apply these rules:
- remove local `h1`
- wrap each settings section in baseline shell
- keep Input/Select components unchanged
- convert page sections to:

```vue
<section class="rounded-2xl border border-brand-border p-6 shadow-sm" :style="{ background: 'var(--color-surface)' }">
```

- [ ] **Step 5: Standardize `AttendancePeriods.vue`**

Apply these rules:
- remove local `h1`
- keep `ModalWrapper`, `ConfirmationModal`, `EmptyState`
- update list/table wrapper to baseline shell
- ensure empty state block uses `EmptyState` only, not fallback inline text

- [ ] **Step 6: Run tests to verify they pass**

Run:
```bash
cd team-sync-fe
bun run test src/tests/admin/attendance/AttendanceSettings.smoke.test.js src/tests/admin/attendance/AttendanceSettings.test.js src/tests/admin/attendance/AttendancePeriods.test.js
```

Expected: PASS.

- [ ] **Step 7: Commit**

```bash
cd team-sync-fe
git add src/views/admin/attendance/AttendanceSettings.vue src/views/admin/attendance/AttendancePeriods.vue src/tests/admin/attendance/AttendanceSettings.smoke.test.js src/tests/admin/attendance/AttendanceSettings.test.js src/tests/admin/attendance/AttendancePeriods.test.js
git commit -m "feat: standardize attendance settings and periods pages"
```

---

### Task 3: Standardize Mismatch + Corrections + Records list pages

**Files:**
- Modify: `team-sync-fe/src/views/admin/attendance/PolicyMismatches.vue`
- Modify: `team-sync-fe/src/views/admin/attendance/AttendanceCorrectionList.vue`
- Modify: `team-sync-fe/src/views/admin/attendance/AttendanceRecordList.vue`
- Test: `team-sync-fe/src/tests/admin/attendance/PolicyMismatches.smoke.test.js`
- Test: `team-sync-fe/src/tests/admin/attendance/PolicyMismatches.test.js`
- Test: `team-sync-fe/src/tests/admin/attendance/AttendanceCorrectionList.smoke.test.js`
- Test: `team-sync-fe/src/tests/admin/attendance/AttendanceRecordList.smoke.test.js`

- [ ] **Step 1: Write failing tests for standardized list pages**

Add assertions for each page:

```javascript
expect(wrapper.find('h1').exists()).toBe(false)
expect(wrapper.findComponent({ name: 'SearchFilter' }).exists()).toBe(true)
expect(wrapper.findComponent({ name: 'EmptyState' }).exists()).toBe(true)
```

For `PolicyMismatches.vue`, add an assertion that inline empty markup is removed.

- [ ] **Step 2: Run tests to verify they fail**

Run:
```bash
cd team-sync-fe
bun run test src/tests/admin/attendance/PolicyMismatches.smoke.test.js src/tests/admin/attendance/PolicyMismatches.test.js src/tests/admin/attendance/AttendanceCorrectionList.smoke.test.js src/tests/admin/attendance/AttendanceRecordList.smoke.test.js
```

Expected: FAIL.

- [ ] **Step 3: Standardize `PolicyMismatches.vue`**

Replace duplicate local title and inline empty state with:

```vue
<EmptyState icon="AlertTriangle" title="No policy mismatches" subtitle="Policy mismatch alerts will appear here when attendance entries need review." />
```

Wrap table/list in baseline shell and use token-safe text styles.

- [ ] **Step 4: Standardize `AttendanceCorrectionList.vue` and `AttendanceRecordList.vue`**

Keep `SearchFilter`, `Pagination`, `Alert`, `EmptyState`, `StatusBadge`.
Update wrappers and tables to:
- remove duplicate `h1`
- use `rounded-2xl border border-brand-border`
- replace remaining `text-gray-*` and `border-gray-*`
- keep current filtering/store logic intact

- [ ] **Step 5: Run tests to verify they pass**

Run:
```bash
cd team-sync-fe
bun run test src/tests/admin/attendance/PolicyMismatches.smoke.test.js src/tests/admin/attendance/PolicyMismatches.test.js src/tests/admin/attendance/AttendanceCorrectionList.smoke.test.js src/tests/admin/attendance/AttendanceRecordList.smoke.test.js
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
cd team-sync-fe
git add src/views/admin/attendance/PolicyMismatches.vue src/views/admin/attendance/AttendanceCorrectionList.vue src/views/admin/attendance/AttendanceRecordList.vue src/tests/admin/attendance/PolicyMismatches.smoke.test.js src/tests/admin/attendance/PolicyMismatches.test.js src/tests/admin/attendance/AttendanceCorrectionList.smoke.test.js src/tests/admin/attendance/AttendanceRecordList.smoke.test.js
git commit -m "feat: standardize attendance list pages"
```

---

### Task 4: Standardize Leave Requests + Holiday Calendar

**Files:**
- Modify: `team-sync-fe/src/views/admin/attendance/LeaveRequestList.vue`
- Modify: `team-sync-fe/src/views/admin/attendance/HolidayCalendar.vue`
- Test: `team-sync-fe/src/tests/admin/attendance/LeaveRequestList.smoke.test.js`
- Test: `team-sync-fe/src/tests/admin/attendance/HolidayCalendar.smoke.test.js`

- [ ] **Step 1: Write failing smoke assertions**

Add assertions:

```javascript
expect(wrapper.find('h1').exists()).toBe(false)
expect(wrapper.findComponent({ name: 'EmptyState' }).exists()).toBe(true)
```

and for leave requests also assert `SearchFilter` and `StatusBadge` stay present.

- [ ] **Step 2: Run tests to verify they fail**

Run:
```bash
cd team-sync-fe
bun run test src/tests/admin/attendance/LeaveRequestList.smoke.test.js src/tests/admin/attendance/HolidayCalendar.smoke.test.js
```

Expected: FAIL if local titles/inline markup remain.

- [ ] **Step 3: Standardize `LeaveRequestList.vue`**

Keep:
- `SearchFilter`
- `Pagination`
- `EmptyState`
- `ModalWrapper`
- `StatusBadge`

Update shells, spacing, table/list typography, remove local `h1`, and use token-compatible wrappers.

- [ ] **Step 4: Standardize `HolidayCalendar.vue`**

Keep `MainCard`, `EmptyState`, `ModalWrapper`, `Pagination`, but:
- remove local `h1`
- ensure `MainCard` used as shell only where appropriate
- replace remaining raw `text-gray-*` / `border-gray-*`
- standardize empty state copy to English

- [ ] **Step 5: Run tests to verify they pass**

Run:
```bash
cd team-sync-fe
bun run test src/tests/admin/attendance/LeaveRequestList.smoke.test.js src/tests/admin/attendance/HolidayCalendar.smoke.test.js
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
cd team-sync-fe
git add src/views/admin/attendance/LeaveRequestList.vue src/views/admin/attendance/HolidayCalendar.vue src/tests/admin/attendance/LeaveRequestList.smoke.test.js src/tests/admin/attendance/HolidayCalendar.smoke.test.js
git commit -m "feat: standardize leave requests and holiday calendar"
```

---

### Task 5: Standardize Hybrid Schedules + Overtime

**Files:**
- Modify: `team-sync-fe/src/views/admin/attendance/HybridScheduleList.vue`
- Modify: `team-sync-fe/src/views/admin/attendance/OvertimeManagement.vue`
- Test: `team-sync-fe/src/tests/admin/attendance/HybridScheduleList.smoke.test.js`
- Test: `team-sync-fe/src/tests/admin/attendance/OvertimeManagement.smoke.test.js`

- [ ] **Step 1: Write failing smoke assertions for high-debt pages**

Add assertions:

```javascript
expect(wrapper.find('h1').exists()).toBe(false)
expect(wrapper.html()).toContain('var(--color-surface)')
expect(wrapper.findComponent({ name: 'EmptyState' }).exists()).toBe(true)
```

For overtime, also assert stats section uses `StatsCard` component.

- [ ] **Step 2: Run tests to verify they fail**

Run:
```bash
cd team-sync-fe
bun run test src/tests/admin/attendance/HybridScheduleList.smoke.test.js src/tests/admin/attendance/OvertimeManagement.smoke.test.js
```

Expected: FAIL.

- [ ] **Step 3: Standardize `HybridScheduleList.vue`**

Keep `MainCard`, `EmptyState`, `StatusBadge`, `ModalWrapper`, but:
- remove local `h1`
- replace segmented filter shell with baseline controls
- update table/list wrappers to token-safe styles
- preserve actions and confirm flows

- [ ] **Step 4: Standardize `OvertimeManagement.vue`**

Key changes:
- remove local `h1`
- replace custom KPI cards with `StatsCard.vue`
- keep `SearchFilter`, `Pagination`, `EmptyState`, `ModalWrapper`
- convert modals and list sections to baseline shells
- replace raw `text-gray-*` / `border-gray-*` debt

Example KPI row:

```vue
<div class="grid gap-6 md:grid-cols-2 xl:grid-cols-4">
    <StatsCard title="Pending overtime" :value="pendingCount" iconName="Clock3" colorScheme="orange" />
    <StatsCard title="Approved hours" :value="approvedHours" iconName="CheckCircle2" colorScheme="green" />
    <StatsCard title="Rejected requests" :value="rejectedCount" iconName="XCircle" colorScheme="red" />
    <StatsCard title="This month" :value="monthlyTotal" iconName="BarChart3" colorScheme="blue" />
</div>
```

- [ ] **Step 5: Run tests to verify they pass**

Run:
```bash
cd team-sync-fe
bun run test src/tests/admin/attendance/HybridScheduleList.smoke.test.js src/tests/admin/attendance/OvertimeManagement.smoke.test.js
```

Expected: PASS.

- [ ] **Step 6: Commit**

```bash
cd team-sync-fe
git add src/views/admin/attendance/HybridScheduleList.vue src/views/admin/attendance/OvertimeManagement.vue src/tests/admin/attendance/HybridScheduleList.smoke.test.js src/tests/admin/attendance/OvertimeManagement.smoke.test.js
git commit -m "feat: standardize hybrid schedule and overtime pages"
```

---

## Final Verification

- [ ] **Step 1: Run attendance-focused test suite**

Run:
```bash
cd team-sync-fe
bun run test src/tests/admin/attendance/
```

Expected: All attendance tests pass.

- [ ] **Step 2: Run full frontend test suite**

Run:
```bash
cd team-sync-fe
bun run test
```

Expected: Entire suite passes with no regressions.

- [ ] **Step 3: Manual attendance smoke check**

Run:
```bash
cd team-sync-fe
bun run dev
```

Verify these routes manually:
- `/admin/attendances`
- `/admin/attendance-settings`
- `/admin/attendance-periods`
- `/admin/attendance-policy-mismatches`
- `/admin/attendance-corrections`
- `/admin/attendance-records`
- `/admin/leave-requests`
- `/admin/holiday-calendar`
- `/admin/hybrid-schedules`
- `/admin/overtime`

Checklist:
- [ ] No duplicate page `h1` under Header
- [ ] Baseline white/tokenized card shells consistent
- [ ] Dark mode readable across all 10 pages
- [ ] Search/filter bars consistent where applicable
- [ ] Empty states use `EmptyState.vue`
- [ ] Overtime KPIs use `StatsCard.vue`
- [ ] Policy mismatches no longer use inline empty markup

- [ ] **Step 4: Commit verification notes**

```bash
cd team-sync-fe
git add docs/plans/on_going/2026-05-22-hr-redesign-plan-2-attendance.md
git commit -m "docs: mark attendance redesign verification complete"
```

---

## Success Criteria

- ✅ All 10 attendance routes standardized
- ✅ Duplicate local page headers removed
- ✅ Attendance overview + overtime use consistent KPI cards
- ✅ Search/list attendance pages use baseline shells and filters
- ✅ Empty states standardized via `EmptyState.vue`
- ✅ Dark mode readable across all attendance pages
- ✅ Attendance-focused tests pass
- ✅ Full frontend suite passes

---

## Next Steps

After Plan 2 complete:
- **Plan 3: Performance Domain**
- **Plan 4: Core Admin**
- **Plan 5: Staff/Project/Team**

