# PR #80 backend Vite upgrade

Status: COMPLETED for implementation and local asset verification. Merge remains gated by current PR checks.

## Scope

Review the open Dependabot PR, rebase it onto current main, use the current Bun graph as the baseline for its Vite 8 / Laravel Vite Plugin 3 changes, and merge only if the latest checks are green. Preserve local secrets and the uncommitted MySQL-only Compose configuration. All commits pushed by this session use the owner's GitHub noreply identity.

## Findings

- The PR title mentions esbuild removal, but the actual manifest upgrades Vite 7 to 8 and Laravel Vite Plugin 2 to 3.
- The npm lock is updated; the tracked Bun lock is stale. A frozen Bun 1.3.13 install in a credential-free disposable copy exited 1 with `lockfile had changes, but lockfile is frozen`.
- Historical BE CI is failed. Retained metadata identifies Run Pest tests; old detailed logs are unavailable. The review and former screenshot checks are not evidence of a verified backend asset build.

## Plan

1. Rebase the PR and preserve private/local files.
2. Preserve current Bun resolutions and update only the selected Vite/Laravel packages within the PR's major ranges. Remove the obsolete npm lock rather than maintaining conflicting dependency graphs; do not introduce or run npm.
3. Run a frozen install and production build, then exercise the Vite dev server against actual backend entry points in a disposable source copy with no private environment.
4. Add backend asset frozen-install/build coverage to the existing backend workflow, record evidence, consolidate into one owner-authored commit, and push the PR branch with an exact lease.
5. Wait for the current head's required checks. Rebase-merge only when green, synchronize main, and remove the completed branch.

No production database, migrations, seeders or live application probes are part of this task.

## Observed verification

- A verified temporary Bun 1.3.13 executable was downloaded outside the repository; its SHA-256 matched the official GitHub release asset digest.
- All dependency installation, production builds and dev-server probes used a disposable copy of tracked backend source, an allowlisted environment and scratch-local home/cache. No private `.env` was copied.
- Importing the PR's npm resolution would downgrade Axios from the current Bun baseline and add advisory IDs. That candidate was rejected, not published.
- The final targeted Bun update selects Vite 8.3.5 and Laravel Vite Plugin 3.2.1, preserving Axios 1.20.0. Frozen installation passed; both Laravel asset entry points compiled and referenced generated manifest files.
- The final Vite dev server served its HMR client and the real app JS, bootstrap JS and CSS with HTTP 200 on loopback, then was stopped. No Laravel application/database endpoint was probed.
- The plugin correctly rejected an initial dev-server smoke using `CI=true`; the actual development smoke used an ordinary non-CI environment, without bypassing that guard.
- Registry audit findings decreased from 19 on current main to 6, with no new advisory IDs. The remaining findings are pre-existing follow-redirects, shell-quote and source-map-js advisories; one is rated critical by the advisory feed. This comparison is not application exploit validation or a clean security certification.
- The existing backend workflow now installs the frozen Bun graph and builds assets with Node 22 before PHP tests, so future bundler PRs are not judged solely by PHP results.
- Historical CI details are limited to a failed Pest step and exit code 2; no precise historical failure cause is claimed. Current-head CI and the final merge decision are recorded on PR #80.
