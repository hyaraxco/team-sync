# PR111 latest-main verification

Status: COMPLETED
Bead: team-sync-pto
PR: https://github.com/hyaraxco/team-sync/pull/111
User authorized verification on latest main and merge if conflict-free. Baseline: 452a7c48b2450a984765753d645ac6355791ca49. Branch rebased without conflict; preserve PR109 heading scopes and Finance project visibility.

1. Review two-file change and affected queue callers. Restrict host fallback to unavailable Compose container, not worker/job errors. Keep mandatory POST observation and explicit readiness denial.
2. Leave a focused queue-selection/error regression check, then squash and push one scoped commit with hyaraxco identity. Run exact-head browser role journeys in the existing offline disposable Linux sandbox. No host env/DB changes, reset wrappers, installs or downloads.
3. Require FE Vitest/build/accessibility and enabled security checks on latest head; backend CI N/A for test-only FE change. Merge only if latest main remains integrated and no conflicts. Verify post-merge FE checks, fast-forward root main, remove merged branch, archive plan and close only this ticket.

Limits: sandbox queue is sync for browser journeys, so prove host/Compose selection and worker-error behavior separately with isolated child-process test doubles. Agent's historical MySQL/Chromium1248 report is not evidence for rebased head. Do not claim an old run as a new-head result. Scope excludes PR105/106 and production code.


## Final evidence

Final head fab8765dabba5eb85977fab957a87563c6992d29, latest main452a7c48b2450a984765753d645ac6355791ca49, no conflicts. Full FE1172 including5 queue regression cases and standard role61/61 pass; image sha256:40df62ed1e33aec088f32de04347dcda207692fed0af41d9441492523833e90b, manifest1b78716f54b87be96265fc04878575ef03d5544354f72b2822fe01263560abb1. Evidence: https://github.com/hyaraxco/team-sync/pull/111#issuecomment-7106170132.

Additional disposable harness with QUEUE_CONNECTION=database, no Docker, scratch SQLite and MAIL_MAILER=array passed9/9 across notification-employee-task/payroll-roles/role-navigation. App/helper/spec bytes exact head; only suite harness env/spec selection changed. Derived image sha256:36fecb1aa66cc825f3cecad32c8cad6b5241895fec77901c09f98bd6e23139c4, manifest9175ff5ad959312febabf437678bd48724bf329055d9823e38c2586d8b200da7. Evidence: https://github.com/hyaraxco/team-sync/pull/111#issuecomment-7106216933. Zero application OOM/watchdog or collection errors; all controls/cleanup pass. Throwaway harness/image removed.

Merged https://github.com/hyaraxco/team-sync/commit/95cb829d30410201cd6227f61556667c708ba06b by hyaraxco, authorhyaraxco/no coauthor. Premerge FE/security passed; post-merge FE Vitest/build/accessibility passed https://github.com/hyaraxco/team-sync/actions/runs/45209993791. Root main fast-forwarded, PTO worktree retained detached at merge, merged branch removed locally/remotely. Backend CI N/A for frontend tests-only diff. No host DB/env edits, installs/downloads, production change, release or deploy.

Limit: historical originating-agent no-click negative control remains historical, not rerun or relabelled. Queue unit regression uses child-process mock; additional database-queue browser proof covers real worker delivery. This plan stays a local ignored archive; public final evidence is in PR111.