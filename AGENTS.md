# PROJECT KNOWLEDGE BASE

> Context engineering untuk AI agents. Dibaca otomatis oleh Pi, Claude Code, Codex.
> Last updated: 2026-05-15

## Sub-Repo Context

When working on a specific sub-repo, **also read its AGENTS.md** for detailed conventions:

- **Backend**: [`team-sync-be/AGENTS.md`](team-sync-be/AGENTS.md) — layering, models, services, commands
- **Frontend**: [`team-sync-fe/AGENTS.md`](team-sync-fe/AGENTS.md) — components, stores, routing, testing

This root file covers cross-cutting concerns: domain rules, role hierarchy, stack overview.

---

# Workflow Kerja Team Sync

1. Baca Context 📖
- ✅ @AGENTS.md (root) — domain rules, stack, architecture
- ✅ Sub-repo AGENTS.md (team-sync-be/AGENTS.md, team-sync-fe/AGENTS.md) — detailed conventions
- ✅ @docs/party-mode.md — multi-persona collaboration guide
2. Panggil Agents/Sub-agents 🤖
Gunakan specialized agents sesuai kebutuhan:
- @oracle — strategic decisions, architecture, compliance analysis
- @fixer — implementation execution
- @librarian — docs/reference lookup
- @designer — UI/UX design
- @explorer — codebase exploration
Load skills yang dibutuhkan (brainstorming, writing-plans, test-driven-development, dll)
3. Buat Plan 📋
Dari hasil agent yang bekerja, buat detailed plan di docs/plans/on_going/
4. Rundingkan dengan Party Mode 🎉
Untuk keputusan kompleks, invoke party mode:
Party mode: [complex decision/problem]
Dispatch 2-3 personas yang relevan in parallel, collect results, synthesize recommendation.
5. Execute Plan ⚙️
- Dispatch @fixer atau specialized agents untuk implementasi
- Follow architecture rules (Controller → Service → Repository)
- Write tests (TDD when applicable)
- Verify dengan semua unit testing yang ada di dalam repo dan sub repo
6. Create PR & Review 🔍
- Branch selalu dari `main`
- Commit bebas selama kerja
- **Sebelum PR: squash** semua commit jadi 1 commit dengan format:
  - `chore: add ...` (fitur baru)
  - `chore: fix ...` (bug fix)
- Push → open PR ke `main`
- Wait for CI (BE tests, FE tests, screenshots) — harus ✅
- Jika conflict dengan main, fix dulu
- **Tunggu approval reviewer**
- **Rebase & merge** ke main (bukan squash merge)
- Hapus branch setelah merge
7. Archive Plan 📦
Move completed plan dari on_going/ ke archive/, update status to COMPLETED

## Overview

Team Sync — HRIS monorepo. Laravel 12 API backend (`team-sync-be`) + Vue 3 SPA frontend (`team-sync-fe`). Manages staff, attendance, payroll, leave, projects, performance reviews, and analytics for an Indonesian workforce context (BPJS, PTKP, tax brackets).

## Structure

```
team-sync/
├── team-sync-be/          # Laravel 12 API (PHP 8.2+, Sanctum auth)
├── team-sync-fe/          # Vue 3 SPA (Vite, Pinia, Tailwind CSS)
├── docs/                  # Plans, references, testing docs (NO executable code)
├── .github/workflows/     # CI: Playwright E2E, smoke tests
└── package.json           # Root workspace (minimal — real deps in subdirs)
```

## Where to Look

| Task                 | Location                                                     | Notes                                            |
| -------------------- | ------------------------------------------------------------ | ------------------------------------------------ |
| API routes           | `team-sync-be/routes/api.php`                                | All under `/api/v1`, Sanctum-guarded             |
| Business logic       | `team-sync-be/app/Services/`                                 | Domain-grouped (Payroll/, Attendance/, Performance/, Analytics/) |
| Data access          | `team-sync-be/app/Repositories/`                             | Interface-bound via `app/Interfaces/`            |
| Models (53)          | `team-sync-be/app/Models/`                                   | Eloquent, heavy relations                        |
| Notifications (32)   | `team-sync-be/app/Notifications/`                            | Queued via database driver — **queue worker required** |
| Frontend views       | `team-sync-fe/src/views/`                                    | Split: `admin/` vs `staff-member/`               |
| State management     | `team-sync-fe/src/stores/`                                   | 25 Pinia stores, one per domain                  |
| Routing              | `team-sync-fe/src/router/`                                   | Split by domain module (10 files)                |
| CI workflows         | `.github/workflows/`                                         | `fe-guard-smoke.yml`, `payroll-ui-e2e.yml`, `playwright.yml` |
| E2E prep script      | `team-sync-fe/scripts/e2e-prepare-be.sh`                     | Seeds/resets BE for E2E runs                     |

