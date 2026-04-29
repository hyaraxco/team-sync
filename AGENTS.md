# PROJECT KNOWLEDGE BASE

**Generated:** 2026-04-27T19:19:38Z
**Commit:** 10bd03f
**Branch:** feat/notification-wiring-deeplinks

## OVERVIEW

Team Sync — HRIS monorepo. Laravel 12 API backend (`team-sync-be`) + Vue 3 SPA frontend (`team-sync-fe`). Manages staff, attendance, payroll, leave, projects, performance reviews, and analytics for an Indonesian workforce context (BPJS, PTKP, tax brackets).

## STRUCTURE

```
team-sync/
├── team-sync-be/          # Laravel 12 API (PHP 8.2+, Sanctum auth)
├── team-sync-fe/          # Vue 3 SPA (Vite, Pinia, Tailwind CSS)
├── docs/                  # Plans, references, testing docs (NO executable code)
├── .agent/                # Gemini agent config (AGENTS.md for Antigravity)
├── .github/workflows/     # CI: Playwright E2E, smoke tests
└── package.json           # Root workspace (minimal — real deps in subdirs)
```

## WHERE TO LOOK

| Task | Location | Notes |
|------|----------|-------|
| API routes | `team-sync-be/routes/api.php` | All under `/api/v1`, Sanctum-guarded |
| Business logic | `team-sync-be/app/Services/` | Domain-grouped (Payroll/, Attendance/, Performance/, Analytics/) |
| Data access | `team-sync-be/app/Repositories/` | Interface-bound via `app/Interfaces/` |
| Models (45) | `team-sync-be/app/Models/` | Eloquent, heavy relations |
| Notifications (25+) | `team-sync-be/app/Notifications/` | Queued via database driver — **queue worker required** |
| Add meeting | `team-sync-be/app/Http/Controllers/MeetingController.php` + `team-sync-fe/src/views/admin/meeting/` | |
| Frontend views | `team-sync-fe/src/views/` | Split: `admin/` vs `staff-member/` |
| State management | `team-sync-fe/src/stores/` | 21 Pinia stores, one per domain |
| Routing | `team-sync-fe/src/router/` | Split by domain module (9 files) |
| CI workflows | `.github/workflows/` | `fe-guard-smoke.yml`, `payroll-ui-e2e.yml`, `playwright.yml` |
| E2E prep script | `team-sync-fe/scripts/e2e-prepare-be.sh` | Seeds/resets BE for E2E runs |

## CONVENTIONS

- **4-space indentation** everywhere (PHP, JS, Vue) — deviates from 2-space JS norm
- **Repository pattern**: Controller → Service → Repository → Interface. Never skip layers
- **DTOs** in `app/DTOs/` for cross-layer data transfer
- **Enums** in `app/Enums/` (20 enums) — use for all fixed option sets
- **Composition API only** — `<script setup>` in all Vue components
- **Pinia stores** — one store per domain, JS (not TS)
- **Tailwind CSS** — utility-first, no custom CSS unless unavoidable
- **Bun** for FE package management and scripts (`bun run dev`, `bun run test`)
- **Pest** for BE tests, **Vitest** for FE unit tests, **Playwright** for E2E
- **Prettier + PHP plugin** for formatting; **Laravel Pint** for PHP style
- **Lucide Vue Next** for icons
- **`@/` path alias** → `src/` in frontend

## ANTI-PATTERNS (THIS PROJECT)

- **NEVER** put executable test code in `docs/` — only `.md` reports/runbooks
- **NEVER** run `DROP`, `TRUNCATE`, or unfiltered `DELETE` without showing the query first
- **NEVER** skip the queue worker — notifications use `QUEUE_CONNECTION=database`; without it, `/api/v1/my-notifications` returns empty
- **NEVER** commit `.env` files or `server.log`
- **DO NOT** mix npm and bun lockfiles in frontend — use bun only
- **DO NOT** add loose PHP scripts to BE root (existing `test-request.php` is legacy debt)
- `TaskStatus::TODO` is an enum value, not a code TODO — don't confuse in searches

## COMMANDS

```bash
# Backend
cd team-sync-be
php artisan serve                    # Dev server
php artisan queue:work               # Queue worker (REQUIRED for notifications)
php artisan test                     # Run Pest tests
./vendor/bin/pest                    # Direct Pest
php artisan migrate                  # Run migrations

# Frontend
cd team-sync-fe
bun install                          # Install deps
bun run dev                          # Vite dev server
bun run test                         # Vitest unit tests
bun run e2e                          # Playwright E2E

# Docker (backend)
cd team-sync-be
docker compose up -d queue           # Queue worker via Docker
```

## NOTES

- **Indonesian payroll context**: BPJS rates, PTKP tax status, tax brackets — domain-specific logic in `Services/Payroll/`
- **Performance module partially stubbed**: Several FE views (GiveFeedback, TeamGoals, ReviewCycleCreate, GoalDetail) have TODO placeholders
- **TOPSIS ranking**: `TopsisService.php` implements multi-criteria decision analysis for performance reviews
- **Dual admin/staff views**: Frontend splits by role — `views/admin/` (HR/manager) vs `views/staff-member/` (employee self-service)
- **Payroll deduction warning**: `DEDUCTION_WARNING_RATIO = 0.5` in PayrollRepository — triggers when deductions exceed 50% of total
- **69 database migrations** — large schema, always use migrations for changes
- **Spatie permissions**: Role-based access via `spatie/laravel-permission`, middleware applied per-route
- **ApexCharts**: Registered globally as `VueApexCharts` component for dashboard/analytics charts
- **Schedule Meeting feature**: HR can broadcast meeting links to divisions/teams via notifications
