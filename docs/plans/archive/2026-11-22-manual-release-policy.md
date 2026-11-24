# team-sync-0i7: explicit release policy

Status: COMPLETED

PR #91 merged as 02fdcf0d851c7c3fcf5f45414cdf18f9333eef6e.
Post-merge run 45014650589: test SUCCESS, publish SKIPPED on main push.
Beads team-sync-0i7 closed. No manual release/deploy performed; actual manual
publication remains untested without separate authorization.

User approved manual releases, not release on every PR merge.
Skills: antislop and context7-mcp (loaded); ponytail unavailable.
Global ~/.agents/main.md unavailable. No UI or application changes.

- Replace auto-tag publisher with explicit version/SHA workflow_dispatch.
- Keep PR/main policy tests without publishing; require both exact-SHA BE/FE runs.
- Validate stable increasing versions, main ancestry, and latest workflow outcome.
- Preserve existing tags/releases. Do not manually publish to prove the policy.
- Verify PR CI, obtain final approval, then verify main policy tests and skipped publisher.

Local evidence: Python stdlib unittest, 5 tests covering valid inputs, invalid
versions/SHA, existing/lower versions, absent/failed/pending/skipped/PR/wrong-SHA
runs, and superseded success. No production write during tests.
