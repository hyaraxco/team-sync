# HR Admin Redesign — Design Specification

**Date:** 2026-05-22  
**Scope:** All HR-accessible admin pages in `team-sync-fe`  
**Goal:** Standardize 40+ HR pages on baseline design system + full dark mode + premium depth

---

## Context

User requested redesign of HR admin views to match baseline quality of `/admin/staff-members`, `/admin/projects`, `/admin/teams`. Initial scope was 13 pages; comprehensive audit expanded to **40+ HR routes**.

**Key pain points:**
- Inconsistent stats cards (custom colored cards vs `StatsCard.vue`)
- Custom search/filter UIs instead of `SearchFilter.vue`
- Empty state drift (inline markup vs `EmptyState.vue`)
- Button style inconsistency (raw `bg-blue-*` vs `.btn-primary` gradient)
- Dark mode broken across most pages (hardcoded `bg-white`, `text-gray-*`)
- Missing page titles in `Header.vue` (20+ routes fall back to "Dashboard")
- Notification panel has no "See all" link + light-only scoped CSS

**Baseline "good" pages:**
- `src/views/admin/staff-member/StaffMemberList.vue`
- `src/views/admin/project/ProjectList.vue`
- `src/views/admin/team/TeamList.vue`

**Design system:**
- Font: Plus Jakarta Sans
- Colors: CSS-variable-based dark mode (no `dark:` Tailwind classes)
- Components: `StatsCard.vue`, `MainCard.vue`, `SearchFilter.vue`, `EmptyState.vue`, `StatusBadge.vue`
- Buttons: `.btn-primary blue-gradient blue-btn-shadow`
- Icons: Lucide Vue Next
- Spacing: 4-space indentation, `tabular-nums` for numbers

---

## Approach

**Baseline Convergence** — standardize all HR pages on proven baseline patterns + full CSS-variable token migration + premium depth enhancements.

**Why this approach:**
- Strongest consistency — entire admin feels like one system
- Dark mode works everywhere via tokens
- Easiest maintenance — fewer custom patterns
- Matches user choice: "same baseline + more premium depth + stronger dark mode"

**Trade-offs:**
- ✅ Highest quality, best long-term maintainability
- ❌ Largest file count (~40 views + 6 shared components)
- ❌ Highest test surface — need to verify all pages render correctly

---

## Design Sections

### 1. Shared Component Token Migration

**Goal:** Full CSS-variable dark mode for all shared components.

**Components to migrate:**
- `src/components/common/StatsCard.vue`
- `src/components/common/MainCard.vue`
- `src/components/common/EmptyState.vue`
- `src/components/common/SearchFilter.vue`
- `src/components/common/StatusBadge.vue` (verify only — already token-based per audit)
- `src/components/admin/NotificationPanel.vue`

**Token strategy:**
- Replace all `bg-white`, `bg-gray-*`, `text-gray-*`, `border-gray-*` with CSS vars from `input.css`
- Use existing tokens: `var(--main-bg)`, `var(--card-bg)`, `var(--text-primary)`, `var(--text-secondary)`, `var(--border-color)`
- If tokens missing, add to `input.css` under `:root` and `.dark` selectors
- Remove hardcoded light-only scoped CSS (e.g., `NotificationPanel.vue` gradients)
- Keep accent colors (`blue-*`, `green-*`, `red-*`) as-is — they're semantic, not theme-dependent

**Example transformation:**
```vue
<!-- Before -->
<div class="bg-white border border-gray-200 text-gray-900">

<!-- After -->
<div class="border" style="background: var(--card-bg); border-color: var(--border-color); color: var(--text-primary);">
```

**Verification:**
- Toggle dark mode via `useDarkMode` composable
- Check each component in isolation (Vitest component test or manual dev server check)
- Ensure no white flash or hardcoded light colors remain

---

### 2. Target Page Standardization

**Goal:** Converge all 40+ HR pages on baseline component patterns.

