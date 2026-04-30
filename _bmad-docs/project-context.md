---
project_name: 'team-sync-pro'
user_name: 'Reyndra'
date: '2026-04-30'
sections_completed: ['technology_stack', 'architecture_layering', 'domain_specific_rules', 'development_workflow', 'anti_patterns']
status: 'complete'
rule_count: 85
optimized_for_llm: true
---

# Project Context for AI Agents

_This file contains critical rules and patterns that AI agents must follow when implementing code in this project. Focus on unobvious details that agents might otherwise miss._

---

## Technology Stack & Versions

### Backend (team-sync-be)
- PHP ^8.2 — Laravel ^12.0
- Database: **MySQL** (dev/prod), SQLite :memory: (tests)
- Auth: Laravel Sanctum ^4.0 (SPA cookie-based)
- Permissions: spatie/laravel-permission ^6.21
- Search: Laravel Scout + Meilisearch
- Queue: database driver
- Cache: Redis
- Session: database driver
- Export: matwebsite/excel, barryvdh/laravel-dompdf
- Testing: Pest ^4.1
- Formatting: Laravel Pint, Prettier + @prettier/plugin-php

### Frontend (team-sync-fe)
- Vue ^3.5 — Composition API only (`<script setup>`)
- **JavaScript only — no TypeScript** (`.js` / `.vue` files)
- Build: Vite ^7
- State: Pinia ^3 (one store per domain)
- Routing: Vue Router ^4
- Styling: Tailwind CSS ^3 (no component library)
- HTTP: Axios (called from stores only, never components)
- Date/Time: Luxon
- Icons: Lucide Vue Next
- Charts: ApexCharts (globally registered as `VueApexCharts`)
- Path alias: `@/` → `src/`
- Runtime: Node ^20.19 || >=22.12, **Bun** (package manager)
- Lock file: `bun.lock` (not package-lock.json)

### API Contract
- All routes under `/api/v1/` — Sanctum-guarded
- Responses via JsonResource transformers — never raw models

### Formatting & Style
- 4-space indentation (all files — PHP, JS, Vue, no exceptions)
- Prettier (singleQuote, tabWidth: 4)
- Laravel Pint for PHP

### Testing Constraints
- **Backend DB**: SQLite `:memory:` — no MySQL-specific syntax in tests
- **Backend Queue**: overridden to `sync` in phpunit.xml — jobs execute immediately in tests
- **Frontend Unit**: Vitest — jsdom env, `globals: true` (no explicit imports needed)
- **E2E**: Playwright — sequential (1 worker, not parallel), `globalSetup` verifies backend health + seeded logins
- **E2E prep**: `bun run e2e:prepare:be` must seed backend before tests
- **CI**: 3 GitHub Actions workflows (fe-guard-smoke, payroll-ui-e2e, playwright), Playwright retries: 1 in CI / 0 locally

### Infrastructure
- Docker Compose (queue worker)
- GitHub Actions CI

---

## Critical Implementation Rules

### Architecture & Layering (Backend)
- **Strict layer order**: Controller (thin) → Service (business logic) → Repository (data access) → Interface (contract)
- NEVER call Repository from Controller directly — always go through Service
- NEVER put business logic in Controllers — extract to Services
- NEVER call Eloquent directly from Controllers — go through Repository
- DTOs in `app/DTOs/` for cross-layer data transfer — not arrays
- FormRequest classes for ALL validation — never validate in controllers
- JsonResource for ALL API responses — never return raw model instances
- Enums in `app/Enums/` (20 enums) for all fixed option sets

### Architecture & Patterns (Frontend)
- **API calls live in Pinia stores only** — components dispatch store actions, never call Axios directly
- One Pinia store per domain (21 stores matching backend domains)
- Composables in `src/composables/use{Name}.js` — reusable logic hooks
- Route guards centralized in `src/router/permissionAccess.js`
- Views split by role: `views/admin/` (HR/manager) vs `views/staff-member/` (employee self-service)
- Components scoped by role: `components/admin/`, `components/staff-member/`, `components/common/`

### Notifications & Queue
- ALL notification classes use `ShouldQueue` — queue worker MUST be running
- Queue uses database driver — without worker, `/api/v1/my-notifications` returns empty
- Notifications fail **silently** without queue worker — no error, just empty results

### Data Access Patterns
- 69 existing migrations — NEVER modify old ones, always create new
- Migrations must always be reversible (include rollback)
- Spatie permissions: role-based access via middleware applied per-route
- `EnsureProjectMembership` is the only custom middleware — guards project-scoped routes

---

### Domain-Specific Rules (Indonesian HRIS)

