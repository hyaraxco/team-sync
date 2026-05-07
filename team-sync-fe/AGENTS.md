# FRONTEND KNOWLEDGE BASE (team-sync-fe)

## OVERVIEW

Vue 3 SPA. Vite 7. Composition API with `<script setup>`. Pinia state management. Tailwind CSS. Bun runtime. Two role-based view trees: admin (HR/manager/finance) and staff-member (employee self-service).

## STRUCTURE

```
src/
├── assets/css/           # Global CSS (Tailwind base)
├── components/
│   ├── admin/            # Admin-only components
│   │   └── analytics/    # Analytics chart components (Enhanced variants)
│   ├── common/           # Shared across roles
│   └── staff-member/     # Staff-member-only components
├── composables/          # 5 reusable logic hooks
│   ├── useAnimatedNumber  # Number animation
│   ├── useConfirmAction   # Confirmation dialogs
│   ├── useDarkMode        # Dark mode toggle
│   ├── useSearchFilter    # List filtering
│   └── useToast           # Toast notifications
├── config/               # App configuration
├── helpers/              # Utility functions (permissionHelper.js)
├── layouts/              # Page layout wrappers
├── plugins/              # Vue plugin registrations
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
├── tests/                # Component/integration tests
│   ├── admin/            # Admin view tests
│   ├── router/           # Route guard tests
│   ├── staff-member/     # Staff view tests
│   └── stores/           # Store tests
├── utils/                # Low-level utilities
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
e2e/                      # 19 Playwright E2E test files (95 tests)
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
| Dashboard | `src/views/admin/Dashboard.vue` | Role-branched rendering |
| Analytics | `src/views/admin/analytics/AnalyticsDashboard.vue` | Tabs gated by permissions |

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

## COMMANDS

```bash
bun install                    # Install dependencies
bun run dev                    # Vite dev server
bun run build                  # Production build
bun run test                   # Vitest unit/integration tests (618 tests)
bun run e2e                    # Playwright E2E tests (95 tests)
bun run e2e:prepare:be         # Seed backend for E2E (without running tests)
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
