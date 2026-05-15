# Page Design Audit & Improvement Plan

> Created: 2026-05-16
> Status: IN PROGRESS
> Scope: All 55 Vue view files in team-sync-fe/src/views/ + shared components + design tokens
> Framework: Nielsen's 10 UX Heuristics + UX Laws (Fitts, Hick, Gestalt, Miller, Jakob) + AI Slop Detection
> Skills applied: `design-taste-frontend`, `redesign-existing-projects`
> Party Mode reviewed by: Eka (Frontend), Budi (PM), Fitri (QA)

---

## Audit Summary

### Total Pages Audited: 55

| Domain | Count | Status |
|--------|-------|--------|
| Admin (general) | 4 | Needs improvement |
| Admin/Attendance | 10 | Needs improvement |
| Admin/Payroll | 9 | Needs improvement |
| Admin/Performance | 15 | Needs improvement |
| Admin/Project | 4 | Needs improvement |
| Admin/Team | 4 | Needs improvement |
| Admin/Meeting | 1 | Needs improvement |
| Admin/Analytics | 1 | Needs improvement |
| Admin/Staff-Member | 5 | Needs improvement |
| Staff-Member | 8 | Needs improvement |
| Auth | 4 | Minor issues |
| Setup | 1 | Minor issues |
| NotFound | 1 | OK |

---

## Design Error Categories

### D1. Inconsistent Gradient Usage (AI Slop Pattern)
**Severity:** Medium | **Affected:** 26 files

Generic `bg-gradient-to-br from-primary-500 to-primary-600` and `bg-gradient-to-r from-blue-600 to-blue-700` used everywhere. This is the #1 AI design tell — LLMs default to gradients instead of intentional color choices.

**Files affected:**
- `admin/team/TeamDetail.vue:610` — red gradient button with `shadow-lg`
- `admin/team/TeamCreate.vue:148` — avatar placeholder gradient
- `admin/team/TeamEdit.vue:171` — avatar placeholder gradient
- `admin/project/ProjectCreate.vue:559` — card background gradient
- `admin/project/ProjectEdit.vue:563` — card background gradient
- `admin/project/ProjectDetail.vue:222,488` — card gradient + red gradient button
- `admin/performance/ReviewCycleDetail.vue:302` — icon gradient
- `admin/performance/PendingCalibration.vue:107,121` — stat card gradients (blue + purple)
- `admin/performance/TeamReviews.vue:119,133,147` — stat card gradients (orange, green, blue)
- `staff-member/MyAttendance.vue:656,822,936` — stat icon gradients
- `staff-member/MyPayslips.vue:312` — button gradient
- `staff-member/PayslipDetail.vue:204,239,490` — button + card gradients
- `staff-member/StaffMemberProfile.vue:364` — team card gradient
- `staff-member/StaffMemberProfileEdit.vue:141` — avatar gradient
- `staff-member/StaffMemberTeam.vue:159` — avatar gradient
- `setup/SetupWizard.vue:148,155` — page background + logo gradient

**Fix:** Replace gradients with solid brand colors. Use `bg-brand-primary` or `bg-[#0C51D9]` for primary actions. For stat cards, use solid tint backgrounds (`bg-blue-50`, `bg-green-50`) without gradients. For avatar placeholders, use `bg-gray-200` with a Lucide icon.

---

### D2. Inconsistent Border Radius (Design System Violation)
**Severity:** Medium | **Affected:** 40+ files

The project uses arbitrary border radius values everywhere: `rounded-[8px]`, `rounded-[12px]`, `rounded-[16px]`, `rounded-[20px]`, `rounded-[24px]`, `rounded-[32px]`. No consistent scale.

**Current arbitrary values found:**
- `rounded-[8px]` — buttons, small cards
- `rounded-[12px]` — inputs, dropdowns, small cards
- `rounded-[16px]` — medium cards, profile sections
- `rounded-[20px]` — large cards, payroll settings cards
- `rounded-[24px]` — setup wizard cards
- `rounded-[32px]` — upgrade plan cards (excessive)

**Fix:** Standardize to Tailwind's built-in scale:
- `rounded-lg` (8px) — buttons, inputs, small elements
- `rounded-xl` (12px) — cards, modals, dropdowns
- `rounded-2xl` (16px) — large containers, profile sections
- `rounded-3xl` (24px) — hero sections (rare)
- `rounded-full` — avatars, badges, pills

Remove ALL arbitrary `rounded-[Npx]` values.

---

### D3. Inconsistent Shadow Usage
**Severity:** Low-Medium | **Affected:** 30+ files