#### Currency & Formatting
- **IDR has NO decimal places** — amounts are integers (millions common)
- Format pattern: `'Rp '.number_format($value, 0, ',', '.')` → `Rp 10.000.000`
- No centralized helper exists — pattern is inline everywhere. Follow the same inline pattern.
- Date format: **`Y-m-d`** (API/storage), `Y-m-d H:i:s` (datetimes), `Y-m` (payroll month input)

#### Timezone
- Configurable per-company in settings (default: WIB / Asia/Jakarta)
- Timestamps stored UTC; attendance policy evaluated in configured timezone

#### Payroll System
- **PPh 21 method**: TER 2024 for monthly calculation (Jan–Nov), annualized in December for year-end reconciliation
- **BPJS rates are database-driven** (not hardcoded) — stored in `bpjs_rates` table, loaded via `BpjsRate::all()->keyBy('component')`
  - JHT: employee 2% / employer 3.7% (no cap)
  - JKK: employer 0.24% (no cap)
  - JKM: employer 0.30% (no cap)
  - JP: employee 1% / employer 2% (cap: Rp 10.042.300)
  - Kesehatan: employee 1% / employer 4% (cap: Rp 12.000.000)
- **Tax constants** in `TaxCalculationService`: `JABATAN_RATE = 0.05`, `JABATAN_MAX_ANNUAL = 6_000_000`, `JABATAN_MAX_MONTHLY = 500_000`
- +20% PPh21 surcharge if employee has no NPWP
- **DEDUCTION_WARNING_RATIO = 0.5** in PayrollRepository — triggers when deductions exceed 50%
- **Payroll status lifecycle**: `processing → pending → approved → paid`
- **GeneratePayrollJob**: queued, unique per salary_month (1hr lock), 3 retries, 10min timeout
- **Overtime/lembur**: NOT YET IMPLEMENTED — no overtime calculation exists in codebase
- **THR (Tunjangan Hari Raya)**: NOT YET IMPLEMENTED

#### Attendance
- Work hours: 9:00–17:00 (8h×5d, Mon–Fri for full_time/contract)
- **Late grace period**: 30 min (full_time/contract), 20 min (part_time)
- Half-day threshold: 4h worked (full_time), 3h (intern), 2h (part_time)
- **Remote employees**: auto-present (no clock-in needed)
- **Hybrid employees**: auto-present on WFH days (resolved via `HybridScheduleResolver`)
- Policies are DB-overridable via `AttendancePolicy` model (defaults in `AttendanceClassifier::DEFAULT_POLICIES`)
- Work location enum: `office`, `remote`, `hybrid` (note: enum uses `REMOTE` not `WFH`)

#### Leave Types (from `LeaveType` enum)
- annual_leave, sick_leave, personal_leave, emergency_leave, maternity_leave, paternity_leave, compassionate_leave
- Status flow: `pending → approved / rejected` (plain strings, not enum)

#### Multi-Tenancy
- Currently **single-tenant** (no company_id/branch_id scoping)
- Future: will become multi-tenant SaaS — design new features with tenant isolation in mind

#### Key Enums with State Semantics
- `TaskStatus`: todo → in_progress → review → done / rejected / cancelled
- `ProjectStatus`: draft → planning → active → on_hold / completed / cancelled
- `AttendanceStatus`: present, late, absent, half_day, sick_leave, annual_leave
- `JobStatus`: active, on_leave, resigned
- `WorkLocation`: office, remote, hybrid
- **Payroll status** (plain string, not enum): processing → pending → approved → paid
- **Note**: State transitions enforced procedurally in Repositories, NOT in enum classes

#### Soft Deletes (17 models)
- User, StaffMemberProfile, JobInformation, BankInformation, EmergencyContact
- Attendance, LeaveRequest, Payroll, PayrollDetail
- Project, ProjectTask, ProjectTaskComment, ProjectTaskAttachment, ProjectTeam
- Team, TeamMember, PerformanceReviewTemplate
- **Always use `->withTrashed()` when you need to include deleted records**

#### Constants (`app/Constants/CacheConstants.php`)
- `PAYROLL_BULK_INSERT_CHUNK_SIZE = 500`
- `DEFAULT_PAGINATION_SIZE = 50`
- Cache key prefixes for: employee_statistics, dashboard_statistics, project_statistics, team_statistics, attendance_statistics, payroll_statistics, payroll_analytics, analytics

#### Other Domain Notes
- **`TaskStatus::TODO`** is an Enum value — not a code TODO. Don't confuse in searches
- **TOPSIS ranking**: `TopsisService.php` — multi-criteria decision analysis for performance reviews
- **Performance module partially stubbed**: FE views `GiveFeedback.vue`, `TeamGoals.vue`, `ReviewCycleCreate.vue`, `GoalDetail.vue`, `FeedbackGiven.vue` have TODO placeholders
- **Schedule Meeting**: HR broadcasts meeting links to divisions/teams via queued notifications

