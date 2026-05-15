# FRONTEND KNOWLEDGE BASE (team-sync-fe)

## OVERVIEW

Vue 3 SPA. Vite 7. Composition API with `<script setup>`. Pinia state management. Tailwind CSS. Bun runtime. Two role-based view trees: admin (HR/manager/finance) and staff-member (employee self-service).

## STRUCTURE

```
src/
├── assets/css/           # Global CSS (Tailwind base, input.css)
├── components/
│   ├── admin/            # Admin-only components
│   │   ├── analytics/    # Analytics chart components (Enhanced variants)
│   │   ├── payroll/      # PayrollPagination, etc.
│   │   ├── project/      # CardList, project detail components
│   │   ├── staff-member/ # Staff member create steps
│   │   └── team/         # Pagination
│   ├── common/           # Shared across roles
│   │   ├── Alert.vue
│   │   ├── AnimatedValue.vue
│   │   ├── ConfirmationModal.vue
│   │   ├── EmptyState.vue
│   │   ├── ErrorBoundary.vue
│   │   ├── MainCard.vue
│   │   ├── ModalWrapper.vue
│   │   ├── SearchFilter.vue
│   │   ├── StatsCard.vue
│   │   └── StatusBadge.vue
│   └── staff-member/     # Staff-member-only components
├── composables/          # 5 reusable logic hooks
│   ├── useAnimatedNumber  # Number animation
│   ├── useConfirmAction   # Confirmation dialogs
│   ├── useDarkMode        # Dark mode toggle
│   ├── useSearchFilter    # List filtering + pagination
│   └── useToast           # Toast notifications
├── config/               # App configuration
├── helpers/              # Utility functions (permissionHelper.js, errorHelper.js)
├── layouts/              # Page layout wrappers (Admin, Auth, StaffMemberCreateLayout)
├── plugins/              # Vue plugin registrations (axios, ApexCharts)
├── router/               # Route definitions (10 files)
│   ├── index.js          # Main router setup
│   ├── permissionAccess.js  # Central permission guard logic
│   ├── analytics.js      # Analytics routes
│   ├── attendance.js     # Attendance routes
│   ├── meeting.js        # Meeting routes
│   ├── payroll.js        # Payroll routes
│   ├── performance.js    # Performance routes
│   ├── project.js        # Project routes
│   ├── staffMember.js    # Staff member routes
│   └── team.js           # Team routes
├── stores/               # 25 Pinia stores (one per domain)
├── tests/                # Vitest component/integration tests
│   ├── admin/            # Admin view tests (smoke + unit)
│   ├── auth/             # Auth flow smoke tests
│   ├── benchmark/        # ProjectCapability.benchmark.test.js
│   ├── components/       # Shared component tests
│   ├── composables/      # Composable unit tests
│   ├── router/           # Route guard tests
│   ├── staff-member/     # Staff view tests
│   ├── stores/           # Store tests
│   ├── utils/            # Utility function tests
│   └── views/            # View-level tests
├── utils/                # Low-level utilities (dateUtils, formatUtils, badgeUtils, attendanceUtils)
└── views/
    ├── admin/            # HR/manager/finance views
    │   ├── analytics/    # AnalyticsDashboard (tabbed, permission-gated)
    │   ├── attendance/   # AttendanceList, Periods, Settings, PolicyMismatches, HybridSchedules, etc.
    │   ├── meeting/      # Meeting management
    │   ├── payroll/      # PayrollDashboard, Create, Detail, Readiness, Settings, THR, ApprovalMatrix
    │   ├── performance/  # ReviewCycles, Goals, Feedback, TOPSIS, Templates, Calibration
    │   ├── project/      # Project & task management
    │   ├── staff-member/ # Staff directory (HR-only)
    │   └── team/         # Team management
    ├── auth/             # Login, password reset
    ├── staff-member/     # Employee self-service views
    │   ├── MyAttendance.vue
    │   ├── MyOvertime.vue
    │   ├── MyPayslips.vue
    │   ├── PayslipDetail.vue
    │   ├── HybridSchedules.vue
    │   ├── StaffMemberProfile.vue
    │   ├── StaffMemberProfileEdit.vue
    │   └── StaffMemberTeam.vue
    └── NotFound.vue      # 404 page

e2e/                      # 15 active Playwright E2E spec files (+ 5 debug)
├── helpers/              # E2E test utilities (auth, api, evidence)
scripts/
└── e2e-prepare-be.sh     # Backend seed/reset for E2E
```

## STORES (25 Pinia stores)