**Pages in scope:**
- Dashboard, Settings, Notifications, Meetings, Analytics
- All staff-member routes (list, create, edit, detail, success)
- All project routes (list, create, edit, detail)
- All team routes (list, create, edit, detail)
- All attendance routes (list, settings, periods, mismatches, corrections, records, leave-requests, holidays, hybrid-schedules, overtime)
- All performance routes (cycles, cycles.create, cycles.detail, outcome-rules, templates, my-reviews, pending-calibration, review.detail, my-goals, feedback.received, feedback.given, feedback.give)

**Standardization rules:**

**Stats cards:**
- Use `StatsCard.vue` for all KPI metrics (white card, accent border, icon tile, `tabular-nums`)
- Use `MainCard.vue` stat mode ONLY for one hero metric per page (dark gradient)
- Remove custom colored stat cards (`bg-primary-50`, `bg-purple-50`, etc.)
- Ensure all numeric values use `tabular-nums` class
- Icon tile size: `w-12 h-12` (increase from `w-10 h-10`)

**Search/filter:**
- Replace custom search inputs with `SearchFilter.vue` (rounded-2xl, brand border, debounced)
- Keep domain-specific filters (date ranges, status dropdowns) but wrap in consistent card shell
- Pages to update: MeetingList, AnalyticsDashboard, ReviewCycleList, PendingCalibration, MyReviews, FeedbackReceived, OvertimeManagement, HybridScheduleList

**Empty states:**
- Replace all inline empty markup with `EmptyState.vue`
- Add missing icons to `EmptyState.vue`: `Video`, `Bell`, `Target`, `BarChart3`, `Layout`, `Calendar`
- Fix typo: `"Data kosong found"` → `"No data found"`
- Standardize English copy: clear title + actionable subtitle
- Icon size: `w-16 h-16` (increase from `w-12 h-12`)
- Title: `text-xl font-semibold` (increase from `text-lg`)
- Spacing: `space-y-4` between icon/title/description/action

**Buttons:**
- Primary CTA: `.btn-primary blue-gradient blue-btn-shadow rounded-lg`
- Secondary: `border-brand-border rounded-lg hover:ring-2 hover:ring-brand-primary/20`
- Destructive: create dedicated danger button variant (not `btn-primary + bg-danger-600`)
- Padding: `px-6 py-3` for primary actions (currently mixed)
- Pages to update: MeetingList, ReviewCycleList, ReviewDetail, FeedbackReceived, OvertimeManagement, TeamDetail

**Tables/lists:**
- White card shell: `bg-white border border-brand-border rounded-2xl p-4 sm:p-5`
- Hover states: `hover:ring-2 hover:ring-brand-primary/20` (not `hover:border-2` — causes layout shift)
- Status badges: use `StatusBadge.vue` + `badgeUtils.js`
- Row height: `min-h-[60px]` for comfortable touch targets
- Shadow: `shadow-sm hover:shadow-md transition-shadow duration-200`

**Pagination:**
- Use `components/admin/team/Pagination.vue` (already shared by staff/project/team)
- Remove custom pagination from meetings/notifications

**Dark mode token migration:**
- Replace all `bg-white`, `text-gray-*`, `border-gray-*` with CSS variables
- Pages with heaviest debt: MeetingList, AnalyticsDashboard, OvertimeManagement, PendingCalibration, ReviewCycleList, ReviewDetail, MyReviews, FeedbackReceived, HybridScheduleList, TemplateManagement, PolicyMismatches, Notifications
- Use tokens: `var(--card-bg)`, `var(--text-primary)`, `var(--text-secondary)`, `var(--border-color)`
- Keep semantic accent colors (blue/green/red/amber for status/categories)

---

### 3. Header Title Map Completion

**Goal:** Fix missing page titles in `Header.vue` so all HR routes show correct titles instead of falling back to "Dashboard".

