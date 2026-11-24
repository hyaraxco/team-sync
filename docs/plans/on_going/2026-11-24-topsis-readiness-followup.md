# TOPSIS readiness and cycle regression follow-up

Status: IN_PROGRESS. Ticket: `team-sync-c94`, discovered from closed `team-sync-6g5`.
Baseline: `dc24691365b75e56a3542d5508427218df06ff7f`.
Branch: `fix/team-sync-c94-topsis-readiness`, separate worktree `team-sync-c94`.

## Scope

- Align readiness's on-time count with ranking's existing inclusive due-date rule, retaining null guards and non-mutating dates.
- Strengthen existing readiness and candidate-pipeline tests: same-day/next-day completion, persisted first/last/outside feedback, shared 15/7 cap above the floor, unlinked goal exclusion and explicitly linked outside inclusion.
- Reconcile historical evidence without reopening or certifying closed tickets. No analytics, payroll, schema, production recalculation, dependencies, shared environment or development database changes.

## Execution

- [x] Read project instructions and relevant Ponytail, antislop and copywriting skills. `~/.agents/main.md` is missing.
- [x] Check current callers and tests; fetch origin and create separate branch/worktree. Record ownership in Beads.
- [x] Add regressions in the two existing test files, committed as `18b3ee6e561e8de8f754b674a40df4325211a799` before the production fix. Independent read-only review requested.
- [x] Use the approved offline source-refresh Dockerfile with compatible existing toolchain only. No downloads or dependency installs during tests.
- [x] Demonstrate readiness failure before the fix, then apply the copied end-of-day comparison and run full backend tests and asset build.
- [x] Record before/after evidence and remaining mutation/MySQL/browser limits below. Broader evidence acceptance is not certified.
- [x] Review scope, timestamp persistence, null behavior and linked-goal semantics. Reconcile historical plan/status with observed CI versus reported local evidence.
- [ ] Commit scoped verified checkpoints with authenticated identity; squash final scope, push and open PR. Require latest-head checks and explicit final-content approval before merge.
- [ ] Verify merge/post-merge checks before closure and archive. No release/deploy.

## Existing evidence and limits

PR104 merge `73463f1f42373d09f92fd0f8d419ee8fdffe74dc` has observed post-merge SQLite CI run `45193628215`: 1700 tests, 7705 assertions, 77.12 seconds, config clear, frozen Bun install/build and endpoint artifact upload. It does not cover the additional cases here.

PR103 merge `452a7c48b2450a984765753d645ac6355791ca49` has observed successful BE/FE post-merge CI. Owner acceptance on head `541456791e7fe13353a16081d2d3a16dd73d8945` reports BE1703/7714, FE1167 and role61/61; this is historical source-specific evidence, not a c94 run. Login accessibility has one incomplete and does not certify authenticated pages.

The approved runner uses SQLite; no isolated MySQL lifecycle is provided. Historical browser ranking2/2 does not establish a current-c94 browser pass. Preserve the resolved fixture fixes and inclusive due-date scoring from PR104.

## Current verification log