---

## Technology Stack

### Backend (team-sync-be)
- PHP ^8.2 — Laravel ^12.0
- Database: **MySQL** (dev/prod), SQLite :memory: (tests)
- Auth: Laravel Sanctum ^4.0 (SPA cookie-based)
- Permissions: spatie/laravel-permission ^6.21
- Search: Laravel Scout + Meilisearch
- Queue: database driver
- Cache: Redis
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
- 4-space indentation everywhere (PHP, JS, Vue)

---

## Architecture Rules

### Backend Layering
- **Strict layer order**: Controller (thin) → Service (business logic) → Repository (data access) → Interface (contract)
- NEVER call Repository from Controller directly — always go through Service
- NEVER put business logic in Controllers — extract to Services
- NEVER call Eloquent directly from Controllers — go through Repository
- DTOs in `app/DTOs/` for cross-layer data transfer — not arrays
- FormRequest classes for ALL validation — never validate in controllers
- JsonResource for ALL API responses — never return raw model instances
- Enums in `app/Enums/` (20 enums) for all fixed option sets

### Frontend Patterns
- **API calls live in Pinia stores only** — components dispatch store actions, never call Axios directly
- One Pinia store per domain (21 stores matching backend domains)
- Composables in `src/composables/use{Name}.js` — reusable logic hooks
- Route guards centralized in `src/router/permissionAccess.js`
- Views split by role: `views/admin/` (HR/manager) vs `views/staff-member/` (employee self-service)
- Components scoped by role: `components/admin/`, `components/staff-member/`, `components/common/`

### Notifications & Queue
- ALL notification classes use `ShouldQueue` — queue worker MUST be running
- Queue uses database driver — without worker, `/api/v1/my-notifications` returns empty
- Meeting broadcasts/reminders use the dedicated `meetings` queue
- Meeting reminders require the Laravel scheduler

### Data Access
- 88 existing migrations — NEVER modify old ones, always create new
- Migrations must always be reversible (include rollback)
- Spatie permissions: role-based access via middleware applied per-route
- `EnsureProjectMembership` is the only custom middleware — guards project-scoped routes

---

## Domain-Specific Rules (Indonesian HRIS)

### Currency & Formatting
- **IDR has NO decimal places** — amounts are integers
- Format: `'Rp '.number_format($value, 0, ',', '.')` → `Rp 10.000.000`
- Date format: `Y-m-d` (API/storage), `Y-m-d H:i:s` (datetimes), `Y-m` (payroll month input)

### Timezone
- Configurable per-company (default: WIB / Asia/Jakarta)
- Timestamps stored UTC; attendance policy evaluated in configured timezone

### Payroll System
- **PPh 21 method**: TER 2024 for monthly (Jan–Nov), annualized in December
- **BPJS rates are database-driven** — stored in `bpjs_rates` table
  - JHT: employee 2% / employer 3.7% (no cap)
  - JKK: employer 0.24% / JKM: employer 0.30% (no cap)
  - JP: employee 1% / employer 2% (cap: Rp 10.042.300)
  - Kesehatan: employee 1% / employer 4% (cap: Rp 12.000.000)
- Tax constants in `TaxCalculationService`: `JABATAN_RATE = 0.05`, `JABATAN_MAX_MONTHLY = 500_000`
- +20% PPh21 surcharge if no NPWP
- **DEDUCTION_WARNING_RATIO = 0.5** — triggers when deductions exceed 50%
- **Payroll status lifecycle**: `processing → pending → approved → paid`
- **GeneratePayrollJob**: queued, unique per salary_month (1hr lock), 3 retries, 10min timeout

### Attendance
- Work hours: 9:00–17:00 (8h×5d, Mon–Fri for full_time/contract)
- Late grace: 30 min (full_time/contract), 20 min (part_time)
- Half-day threshold: 4h (full_time), 3h (intern), 2h (part_time)
- Remote employees: auto-present (no clock-in needed)
- Hybrid employees: auto-present on WFH days (via `HybridScheduleResolver`)
- Work location enum: `office`, `remote`, `hybrid`

