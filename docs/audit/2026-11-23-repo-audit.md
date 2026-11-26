# Team Sync — Repository Audit

**What this repo does:** HRIS for a single Indonesian company. Staff clock in, request leave, view payslips. Managers handle teams, projects, tasks. HR manages people and attendance. Finance runs payroll with PPh 21 / BPJS. Analytics dashboards summarize everything.

**Assumed load:** One company, tens to low hundreds of concurrent users, one web server, one queue worker, one scheduler. MySQL in production, SQLite in tests.

**Date:** 2026-11-23  
**Method:** Static source analysis only. No code was run, changed, or deployed.

---

## Must fix

### 1. **Goal creation IDOR — any user can assign goals to any employee**
`team-sync-be/app/Http/Controllers/PerformanceGoalController.php:61-77`  
`team-sync-be/app/Http/Requests/Performance/CreateGoalRequest.php:17`

- **What this is:** `store()` requires only `goal-create-own` (which every role has). The request accepts any `staff_member_id` and `assigned_by` from the client.
- **Problem:** A Staff user can create goals assigned to any other employee, including the CEO. The repository (`createGoal`) writes the client-supplied `staff_member_id` directly. There is no ownership or team-membership check.
- **Fix:** In `store()`, if the user lacks `goal-assign-team`, force `staff_member_id` to `Auth::user()->staffMemberProfile->id` and `assigned_by` to the same. If the user has `goal-assign-team`, validate that the target `staff_member_id` belongs to their team.
- **If we skip it:** Any authenticated user can create performance records for other employees, polluting their review data.

### 2. **Manager can update/delete any goal across all teams**
`team-sync-be/app/Http/Controllers/PerformanceGoalController.php:93-106`

- **What this is:** `update()` and `destroy()` check `$user->can('goal-assign-team')` and if true, allow the action on ANY goal by ID — no team-scope filter.
- **Problem:** A Manager in Team A can modify or delete goals belonging to Team B employees.
- **Fix:** When the user has `goal-assign-team`, verify the goal's `staff_member_id` belongs to a team the user manages (same team or direct report check).
- **If we skip it:** Cross-team data tampering by any Manager.

### 3. **Team update/delete policy has no team-scope check**
`team-sync-be/app/Policies/TeamPolicy.php:55-73`

- **What this is:** `update()` and `delete()` check only `team-edit` / `team-delete` permission, with no object-level scoping.
- **Problem:** A Manager with `team-edit` (which the seeder grants) can update or delete ANY team, not just their own.
- **Fix:** Add a scope check: allow if the user is the team's `team_lead_id`, or if the user has a superadmin/HR role. Keep the permission check for other roles.
- **If we skip it:** A Manager can rename, dissolve, or reassign leadership of other Managers' teams.

### 4. **Logout navigates before revoking token — token stays valid**
`team-sync-fe/src/stores/auth.js:85-131`

- **What this is:** `logout()` removes the cookie, clears state, calls `router.replace()`, then calls `window.location.replace()` (line 111), and only afterward fires the server revoke request (line 123).
- **Problem:** `window.location.replace()` triggers a full page navigation that cancels in-flight XHR. The `void axiosInstance.post("/logout")` at line 123 never completes. The Sanctum token remains valid server-side until it expires.
- **Fix:** Move the server revoke call before `window.location.replace()`. Use `await` or at minimum `navigator.sendBeacon` / `fetch({ keepalive: true })` so it survives navigation.
- **If we skip it:** Stolen tokens (from XSS, shared computers) remain valid indefinitely. Sanctum personal access tokens don't expire by default.

### 5. **Analytics utilization rate inflated by task-row multiplication**
`team-sync-be/app/Repositories/AnalyticsRepository.php:2079-2115`

- **What this is:** `getProjectResourceUtilization` joins teams → members → tasks, then computes `AVG(CASE WHEN project_tasks.id IS NOT NULL THEN 1 ELSE 0 END)` as utilization.
- **Problem:** A member with 9 tasks produces 9 rows (all with value 1). A member with 0 tasks produces 1 row (value 0). The AVG becomes 9/10 = 90%, when the real utilization is 1 out of 2 members = 50%. The function already computes `members_with_tasks` / `total_members` correctly via DISTINCT — but then ignores it for the `utilization_rate`.
- **Fix:** Replace the AVG expression with: `ROUND(COUNT(DISTINCT CASE WHEN project_tasks.id IS NOT NULL THEN team_members.staff_member_id END) * 100.0 / NULLIF(COUNT(DISTINCT team_members.staff_member_id), 0), 1) as utilization_rate`.
- **If we skip it:** Dashboard shows inflated utilization. Teams with uneven task distribution look overloaded.

