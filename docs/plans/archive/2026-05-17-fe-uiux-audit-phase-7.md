# FE UI/UX Audit — Phase 7: Qualitative Polish (Spacing, Hierarchy, Density)

**Status:** COMPLETED — PR #37 (PR-A) + PR #38 (PR-B) + PR #39 (PR-C) merged
**Date:** 2026-05-17
**Predecessors:** P1–P5 (token + visual consistency, PRs #30 #31 #32 #33 #34), P6 (test coverage, PR #35 #36)
**Authoritative spec:** [`team-sync-fe/docs/design-system.md`](../../team-sync-fe/docs/design-system.md)

---

## Context

Phases 1–5 made the codebase mechanically compliant: zero arbitrary tokens, zero `lang="ts"`, zero `focus:border-2`, zero degenerate gradients. Phase 6 closed test coverage gaps. What remains is **qualitative** UX/UI work that grep cannot detect:

- Spacing/padding inconsistency (gap-4 vs 6 vs 8 chaos within same view family)
- Visual hierarchy (heading levels, type-scale role mixing)
- Overcrowded/dense layouts (too many actions/cards above the fold)
- Misalignment (row/column rhythm broken)
- Typography sizing inconsistency (`text-[15px]` arbitrary sizes still present)
- Responsive behavior gaps (mobile breakpoints not always defined)
- Interaction feedback weakness (some buttons lack loading/disabled affordance)
- Confusing flows (back-nav inconsistency, breadcrumb gaps)

Plus the concrete bug surfaced during the most recent screenshot review: **Login.vue Sign In button hover-overlay obscures the button label** and the **Auth layout has wasted vertical space** below the centered form on tall viewports.

## Goals

- Apply Nielsen's 10 heuristics + Aesthetic & Minimalist Design across high-traffic surfaces
- Normalize spacing/typography scales (no more `text-[15px]`, `gap-[18px]`-style ad-hoc values)
- Fix concrete layout bugs found during review (Login button, Auth bottom gap)
- Reduce density on the worst offenders (TeamDetail with 14 headings, MyAttendance with 11)
- Keep all 1010 FE tests + 1504 BE tests green throughout
- Ship as 3 PRs (one per phase) for reviewable diff size

## Non-Goals

- New features or new views
- Visual rebrand (colors/font remain per design-system.md)
- BE changes
- Component API refactors
- Animation overhauls
- Touching the partially-stubbed performance views (GiveFeedback, TeamGoals, ReviewCycleCreate, GoalDetail, FeedbackGiven) beyond surface polish

---

## Static survey results

### Heading density (potential hierarchy issues)

| Rank | View | `<h*>` count | Deep-audit candidate? |
|------|------|---|---|
| 1 | admin/team/TeamDetail.vue | 14 | YES |
| 2 | staff-member/MyAttendance.vue | 11 | YES |
| 3 | admin/staff-member/StaffMemberDetail.vue | 11 | YES |
| 4 | admin/performance/ReviewDetail.vue | 10 | YES |
| 5 | admin/payroll/PayrollDashboard.vue | 10 | YES |
| 6 | admin/project/ProjectDetail.vue | 9 | yes |
| 7 | admin/payroll/PayrollSettings.vue | 9 | yes |
| 8 | staff-member/StaffMemberTeam.vue | 8 | yes |
| 9 | admin/attendance/AttendanceList.vue | 8 | yes |

### Concrete bugs found in review

| Issue | File | Symptom |
|---|---|---|
| Sign In button overlay covers label | `views/auth/Login.vue:167-170` | `bg-white/20` hover overlay sits on top of the button content; on default state it sits below (`translate-y-full`) but the absolute div + `overflow-hidden` parent renders the button as washed-out in some browsers |
| Wasted vertical space below auth form | `layouts/Auth.vue:85-88` | `<main>` uses `items-center justify-center` → form is vertically centered → large dead-zone below the form on tall viewports |
| Same overlay pattern in ForgotPassword | `views/auth/ForgotPassword.vue:127-130` | Same as Login |
| Arbitrary `text-[15px]` | `views/auth/Login.vue:46`, `ForgotPassword.vue:33,54` | Should be `text-sm` (14px) or `text-base` (16px), not arbitrary 15px |
| `min-h-[100dvh]` on a view | `views/admin/staff-member/StaffMemberDetail.vue` | Layouts already provide full height; views shouldn't redeclare this |

---

## Phase split (3 PRs)

### PR-A — Auth layout fixes + targeted typography (small, focused)

**Branch:** `feat/fe-auth-layout-polish`

#### Tasks
1. **Login button overlay** — replace the `translate-y-full` absolute overlay div with a simple `hover:bg-primary-700` background change. Same for ForgotPassword "Send reset link" and ForgotPassword success-state "Return to sign in".
2. **Auth layout vertical balance** — change `<main>` from `items-center justify-center` to `items-center justify-center py-16 lg:py-24`, AND wrap the auth content in a max-height-aware container so on tall viewports the form sits in the upper-third (Z-pattern reading) instead of being mathematically centered with huge bottom gap.
3. **`text-[15px]` cleanup** — replace 3 occurrences in auth views with `text-sm` (subtitle copy, fits design system §4 type scale).
4. **`min-h-[100dvh]` redundancy in StaffMemberDetail.vue** — remove if the Admin layout already provides height (verify before edit).
5. **Skip-link audit** — verify the Auth.vue skip-link target `#auth-main` actually exists (it does at line 86) and copies are descriptive.
6. **Heading hierarchy in auth views** — verify single `<h1>` per page, no skipping levels.

#### Verify
- 1010/1010 FE tests pass
- `bun run build` clean
- Take screenshots at 1440×900 (desktop) and 375×812 (mobile) — should look balanced
- `bun run test:a11y` passes

#### PR
Title: `chore(fe): fix auth button overlay + balance vertical layout`
Squash to 1 commit.

---

### PR-B — Spacing & hierarchy normalization (Tier-1 high-traffic views)

**Branch:** `feat/fe-spacing-hierarchy-tier1`

#### Scope (9 high-density views)
admin/team/TeamDetail, admin/staff-member/StaffMemberDetail, admin/performance/ReviewDetail, admin/payroll/PayrollDashboard, admin/payroll/PayrollDetail, admin/project/ProjectDetail, admin/payroll/PayrollSettings, staff-member/MyAttendance, admin/attendance/AttendanceList.

#### What to standardize per view
1. **Heading hierarchy** — exactly one `<h1>` per page (the page title), all section titles `<h2>`, sub-section titles `<h3>`. Demote/promote as needed. No level-skipping.
2. **Section spacing** — adopt one rhythm: `space-y-6` between top-level sections on desktop, `space-y-4` on mobile. Reject ad-hoc `mt-8`/`mb-10` chains.
3. **Card padding** — `p-6` for cards (matches design-system §6 MainCard/StatsCard contract). Remove `p-4` and `p-8` deviations except where stated in spec.
4. **Type sizes** — page title `text-2xl font-semibold` (h1), section title `text-lg font-semibold` (h2), sub-section `text-base font-medium` (h3), body `text-sm`, helper `text-xs`. Replace any `text-[15px]`, `text-[13px]` arbitrary sizes.
5. **Action button placement** — primary CTA always last in flex row (right side on desktop), secondary buttons left of primary. Toolbar height `h-10` consistent.
6. **Empty/loading state** — verify all data sections use `EmptyState` + skeleton (no inline `<div>Loading…</div>`).
7. **Stat card alignment** — when 3+ StatsCards in a row, all use same height (`min-h-32`). Numbers right-aligned, labels left-aligned within card.

#### Method
Dispatch @designer with the spec above for each view in 3 parallel batches (3 views per batch).

#### Verify
- 1010/1010 FE tests
- `bun run build` clean
- `bun run test:a11y` passes
- Visually check heading outline using browser dev tools: each Tier-1 view's heading map should be `H1 > H2 > H2 > H2…` with no skipped levels.

#### PR
Title: `chore(fe): normalize spacing + heading hierarchy on Tier-1 views`

---

### PR-C — Density reduction + clutter elimination (depends on PR-B merge)

**Branch:** `feat/fe-density-reduction`

#### Scope
Same 9 Tier-1 views as PR-B, plus the analytics dashboard tabs.

#### What to reduce
1. **Above-the-fold actions** — max 3 primary actions per page header. Excess goes into an overflow menu or moves below.
2. **Tab-pill overload** — pages with 5+ tabs get progressive disclosure (group related tabs).
3. **Decorative gradients/icons** — keep only icons that aid recognition. Remove pure-decoration ones.
4. **Redundant metadata** — same field shown twice (e.g., status badge + "Status: active" text) — collapse to one source of truth.
5. **Helper text spam** — caption beneath every input → keep only where genuinely needed (validation, format hints).
6. **Whitespace rebalance** — tightening over-spaced sections and opening up over-cramped ones based on current density measurement.

#### Method
Dispatch @designer with screenshots + analysis. Per-view density score (issue count) → fix list.

#### Verify
- 1010/1010 FE tests
- Build + a11y green
- Compare before/after screenshots at 1440px and 375px

#### PR
Title: `chore(fe): reduce visual density on high-traffic views`

---

## Out of Scope (explicitly deferred)

- Tier-2 forms (StaffMemberCreate/Edit, ProjectCreate/Edit, etc.) — well-trodden form patterns, low return-on-fix
- Tier-3 edge views (NotFound, VerifyEmailResult, StaffMemberSuccess) — already audited in P3
- Component-library refactor (e.g., centralizing PageHeader component) — separate plan if needed
- Dark mode — explicitly removed per design-system.md anti-pattern
- Animation/motion polish — separate concern, would need motion brief

## Risk + Rollback

- All work is CSS class swaps + minor template restructuring. No store/router/API changes.
- Each PR is squashable to one commit; revert is `git revert <sha>` if regression found.
- Tests gate every PR — if a smoke test starts failing on a structural change, fix the test or rethink the change.

## Success Criteria

- [ ] PR-A merged: auth pages render full-screen with visible CTAs on desktop + mobile
- [ ] PR-B merged: heading map of every Tier-1 view shows clean `H1→H2→H3` tree (no skip)
- [ ] PR-B merged: spacing scale audit shows ≤ 2 ad-hoc spacing values across Tier-1 views (vs. current ~12 arbitrary `mt-X`/`gap-X` permutations)
- [ ] PR-C merged: above-the-fold action count reduced where >3
- [ ] Test counts unchanged: 1010 FE + 1504 BE
- [ ] design-system.md updated only if new patterns are introduced (don't fork the spec silently)

## Execution Order

1. PR-A (auth) — fast, concrete, low risk
2. After PR-A merged: PR-B (Tier-1 normalization)
3. After PR-B merged: PR-C (density reduction)
4. Archive plan to `docs/plans/archive/2026-05-17-fe-uiux-audit-phase-7.md`