```
analytics, attendance, attendanceCorrection, attendancePeriod, attendancePolicy,
auth, dashboard, holidayCalendar, hybridSchedule, leaveEntitlement, leaveRequest,
meeting, notifications, option, overtime, payroll, performanceFeedback,
performanceGoal, performanceReview, project, setup, staffMember, task, team, thr
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
| Permission helper | `src/helpers/permissionHelper.js` | `can()`, `canOneOf()` |
| API calls | Inside Pinia stores (Axios) | Never call API from components directly |
| Error handling | `src/helpers/errorHelper.js` | `handleError()` extracts user-friendly messages |
| Dashboard | `src/views/admin/Dashboard.vue` | Role-branched rendering |
| Analytics | `src/views/admin/analytics/AnalyticsDashboard.vue` | Tabs gated by permissions |

## DESIGN SYSTEM

### Tailwind Config (`tailwind.config.js`)
- **Font**: `Plus Jakarta Sans` (NOT Inter)
- **Colors**: `primary` (50-900 blue scale), `brand-dark` (#0C1C3C), `brand-light` (#6B7280), `brand-border` (#DCDEDD)
- **Semantic**: `success` (50-700 green), `danger` (50-700 red), `warning` (50-700 amber)
- **Border radius**: `card` = 20px (maps to `rounded-2xl`)
- **Animation**: `fadeIn` keyframe for auth pages

### Custom CSS (`src/assets/css/input.css`)
- `.btn-primary`, `.btn-secondary`, `.btn-details` — button styles
- `.blue-gradient`, `.blue-btn-shadow` — accent button variants
- `.main-card` — dark gradient stat card (used in MainCard wrapper mode)
- `.nav-link`, `.nav-link-active` — sidebar navigation states
- Badge classes: expert (green), intermediate (blue), beginner (amber), active (green), growing (amber), creative (purple), high-performing (green), 247 (red)
- Tooltip CSS for collapsed sidebar (pseudo-elements `::after`/`::before`)
- View Transitions API CSS (progressive enhancement)
- Focus-visible styles (accessibility)

## CONVENTIONS

- **`<script setup>`** — always. No Options API
- **JS, not TS** — stores and components are `.js`/`.vue`, not TypeScript
- **`@/` alias** → `src/` (configured in `vite.config.js` + `jsconfig.json`)
- **One Pinia store per domain** — 25 stores matching backend domains
- **API calls live in stores** — components dispatch store actions, never call Axios directly
- **Tailwind utility classes** — no custom CSS unless truly unavoidable
- **4-space indentation** everywhere
- **Lucide Vue Next** for all icons
- **ApexCharts** — globally registered as `VueApexCharts` for charts
- **Luxon** for date/time handling (not moment, not dayjs)
- **Vitest config**: `vitest.config.js` — jsdom environment, global test APIs
- **Numeric displays**: use `tabular-nums` class on financial/data values

## ROLE-BASED RENDERING

Dashboard and analytics use permission-based branching:

| Role | Dashboard Shows | Analytics Tabs |
|------|----------------|----------------|
| Staff | EmployeeStatistics only | — (no access) |
| Manager | TeamPulse + EmployeeStatistics + UpcomingMeetings | Performance, Project |
| HR | Full company-wide stats (gated by `dashboard-hr-view`) | Workforce, Attendance, Leave, Performance, Project |
| Finance | PayrollAnalyticsEnhanced | Payroll |
| Superadmin | Full company-wide stats | All tabs |

## ANTI-PATTERNS

- **NEVER** use Options API — Composition API with `<script setup>` only
- **NEVER** call Axios from components — all API calls go through Pinia stores
- **NEVER** put test code in `docs/` — unit tests in `src/tests/`, E2E in `e2e/`
- **NEVER** use npm — use bun for all package operations
- **DO NOT** create TypeScript files for app code — project uses JS
- **DO NOT** add global CSS — use Tailwind utilities
- **DO NOT** mix npm and bun lockfiles — use bun only
- **DO NOT** use `h-screen` — use `min-h-[100dvh]` for full-height layouts (iOS Safari bug)
- **DO NOT** use `hover:border-2` — causes 1px layout shift; use `hover:ring-2` instead
- **DO NOT** use `dark:` classes — partial dark mode not supported; remove entirely

## COMMANDS

```bash
bun install                    # Install dependencies
bun run dev                    # Vite dev server
bun run build                  # Production build
bun run test                   # Vitest unit/integration tests (981 tests)
bun run e2e                    # Playwright E2E tests (109 tests)
bun run e2e:prepare:be         # Seed backend for E2E (without running tests)
bun run test:a11y              # Accessibility audit (runs after build in CI)
```

## NOTES

- **Performance views partially stubbed**: `GiveFeedback.vue`, `TeamGoals.vue`, `ReviewCycleCreate.vue`, `GoalDetail.vue`, `FeedbackGiven.vue` have TODO placeholders
- **`permissionAccess.js`** is the central route guard — checks `meta.requiredPermission` or `meta.requiredAnyPermissions`
- **`permissionHelper.js`** provides `can(permission)` and `canOneOf(permissions)` using `includes()` on string arrays from auth store
- **Test structure mirrors view structure**: `tests/admin/payroll/` tests `views/admin/payroll/`
- **`e2e-prepare-be.sh`** must run before E2E tests — seeds backend database
- **E2E test accounts**: `agung@teamsync.com` (staff), `yudhis@teamsync.com` (manager), `tasyia@teamsync.com` (hr), `dwimeta@teamsync.com` (finance) — password: `teamsync`
- **E2E runs sequentially** (1 worker) — tests may share state
- **Dashboard.vue** uses role-branched rendering — different components per role, not just show/hide
- **AnalyticsDashboard.vue** auto-selects default tab based on user's first available permission
- **Error Boundary**: `ErrorBoundary.vue` wraps RouterView in all 3 layouts (Admin, Auth, StaffMemberCreateLayout)
- **ConfirmationModal**: All destructive actions use this component — zero `window.confirm` calls
- **EmptyState**: Consistent empty state component — replace inline empty divs
- **OrbStack Meilisearch fix**: Docker containers need `NO_PROXY` env var to avoid proxy routing