Shadows range from Tailwind defaults (`shadow-sm`, `shadow-lg`, `shadow-xl`) to arbitrary custom values (`shadow-[0_8px_30px_rgb(0,0,0,0.04)]`, `shadow-[0_1px_2px_rgba(15,23,42,0.03)]`, `shadow-[0_20px_50px_rgba(12,81,217,0.15)]`).

**Fix:** Standardize to a 3-tier system:
- `shadow-sm` — cards at rest, inputs
- `shadow-md` — cards on hover, dropdowns
- `shadow-lg` — modals, overlays, floating elements

Remove all arbitrary `shadow-[...]` values. Use CSS variables if custom shadows are truly needed.

---

### D4. Spacing & Density Inconsistencies
**Severity:** High | **Affected:** Most pages

#### D4a. Inconsistent Section Gaps
Pages use varying padding: `p-4`, `p-5`, `p-6`, `p-8` with no pattern. Section gaps vary between `mb-4`, `mb-5`, `mb-6`, `mb-8`, `gap-4`, `gap-5`, `gap-6`.

**Fix:** Standardize spacing scale:
- Card internal padding: `p-5` (20px) consistently
- Section gap: `mb-6` (24px) consistently
- Page-level gap: `space-y-6` on parent container
- Form field gap: `space-y-4` within forms

#### D4b. Cramped Data Tables
Several list/detail pages have tables with insufficient row padding (`px-3 py-2`), making dense data hard to scan.

**Files:**
- `admin/attendance/AttendanceList.vue:150-186` — card rows with `p-4` but dense content
- `admin/payroll/PayrollDashboard.vue:505` — progress bars cramped
- `admin/payroll/PayrollSettings.vue:455-928` — cards with `p-6` but nested content cramped

**Fix:** Increase table row padding to `px-4 py-3` minimum. Add `gap-3` between columns.

#### D4c. Excessive Whitespace in Auth Pages
Auth pages (`Login.vue`, `ForgotPassword.vue`, `ResetPassword.vue`) use `min-h-screen` centering with large empty zones. On desktop this creates awkward whitespace.

**Fix:** Add subtle background pattern or illustration to fill void. Keep `min-h-[100dvh]` (not `h-screen`) for mobile safety.

---

### D5. Visual Hierarchy Issues
**Severity:** High | **Affected:** 20+ files

#### D5a. Missing Heading Size Hierarchy
Many pages use `text-lg font-semibold` for section titles and `text-base font-medium` for subtitles — the size difference is too small to create clear hierarchy.

**Fix:** Standardize heading scale:
- Page title: `text-2xl font-bold tracking-tight`
- Section title: `text-lg font-semibold`
- Subsection title: `text-base font-medium`
- Body text: `text-sm` (not `text-base` for dense UI)
- Caption/helper: `text-xs text-gray-500`

#### D5b. Stat Cards Lack Visual Differentiation
Dashboard pages (PayrollDashboard, AnalyticsDashboard, PendingCalibration, TeamReviews) use identical card structures for different metric types. No visual distinction between "good" (green), "warning" (amber), and "bad" (red) metrics.

**Files:**
- `admin/payroll/PayrollDashboard.vue:393-550`
- `admin/performance/PendingCalibration.vue:107-121`
- `admin/performance/TeamReviews.vue:119-147`

**Fix:** Use color-coded left borders or icon backgrounds to differentiate metric types. Example: green left border for positive metrics, amber for warnings, red for alerts.

#### D5c. Action Buttons Blend with Content
Primary action buttons ("Create", "Save", "Submit") often use the same visual weight as secondary buttons ("Cancel", "Back"). No clear primary/secondary distinction.

**Files:**
- `admin/team/TeamCreate.vue` — create/cancel buttons same weight
- `admin/project/ProjectCreate.vue` — same issue
- `admin/payroll/PayrollSettings.vue:928` — save button visually same as cancel

**Fix:** Primary actions: solid `bg-brand-primary text-white`. Secondary actions: `border border-gray-300 text-gray-700 hover:bg-gray-50`. Destructive actions: `text-red-600 hover:bg-red-50`.

---

### D6. UX Heuristic Violations

#### D6a. Visibility of System Status (Heuristic #1)
**Severity:** Medium | **Affected:** 15+ files

- Loading spinners are generic `animate-spin rounded-full` without context. User doesn't know WHAT is loading.
- No skeleton loaders for data-heavy pages (PayrollDashboard, AnalyticsDashboard, StaffMemberDetail).
- Form submission has no "saving..." state — button just disables.

**Fix:**
- Replace generic spinners with skeleton loaders matching layout shape
- Add "Saving..." text to submit buttons during async operations
- Add loading overlay for full-page operations (payroll generation)

