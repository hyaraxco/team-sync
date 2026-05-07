# E2E Test Suite — Team Sync

## Overview

End-to-end tests using **Playwright** against the Team Sync fullstack application (Vue 3 frontend + Laravel 12 backend).

Tests run sequentially (1 worker) because they share a seeded backend database.

## Prerequisites

- Node.js 22+ (see `.nvmrc`)
- Bun package manager
- Backend running at `http://127.0.0.1:8000`
- Backend seeded with E2E test data

## Setup

```bash
# Install dependencies
bun install

# Install Playwright browsers (first time only)
bun run e2e:install

# Seed the backend database for E2E
bun run e2e:prepare:be
```

## Running Tests

```bash
# Full E2E run (seeds backend + runs Playwright)
bun run e2e

# Run Playwright only (skip backend seeding)
bun run e2e:ui

# View HTML report after a run
bun run e2e:report
```

### Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `E2E_FE_BASE_URL` | `http://127.0.0.1:4173` | Frontend URL |
| `VITE_API_BASE_URL` | `http://127.0.0.1:8000/api/v1` | Backend API URL |
| `CI` | (unset) | Set in CI to enable retries |

## Architecture

```
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
│   └── page-objects/      # (future) Page object models
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

- **`auth.ts`** — `loginAsRole(page, role)` handles the full login flow for `manager`, `hr`, `finance`, or `employee`
- **`backend.ts`** — `processQueueOnce()` and `drainQueue()` for triggering queued jobs during E2E
- **`evidence.ts`** — `captureEvidence(page, filename)` for full-page screenshots

## Best Practices

1. **Selectors**: Use `data-testid` attributes, `getByRole()`, `getByText()`, or `getByLabel()` — never CSS class selectors
2. **Isolation**: Each test should be independent. Don't rely on test execution order
3. **No hard waits**: Use `expect().toBeVisible()`, `waitForURL()`, or `waitForResponse()` — never `page.waitForTimeout()`
4. **Login once per test**: Use the `authenticatedPage` fixture or `loginAs` helper
5. **Evidence on failure**: Playwright auto-captures screenshots and traces on failure (configured in `playwright.config.ts`)

## CI Integration

- **Reporter**: JUnit XML output at `test-results/junit-results.xml` for CI dashboards
- **Retries**: 1 retry in CI (`process.env.CI`), 0 locally
- **Artifacts**: Traces and screenshots retained on failure only
- **Backend prep**: CI must run `bun run e2e:prepare:be` before E2E tests

## Seeded Test Accounts

| Role | Email | Password |
|------|-------|----------|
| Manager | yudhis@teamsync.com | teamsync |
| HR | tasyia@teamsync.com | teamsync |
| Finance | dwimeta@teamsync.com | teamsync |
| Employee | agung@teamsync.com | teamsync |

## Troubleshooting

- **"Backend health endpoint is not ready"** — Start the backend: `cd ../team-sync-be && composer dev`
- **"Seeded login for role X is unavailable"** — Run `bun run e2e:prepare:be` to seed the database
- **Flaky timeout on login** — Increase `timeout` in `loginAsRole` or check backend response time
- **"Cannot find module"** — Run `bun install` and `bun run e2e:install`