- `git diff --check`: passed for the initial regressions and production change.
- `python3 -I scripts/sandbox/probe.py --self-check`: passed. This tests runner controls, not application behavior.
- Initial `team-sync-c94:before` image used the short revision label `18b3ee6`; the runner rejected it before application execution. No application result exists for that attempt.
- Rebuilt offline using approved local toolchain `team-sync-tea13-recipe:final`, no pull/network and full revision `18b3ee6e561e8de8f754b674a40df4325211a799`. Resulting before-image config ID: `sha256:2bdf21674bc4ec238622a92107b95bec08a0dd220dd413fb34dff07b6bc3a5c4`.
- `sh scripts/sandbox/run.sh team-sync-c94:before --phase be --seconds 600`: completed with expected application exit 1, no application OOM/watchdog. Exactly the two readiness same-day cases failed (`19:00:00`, `23:59:59`), returning 0 instead of 1 at the `goals_on_time` assertion. Other cases, including all strengthened candidate-pipeline boundaries, passed. Total: 1719 passed, 2 failed, 1 skipped, 7864 assertions, 505.75 seconds; JUnit: 1722 tests, 2 failures, 0 errors. Collector errors empty. Local collector: `c94-before-be-fullsha.jsonl` in the approved session temporary directory. The image freezes pre-fix application/test content; subsequent working-tree edits do not affect it.
- Before image manifest list: `sha256:58eaf83cd4b2e9262ee9709ac3ed342b08cfbccb5b70cd02a651f71abff1dee3`. Collector source manifest: `882bf50f47956fda20101a0272ae9130e15565dcb249ff4019ce93716102832e`. JUnit SHA-256: `abcc937e713bae34e01167bd478aa1a43f3f2be8aa06ca77aeb6bb02c0143604` (summary/hash retained, not promoted raw report). This run predates the additional retained-instance test and cannot prove that test catches a missing `copy()`.
- Independent review identified that the persisted due-date assertion cannot detect transient mutation from removing `copy()`. Added a separate HTTP regression binding a repository stub to retained `Asia/Jakarta` Carbon instances, including a null due-date goal. Its final assertion checks the same instance remains at midnight with the original timezone. This test passed in the after-working run; the existing persisted-date assertion is only a database non-persistence check. A missing-copy mutation was not executed.
- Independent follow-up review found no actionable static issues in the retained-instance test. No runtime pass was claimed by the reviewer.
- Built `team-sync-c94:after-working` offline, including the working-tree fix and retained-instance test. Baseline revision label remains `18b3ee6e561e8de8f754b674a40df4325211a799`; this is not a claim that the uncommitted additions belong to that commit. Tested image manifest list: `sha256:c8e2a2a4f2d8f4e86bd5017091b4077338a54b22c09ea450ae57180149206bfd`; collector source manifest: `763c0988f30058ec70e2d8d160eecec0fbec9b8492580f5aa619bf6967f5a9f8`.
- `sh scripts/sandbox/run.sh team-sync-c94:after-working --phase build-be --seconds 180`: passed (phase exit 0, no OOM/watchdog, collector errors empty). Vite 8.3.5 built 59 modules in 323ms. Runtime: PHP 8.4.28, Xdebug 3.5.3, Node v22.23.5, Bun 1.3.13. This reuses existing frozen-manifest-checked dependencies; no new frozen install was run locally. Collector: `c94-after-build-be.jsonl` in approved temporary directory.
- `sh scripts/sandbox/run.sh team-sync-c94:after-working --phase be --seconds 600`: passed, exit 0, no application OOM/watchdog, empty collector errors. 1722 passed, 1 skipped, 7878 assertions, 465.99 seconds. JUnit: 1723 tests, 0 failures/errors, SHA-256 `722fa91108ce893a02b473756d291dd8ec8b9c8d1617c6eb93c59c36422fc9cf`. All four readiness/ranking boundary cases and the retained-instance/null-due-date test passed. Collector: `c94-after-be.jsonl` in approved temporary directory. Runner pressure-proof OOM and watchdog self-test exit 124 are expected proof events, not failures of the application phase.
- The SQLite suite's one skip is the existing MySQL-only payroll analytics test (`AnalyticsRepositoryAggregationTest.php`), which requires `DATE_FORMAT`. No analytics files were changed; this does not substitute for TOPSIS MySQL verification.

## Remaining limits and integration gate

- Before/after proves the readiness defect only. Cap/count date boundary tests pass on current code, but fixed-ten, per-employee denominator and missing-copy mutation runs have not been executed. The approved runner exposes full phases, not targeted mutation execution. No runner changes are included in this fix.
- MySQL verification requires an isolated, owner-reviewed lifecycle and a toolchain with `pdo_mysql`; the existing recipe provides SQLite. No development database fallback is authorized.
- Current-source browser ranking smoke requires a reviewed ranking phase/recipe; existing runner phases do not provide it. Historical browser 2/2 is not reused as a c94 pass.
- FE unit/build/accessibility checks are outside this backend-only diff, not claimed passed. Backend CI including frozen Bun install/build remains required on the final PR head.
- User approval of the final content, latest-head checks and verified merge/post-merge are required before ticket closure. The plan remains ongoing, not COMPLETED.
