# PR105 / PR106 conditional integration

Status: COMPLETED_WITH_WORKFLOW_DEVIATION
User authorization: verify against latest main; merge only if no conflict.
Baseline: 95cb829d30410201cd6227f61556667c708ba06b.

1. Inspect both PR diffs, ticket acceptance, existing main implementation, and merge conflicts without changing application behavior.
2. Replace PR105's HTTP 500 allowlist with portable month/hour SQL expressions and deterministic HTTP 200 numerical fixtures, as requested by the user review. Preserve SQL aggregation and MySQL whole-hour semantics. Verify in the existing offline Linux sandbox with no installs or host DB/env access.
3. Merge only if acceptance, review, and latest-head checks pass. PR106 conflicts in repository and aggregation regression; PR108 already merged the same task upper bound plus due-date lower bound. Do not resolve or merge PR106 under conditional authorization.
4. Require four nonempty successful endpoint fixtures covering correction counts/rate, truncated-hour leave statistics, payroll cost/distinct headcount and fractional average, and processing-time status/date/null exclusions. Run affected analytics plus full BE and backend assets on the new exact SHA. No HTTP 500 acceptance remains.
5. Record observed results on PRs and beads; preserve branches and data for blocked work. Archive this review plan once all reachable verification and reporting are complete. Close no ticket lacking acceptance and merge evidence.

Skills: antislop and security-audit reviewed; ponytail-review unavailable. No UI changes. PHP LSP unavailable (neither intelephense nor phpactor installed); source evidence used without installing tools.

## Verification iterations

- Original rebased PR105 candidate: full BE 1703 passed, but four endpoint 500 expectations do not satisfy acceptance.
- Portable candidate: three numeric fixtures passed; processing fixture errored because `approved_at` is not a payroll column. Full BE: 1703 tests, zero assertion failures, one error. Backend asset build passed.
- Review correction applied: processing time joins approved-step completion grouped by payroll ID using `MAX(approved_at)` from `payroll_approvals`. The fixture includes two approved steps, a rejected timestamp, pending payroll, null completion and out-of-window payroll. Corrected full BE completed: 1703 passed, 7701 assertions, zero failures/errors/skips.

Overlap: team-sync-gwe owns the remaining undefined payroll model status constants. PR105 changes only its two affected endpoints; GWE owner notified. No closure claim for GWE.

## Final result

[PR105](https://github.com/hyaraxco/team-sync/pull/105) merged as [005d6cac69840db51009fb444f66e169789b4827](https://github.com/hyaraxco/team-sync/commit/005d6cac69840db51009fb444f66e169789b4827) by hyaraxco, with one parent (95cb829) and no coauthor. Full corrected sandbox BE: 1703 tests and 7701 assertions passed, collection errors empty; source manifest `6930534ac0632a8926242f7e6e96ece15539f3e94272d0f74e20528524df4f42`. Analytics on exact pushed head `ace2cc279271dfd24c0a82754b81351bfa060009`: 30 tests and 70 assertions passed. Backend build passed. [Premerge BE](https://github.com/hyaraxco/team-sync/actions/runs/45214890227) and GitGuardian passed. [Actual merge-SHA BE](https://github.com/hyaraxco/team-sync/actions/runs/45215403625) passed, including frozen Bun install/backend build. FE/a11y N/A for BE-only change. MySQL SQL branch not executed locally. Root main fast-forwarded; disposable candidate images removed, user toolchain/data/env preserved.

Workflow deviation: the branch retained original `7b8bc32` plus follow-up `ace2cc2` when squash merge executed. The merge command was cancelled after review guidance flagged the premerge branch-squash omission, but GitHub had already completed it. Main has one squash commit, but the required premerge branch squash was missed. [The corrected PR record](https://github.com/hyaraxco/team-sync/pull/105#issuecomment-7107110930) supersedes earlier comments attributing the intervention to a user message. No main rewrite or relabelled checks.

PR106 remains open and untouched because latest-main merge simulation conflicts and PR108 already superseded its predicate. DTW was already closed, no status change. GWE remains separate for other status constant occurrences. No release/deploy.

## Archive persistence correction

The first completion claim was premature: the archive existed only in the auxiliary ARN worktree, not root main. Cleanup was reopened and ARN returned to review. [PR112](https://github.com/hyaraxco/team-sync/pull/112) persisted the archive as [b164cbe](https://github.com/hyaraxco/team-sync/commit/b164cbefe951c1f34343fe02872e7633d8125acb). [PR113](https://github.com/hyaraxco/team-sync/pull/113) corrected the intervention attribution as [ca16075](https://github.com/hyaraxco/team-sync/commit/ca160755699c8c3d37c0e612853c7d79d2aa1ace): review guidance, not a user message, flagged the omission. Root main was fast-forwarded and its archive content verified after PR113. Documentation checks covered content and Markdown links; application suites were N/A for both docs-only PRs. No application checks were relabelled.