### 6. **Team performance attendance/task rates skewed by cross-join**
`team-sync-be/app/Repositories/AnalyticsRepository.php:2126-2178`

- **What this is:** `getTeamPerformanceData` left-joins members → attendances AND members → tasks in the same query without intermediate aggregation.
- **Problem:** A member with 1 attendance and 9 tasks produces 9 rows in the join. `COUNT(attendances.id)` becomes 9 instead of 1. A member with 5 attendances and 1 task produces 5 attendance rows. The two sets multiply, skewing both rates toward members with more of the other dimension.
- **Fix:** Aggregate attendance and tasks in separate subqueries (or CTEs), then join the results to teams.
- **If we skip it:** Dashboard team performance comparison is unreliable. Decisions based on it (staffing, reviews) use wrong numbers.

### 7. **FE Dockerfile uses npm instead of bun — different lockfile, different dependencies**
`team-sync-fe/Dockerfile:4`

- **What this is:** The Docker build stage runs `npm ci` or `npm install` with `package-lock.json`, but the project uses `bun.lock`.
- **Problem:** `package-lock.json` does not exist in the repo. `npm install` resolves dependencies from scratch, potentially installing different versions than what developers use with `bun install`. The build is not reproducible.
- **Fix:** Use `FROM oven/bun:1-alpine AS build` and `RUN bun install --frozen-lockfile`. Copy `bun.lock` alongside `package.json`.
- **If we skip it:** Docker builds may use different dependency versions than dev/CI, introducing hard-to-diagnose production-only bugs.

---

## Should fix

### 8. **Analytics test accepts 500 as success — MySQL-only SQL untested**
`team-sync-be/tests/Feature/Analytics/AnalyticsEndpointGapTest.php:60-85`

- **What this is:** `assertAnalyticsRouteAccessible` asserts only "not 404, not 401, not 403". When the endpoint returns 500 (MySQL SQL on SQLite), it asserts `success => false` and moves on.
- **Problem:** These tests prove the route is wired but cannot catch logic bugs, wrong column names, or broken calculations. The analytics math bugs (findings 5, 6) were invisible to this suite.
- **Fix:** Either use MySQL in CI for the analytics suite, or refactor the MySQL-specific SQL (`DATE_FORMAT`, `TIMESTAMPDIFF`) to use query builder methods that work on both engines.
- **If we skip it:** Analytics bugs ship undetected. The test suite gives false confidence.

### 9. **Project KPI counts "in_progress" projects, but enum value is "active"**
`team-sync-be/app/Repositories/AnalyticsRepository.php:1708-1709`

- **What this is:** `getProjectKpis` queries `whereIn('status', ['in_progress', 'on_hold'])`. The `ProjectStatus` enum defines `ACTIVE = 'active'` — there is no `'in_progress'` value.
- **Problem:** The active-project count is always zero (or only counts `on_hold`). The executive dashboard KPI is wrong.
- **Fix:** Change `'in_progress'` to `'active'`, or use `ProjectStatus::ACTIVE->value`.
- **If we skip it:** "Active Projects" KPI on the executive dashboard always shows the wrong number.

### 10. **Team store doesn't clear `success` before create/update — stale state causes phantom navigation**
`team-sync-fe/src/stores/team.js:117-130`  
`team-sync-fe/src/views/admin/team/TeamCreate.vue:65-70`

- **What this is:** `createTeam()` and `updateTeam()` set `this.success` on success but don't reset it at the start. Views check `if (success.value)` after `await` to decide whether to navigate.
- **Problem:** If a previous create succeeded, `success` is truthy. The next failed create skips the `catch` (error is set, but success is not cleared), so `success.value` is still truthy → the view navigates away as if the create worked.
- **Fix:** Add `this.success = null; this.error = null;` at the top of `createTeam()` and `updateTeam()`, matching the pattern already used in `deleteTeam()`, `addMember()`, and `removeMember()`.
- **If we skip it:** Users who fail a form submit after a previous success are redirected away without seeing the error.

### 11. **Four Enhanced analytics components are dead code (840 lines)**
`team-sync-fe/src/components/admin/analytics/WorkforceAnalyticsEnhanced.vue` (219 lines)  
`team-sync-fe/src/components/admin/analytics/AttendanceAnalyticsEnhanced.vue` (190 lines)  
`team-sync-fe/src/components/admin/analytics/LeaveAnalyticsEnhanced.vue` (215 lines)  
`team-sync-fe/src/components/admin/analytics/ProjectAnalyticsEnhanced.vue` (216 lines)

