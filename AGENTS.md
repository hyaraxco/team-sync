# PROJECT KNOWLEDGE BASE

> Context engineering untuk AI agents. Dibaca otomatis oleh Pi, Claude Code, Codex.
> Last updated: 2026-11-05

## Sub-Repo Context

When working on a specific sub-repo, **also read its AGENTS.md** for detailed conventions:

- **Backend**: [`team-sync-be/AGENTS.md`](team-sync-be/AGENTS.md) — layering, models, services, commands
- **Frontend**: [`team-sync-fe/AGENTS.md`](team-sync-fe/AGENTS.md) — components, stores, routing, testing

This root file covers cross-cutting concerns: domain rules, role hierarchy, stack overview.

---

## Workflow Kerja Team Sync

1. **Baca Context** — AGENTS.md (root + sub-repo), docs/party-mode.md
2. **Baca Skills (wajib)** — Pilih dan baca instruksi skills yang relevan dengan tiket/task sebelum membuat plan implementasi atau mulai eksekusi. Jangan hanya menyebut nama skill.
3. **Panggil Agents sesuai kebutuhan** — @oracle (strategy), @fixer (implementation), @librarian (docs), @designer (UI/UX), @explorer (codebase).
4. **Buat Plan** — Tulis di `docs/plans/on_going/`
5. **Party Mode** (opsional) — Untuk keputusan kompleks: `Party mode: [problem]`. Dispatch 2-3 persona parallel. Lihat `docs/party-mode.md`.
6. **Execute Plan** — Follow architecture rules, verifikasi suite yang terdampak dan jalankan smoke pada jalur yang berubah.
7. **Integrasi dan Penutupan Tiket** — Ikuti gate CI/CD di bawah; implementasi selesai belum berarti tiket boleh langsung ditutup.
8. **Archive Plan** — Jika ada plan, pindah ke `docs/plans/archive/` dan beri status COMPLETED setelah acceptance criteria terpenuhi; catat batas verifikasi yang masih ada.

### Gate Skills Sebelum Memulai Tiket/Task

- Baca scope, acceptance criteria, dan bukti pekerjaan yang sudah ada. Jangan mengulang implementasi atau membuka kembali pekerjaan yang sudah selesai tanpa alasan dari tiket.
- Untuk coding, baca `ponytail` dan `antislop`. Ponytail menjadi prinsip kerja default: YAGNI, reuse pola/solusi yang ada, dan pilih perubahan paling sederhana yang menyelesaikan seluruh scope.
- Tambahkan skills sesuai pekerjaan: `antislop-copywriting` untuk prose/dokumentasi; `antislop-ui`, `antislop-human`, dan `antislop-layoutmobile` sesuai kebutuhan UI; `ponytail-review` untuk review; `security-audit` untuk pemeriksaan keamanan; `reverse-engineer-anything` untuk analisis artifact/runtime.
- Sampaikan singkat skills yang dipakai dan kaitannya dengan task sebelum eksekusi. Jika skill tidak tersedia, nyatakan keterbatasannya; jangan mengaku sudah membacanya atau menjalankannya.
- Tidak perlu menanyakan level Ponytail pada setiap job. Tanya user hanya jika ada keputusan scope, tradeoff, atau risiko yang memang membutuhkan keputusan user.
- Ponytail bukan alasan untuk mengurangi kebutuhan yang diminta, validasi, keamanan, aksesibilitas, penanganan error, atau verifikasi.

### Gate Penutupan Tiket dan CI/CD