**Missing routes to add:**
- `admin.settings` → "Settings"
- `admin.analytics` → "Analytics"
- `admin.meetings` → "Meetings"
- `admin.attendance.settings` → "Attendance Settings"
- `admin.attendance.periods` → "Attendance Periods"
- `admin.attendance.mismatches` → "Policy Mismatches"
- `admin.attendance.corrections` → "Attendance Corrections"
- `admin.attendance.records` → "Attendance Records"
- `admin.attendance.leave-requests` → "Leave Requests"
- `admin.attendance.holidays` → "Holiday Calendar"
- `admin.attendance.hybrid-schedules` → "Hybrid Schedules"
- `admin.attendance.overtime` → "Overtime Management"
- `admin.performance.cycles` → "Review Cycles"
- `admin.performance.cycles.create` → "Create Review Cycle"
- `admin.performance.cycles.detail` → "Review Cycle Details"
- `admin.performance.outcome-rules` → "Outcome Rules"
- `admin.performance.templates` → "Review Templates"
- `admin.performance.my-reviews` → "My Reviews"
- `admin.performance.pending-calibration` → "Pending Calibration"
- `admin.performance.review.detail` → "Review Details"
- `admin.performance.my-goals` → "My Goals"
- `admin.performance.feedback.received` → "Feedback Received"
- `admin.performance.feedback.given` → "Feedback Given"
- `admin.performance.feedback.give` → "Give Feedback"

**Implementation:**
- Extend `pageTitles` computed property in `Header.vue`
- Match route names from router files (`analytics.js`, `meeting.js`, `performance.js`, `attendance.js`)
- Remove duplicate `h1/h2` tags from 20+ view files:
  - Settings, Notifications, MeetingList, AnalyticsDashboard, AttendanceList, PolicyMismatches, HybridScheduleList, OvertimeManagement
  - ReviewCycleList, ReviewCycleDetail, TemplateManagement, PendingCalibration, ReviewDetail, MyReviews, MyGoals, FeedbackReceived
  - StaffMemberDetail, TeamDetail

---

### 4. Notification Panel Enhancement

**Goal:** Add "See all notifications" link + full dark mode token migration.

**Changes:**
- Add footer link below notification list: "See all notifications" → routes to `/admin/notifications`
- Remove hardcoded light-only scoped CSS:
  - `background: linear-gradient(180deg, #ffffff 0%, #fcfdff 100%)`
  - Blue shadow `0 4px 6px -1px rgba(59, 130, 246, 0.1)`
  - All `bg-white`, `text-gray-*`, `border-gray-*`, `bg-blue-*`
- Replace with CSS variables: `var(--card-bg)`, `var(--text-primary)`, `var(--border-color)`
- Keep accent colors (blue/green/red/amber for notification types) — semantic, not theme-dependent
- Footer link style: `text-brand-primary hover:text-brand-primary-dark font-medium text-sm`

**Layout:**
```
┌─ NotificationPanel ─────────┐
│ [5 latest notifications]    │
│ ...                          │
│ ─────────────────────────    │
│ See all notifications →      │
└──────────────────────────────┘
```

---

### 5. Premium Depth Enhancements

**Goal:** Elevate visual quality beyond baseline — better spacing, softer surfaces, refined interactions.

**Spacing improvements:**
- Increase section vertical spacing from `space-y-6` to `space-y-8` on main page containers
- Card internal padding: `p-6` minimum (currently mixed `p-4/p-5/p-6`)
- Stats card grid: `gap-6` instead of `gap-4`
- Table row height: `min-h-[60px]` for comfortable touch targets
- Form field spacing: `space-y-4` between fields, `space-y-6` between sections

**Surface refinements:**
- Card shadows: replace flat borders with subtle elevation
  - Default: `shadow-sm` (existing Tailwind)
  - Hover: `hover:shadow-md transition-shadow duration-200`
  - Keep `border-brand-border` for structure
- Stats card icon tiles: increase from `w-10 h-10` to `w-12 h-12`
- Button padding: `px-6 py-3` for primary actions (currently mixed)
- Border radius consistency: `rounded-xl` for cards (20px from config), `rounded-lg` for buttons/inputs

**Interaction polish:**
- All interactive elements: `transition-all duration-200 ease-in-out`
- Hover states: `hover:ring-2 hover:ring-brand-primary/20` (not `hover:border-2` — causes layout shift)
- Focus states: already handled by `input.css` focus-visible styles
- Loading states: skeleton loaders matching layout shape (not generic spinners)
- Disabled states: `opacity-50 cursor-not-allowed` consistently applied

