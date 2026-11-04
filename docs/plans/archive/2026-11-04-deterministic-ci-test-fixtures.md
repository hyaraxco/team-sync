# Deterministic CI test fixtures

Status: COMPLETED for test-fixture implementation. Final PR checks gate the authorized rebase merge.

## Scope

Review and repair the failed leave-date test, remove the backend fixture collision discovered by the next CI run, and remove the PR screenshot workflow at the owner's request. Rebase and merge PR #81 only after the remaining checks pass. Preserve user author identity and all local-only configuration.

## Diagnosis

The existing reversed-date test uses June 2026 dates without controlling the clock. The November CI run reaches the earlier past-start-date guard. The blocked-submission contract is useful; an exact warning-string assertion unnecessarily couples the test to wording and does not prove valid ranges can submit.

## Plan

1. Freeze Date only for the reversed-range scenario and restore the real clock after each validation test.
2. Assert that a reversed future range does not submit, then correct only the end date and assert successful submission and modal transition. Do not change production validation.
3. Verify the actual Vitest scenario and full CI; use CI because local Bun and frontend dependencies are absent. Do not install runtimes or reset databases.
4. Record verification, update changelog, consolidate into one user-authored commit, rebase on current main, push with an exact force-with-lease, then wait for checks before rebase-merging and removing the branch.

## Local safety

Stage only explicitly reviewed test/docs paths. Preserve hashes of environment files, Beads metadata and the tracked local Compose change; never include them in outgoing commits. Do not bypass checks or branch protection.

## Verification and integration boundary

- The original frontend CI failure was observed at `MyAttendance.test.js:229`; no failing-before rerun was needed.
- `node --check` passed for the repaired test. A throwaway harness executed the actual production submission function and verified rejection of a reversed future range followed by acceptance of the corrected range, including modal transitions.
- Throwaway mutation checks detected both removal of the end-date guard and an always-reject implementation. No harness file was added to the repository.
- Frontend run https://github.com/hyaraxco/team-sync/actions/runs/41861262315 passed Vitest, build and accessibility audit after the fix. The frozen clock is limited to Date and restored after the validation tests.
- Backend run https://github.com/hyaraxco/team-sync/actions/runs/41861262339 exposed a random duplicate date in `MyHybridOverridesTest::test_overrides_are_paginated`: the database correctly rejected duplicate `(staff_member_id, date)` values before pagination was exercised.
- Multi-row fixtures sharing a staff member now use distinct deterministic dates in both affected override-list test classes. The status-filter pair also has explicit distinct dates. The factory, production code and uniqueness constraint remain unchanged.
- Backend execution is deferred to the final PR checks: PHP/Composer are not on the local PATH. No local runtime installation, migrations or database resets were performed.
- The branch remains a single user-authored commit. Final checks and merge results are recorded on PR #81, not claimed here before execution.

## Owner-requested workflow removal

- The screenshot check had swallowed a duplicate-frontend-server startup failure and uploaded no artifacts. Review evidence is recorded on PR #81.
- The owner chose removal rather than repair. Delete `.github/workflows/pr-screenshots.yml`, update active context/changelog references, and preserve the existing E2E suite, Playwright configuration and failure-capture helpers.
- FE/BE and security checks remain merge gates. No branch-protection setting or ruleset was disabled for this removal.