#### D6b. User Control & Freedom (Heuristic #6)
**Severity:** Medium | **Affected:** 10+ files

- No "Clear all filters" button on list pages with multiple filter inputs
- Date range pickers have no "Reset to default" option
- Bulk action confirmations have no "Undo" option after execution

**Files:**
- `admin/attendance/AttendanceList.vue` — filters with no clear-all
- `admin/payroll/PayrollDashboard.vue` — month selector with no reset
- `admin/performance/ReviewCycleList.vue` — filters with no clear-all

**Fix:** Add "Clear all" button next to filter groups. Add toast with "Undo" action after destructive bulk operations.

#### D6c. Consistency & Standards (Heuristic #4)
**Severity:** High | **Affected:** 30+ files

**Button patterns vary wildly:**
- Some use `btn-primary` class, others use inline `bg-[#0C51D9]`
- Some use `blue-gradient blue-btn-shadow` custom classes, others use plain Tailwind
- Icon buttons use `p-1`, `p-2`, `w-10 h-10`, `w-8 h-8` with no pattern

**Card patterns vary:**
- Some use `MainCard` component, others use raw `div` with classes
- Card borders: `border-[#DCDEDD]` vs `border-gray-200` vs `border-gray-300`
- Card hover effects: some have `hover:border-[#0C51D9]`, some don't

**Form input patterns vary:**
- Some use `border-gray-300`, others use `border-[#DCDEDD]`
- Focus rings: `focus:ring-brand-primary` vs `focus:ring-[#0C51D9]` vs `focus:ring-blue-500`

**Fix:** Create/audit design tokens in `tailwind.config.js`:
```js
colors: {
  border: { DEFAULT: '#DCDEDD' },
  primary: { DEFAULT: '#0C51D9' },
}
```
Then enforce: ALL borders use `border-border`, ALL primary actions use `bg-primary`, ALL focus rings use `focus:ring-primary/20`.

#### D6d. Error Prevention (Heuristic #7)
**Severity:** Medium | **Affected:** 8+ files

- Payroll generation has no "preview" step before bulk creation
- Delete actions on list pages use `window.confirm()` in some places, custom `ConfirmationModal` in others
- No "Are you sure?" for bulk status changes (approve/reject leave requests)

**Fix:** Standardize on `ConfirmationModal` component for ALL destructive actions. Add preview step for payroll generation.

#### D6e. Recognition Rather Than Recall (Heuristic #8)
**Severity:** Low-Medium | **Affected:** 12+ files

- Status badges use color-only indicators (green dot, red dot) without text labels
- Icon-only buttons without tooltips (edit pencil, delete trash)
- Abbreviated column headers ("FTE", "WTE", "YTD") without explanation

**Files:**
- `admin/attendance/AttendanceRecordList.vue` — status dots without labels
- `admin/project/ProjectList.vue` — status badges color-only
- `admin/payroll/PayrollDashboard.vue` — abbreviated metrics

**Fix:** Add text labels alongside color indicators. Add `title` attribute or tooltip component for icon-only buttons. Expand abbreviations on hover or add info icon with tooltip.

---

### D7. UX Law Violations

#### D7a. Hick's Law (Choice Overload)
**Severity:** Medium | **Affected:** 6+ files

- PayrollSettings.vue has 7+ settings sections visible at once — overwhelming
- StaffMemberCreate.vue has 4-step form with 20+ fields per step
- PayrollDashboard.vue sidebar has 6 navigation options — too many at once

**Fix:**
- Group settings into collapsible sections (PayrollSettings)
- Add progress indicators and field grouping (StaffMemberCreate)
- Prioritize top 3 actions, move rest to "More" dropdown (PayrollDashboard)

#### D7b. Fitts's Law (Target Size)
**Severity:** Low | **Affected:** Partially addressed in Phase 4

Phase 4 already fixed close/dismiss buttons to min 24x24px. Remaining issues:
- Small clickable areas on status badges (`px-2 py-0.5`)
- Small dropdown menu items in AnalyticsDashboard

**Fix:** Ensure ALL interactive elements have min `min-w-6 min-h-6` (already done for close buttons, extend to badges and menu items).

#### D7c. Gestalt Principles (Proximity & Similarity)
**Severity:** Medium | **Affected:** 15+ files

- Related form fields not visually grouped (e.g., address fields separated by large gaps)
- Unrelated sections placed too close together (no clear visual separation)
- Similar-looking elements used for different purposes (e.g., same card style for stats and action items)