---

### Development Workflow

#### Cold Start (Zero to Running)
```bash
# Backend
cd team-sync-be
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate --seed
composer dev  # Runs server + queue + logs + vite concurrently

# Frontend (separate terminal)
cd team-sync-fe
bun install
bun run dev

# Verify: http://localhost:8000/up → 200
```

#### Commands Quick Reference
- **Backend dev**: `composer dev` (DO NOT run `artisan serve` manually — `composer dev` handles everything)
- **Backend tests**: `composer test` (clears config cache, then runs Pest)
- **Backend direct Pest**: `./vendor/bin/pest` (requires manual `php artisan config:clear` first)
- **Backend format**: `./vendor/bin/pint`
- **Frontend dev**: `bun run dev`
- **Frontend tests**: `bun run test`
- **Frontend E2E**: `bun run e2e` (runs `e2e-prepare-be.sh` + Playwright)
- **Frontend E2E prep only**: `bun run e2e:prepare:be` (seeds backend — required before first E2E run)
- **Docker queue**: `docker compose up -d queue` (alternative to `composer dev` queue)
- **Model scaffold**: `php artisan make:model Name -mfsr`

#### Cache Invalidation (Laravel)
- After changing `.env`: `php artisan config:clear`
- After changing routes: `php artisan route:clear`
- After changing service providers: `php artisan clear-compiled`
- Nuclear reset: `php artisan config:clear && php artisan route:clear && php artisan cache:clear`
- Local dev does NOT cache by default — don't run `config:cache` locally

#### Testing Conventions

##### Backend (Pest)
- Run: `composer test` (preferred — clears config first)
- Test DB: SQLite :memory: (phpunit.xml) — no MySQL-specific syntax
- Queue: `QUEUE_CONNECTION=sync` in tests — jobs execute immediately, NO worker needed
- File convention: `tests/Feature/`, `tests/Unit/` — standard Laravel structure
- Seeding: `migrate:fresh --seed` is safe locally for clean state

##### Frontend Unit (Vitest)
- Run: `bun run test`
- File pattern: `src/**/*.test.{js,ts}` — files outside `src/` are INVISIBLE to runner
- Placement: Mirror view structure (e.g., `src/tests/admin/payroll/` tests `views/admin/payroll/`)
- Environment: jsdom, globals enabled (no need to import describe/it/expect)

##### E2E (Playwright)
- Prep: `bun run e2e:prepare:be` seeds backend (REQUIRED before first run)
- Run: `bun run e2e` (runs prep + Playwright)
- Execution: Sequential (1 worker, NOT parallel) — tests may share state
- globalSetup: verifies backend `/up` health + seeded login credentials
- Retries: 0 local, 1 in CI
- File location: `e2e/` directory

##### Queue Behavior Disambiguation
- **In Pest tests**: QUEUE_CONNECTION=sync — notifications fire immediately, no worker needed
- **In E2E / manual testing**: Queue worker MUST be running (`composer dev` or `docker compose up -d queue`)

### Critical Anti-Patterns (NEVER DO)

#### Backend
- NEVER put business logic in controllers — extract to Services
- NEVER call Eloquent directly from controllers — go through Repository
- NEVER modify existing migrations — create new ones
- NEVER return raw model instances from API — use Resources
- NEVER commit `.env` files or `server.log`
- NEVER run `php artisan migrate:fresh` outside local dev
- NEVER run `./vendor/bin/pest` without `config:clear` first (use `composer test` instead)
- DO NOT add loose scripts to project root (`test-request.php` is legacy debt)
- DO NOT log to `server.log` in root — use `storage/logs/`
- DO NOT run `php artisan serve` manually — use `composer dev`

#### Frontend
- NEVER use Options API — Composition API with `<script setup>` only
- NEVER call Axios from components — all API calls go through Pinia stores
- NEVER put test files outside `src/` — Vitest won't find them
- NEVER use npm — use bun for all package operations
- DO NOT create TypeScript files — project uses JS
- DO NOT add global CSS — use Tailwind utilities

#### General
- NEVER run `DROP`, `TRUNCATE`, or unfiltered `DELETE` without showing the query first
- DO NOT mix npm and bun lockfiles — use bun only

---

## Usage Guidelines

**For AI Agents:**
- Read this file before implementing any code
- Follow ALL rules exactly as documented
- When in doubt, prefer the more restrictive option
- Check the relevant section before making assumptions about domain logic

**For Humans:**
- Keep this file lean and focused on agent needs
- Update when technology stack or business rules change
- Review quarterly for outdated rules
- Remove rules that become obvious over time

---

_Last Updated: 2026-04-30_
