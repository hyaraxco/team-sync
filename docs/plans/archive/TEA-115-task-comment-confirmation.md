# TEA-115: Wait for confirmation before deleting task comments

Status: COMPLETED

## Scope

Fix comment deletion in `TaskDetailModal`: opening or cancelling confirmation must not call the delete API; confirming calls it once. On failure, keep the dialog available for retry and show the existing error toast. Do not exercise deletion against real comments.

## Approach

Remove the eager API call after opening the dialog. Keep deletion inside the confirmation action, and only close the confirmation when that action succeeds. Cover cancel, one-time confirm, and a failed attempt followed by a retry using mocked task-store calls.

## Verification

Passed: `bun install --frozen-lockfile`, focused tests (89/89), build, and ESLint on the changed files.
Full Vitest failed locally in 5 unrelated files (62 tests failed; 158 files passed) due to missing `localStorage`/`document` globals. Repository-wide lint reported 12 warnings in unrelated files.
Accessibility audit could not run: Chrome was unavailable after attempting `bunx browser-driver-manager install chrome`. Frontend CI must pass on the latest PR SHA before merge.