**Files:**
- `admin/payroll/PayrollCreate.vue` — form fields not grouped by logical section
- `admin/staff-member/StaffMemberCreate.vue` — same issue
- `admin/attendance/AttendanceSettings.vue` — settings sections blend together

**Fix:** Use visual grouping:
- Related fields: wrap in `div` with `space-y-3` and shared background
- Unrelated sections: `mb-6` gap + optional divider line
- Different purposes: different card styles (stats = colored left border, actions = plain white)

#### D7d. Miller's Law (Chunking)
**Severity:** Low-Medium | **Affected:** 5+ files

- Tables with 10+ columns on desktop — impossible to scan
- Long forms with 20+ fields without section breaks
- Dashboard pages showing 8+ metrics at once

**Files:**
- `admin/payroll/PayrollReadiness.vue` — table with many columns
- `admin/attendance/AttendanceList.vue` — dense table
- `admin/payroll/PayrollDashboard.vue` — 8+ metrics

**Fix:**
- Hide non-essential columns by default, show via "Show all columns" toggle
- Break long forms into logical sections with heading + divider
- Prioritize top 4-5 metrics, move rest to "View details" link

---

### D8. AI Slop Patterns (Beyond Gradients)

#### D8a. Generic Spinner Loaders
**Severity:** Low | **Affected:** 20+ files

Every page uses `<div class="animate-spin rounded-full h-8 w-8 border-b-2 border-brand-primary"></div>` — the most common AI-generated loading pattern.

**Fix:** Replace with skeleton loaders matching the actual content shape. Use `animate-pulse` on placeholder rectangles.

#### D8b. Centered Empty States with Generic Icons
**Severity:** Low | **Affected:** Partially addressed in Phase 4

Phase 4 replaced inline empty states with `EmptyState` component. Remaining issue: all empty states use the same centered layout with icon + title + subtitle. No variation.

**Fix:** Vary empty state layouts by context:
- List pages: centered icon + text + action button (current)
- Dashboard: illustration + "Get started" CTA
- Search results: magnifying glass + "No results for 'X'" + "Clear filters"

#### D8c. "Inter" Font (If Present)
**Severity:** ~~Check needed~~ VERIFIED: Font is `Plus Jakarta Sans` — NOT Inter. **Passes design-taste check.**

---

### D9. Dark Mode Inconsistencies
**Severity:** Low | **Affected:** 8+ files

Some pages have `dark:` variants (`dark:bg-gray-800`, `dark:border-gray-700`), others don't. The dark mode support is partial and inconsistent.

**Files with dark mode:**
- `admin/payroll/PayrollDashboard.vue`
- `admin/UpgradePlan.vue`
- `components/common/MainCard.vue` (`dark:bg-gray-800 dark:border-gray-700`)
- `input.css` (dark mode overrides for nav-link, section-title, brand colors)

**Files without dark mode:**
- All attendance pages
- All performance pages
- All staff-member pages

**Fix:** Remove partial `dark:` classes — dark mode is not a supported feature. Full dark mode is deferred as a separate project.

---

### D10. Critical Design System Issues (Deep Audit)
**Severity:** HIGH | **Root cause of most other issues**

#### D10a. `tailwind.config.js` is Nearly Empty
The Tailwind config only defines `primary` color (50/100/500/600/700/900). ALL other design tokens live in `input.css` as raw CSS utility classes (`.text-brand-dark`, `.text-brand-light`, `.text-success`, `.text-danger`, `.blue-gradient`, `.blue-btn-shadow`, `.main-card`, `.btn-primary`, etc.).

