# TEA-63 role-by-feature behavior coverage

Status: COMPLETED
Bead: team-sync-7fk

## Contract

Use the source inventory in `team-sync-fe/e2e/rbac-feature-matrix.md`. Reuse existing domain journeys. Add representative missing browser outcomes with real API responses, protected API denials, ownership and Manager team scope. Keep Staff, Manager, HR, Finance and Superadmin as distinct employee accounts with explicit RBAC; employment position grants no permission. Do not alter the 11 Staff-only mobile fixtures.

## Implementation

- Shared shell: employee identity/profile persistence, notifications, dashboards, analytics and license boundaries.
- Workforce: directory/team, attendance policies, leave/overtime and meetings.
- Performance: self-service, Manager team work and HR administration.
- Finance/project: payroll/THR authority, own payslips and project membership.
- Integration: source inventory links to exact scenarios; uncovered cells remain explicitly unverified.

## Verification

Run focused new/changed specs, then the complete role suite only in the guarded SQLite sandbox or the pinned isolated E2E Compose project. Do not reset or connect to working databases. Record exact outcomes and fixture limitations before review. Preserve failure identity by role and scenario; do not turn authorization defects into passing expectations.

## Execution evidence

Session `01a21c55-6a20-7162-ad39-dae9525e51fa` associated with the board card. Prerequisites are closed. Work started from clean, fast-forwarded main on `test/team-sync-7fk-role-feature-journeys`. The guarded isolated backend policy/review regression passed 7 tests / 45 assertions before subsequent entitlement/team-scope changes; the final outcome-rule backend test now passes 11 / 35 assertions. No working database was reset or used.

Headed foreground verification uses the pinned isolated Compose target with its own MySQL, Redis and Meilisearch volumes. Workforce search remains enabled; the backend addresses Meilisearch through the isolated service network. The reset guard approves the exact updated definition, and setup waits for search health before seeding. The earlier search-disabled seed attempt is not acceptance evidence.

The isolated Compose target explicitly permits 1000 login attempts per minute for browser role journeys. This override is read only when `APP_ENV=testing`; other environments and ordinary backend tests retain five attempts per minute. The existing authentication regression checks that request six is rejected under the default configuration. Search setup now deletes isolated indexes before seeding, imports all employee documents, waits for completion and asserts document counts.

Verification completed in visible foreground using `/Applications/Helium.app/Contents/MacOS/Helium` against the guarded isolated MySQL/Redis/Meilisearch project. Fresh setup reset the database and Meilisearch indexes, imported 10 staff documents, and waited for task completion. Focused role convergence passed all 86 behavior scenarios; focused project lifecycle passed 14/14; default login throttle passed 1 test / 18 assertions. Complete role invocation ran all named matrix and domain specs: 175 passed, 0 failed, 0 skipped, 0 errors (Playwright/JUnit duration 989.238 s; wall time 16.5 min). This includes real UI/API allowed and denied outcomes, Manager team-scope boundaries, and cross-role workflows. The source matrix still labels its explicit unverified cells; the suite does not assert those as covered.

Review handoff: representative scenarios passed; the route/source inventory retains explicitly unverified API-only and endpoint-wide cases. See the matrix rather than interpreting the 175 passing tests as exhaustive authorization proof.