- **What this is:** Four chart components that import `capitalize` from `@/helpers/format`. `AnalyticsDashboard.vue` imports the non-Enhanced variants (`WorkforceAnalytics.vue`, etc.).
- **Problem:** No file in the codebase imports these four components. They also have a broken import: `@/helpers/format` does not export `capitalize` (it only exports `DEFAULT_AVATAR` and `formatToClientTimezone`), so they would crash on mount if ever rendered.
- **Fix:** Delete the four files. If they were intended as replacements, fix the import to `@/utils/formatUtils` and wire them into `AnalyticsDashboard.vue`.
- **If we skip it:** 840 lines of dead code with a broken import. Harmless but confusing.

### 12. **Unused FE dependencies bloat the bundle and install**
`team-sync-fe/package.json`

- **What this is:** Several declared dependencies have zero imports in `src/`:
  - `jspdf` + `jspdf-autotable` — zero imports anywhere.
  - `vue3-emoji-picker` — zero imports anywhere.
  - `lucide` (vanilla) — only one `data-lucide` attribute in `TeamDetail.vue:505` which renders nothing because `createIcons()` is never called. The project uses `lucide-vue-next` for all icons.
  - `lodash-es` — only `Header.vue:20` uses `join()`. Can be replaced with `(user?.roles || []).join(", ")`.
  - `concurrently` (devDependency) — no script in `package.json` references it.
- **Problem:** Unnecessary packages in `dependencies` ship to the browser (tree-shaking catches unused ESM but not CJS like `jspdf`). `concurrently` wastes install time.
- **Fix:** Remove `jspdf`, `jspdf-autotable`, `vue3-emoji-picker`, `lucide`, `lodash-es`, and `concurrently` from `package.json`. Replace the `lodash-es` import in `Header.vue` with native JS. Fix the `data-lucide` icon in `TeamDetail.vue` to use a `lucide-vue-next` component.
- **If we skip it:** Larger install, larger bundle, false sense that these libraries are in use.

### 13. **Both `lodash` and `lodash-es` are installed — pick one**
`team-sync-fe/package.json:36-37`

- **What this is:** `lodash` (CJS, full bundle) and `lodash-es` (ESM, tree-shakable) are both declared. 16 files import from `lodash`, 1 from `lodash-es`.
- **Problem:** `lodash` is CJS — Vite/Rollup can't tree-shake it. The full 70KB library ships to the browser even though only `debounce`, `startCase`, `capitalize`, and `join` are used.
- **Fix:** Remove `lodash` and `lodash-es`. Replace usages: `debounce` → `lodash-es/debounce` (or a 10-line inline), `startCase`/`capitalize` → already exist in `formatUtils.js`, `_` (full import in 4 files) → import only used functions. Or keep `lodash-es` only and migrate all imports.
- **If we skip it:** ~70KB unnecessary JS in the production bundle.

---

## Nice to have

### 14. **`data-lucide` icon renders nothing**
`team-sync-fe/src/views/admin/team/TeamDetail.vue:505`

- **What this is:** `<i data-lucide="graduation-cap">` uses vanilla Lucide's convention, which requires `createIcons()` to transform `<i>` tags into SVGs.
- **Problem:** `createIcons()` is never called. The icon renders as an empty `<i>` element.
- **Fix:** Replace with `<GraduationCap class="w-4 h-4" />` from `lucide-vue-next`.
- **If we skip it:** One missing icon in a hardcoded demo section of TeamDetail. Cosmetic only.

### 15. **Team store uses `lodash` full import in 4 view files**
`team-sync-fe/src/views/admin/team/TeamDetail.vue:30`  
`team-sync-fe/src/views/admin/project/ProjectDetail.vue:33`  
`team-sync-fe/src/components/admin/staff-member/list/CardList.vue:4`  
`team-sync-fe/src/components/admin/project/list/CardList.vue:4`

- **What this is:** `import _ from "lodash"` pulls the entire CJS bundle.
- **Problem:** Only `_.debounce` or similar is used, but the full library is imported.
- **Fix:** `import { debounce } from "lodash-es"` (or inline debounce).
- **If we skip it:** Slightly larger bundle per chunk. Not critical.

### 16. **`concurrently` devDependency unused**
`team-sync-fe/package.json:62`

- **What this is:** `concurrently` is in devDependencies but no npm script uses it.
- **Problem:** Wastes install time. The backend uses its own `concurrently` via Composer.
- **Fix:** Remove from `devDependencies`.
- **If we skip it:** Harmless. Only slows `bun install` slightly.