1. **Main dan branch baru:** `main` adalah baseline integrasi yang dijaga stabil, bukan tempat mengerjakan tiket atau checkpoint commit. Fetch `origin`, perbarui baseline dengan fast-forward bila aman, lalu buat branch baru untuk setiap tiket. Gunakan nama deskriptif seperti `fix/<ticket-id>-leave-dates`, `feat/<ticket-id>-team-goals`, `chore/<ticket-id>-dependencies`, atau `docs/ticket-workflow` bila tidak ada ID tiket. Jangan menimpa perubahan lokal user atau memakai kembali branch yang sudah merged. Jangan push langsung atau force-push ke `main`.
2. **Checkpoint selama pengerjaan:** commit setiap unit kerja kecil yang sudah utuh dan diverifikasi secara proporsional; jangan menunggu seluruh tiket selesai sampai perubahan menumpuk. Gunakan pesan yang menjelaskan hasil kerja. Ini bukan kewajiban commit setiap baris/tool call. Aturan commit aman berikut berlaku pada setiap checkpoint, bukan hanya commit final.
3. **Verifikasi hasil:** cocokkan implementasi dengan acceptance criteria, periksa caller/kontrak yang terdampak, lakukan self-review, dan jalankan tes serta smoke yang relevan. Update dokumentasi/changelog sesuai perubahan. Untuk docs-only, gunakan pemeriksaan dokumentasi; jangan mengklaim suite aplikasi dijalankan.
4. **Commit aman dan squash:** stage hanya file dalam scope dan periksa isi staging, termasuk secret pada file yang sudah tracked. Pertahankan `.env`, kredensial, state Beads, dan konfigurasi mesin lokal di luar commit. Verifikasi author/committer sesuai akun GitHub user yang terautentikasi, gunakan noreply bila diperlukan, dan jangan mengubah identitas Git global. Setelah scope tiket selesai dan verifikasi lokal lulus, squash checkpoint menjadi satu commit bermakna sesuai perubahan, misalnya `fix: validate leave dates` atau `chore: update dependencies`.
5. **Segera buka PR:** push hasil squash dan buka PR untuk review bersama user, dengan ID tiket bila tersedia, scope, bukti verifikasi, dan batasnya. Tentukan checks wajib dari scope perubahan dan tunggu hasilnya pada **SHA head terbaru**, bukan hasil commit lama. Backend memerlukan Pest serta frozen Bun install/build aset; frontend memerlukan Vitest, build dan accessibility sesuai workflow aktif. Periksa juga security checks yang diaktifkan. Gunakan aturan N/A di akhir gate ini untuk checks yang memang di luar scope.
6. **Review bersama, rebase, lalu merge:** periksa bug/regresi, keamanan dan konflik bersama user. Jika review memerlukan perbaikan, buat checkpoint baru dan rapikan/squash kembali sebelum merge. Rebase terhadap `main` terbaru dan selesaikan konflik tanpa membuang perubahan pihak lain. Merge hanya setelah checks yang diwajibkan untuk scope tersebut lulus pada SHA terbaru dan user menyetujui isi final. Perubahan isi setelah approval, termasuk hasil resolusi konflik, wajib direview ulang; squash/rebase yang hanya mengubah history tanpa perubahan isi tidak memerlukan review ulang seluruh diff, tetapi checks tetap harus cocok dengan SHA terbaru. Jika rewrite branch diizinkan, gunakan `--force-with-lease`, bukan `--force`. Jangan menonaktifkan proteksi atau menelan kegagalan agar terlihat hijau.
7. **Sinkronisasi setelah merge:** verifikasi status merged dan SHA/author di GitHub, fast-forward `main` lokal ke `origin/main`, lalu hapus branch kerja lokal/remote yang sudah merged. Pastikan perubahan lokal yang dikecualikan tetap utuh. Periksa hasil checks pasca-merge; laporkan kegagalan, jangan menyebut seluruh CI/CD sukses hanya karena PR sudah merged.
8. **Tutup tiket terkait:** tutup Beads hanya setelah acceptance criteria terpenuhi dan hasil merge terverifikasi. Gunakan ID eksplisit, misalnya `bd close <id> --reason "PR #<nomor>, merge <sha>, verifikasi <hasil/batas>"`. Jangan menutup tiket lain hanya karena menyebut PR yang sama, dan jangan memasukkan secret ke bukti penutupan.
9. **Release/deploy terpisah:** selesai satu tiket tidak mewajibkan release manual atau deploy production. Repo saat ini memiliki `auto-tag.yml` yang dapat membuat tag/release setelah push ke `main`; periksa hasilnya dan jangan menganggap automasi itu dinonaktifkan oleh aturan ini. Jangan publish draft, menghapus/memaksa tag, atau melakukan deploy manual tanpa otorisasi yang sesuai. Jika release bukan acceptance criteria, pisahkan status tiket implementasi dari kegagalan release dan catat tindak lanjutnya.

Checks yang diwajibkan dan relevan dengan scope harus lulus; `queued`, `pending`, timeout, konflik yang belum selesai, atau checks wajib yang tidak berjalan menghalangi merge. Checks yang benar-benar tidak relevan dicatat N/A dengan alasan dan verifikasi alternatif yang sesuai, bukan diklaim lulus. Jangan menyebut check N/A hanya karena filter workflow gagal memicu tes yang sebenarnya diperlukan. Persetujuan satu PR tidak berlaku otomatis untuk PR lain. Checks mengurangi risiko, bukan menjamin nol bug. Aturan ini tidak mengaktifkan branch protection/ruleset GitHub; penegakan server-side harus dikonfigurasi terpisah.

---

## Overview

Team Sync — HRIS monorepo. Laravel 12 API backend (`team-sync-be`) + Vue 3 SPA frontend (`team-sync-fe`). Manages staff, attendance, payroll, leave, projects, performance reviews, and analytics for an Indonesian workforce context (BPJS, PTKP, tax brackets).

## Structure

```
team-sync/
├── team-sync-be/          # Laravel 12 API (PHP 8.2+, Sanctum auth)
├── team-sync-fe/          # Vue 3 SPA (Vite, Pinia, Tailwind CSS)
├── docs/                  # Plans, references, testing docs (NO executable code)
├── .github/workflows/     # CI: be-tests, fe-tests, auto-tag
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
| CI workflows         | `.github/workflows/`                                         | `be-tests.yml`, `fe-tests.yml`, `auto-tag.yml` |
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
- One Pinia store per domain (25 stores matching backend domains)
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

| Suite | Command | Count | Notes |
|-------|---------|-------|-------|
| Backend (Pest) | `composer test` | 1478 tests | SQLite :memory:, queue=sync |
| Frontend (Vitest) | `bun run test` | 981 tests | jsdom, globals=true |
| E2E (Playwright) | `bun run e2e` | 109 tests | Sequential (1 worker), needs backend seeded |
| Accessibility | `bun run test:a11y` | — | Runs after build in CI |

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
- **Performance module fully wired**: All FE views (GiveFeedback, TeamGoals, ReviewCycleCreate, GoalDetail, FeedbackGiven, MyGoals, PendingCalibration, TemplateManagement) now call real Pinia store actions
- **ApexCharts**: Registered globally as `VueApexCharts`
- **Schedule Meeting**: HR broadcasts meeting links to divisions/teams via queued notifications
- **Constants** (`app/Constants/CacheConstants.php`): `PAYROLL_BULK_INSERT_CHUNK_SIZE = 500`, `DEFAULT_PAGINATION_SIZE = 50`
- **Meilisearch proxy fix**: OrbStack injects `http_proxy` into Docker containers. `NO_PROXY` must include `localhost,127.0.0.1,mysql,redis,meilisearch,phpmyadmin,*.orb.internal` in `docker-compose.yml`