**Typography refinements:**
- Page titles (Header.vue): `text-2xl font-bold` (currently `text-xl`)
- Section headers: `text-xl font-semibold` (currently mixed)
- Body text: `text-base leading-relaxed` (increase line-height from default)
- Numeric values: always `tabular-nums font-medium`
- Labels: `text-sm font-medium text-brand-light` consistently

**Empty state improvements:**
- Icon size: `w-16 h-16` (currently `w-12 h-12`)
- Icon color: `text-brand-light` with subtle opacity
- Title: `text-xl font-semibold` (currently `text-lg`)
- Spacing: `space-y-4` between icon/title/description/action

---

## Implementation Priorities

1. **Shared components first** (Section 1) — highest leverage, affects all pages
2. **Header title map** (Section 3) — quick win, removes duplicate titles
3. **Notification panel** (Section 4) — isolated, high user visibility
4. **Page standardization** (Section 2) — largest scope, batch by domain:
   - Attendance pages (10 routes)
   - Performance pages (14 routes)
   - Staff/Project/Team pages (12 routes)
   - Dashboard/Analytics/Meetings/Settings/Notifications (5 routes)
5. **Premium depth polish** (Section 5) — final pass across all pages

---

## Testing Strategy

**Unit tests (Vitest):**
- Shared component dark mode: toggle `useDarkMode`, verify no hardcoded light colors
- `EmptyState.vue`: verify new icons render correctly
- `NotificationPanel.vue`: verify footer link routes correctly

**Visual regression:**
- Screenshot all 40+ HR pages in light + dark mode
- Compare before/after for unintended changes

**E2E (Playwright):**
- Smoke test critical flows: staff list, meeting list, attendance list, performance cycles
- Verify no broken layouts, missing data, or console errors

**Manual QA:**
- Toggle dark mode on every HR page
- Verify stats cards, empty states, buttons, tables render consistently
- Check responsive behavior (mobile/tablet/desktop)

---

## Risks & Mitigations

| Risk | Likelihood | Mitigation |
|------|-----------|------------|
| Breaking existing tests | High | Run `bun run test` after each component migration; fix tests incrementally |
| Dark mode regressions | Medium | Visual regression screenshots; manual QA checklist |
| Performance impact (40+ files) | Low | Changes are CSS-only; no new JS logic |
| Scope creep (40+ pages) | Medium | Batch by domain; ship incrementally if needed |
| Missing CSS variables | Low | Audit `input.css` first; add missing tokens before page migration |

---

## Success Criteria

- ✅ All 40+ HR pages use baseline components (`StatsCard`, `SearchFilter`, `EmptyState`, `StatusBadge`)
- ✅ Dark mode works on all HR pages (no white flash, no hardcoded light colors)
- ✅ All HR routes show correct page titles in `Header.vue`
- ✅ Notification panel has "See all" link + dark mode support
- ✅ Premium depth enhancements applied (spacing, shadows, typography, interactions)
- ✅ All existing tests pass (981 Vitest + 109 Playwright)
- ✅ No console errors or broken layouts
- ✅ English wording only (no Indonesian copy)

---

## Out of Scope

- Finance-only payroll operations pages (not HR-accessible)
- Staff self-service pages (separate role tree)
- Manager-only team pulse features
- Backend API changes
- New features or functionality
- Multi-tenancy considerations
- Mobile app (this is SPA only)

---

## Notes

- **Language:** All copy must be English (user requirement)
- **No TypeScript:** Project uses JS only
- **No `dark:` classes:** Use CSS variables only
- **No `h-screen`:** Use `min-h-[100dvh]` for full-height layouts
- **No `hover:border-2`:** Use `hover:ring-2` to avoid layout shift
- **Bun only:** No npm commands
- **4-space indentation:** Everywhere (PHP, JS, Vue)
- **Composition API only:** No Options API
- **API calls in stores only:** Never in components