### 17. **Hardcoded demo content in TeamDetail sidebar**
`team-sync-fe/src/views/admin/team/TeamDetail.vue:498-508`

- **What this is:** Static text: "Team training session conducted: Advanced React Patterns" with "2 weeks ago" and a broken icon.
- **Problem:** This is placeholder content that ships to production. Users see a fake activity log entry.
- **Fix:** Remove or replace with actual data from an API endpoint.
- **If we skip it:** Users see fake data on the team detail page. Confusing but not a data-integrity issue.

---

## Verification status (2026-11-24, against latest main)

Checked statically after PR #99–#115 merges. Status per finding:

| # | Finding | Status |
|---|---------|--------|
| 1 | Goal creation IDOR | **OPEN** — `store()` still writes client-supplied `staff_member_id`; no ownership force or team-membership check (`CreateGoalRequest.php:17`, controller `store()`). |
| 2 | Cross-team goal update/delete | **OPEN (narrowed)** — update/destroy/show now block users *without* `goal-assign-team`, but any Manager *with* that permission can still modify any team's goals by ID. |
| 3 | TeamPolicy scope | **OPEN** — `update()`/`delete()` still check only `team-edit`/`team-delete` with no team-lead or HR scoping. |
| 4 | Logout token race | **CLOSED** — revoke request now awaited before cookie removal and navigation (`stores/auth.js`). |
| 5 | Utilization row multiplication | **CLOSED** — `utilization_rate` now `COUNT(DISTINCT members with tasks) * 100 / COUNT(DISTINCT members)`. |
| 6 | Team performance cross-join | **CLOSED** — attendance and task aggregates moved to `leftJoinSub` subqueries; task aggregate also window-bounded (PR #106 content landed via #108). |
| 7 | FE Dockerfile npm vs bun | **CLOSED** — `oven/bun:1.3.13-alpine`, `bun install --frozen-lockfile`, `bun.lock` copied. |
| 8 | Analytics tests accepting 500 | **CLOSED (largely)** — `assertAnalyticsRouteAccessible` now `assertOk()` + `success:true`; payroll endpoint has a seeded MySQL HTTP totals test (PR #115). CI still runs SQLite, so MySQL-only paths skip there — permanent MySQL CI service remains open. |
| 9 | Project KPI wrong enum value | **CLOSED** — `ProjectStatus::ACTIVE->value` used at line 1711. |
| 10 | Team store stale success | **CLOSED** — `createTeam()` resets `error`/`success` up front. |
| 11 | Dead Enhanced components | **CLOSED** — the four unused `*AnalyticsEnhanced.vue` files are deleted. `PayrollAnalyticsEnhanced.vue` remains and is genuinely used by `views/admin/Dashboard.vue`. |
| 12 | Unused FE dependencies | **CLOSED (mostly)** — `jspdf`, `jspdf-autotable`, `vue3-emoji-picker`, vanilla `lucide`, `lodash-es`, `concurrently` removed. |
| 13 | lodash vs lodash-es duplication | **CLOSED** — `lodash-es` removed; `lodash` retained with 9 importing files. |
| 14 | `data-lucide` dead icon | **CLOSED** — no `data-lucide` remains in `TeamDetail.vue`. |
| 15 | Full `import _ from "lodash"` | **OPEN (nice-to-have)** — still present in `project/detail/TaskDetailModal.vue` uses named `debounce` (fine), but full imports remain in `project/list/CardList.vue`, `staff-member/list/CardList.vue`, `team/detail/Header.vue`, `project/ProjectDetail.vue`. |
| 16 | Unused `concurrently` devDependency | **CLOSED** — removed from `package.json`. |
| 17 | Hardcoded demo content in TeamDetail | **CLOSED** — demo activity text no longer present. |

**Remaining actionable work:** findings 1, 2, 3 (authorization gaps — highest priority), the permanent MySQL CI service for analytics (follow-up of 8), and the cosmetic lodash full imports (15). Findings 1–3 are not yet tracked as beads tickets in the roots I can see; they should be ticketed before implementation.

**Lean (delivered):** dead Enhanced components removed and 6 dependencies dropped by the cleanup PRs; `lodash` remains the single lodash package.

**Not checked:** Runtime performance under load, actual test coverage percentage (no Xdebug/PCOV/coverage-v8), E2E suite pass rate, queue/scheduler behavior, Redis cache correctness, Meilisearch indexing, production Docker deployment, CORS configuration, rate limiting, CSRF double-submit, file upload validation beyond FormRequest rules, PDF/Excel export output correctness, mobile responsiveness, full RBAC matrix for all 40 controllers.