This means:
- Tailwind's intellisense and JIT don't know about brand colors → developers hardcode hex values
- `border-[#DCDEDD]` used 40+ times instead of a semantic `border-brand-border` token
- `bg-[#0C51D9]` used instead of `bg-primary-500` (which exists but isn't used!)
- No `success`, `danger`, `warning` color scales in config

**Fix:** Migrate design tokens FROM `input.css` INTO `tailwind.config.js`:
```js
colors: {
  'brand-dark': '#0C1C3C',
  'brand-light': '#6B7280',
  'brand-border': '#DCDEDD',
  success: { DEFAULT: '#059669', 50: '#ECFDF5', ... },
  danger: { DEFAULT: '#DC2626', 50: '#FEF2F2', ... },
  warning: { DEFAULT: '#D97706', 50: '#FFFBEB', ... },
}
```

#### D10b. `.btn-primary` CSS Class is Hollow
`.btn-primary`, `.btn-secondary`, `.btn-details` classes in `input.css` only define `display: flex; align-items: center; justify-content: center; gap: 0.375rem` — NO colors, backgrounds, padding, or borders. Actual button styling is inline everywhere with different patterns:
- `bg-[#0C51D9] text-white` (Login.vue)
- `blue-gradient blue-btn-shadow border-[#2151A0]` (ProjectList.vue)
- `bg-primary-600 hover:bg-primary-700 text-white` (various)
- `bg-brand-primary` (some stores/views)

**Fix:** Define actual button styles in Tailwind config OR create Button.vue component with variant props.

#### D10c. `hover:border-2` Causes Layout Shift
`StatsCard.vue` and multiple views use `hover:border-[#0C51D9] hover:border-2` — changing border from 1px to 2px on hover causes 1px layout shift (content jumps).

**Fix:** Replace with `hover:ring-2 hover:ring-primary-500/20` (ring doesn't affect layout) or use `border-2 border-transparent hover:border-primary-500`.

#### D10d. `h-screen` in Admin.vue Layout
`Admin.vue` uses `flex h-screen overflow-hidden` — causes viewport jumping on iOS Safari when address bar collapses.

**Fix:** Replace with `flex min-h-[100dvh] overflow-hidden` (safe dynamic viewport unit).

#### D10e. Badge CSS Classes All Identical
8 badge classes in `input.css` (`.badge-expert`, `.badge-intermediate`, `.badge-beginner`, etc.) ALL have identical styles: `bg: #EBF8FF`, `color: #1E40AF`. No visual differentiation between skill levels.

**Fix:** Give each badge its own color from the semantic palette (expert=green, intermediate=blue, beginner=amber, etc.).

#### D10f. Missing `tabular-nums` on Financial Data
HRIS app displays currency (IDR), percentages, dates, counts everywhere — but no `font-variant-numeric: tabular-nums`. Numbers misalign in tables and stat cards because digits have different widths in proportional fonts.

**Fix:** Add `tabular-nums` to all numeric display elements (stat cards, tables, payroll amounts). Add to Tailwind base: `.tabular-nums { font-variant-numeric: tabular-nums; }` or use `font-mono` for key numeric displays.

#### D10g. Inline `.main-card` Duplication
`AttendanceList.vue` manually recreates the `.main-card` dark gradient stat card instead of using `MainCard` component. Several other views do the same.

**Fix:** Audit all views for inline `.main-card` usage and replace with `<MainCard>` component.

#### D10h. Multiple Accent Colors in Charts
ApexCharts uses `#8B5CF6` (purple) + `#3B82F6` (blue) + `#10B981` (green) as chart colors. The purple violates the single-accent-color rule and creates the "AI purple/blue gradient" aesthetic.

**Fix:** Define chart color palette using primary blue shades + neutral grays. Remove purple entirely:
```js
chartColors: ['#0C51D9', '#3B82F6', '#93C5FD', '#6B7280', '#D1D5DB']
```

#### D10i. Custom `<style>` Blocks in Components
`Login.vue` and `Input.vue` have custom `<style>` blocks with CSS animations (fadeIn). This violates the "no global CSS" rule and creates non-Tailwind styling.

**Fix:** Replace with Tailwind animation utilities (`animate-fadeIn`) defined in `tailwind.config.js` extend section.

#### D10j. Sidebar Tooltips Clipped in Collapsed Mode
`Sidebar.vue:42` — the `<aside>` has `overflow-hidden` which clips the CSS tooltip pseudo-elements (`::after` at `left: calc(100% + 12px)`) when sidebar is collapsed. Tooltips render but are invisible because they extend beyond the sidebar's `width: 68px` boundary.

**Root cause:** `overflow-hidden` exists to prevent text bleeding during collapse/expand animation, but it also clips tooltips that intentionally overflow.
**Current state:** `input.css:212-252` defines tooltips via `::after`/`::before` pseudo-elements with `opacity: 0` → `opacity: 1` on hover. `z-index: 100`. Positioning is correct — the overflow clip is the only issue.

**Fix:** Remove `overflow-hidden` from `<aside>` in `Sidebar.vue`. The collapse animation already works via:
1. Inline `width: 68px` style constraint
2. `v-show="!isCollapsed"` on text labels
3. `w-0 opacity-0` transition on logo text container (lines 74-76)

None of these depend on `overflow-hidden`. If any edge-case text bleeding occurs during animation, apply `overflow-hidden` only to the logo text wrapper (`div` on line 73), not the entire `<aside>`.

---

## Party Mode Review — Revised Priorities

> Reviewed by: Eka (Frontend), Budi (PM), Fitri (QA)
> Date: 2026-05-15

### Key Decisions

1. **Scope reduction:** Not all 55 pages need equal attention. Focus on what users FEEL.
2. **PR strategy:** 2 PRs (not 3) to reduce review overhead while keeping changes manageable.
3. **No behavior changes in PR 1:** PR 1 is CSS-only — purely visual consistency, zero risk of breaking logic.
4. **Design tokens via `tailwind.config.js`:** Compliant with "no global CSS" rule in AGENTS.md. Tailwind config is the canonical token source.
5. **`MainCard` component:** Should absorb standardized shadow/radius so individual pages don't repeat classes.
6. **Dark mode:** Remove partial `dark:` classes. Full dark mode is a separate project if needed later.
7. **Skeleton loaders:** Deferred to separate PR — high effort, cross-cutting, needs its own testing.

### Risk Assessment (Revised)

| Risk | Impact | Mitigation |
|------|--------|------------|
| CSS class changes break layout | Medium | Manual spot-check on 10 representative pages (1 per domain) after each task group |
| Mobile layout breakage | Medium | Test on 375px viewport for every modified page |
| Too many files changed in one PR | Medium | Split into 2 PRs with clear boundaries |
| Design churn (users accustomed to current UI) | Low | No layout structure changes — only visual consistency |
| Test regressions | Low | 981+ unit tests catch structural issues; E2E tests catch navigation flows |
| Rollback needed | Low | Each PR in its own branch — revert the entire branch if needed |

---

## Implementation Plan

### PR 1: Design Consistency (P0 — Ship First)
**Branch:** `feat/fe-design-consistency`
**Goal:** Remove visual inconsistencies that make the app feel unprofessional. CSS-only changes — no behavior modifications.
**Estimated effort:** 2-3 days

#### Phase 1: Design Token Foundation

| # | Task | Category | Files | Effort |
|---|------|----------|-------|--------|
| 1.1 | Migrate design tokens from `input.css` into `tailwind.config.js` — add `brand-dark`, `brand-light`, `brand-border`, `success` scale, `danger` scale, `warning` scale. Add `rounded-card` (20px→`rounded-2xl`) custom value. Add custom `animate-fadeIn` keyframe. | D10a, D2, D3 | `tailwind.config.js` | 0.5d |
| 1.2 | Clean up `input.css` — remove color definitions now in Tailwind config. Fix `.btn-primary`/`.btn-secondary` to include actual visual styling (bg, text color, padding, hover). Give each badge class unique colors. | D10b, D10e | `input.css` | 0.5d |
| 1.3 | Update `MainCard.vue` — use Tailwind tokens (`rounded-2xl`, `border-brand-border`, `shadow-sm`). Remove hardcoded `border-[#DCDEDD]`, `rounded-[20px]`. Remove `dark:` variants. | D10g, D2, D9 | `MainCard.vue` | 0.25d |
| 1.4 | Update `StatsCard.vue` — fix `hover:border-2` layout shift → use `hover:ring-2 hover:ring-primary-500/20`. Standardize `rounded-[20px]` → `rounded-2xl`, `rounded-[12px]` → `rounded-xl`. | D10c, D2 | `StatsCard.vue` | 0.25d |
| 1.5 | Fix `Admin.vue` — replace `h-screen` with `min-h-[100dvh]` | D10d | `Admin.vue` | 0.1d |
| 1.6 | Add `tabular-nums` utility to financial/numeric displays in stat cards, tables, payroll amounts | D10f | Shared components + key views | 0.25d |
| 1.7 | Replace custom `<style>` blocks in `Login.vue` and `Input.vue` with Tailwind animation utilities | D10i | 2 files | 0.1d |
| 1.8 | Fix sidebar tooltip clipping — remove `overflow-hidden` from `<aside>` in `Sidebar.vue` | D10j | `Sidebar.vue` | 0.1d |

#### Phase 2: Visual Consistency Rollout (All Views)

| # | Task | Category | Files | Effort |
|---|------|----------|-------|--------|
| 2.1 | Replace ALL arbitrary `rounded-[Npx]` with Tailwind scale: `[8px]`→`rounded-lg`, `[10px]`→`rounded-lg`, `[12px]`→`rounded-xl`, `[16px]`→`rounded-2xl`, `[20px]`→`rounded-2xl`, `[24px]`→`rounded-3xl`, `[32px]`→`rounded-3xl` | D2 | 40+ files | 0.5d |
| 2.2 | Replace ALL arbitrary `shadow-[...]` with `shadow-sm`/`shadow-md`/`shadow-lg` | D3 | 30+ files | 0.25d |
| 2.3 | Unify border colors → `border-brand-border` (Tailwind token). Remove `border-[#DCDEDD]`, `border-gray-300` variants. | D6c | 30+ files | 0.25d |
| 2.4 | Unify focus rings → `focus:ring-primary-500/20` (remove `focus:ring-[#0C51D9]`, `focus:ring-blue-500`, `focus:ring-indigo-500`) | D6c | 15+ files | 0.25d |
| 2.5 | Fix ALL `hover:border-2` → `hover:ring-2` across views (same pattern as StatsCard fix) | D10c | 10+ files | 0.25d |
| 2.6 | Replace inline `.main-card` duplications with `<MainCard>` component | D10g | 5+ files | 0.25d |

#### Phase 3: AI Slop Removal + Hierarchy

| # | Task | Category | Files | Effort |
|---|------|----------|-------|--------|
| 3.1 | Remove gradient backgrounds → solid brand colors. Stat icon containers: `bg-primary-50` with `text-primary-600` icon. Avatar placeholders: `bg-gray-200` + icon. | D1 | 26 files | 0.5d |
| 3.2 | Standardize button patterns → use updated `.btn-primary` from Phase 1.2. Remove all inline `bg-[#0C51D9]`, `blue-gradient blue-btn-shadow`, `border-[#2151A0]`. | D5c, D6c, D10b | 30+ files | 0.5d |
| 3.3 | Apply heading size hierarchy: page title `text-2xl font-bold tracking-tight`, section `text-lg font-semibold`, subsection `text-base font-medium` | D5a | 20+ files | 0.5d |
| 3.4 | Standardize spacing: card padding `p-5`, section gaps `space-y-6`, form gaps `space-y-4` | D4a | 20+ files | 0.5d |
| 3.5 | Increase table row padding to `px-4 py-3` minimum | D4b | 10+ files | 0.25d |
| 3.6 | Remove ALL partial `dark:` classes from views and shared components | D9 | 8+ files | 0.25d |
| 3.7 | Standardize chart color palette — remove purple (#8B5CF6), use primary blue scale + grays | D10h | PayrollDashboard, AnalyticsDashboard | 0.25d |

**PR 1 verification:**
- [ ] `bun run test` — all 981+ tests pass
- [ ] Manual spot-check: Dashboard, PayrollDashboard, AttendanceList, ProjectList, TeamList, StaffMemberList, MyAttendance, Login, MeetingList, ReviewCycleList
- [ ] Zero arbitrary `rounded-[Npx]` remaining
- [ ] Zero arbitrary `shadow-[...]` remaining
- [ ] Zero `bg-gradient-to-*` remaining (except SetupWizard hero bg if intentional)
- [ ] Zero `border-[#DCDEDD]` remaining (all migrated to `border-brand-border`)
- [ ] Zero `hover:border-2` remaining (all migrated to `hover:ring-2`)
- [ ] Zero `dark:` classes remaining in views
- [ ] Zero inline `.main-card` duplications (all use `<MainCard>`)
- [ ] `h-screen` replaced with `min-h-[100dvh]` in Admin.vue
- [ ] `tabular-nums` applied to financial/numeric displays
- [ ] No purple (#8B5CF6) in chart color palettes
- [ ] Mobile viewport (375px) — no horizontal overflow on spot-checked pages
- [ ] E2E tests pass (`bun run e2e`)

---

### PR 2: UX Polish (P1 — Next)
**Branch:** `feat/fe-ux-polish`
**Goal:** Fix UX heuristic violations and apply UX law improvements. Some behavioral changes (collapsible sections, filter controls, tooltips).
**Estimated effort:** 3-4 days
**Prerequisite:** PR 1 merged

#### Phase 4: UX Heuristic Fixes

| # | Task | Category | Files | Effort |
|---|------|----------|-------|--------|
| 4.1 | Add "Clear all filters" button to list pages with multiple filter inputs | D6b | AttendanceList, AttendanceRecordList, LeaveRequestList, PayrollDashboard, ReviewCycleList, StaffMemberList | 0.5d |
| 4.2 | Add `title` attribute / tooltip to icon-only action buttons (edit, delete, view) | D6e | 12+ files | 0.5d |
| 4.3 | Add text labels alongside color-only status badges (attendance dots, project status) | D6e | AttendanceRecordList, ProjectList, PayrollDashboard | 0.5d |
| 4.4 | Standardize ALL destructive confirmations to use `ConfirmationModal` (remove any `window.confirm` usage) | D6d | 5+ files | 0.25d |

#### Phase 5: UX Law Compliance

| # | Task | Category | Files | Effort |
|---|------|----------|-------|--------|
| 5.1 | Add collapsible sections to PayrollSettings and AttendanceSettings (Hick's Law — reduce visible choices) | D7a | 2 files | 0.5d |
| 5.2 | Add visual grouping (shared bg, dividers) to related form fields (Gestalt proximity) | D7c | PayrollCreate, StaffMemberCreate, ProjectCreate, TeamCreate, AttendanceSettings | 0.5d |
| 5.3 | Add color-coded left borders to stat/metric cards for visual differentiation | D5b | PayrollDashboard, PendingCalibration, TeamReviews, AnalyticsDashboard | 0.5d |
| 5.4 | Chunk dense tables — add column toggle or responsive horizontal scroll | D7d | PayrollReadiness, AttendanceList | 0.5d |
| 5.5 | Prioritize dashboard metrics — show top 4-5, move rest behind "View all" | D7d | PayrollDashboard, AnalyticsDashboard | 0.5d |

**PR 2 verification:**
- [ ] `bun run test` — all tests pass
- [ ] Manual spot-check on all modified pages
- [ ] "Clear all filters" works on every list page with filters
- [ ] Tooltips appear on hover for icon-only buttons
- [ ] Collapsible sections default open, toggle smoothly
- [ ] Mobile viewport (375px) — no layout issues
- [ ] E2E tests pass (`bun run e2e`)

---

### Deferred (Separate Future PRs)

| Item | Why Deferred |
|------|-------------|
| Skeleton loaders (replace generic spinners) | High effort (~20+ files), cross-cutting, needs its own design iteration |
| Varied empty state layouts by context | Low impact, EmptyState component already consistent |
| Full dark mode support | Major scope — separate project, needs design system commitment |
| Auth page background patterns | Low priority, auth pages are minimal by design |

---

## Execution Strategy

### Per AGENTS.md Workflow:
1. **Baca Context** ✅ — AGENTS.md, sub-repo AGENTS.md, party-mode.md reviewed
2. **Plan** ✅ — This document
3. **Execute** — Branch from `main`, implement phases sequentially within each PR
4. **Verify** — Run `bun run test` + `bun run e2e` + manual spot-check after each phase
5. **PR** — Squash commits → `chore: fix design consistency across FE pages` / `chore: add UX polish improvements`
6. **Wait for CI** → Wait for reviewer → Rebase & merge → Delete branch
7. **Archive** — Move this plan to `docs/plans/archive/` after both PRs merged

### Manual QA Checklist (10 Representative Pages)

After each phase completion, visually verify these 10 pages on both desktop (1440px) and mobile (375px):

| # | Page | Domain | Why Representative |
|---|------|--------|--------------------|
| 1 | Dashboard | General | Entry point, stat cards, quick actions |
| 2 | PayrollDashboard | Payroll | Most complex — charts, metrics, tables, filters |
| 3 | AttendanceList | Attendance | Dense table, filters, status badges |
| 4 | StaffMemberList | Staff | List with search, filters, cards |
| 5 | ProjectList | Project | Card grid, status badges |
| 6 | TeamList | Team | Simple list, avatars |
| 7 | ReviewCycleList | Performance | Filters, status workflow |
| 8 | MyAttendance | Staff-Member | Self-service view, stat cards |
| 9 | Login | Auth | Clean form, branding |
| 10 | MeetingList | Meeting | Simple list, empty states |

---

## Success Criteria

### PR 1 (Design Consistency)
1. Zero arbitrary `rounded-[Npx]` values in any view file
2. Zero arbitrary `shadow-[...]` values in any view file
3. Zero `bg-gradient-to-*` in view files (except SetupWizard if intentional)
4. Zero `border-[#DCDEDD]` — all use `border-brand-border` Tailwind token
5. Zero `hover:border-2` — all use `hover:ring-2` (no layout shift)
6. Zero partial `dark:` classes in views
7. Zero inline `.main-card` duplications
8. All buttons follow `.btn-primary`/`.btn-secondary` pattern with actual visual styling
9. All focus rings unified to `focus:ring-primary-500/20`
10. `h-screen` fixed to `min-h-[100dvh]` in Admin.vue
11. `tabular-nums` on financial/numeric displays
12. Chart colors use primary blue scale (no purple)
13. Badge classes have unique colors per level
14. All 981+ unit tests pass
15. No visual breakage on 10 representative pages
16. Sidebar tooltips visible on hover when sidebar is collapsed

### PR 2 (UX Polish)
1. All list pages with filters have "Clear all filters" button
2. All icon-only buttons have tooltips or title attributes
3. All status badges have text labels alongside color indicators
4. All destructive actions use ConfirmationModal (zero `window.confirm`)
5. Settings pages have collapsible sections
6. Stat cards have color-coded differentiation
7. All tests pass (unit + E2E)
