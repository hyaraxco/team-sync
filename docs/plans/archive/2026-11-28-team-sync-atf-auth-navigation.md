# Authentication navigation fixes (team-sync-atf)

Status: COMPLETED (implementation and local acceptance verified; PR review pending)

## Evidence and scope

A clean browser entering `/` redirects to login in the same document. Login and `/me` return 200, the cookie and user exist, but the non-reactive token getter remains empty and rejects dashboard navigation. Refresh remains on login; a second login succeeds. A separate intercepted `/me` 503 reproduces repeated requests and an infinite redirect.

Use Ponytail for minimal implementation and antislop for preserving the existing surface. No UI restyling, backend changes, new dependencies, or database resets.

## Implementation

1. Initialize token as reactive auth state from the existing cookie. Centralize token writes and session clearing for login, logout, HTTP 401, and setup bootstrap.
2. Make `checkAuth()` reject failed profile reads while retaining its visible error; the profile-edit initializer catches that rejection. `updateProfile()` catches write or refresh failures, sets the visible error, and resolves `undefined`; it reports success only after refresh succeeds.
3. Mark the login route guest-only and validate existing sessions before redirecting to dashboard. Leave login available when profile validation fails.
4. Update existing login and logout assertions, add real-store/router regression coverage, and cover HTTP 401 cleanup. Preserve Remember me cookie lifetime and server revocation order.
5. Load the auth store only inside the HTTP 401 handler, awaiting session cleanup before redirecting. Ordinary API consumers must not initialize the router through the shared HTTP client; unrelated component router mocks remain unchanged.

## Acceptance and checks

- One submit after a protected-route redirect displays dashboard content without a document reload.
- Dashboard reload and login-page refresh restore a valid session.
- Profile HTTP 500/503 and network failures stop authenticated navigation and do not loop.
- HTTP 401 and logout clear cookie, token state, and user; setup-created tokens enter the same state path.
- Run focused auth, router, profile and affected caller tests once after edits. Browser smoke uses the running isolated local API without reseeding.
- Open a PR and leave the ticket in review. Merge and closure require separate user approval and current-SHA checks.

## Verification results

Focused Vitest run after review corrections: 9 files, 78 tests passed using `NODE_OPTIONS=--no-experimental-webstorage` to avoid the host Node 26 native localStorage conflict. Includes real-store/router entry-path regressions, HTTP 401 cleanup, logout revocation order, login, profile edit, profile-update refresh failures (503 and network), and auth caller contracts. The setup component test submits the admin form, asserts `setToken("fake-token")`, observes completion, and exercises login navigation; bootstrap API behavior remains mocked. The obsolete setup cookie mock was removed. The existing jsdom hard-navigation diagnostic remains non-failing.

Import-boundary correction: the shared HTTP client no longer imports auth or routing at module initialization. The async 401 handler loads auth only when Pinia is active and clears the session before redirecting. If that import or cleanup fails, cookie removal and redirect still proceed, and the original API error is preserved. Focused verification after the final correction passed 12 files and 87 tests, including both consumer suites that failed in CI (AttendancePeriods and MeetingList), real HTTP 401 cleanup, rejected-auth-import cleanup and redirect, and the nine-file authentication regression set above. No unrelated router mocks changed.

Browser smoke passed against the running isolated API: protected-root first-submit login displays employee greeting and Upcoming Tasks; dashboard reload and login-page session restoration display the greeting; intercepted profile 503 and network failure each leave login with a visible error and enabled retry, without page exceptions or navigation loops. After review corrections, a profile-update browser smoke intercepted the successful write and failing refresh: only the refresh error was visible, retry was enabled, and no page exception occurred. No real profile writes, database reset, or dependency installation.

Limits: decisive browser smoke uses the employee account and existing managed Chromium, not all roles or production. Full-suite, build, and accessibility evidence will come from the frontend PR workflow. Merge and ticket closure are not authorized by implementation approval.
