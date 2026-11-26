# E2E Test Suite — Team Sync

## Overview

End-to-end tests using **Playwright** against the Team Sync fullstack application (Vue 3 frontend + Laravel 12 backend).

Tests run sequentially (1 worker) because they share a seeded backend database.

Only regression specs belong in this suite. Temporary log-only debug specs
were removed during cleanup; do not commit page/API dumps as passing tests.

## Prerequisites

- Node.js `^20.19.0 || >=22.12.0` (see `package.json`; `.nvmrc` selects the local version)
- Bun package manager
- Backend at `http://127.0.0.1:8000` using a verified disposable test database only
- Seeded E2E role accounts; Superadmin is pending `team-sync-dps`

## Setup

Run commands from `team-sync-fe/`. Preparation accepts only the checked-in E2E Compose file with its pinned hash. It uses the separate `teamsync-e2e-isolated` project, project-scoped volumes, test-only database credentials, and a read-only `/dev/null` mount over the backend `.env`. It does not read the development `.env` or use the default Compose project. `migrate:fresh` resets only this E2E database. Confirm `127.0.0.1:8000` is free; a port bind conflict stops startup. The guard and wrapper preflight pass without starting Docker. No Docker services or database reset were run during this change.

Install dependencies and the browser without touching the backend database:

```bash
bun install
bun run e2e:install
```

Run `bash ../team-sync-be/scripts/e2e-prepare-be-guard-test.sh` for isolated-target acceptance/refusal checks; it does not start Docker or run seeds. For destructive role coverage without MySQL, use the independent guarded SQLite sandbox described below. Never point reset commands at development or production resources.

## Running Tests

`bun run e2e` runs backend preparation and resets the approved E2E database. Use only its pinned Compose target; do not override `E2E_BE_DIR` or the Compose file to point at a development or production stack. The guard acceptance test is `bash ../team-sync-be/scripts/e2e-prepare-be-guard-test.sh`. A full container/database reset was not run during this change.

For destructive role coverage without MySQL, provision the guarded SQLite sandbox's local toolchain image as documented in [`../../scripts/sandbox/README.md`](../../scripts/sandbox/README.md), then run from the repository root:

```sh
sh scripts/sandbox/run.sh team-sync-tea13-toolchain:local --phase roles --seconds 1800
```

`bun run e2e:ui` skips preparation but still sends requests to `VITE_API_BASE_URL`; use it only with an already seeded disposable backend.

## Role fixtures and matrix

Source-backed permissions and endpoint boundaries are tracked in [the RBAC route and API source inventory](./rbac-feature-matrix.md). Its allow/deny cells reflect source permissions, not verified runtime behavior; API authorization remains unverified where route declarations provide no explicit boundary. Unverified outcomes are not behavior-test evidence. A hidden link or frontend redirect does not prove that the backend denies access.

## Current normal-fixture accounts

Each listed account belongs to the normal E2E seed. The current seed does not create a Superadmin account. Passwords are local test values and must not be reused in production.

| RBAC role | Employee account |
|---|---|
| Staff | `agung@teamsync.com` |
| Manager | `yudhis@teamsync.com` |
| HR | `tasyia@teamsync.com` |
| Finance | `dwimeta@teamsync.com` |
| Superadmin | Not seeded yet. Tracked by `team-sync-dps`. |

### Environment variables

| Variable | Default | Description |
|----------|---------|-------------|
| `E2E_FE_BASE_URL` | `http://127.0.0.1:4173` | Frontend URL |
| `VITE_API_BASE_URL` | `http://127.0.0.1:8000/api/v1` | Backend API URL |
| `CI` | (unset) | Set in CI to enable retries |

## Architecture

e2e/
├── *.spec.ts              # Test spec files
├── global-setup.ts        # Health check + seeded login verification
├── helpers/
│   ├── auth.ts            # Role-based login helper
│   ├── backend.ts         # Queue processing helpers
│   └── evidence.ts        # Screenshot capture helper
├── support/
│   ├── fixtures/
│   │   └── index.ts       # Extended test fixtures (authenticatedPage, userFactory, loginAs)
│   ├── factories/
│   │   └── user.factory.ts # User data factory with auto-cleanup
└── README.md              # This file
```

### Fixtures

The fixture architecture extends Playwright's `test.extend()` to provide:

- **`authenticatedPage`** — A page pre-logged-in as HR (most common E2E actor)
- **`userFactory`** — Factory for building test user payloads with auto-cleanup
- **`loginAs`** — Helper to login as any role on the current page

```typescript
import { test, expect } from '../support/fixtures';

test('HR can view staff list', async ({ authenticatedPage }) => {
    const { page } = authenticatedPage;
    await page.goto('/admin/staff-members');
    await expect(page.getByText('Staff Members')).toBeVisible();
});
```

### Factories

Factories build deterministic test data payloads:

```typescript
import { test } from '../support/fixtures';

test('create user flow', async ({ userFactory, page }) => {
    const payload = userFactory.buildUserPayload({ name: 'Jane Doe' });
    // Use payload in form fills or API calls
});
```

### Helpers

- **`auth.ts`** — `loginAsRole(page, role)` handles the seeded roles listed above.
- **`backend.ts`** — `processQueueOnce()` and `drainQueue()` for triggering queued jobs during E2E
- **`evidence.ts`** — `captureEvidence(page, filename)` for full-page screenshots

`drainQueue()` checks for a running Compose `web` container before selecting the worker. If Compose or the container is unavailable, it uses PHP in `E2E_BE_DIR` (default `../team-sync-be`). Both workers inherit the selected backend environment: use only a disposable test database. Worker failures propagate without retrying against another backend. Payroll generation checks require an observed `POST /payrolls/generate`; a disabled submit button must have an explicit readiness denial.

## Best Practices

1. **Selectors**: Use `data-testid` attributes, `getByRole()`, `getByText()`, or `getByLabel()` — never CSS class selectors
2. **Isolation**: Each test should be independent. Don't rely on test execution order
3. **No hard waits**: Use `expect().toBeVisible()`, `waitForURL()`, or `waitForResponse()` — never `page.waitForTimeout()`
4. **Login once per test**: Use the `authenticatedPage` fixture or `loginAs` helper
5. **Evidence on failure**: Playwright auto-captures screenshots and traces on failure (configured in `playwright.config.ts`)

## CI Integration

- FE CI runs Vitest, production build and the configured accessibility audit. It does not run Playwright role E2E.
- Playwright writes JUnit output; the local config retains traces, screenshots and video on failure.
- Do not describe normal E2E seeding as isolated until `team-sync-wlk` passes.


## Troubleshooting

- **"Backend health endpoint is not ready"** — Start the backend: `cd ../team-sync-be && composer dev`
- **"Seeded login for role X is unavailable"** — Do not run `bun run e2e:prepare:be` until `team-sync-wlk` proves the target is isolated. Use only the guarded SQLite sandbox for destructive role tests until then.
- **Flaky timeout on login** — Increase `timeout` in `loginAsRole` or check backend response time
- **"Cannot find module"** — Run `bun install` and `bun run e2e:install`
