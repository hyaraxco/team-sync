# FE Visual + UX Audit — Phase 5: Focus States & Layout Shift

**Status:** COMPLETED
**Date:** 2026-05-17
**Predecessors:**
- [`2026-05-17-fe-visual-ux-audit.md`](../archive/2026-05-17-fe-visual-ux-audit.md) (Phases 1–3, PRs #30 #31 #32)
- [`2026-05-17-fe-audit-phase-4-components.md`](../archive/2026-05-17-fe-audit-phase-4-components.md) (Phase 4, PR #33)
**Authoritative spec:** [`team-sync-fe/docs/design-system.md`](../../../team-sync-fe/docs/design-system.md)

---

## Context

Phases 1–4 eliminated all hex colors, degenerate gradients, arbitrary radii, `lang="ts"`, and banned patterns (`hover:border-2`, `dark:`, `shadow-[…]`, `h-screen`).

Party-mode review surfaced two new follow-up items missed by the original scope:

1. **`focus:border-2` (28 locations, 15 files)** — same 1px layout-shift issue as the banned `hover:border-2`. AGENTS.md anti-pattern explicitly bans `hover:border-2` for layout shift; `focus:border-2` is the same defect on keyboard focus. WCAG 2.4.7 (focus visible) and 2.4.11 (focus not obscured) implications.
2. **`hover:rounded-xl` + `focus:rounded-xl` (7 locations, 2 files)** — radius changes on interaction cause visual jank.

## Goals

- Zero `focus:border-2` across `src/`
- Zero `hover:rounded-*` and `focus:rounded-*` (radius transitions on interaction)
- Match existing `hover:ring-2 hover:ring-brand-primary/20` pattern documented in `design-system.md` §8.3 (StatsCard hover) and §8.7 (SearchFilter)
- Maintain WCAG 2.4.7 focus-visible contrast
- 991/991 FE tests pass
- Build clean

## Non-Goals

- No new tokens
- No changes to `tailwind.config.js`
- No changes to `input.css`
- No layout/component restructuring

---

## Approach

### Replacement Pattern A — focus:border-2

**Before** (causes 1px layout shift):
```html
class="border border-gray-200 ... focus:border-brand-primary focus:border-2 focus:bg-white"
```

**After** (no layout shift, ring outside layout flow):
```html
class="border border-gray-200 ... focus:border-brand-primary focus:ring-2 focus:ring-brand-primary/20 focus:bg-white"
```

The `border-brand-primary` keeps the colored border indicator on focus. The `ring-2 ring-brand-primary/20` adds a halo OUTSIDE the layout box (rings don't shift layout). This matches the documented hover pattern.

### Replacement Pattern B — hover:rounded-xl / focus:rounded-xl

**Before** (radius jank):
```html
class="rounded-2xl ... hover:rounded-xl focus:rounded-xl"
```

**After** (consistent radius):
```html
class="rounded-2xl ..."
```

Just delete the `hover:rounded-*` and `focus:rounded-*` modifiers.

---

## File Inventory

### Pattern A — focus:border-2 (15 files, 28 occurrences)

| File | Count |
|------|-------|
| `src/components/admin/dashboard/QuickActions.vue` | 1 |
| `src/components/admin/project/detail/TaskBoard.vue` | 1 |
| `src/components/admin/staff-member/create/steps/Step2JobInfo.vue` | 2 |
| `src/components/common/SearchFilter.vue` | 2 |
| `src/components/common/form/Select.vue` | 1 |
| `src/components/common/form/TextArea.vue` | 1 |
| `src/views/admin/payroll/PayrollCreate.vue` | 1 |
| `src/views/admin/payroll/PayrollDashboard.vue` | 6 |
| `src/views/admin/project/ProjectCreate.vue` | 1 |
| `src/views/admin/project/ProjectEdit.vue` | 1 |
| `src/views/admin/team/TeamCreate.vue` | 1 |
| `src/views/admin/team/TeamDetail.vue` | 1 |
| `src/views/admin/team/TeamEdit.vue` | 1 |
| `src/views/staff-member/MyAttendance.vue` | 6 |
| `src/views/staff-member/MyPayslips.vue` | 2 |

### Pattern B — hover:rounded-xl / focus:rounded-xl (2 files, 7 occurrences)

| File | Count |
|------|-------|
| `src/components/admin/dashboard/QuickActions.vue` | 1 |
| `src/views/admin/payroll/PayrollDashboard.vue` | 6 |

---

## Tasks

### Task 1 — Replace `focus:border-2` with `focus:ring-2`

**Files:** see Pattern A inventory above (15 files, 28 occurrences)

**Steps:**
1. For each occurrence, replace `focus:border-2` with `focus:ring-2 focus:ring-brand-primary/20`
2. Keep `focus:border-brand-primary` (color indicator)
3. Verify no remaining `focus:border-2` via grep

### Task 2 — Remove `hover:rounded-*` and `focus:rounded-*`

**Files:** see Pattern B inventory above (2 files, 7 occurrences)

**Steps:**
1. Delete `hover:rounded-xl` and `focus:rounded-xl` modifiers
2. Keep base `rounded-2xl` (unchanged)
3. Verify no remaining via grep

### Task 3 — Verify

**Steps:**
1. `bun run test` → expect 991/991 pass
2. `bun run build` → expect success
3. `rg 'focus:border-2'` → expect 0 matches
4. `rg 'hover:rounded'` → expect 0 matches
5. `rg 'focus:rounded'` → expect 0 matches

---

## Acceptance Criteria

- ✅ Zero `focus:border-2` in `src/`
- ✅ Zero `hover:rounded-*` in `src/`
- ✅ Zero `focus:rounded-*` in `src/`
- ✅ 991/991 FE tests pass
- ✅ `bun run build` succeeds
- ✅ Visual: input focus shows colored border + outer halo, no layout shift
- ✅ Visual: card/button hover keeps radius, no jank

---

## Out of Scope (deferred)

- ESLint plugin to prevent regression (separate task)
- Token duplication audit between `input.css` classes and `tailwind.config.js` tokens (separate task)
- Note: `border-2` STAYS for form error states (`Select.vue`/`TextArea.vue`) — that's an intentional 2px error border per design-system.md §8.12, not a focus state shift.
