# TEA-63 role-by-feature behavior coverage

Status: IN_PROGRESS — waiting for a successful guarded focused/full role run; CI passes on the current head.
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

Session `01a21c55-6a20-7162-ad39-dae9525e51fa` associated with the board card. Prerequisites are closed. Work started from clean, fast-forwarded main on `test/team-sync-7fk-role-feature-journeys`. Earlier local backend and browser results below used uncommitted prerequisite changes. They do not validate PR head `7a8409606eace2b628de15ee41bd1c75e8d231ef`: backend CI failed two outcome-rule tests because their request fixes were absent. Review also found missing authorization and sandbox integration changes, unscoped performance analytics and two false-positive browser assertions. Fresh committed-snapshot verification is required. No working database was reset or used.

Headed foreground verification uses the pinned isolated Compose target with its own MySQL, Redis and Meilisearch volumes. Workforce search remains enabled; the backend addresses Meilisearch through the isolated service network. The reset guard approves the exact updated definition, and setup waits for search health before seeding. The earlier search-disabled seed attempt is not acceptance evidence.

The isolated Compose target explicitly permits 1000 login attempts per minute for browser role journeys. This override is read only when `APP_ENV=testing`; other environments and ordinary backend tests retain five attempts per minute. The existing authentication regression checks that request six is rejected under the default configuration. Search setup now deletes isolated indexes before seeding, imports all employee documents, waits for completion and asserts document counts.

Historical dirty-checkout browser run (not PR acceptance): visible Helium against guarded isolated MySQL/Redis/Meilisearch fixtures reported 86 focused behavior scenarios, 14 project lifecycle scenarios and 175 complete role scenarios passing. Default login throttle reported 1 test / 18 assertions. The final committed snapshot must be checked independently; do not reuse these totals as proof that the current branch passes. The source matrix retains explicit unverified cells; representative journeys are not endpoint-wide authorization proof.

Review handoff remains blocked pending the scoped fixes and current-SHA CI. Keep the source matrix's explicit unverified cases visible.

## PR review remediation

- Include the missing inactive outcome-rule overlap validation and Manager restrictions for policy/entitlement mutations, attendance reads and assigned review detail, together with their regression tests.
- Restrict team performance analytics to led teams; retain company-wide metrics for the seeded `analytics-hr-view` permission. The Manager UI selects a led team and does not request company ratings.
- Exercise the actual Leave Entitlements edit control and denied API update; require Finance project-list data and check the creation link plus denied API creation.
- Run focused and complete SQLite selections independently on fresh guarded fixtures; require both JUnit summaries. Keep the executable shell guard, remove executable permissions from ordinary source/test files.
- Local checks cover the changed backend tests and their callers, analytics store and attendance component tests, and sandbox collector checks. Commit the candidate before building the offline acceptance image; then run role/browser verification and wait for required CI on that head. Record results below only after observation.

## Final review follow-up

- Move Manager team/member authorization lookup behind `AnalyticsRepositoryInterface`; keep request validation and authorization response at the FormRequest boundary.
- Validate effective `min_rating <= max_rating` for partial active outcome-rule updates before checking overlaps.
- Preserve focused and complete Playwright exit statuses independently; both runs and both JUnit reports are required.
- Prior guarded role attempts reached the 3600-second watchdog while browser cases were still running. Re-run after these fixes on a fresh committed archive with host cleanup headroom; partial cases are not acceptance.
BE/FE CI passed on `cf5732f1a5f2080103c1d981f65bdf12c09a2a11`. The matrix links this plan; add it to version control despite the directory ignore rule.

Focused backend checks on the current code: 68 tests / 398 assertions; the one existing MySQL-only analytics test was skipped under SQLite. The analytics export test doubles pass 9 tests / 20 assertions. The inactive and active outcome-bound contract passes 13 tests / 47 assertions.

The full guarded suite has not completed successfully. Prior attempts hit the 3600-second watchdog before finishing the focused behavior selection, so none of their partial cases or missing reports count as acceptance. Latest BE/FE CI and GitGuardian checks pass on `cf5732f1a5f2080103c1d981f65bdf12c09a2a11`; merge remains blocked until both JUnit reports and terminal suite status pass.
tracked evidence