### Leave Types (from `LeaveType` enum)
- annual_leave, sick_leave, personal_leave, emergency_leave, maternity_leave, paternity_leave, compassionate_leave
- Status flow: `pending → approved / rejected`

### Role Hierarchy (Strict Least-Privilege)
- **Staff**: self-service only (own attendance, leave, payroll, goals)
- **Manager**: team-scoped (team pulse, project management, performance reviews)
- **HR**: workforce/attendance/leave/performance (company-wide, NO payroll ops)
- **Finance**: payroll/THR owner (generate, approve, process, settings)
- **Superadmin**: all permissions

### Key Enums with State Semantics
- `TaskStatus`: todo → in_progress → review → done / rejected / cancelled
- `ProjectStatus`: draft → planning → active → on_hold / completed / cancelled
- `AttendanceStatus`: present, late, absent, half_day, sick_leave, annual_leave
- `JobStatus`: active, on_leave, resigned
- Payroll status (plain string): processing → pending → approved → paid

### Soft Deletes (17 models)
- User, StaffMemberProfile, JobInformation, BankInformation, EmergencyContact
- Attendance, LeaveRequest, Payroll, PayrollDetail
- Project, ProjectTask, ProjectTaskComment, ProjectTaskAttachment, ProjectTeam
- Team, TeamMember, PerformanceReviewTemplate

---

## Development Workflow

### Commands

```bash
# Backend
cd team-sync-be
composer dev                         # Server + queue + scheduler + logs (PREFERRED)
composer test                        # Clears config, runs Pest
./vendor/bin/pint                    # PHP formatting
php artisan migrate                  # Run migrations

# Frontend
cd team-sync-fe
bun install                          # Install deps
bun run dev                          # Vite dev server
bun run test                         # Vitest unit tests
bun run e2e                          # Playwright E2E (runs prep + tests)

# Docker (alternative queue/scheduler)
cd team-sync-be
docker compose up -d queue scheduler
```

### Testing

| Suite | Command | Notes |
|-------|---------|-------|
| Backend (Pest) | `composer test` | SQLite :memory:, queue=sync |
| Frontend (Vitest) | `bun run test` | jsdom, globals=true |
| E2E (Playwright) | `bun run e2e` | Sequential (1 worker), needs backend seeded |

### Cache Invalidation
- After `.env` change: `php artisan config:clear`
- After route change: `php artisan route:clear`
- Nuclear: `php artisan config:clear && php artisan route:clear && php artisan cache:clear`

---

## Anti-Patterns (NEVER DO)

### Backend
- NEVER put business logic in controllers
- NEVER call Eloquent directly from controllers
- NEVER modify existing migrations — create new ones
- NEVER return raw model instances from API — use Resources
- NEVER commit `.env` files or `server.log`
- NEVER run `./vendor/bin/pest` without `config:clear` first (use `composer test`)
- DO NOT add loose scripts to project root

### Frontend
- NEVER use Options API — Composition API with `<script setup>` only
- NEVER call Axios from components — all API calls through Pinia stores
- NEVER put test files outside `src/` — Vitest won't find them
- NEVER use npm — use bun for all package operations
- DO NOT create TypeScript files — project uses JS only
- DO NOT add global CSS — use Tailwind utilities

### General
- NEVER run `DROP`, `TRUNCATE`, or unfiltered `DELETE` without showing the query first
- DO NOT mix npm and bun lockfiles
- `TaskStatus::TODO` is an enum value, not a code TODO — don't confuse in searches

---

## Notes

- **Multi-tenancy**: Currently single-tenant. Future: multi-tenant SaaS — design with tenant isolation in mind.
- **TOPSIS ranking**: `TopsisService.php` — multi-criteria decision analysis for performance reviews
- **Performance module partially stubbed**: FE views GiveFeedback, TeamGoals, ReviewCycleCreate, GoalDetail have TODO placeholders
- **ApexCharts**: Registered globally as `VueApexCharts`
- **Schedule Meeting**: HR broadcasts meeting links to divisions/teams via queued notifications
- **Constants** (`app/Constants/CacheConstants.php`): `PAYROLL_BULK_INSERT_CHUNK_SIZE = 500`, `DEFAULT_PAGINATION_SIZE = 50`
