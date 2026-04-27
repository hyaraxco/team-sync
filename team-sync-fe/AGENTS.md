# FRONTEND KNOWLEDGE BASE (team-sync-fe)

## OVERVIEW

Vue 3 SPA. Vite 7. Composition API with `<script setup>`. Pinia state management. Tailwind CSS. Bun runtime. Two role-based view trees: admin (HR/manager) and staff-member (employee self-service).

## STRUCTURE

```
src/
├── assets/css/           # Global CSS (Tailwind base)
├── components/
│   ├── admin/            # Admin-only components
│   ├── common/           # Shared across roles
│   └── staff-member/     # Staff-member-only components
├── composables/          # Reusable logic hooks
│   ├── useAnimatedNumber  # Number animation
│   ├── useConfirmAction   # Confirmation dialogs
│   ├── useSearchFilter    # List filtering
│   └── useToast           # Toast notifications
├── config/               # App configuration
├── helpers/              # Utility functions
├── layouts/              # Page layout wrappers
├── plugins/              # Vue plugin registrations
├── router/               # Route definitions (9 domain files)
├── stores/               # Pinia stores (21 stores)
│   └── __tests__/        # Store unit tests
├── tests/                # Component/integration tests
│   ├── admin/            # Admin view tests
│   ├── router/           # Route guard tests
│   ├── staff-member/     # Staff view tests
│   └── stores/           # Store tests (alt location)
├── utils/                # Low-level utilities
└── views/
    ├── admin/            # HR/manager views
    │   ├── analytics/
    │   ├── attendance/
    │   ├── payroll/
    │   ├── performance/
    │   ├── project/
    │   ├── staff-member/
    │   └── team/
    ├── auth/             # Login, password reset
    ├── staff-member/     # Employee self-service views
    └── NotFound.vue      # 404 page
e2e/                      # Playwright E2E tests
├── helpers/              # E2E test utilities
scripts/
└── e2e-prepare-be.sh     # Backend seed/reset for E2E
```

## WHERE TO LOOK

| Task | Location | Notes |
|------|----------|-------|
| Add page | `src/views/{role}/` + route in `src/router/{domain}.js` | Match existing domain split |
| Add store | `src/stores/{domain}.js` | One store per domain, Pinia |
| Add component | `src/components/{role}/` or `common/` | Role-scoped or shared |
| Add composable | `src/composables/use{Name}.js` | `use` prefix convention |
| Add unit test | `src/tests/{role}/` | Mirror view structure |
| Add E2E test | `e2e/` | Playwright, uses `e2e-prepare-be.sh` |
| Route guards | `src/router/permissionAccess.js` | Central permission logic |
| API calls | Inside Pinia stores (Axios) | Never call API from components directly |

## CONVENTIONS

- **`<script setup>`** — always. No Options API
- **JS, not TS** — stores and components are `.js`/`.vue`, not TypeScript
- **`@/` alias** → `src/` (configured in `vite.config.js` + `jsconfig.json`)
- **One Pinia store per domain** — 21 stores matching backend domains
- **API calls live in stores** — components dispatch store actions, never call Axios directly
- **Tailwind utility classes** — no custom CSS unless truly unavoidable
- **Lucide Vue Next** for all icons
- **ApexCharts** — globally registered as `VueApexCharts` for charts
- **Luxon** for date/time handling (not moment, not dayjs)
- **Lodash** for utility functions
- **Vitest config**: `vitest.config.js` — jsdom environment, global test APIs

## ANTI-PATTERNS

- **NEVER** use Options API — Composition API with `<script setup>` only
- **NEVER** call Axios from components — all API calls go through Pinia stores
- **NEVER** put test code in `docs/` — unit tests in `src/tests/`, E2E in `e2e/`
- **NEVER** use npm — use bun for all package operations
- **DO NOT** create TypeScript files for app code — project uses JS
- **DO NOT** add global CSS — use Tailwind utilities

## COMMANDS

```bash
bun install                    # Install dependencies
bun run dev                    # Vite dev server
bun run build                  # Production build
bun run test                   # Vitest unit/integration tests
bun run e2e                    # Playwright E2E tests
```

## NOTES

- **Performance views partially stubbed**: `GiveFeedback.vue`, `TeamGoals.vue`, `ReviewCycleCreate.vue`, `GoalDetail.vue`, `FeedbackGiven.vue` have TODO placeholders
- **Dual lockfile issue**: Both `bun.lock` and `package-lock.json` exist — use bun only
- **`permissionAccess.js`** is the central route guard — all permission checks route through here
- **Test structure mirrors view structure**: `tests/admin/payroll/` tests `views/admin/payroll/`
- **`e2e-prepare-be.sh`** must run before E2E tests — seeds backend database
- **`dist/` and `playwright-artifacts/`** are build/test outputs — should be gitignored
