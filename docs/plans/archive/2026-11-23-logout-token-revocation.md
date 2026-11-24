# Plan: Guarantee Sanctum Token Revocation Before Logout Navigation (team-sync-apx)

**Status:** COMPLETED (2026-11-23)  
**Ticket:** `team-sync-apx`  
**Branch:** `fix/team-sync-apx-logout-token-revocation`  
**Pull Request:** [PR #95](https://github.com/hyaraxco/team-sync/pull/95)  
**Merge commit:** `c0500aa144042d9e4afb23cecd3ad76477fa8401`  
**Post-merge CI:** FE Tests run `45129290411` on `main` passed — 148 test files, production build, accessibility audit.  

## Objective
Ensure the Sanctum personal access token is actually revoked server-side during logout, before the SPA/full-page navigation cancels the in-flight XHR.

## Problem Statement
`team-sync-fe/src/stores/auth.js` `logout()` previously:
1. Removed the token cookie and reset all stores.
2. Started `router.replace()` and `window.location.replace()`, which triggers a full page navigation.
3. Only afterwards fired a fire-and-forget `axiosInstance.post("/logout")`.

`window.location.replace()` unloads the document and cancels in-flight XHR, so the revoke request was routinely aborted. The backend revoke itself is correct (`AuthRepository::logout()` calls `$user->tokens()->delete()`; the route is `POST /api/v1/logout` behind `auth:sanctum`), but it was never reached.

Impact: a stolen or shared-machine token stayed valid indefinitely (Sanctum personal access tokens do not expire by default).

## Implementation
1. Read the token from the store getter first.
2. `await axiosInstance.post("/logout", ...)` with `Authorization: Bearer <token>` and a 5s timeout **before** removing the cookie, resetting stores, or navigating.
3. Swallow revoke errors so a network/backend failure never traps the user in the app; local custody is still cleared and navigation still happens.
4. Keep the non-blocking SPA navigation and hard-redirect fallback, now strictly after the revoke attempt.

## Tests
`team-sync-fe/src/tests/stores/auth.logout.test.js` (rewritten, 4 tests):
- Revoke first: while the request is in flight, no cookie removal, no router navigation, no hard navigation and no state reset occur; after release the order is `revoke -> removeCookie`.
- Revoke failure still clears the local session and navigates (no user lock-in).
- No API call when there is no stored token.
- A hanging SPA navigation promise cannot block logout completion.

## Local Verification (targeted, non-watch, bounded)
- `./node_modules/.bin/vitest run` (repo-pinned local install, 25s full run, no watch mode).
- With Node's `--localstorage-file` shim: **148 files / 1154 tests passed**, including the 4 rewritten logout tests.
- Without that shim, 41 tests in `Header.test.js`, `Header.smoke.test.js` and `useDarkMode.test.js` fail with `localStorage ... undefined`. The identical 41-failure set reproduces on an untouched `f51a342` worktree, so it is an environment artifact of this machine's Node 25+ localStorage removal, not a regression from this change.
- Removed the temporary baseline worktree and localStorage file after use; no development database, server or browser was touched.

## Limits
- No browser E2E or live backend run: full OS-enforced isolation is still unavailable (`team-sync-3ij`). GitHub Actions `fe-tests.yml` remains the authoritative CI evidence for this diff.
- Backend behavior was verified by source reading only; no backend change is included.
- Committed via `git` as the authenticated author; this plan file remains untracked like the other local plans in this repository.
