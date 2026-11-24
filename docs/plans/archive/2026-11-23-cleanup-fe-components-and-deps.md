# team-sync-5ns — Remove unused Enhanced analytics components and clean FE dependencies

Date: 2026-11-23
Ticket: `team-sync-5ns` (P2)
Branch: `chore/team-sync-5ns-cleanup-fe-components-and-deps`

## Context & Findings

1. Four unused components in `team-sync-fe/src/components/admin/analytics/` (840 lines total) were dead code without callers:
   - `WorkforceAnalyticsEnhanced.vue`
   - `AttendanceAnalyticsEnhanced.vue`
   - `LeaveAnalyticsEnhanced.vue`
   - `ProjectAnalyticsEnhanced.vue` (had broken import `capitalize` from lodash)
   (Note: `PayrollAnalyticsEnhanced.vue` is in active use in `Dashboard.vue` and kept untouched).
2. Unused dependencies in `package.json`: `jspdf`, `jspdf-autotable`, `vue3-emoji-picker`, `lucide`, `concurrently`. None are imported in `src/`.
3. In `src/components/admin/Header.vue`: replaced `join` from `lodash-es` with native Array join helper handling string/object roles. Removed `lodash-es` from dependencies.
4. `TeamDetail.vue` vanilla icon placeholder `<i data-lucide="graduation-cap">` had already been eliminated in PR #96 / ticket `team-sync-prk` (verified via git log and existing test `TeamDetail.smoke.test.js`).
5. Synchronized `bun.lock` dependencies/devDependencies section with `package.json`.

## Verification

- `npx vite build`: build succeeds in ~4.5s with all chunks cleanly generated.
- `vitest run`: all 148 test files passed (1156 tests).
- Header smoke & unit tests: passed.

## Limits

- Node.js environment was used locally; CI runs the authoritative `bun install --frozen-lockfile` and Playwright/a11y workflow.
