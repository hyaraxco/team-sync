# Payroll Role E2E QA Runbook

## Tujuan
- Memverifikasi fitur payroll berjalan sesuai role `manager`, `hr`, `finance`, dan `employee`.
- Memastikan status payroll konsisten dari draft `pending` sampai `paid`.
- Mencatat gap implementasi employee payslip bila handoff backend belum tersedia.

## Persiapan
1. Seed data minimum:
   ```bash
   cd /Users/hyarax/Documents/project/team-sync/team-sync-be
   php artisan db:seed --class=MinimalPayrollE2ESeeder
   ```
   Alternatif jika ingin employee langsung melihat data `My Payroll` tanpa generate manual:
   ```bash
   php artisan db:seed --class=MinimalPayrollE2EReadySeeder
   ```
   Opsi paling cepat sebelum manual testing (reset data payroll QA + health check endpoint payroll per role):
   ```bash
   php artisan qa:payroll-ready
   ```
2. Gunakan bulan berjalan sebagai bulan uji payroll.
3. Akun yang tersedia:
   - `yudhis@teamsync.com` / `teamsync`
   - `tasyia@teamsync.com` / `teamsync`
   - `dwimeta@teamsync.com` / `teamsync`
   - `agung@teamsync.com` / `teamsync`

## Urutan Test Manual
1. `Manager`
   - Login berhasil.
   - Sidebar tidak menampilkan menu `Payroll`.
   - Dashboard tidak menampilkan quick action `Process Payroll`.
   - Dashboard dapat menampilkan quick action personal seperti `Request Leave` sesuai permission self-service.
   - Bisa membuka route personal (`My Profile`, `My Attendance`, `My Payroll`) pada akun yang sama.
   - Akses `/admin/payroll`, `/admin/payroll/create`, dan detail payroll diarahkan kembali atau ditolak.
   - Request API payroll utama harus `403`.
2. `HR`
   - Login berhasil.
   - Sidebar menampilkan menu `Payroll`.
   - Dashboard quick action menampilkan `Process Payroll`.
   - Masuk ke `/admin/payroll`, daftar payroll tampil.
   - View payroll menggunakan mode operasional tanpa statistik gaji agregat.
   - Masuk ke `/admin/payroll/create` dan generate payroll bulan berjalan.
   - Setelah generate selesai diproses, payroll baru muncul dengan status `pending`.
   - Buka detail payroll dan pastikan tombol `Mark as Paid` tidak tampil.
   - Akses statistik payroll sensitif harus ditolak.
3. `Finance`
   - Login berhasil.
   - Sidebar menampilkan menu `Payroll`.
   - Dashboard quick action tidak menampilkan `Process Payroll`.
   - Masuk ke `/admin/payroll`, statistik payroll sensitif tampil.
   - Action utama finance adalah `Export Payroll Report`.
   - `Payroll Settings` bisa diakses dan disimpan untuk role finance.
   - Akses `/admin/payroll/create` harus ditolak atau diarahkan kembali.
   - Buka detail payroll `pending`, statistik payroll tampil, dan tombol `Mark as Paid` tersedia.
   - `Export Payroll Report` dan export Excel detail dianggap valid untuk role ini.
   - Jalankan `Mark as Paid` lalu pastikan status berubah menjadi `paid` di dashboard, detail, dan API.
   - Setelah `paid`, detail payroll menampilkan informasi bahwa notifikasi employee sudah dikirim otomatis.
   - API `generate payroll` harus tetap `403`.
4. `Employee`
   - Login berhasil.
   - Tidak bisa masuk route payroll admin dan tidak melihat menu payroll admin.
   - API payroll admin harus `403`.
   - Jika punya permission `payslip-view`, sidebar menampilkan `My Payroll`.
   - `My Payroll` hanya menampilkan slip gaji milik employee yang status payroll-nya sudah `paid`.
   - Employee bisa buka detail payslip dan download PDF.
   - Employee tidak bisa membuka payslip milik employee lain lewat URL langsung.

## Expected Result
- `manager` tidak punya akses payroll admin, tetapi tetap punya self-service personal.
- `hr` hanya bisa membuat dan memonitor draft payroll.
- `finance` hanya bisa review, lihat statistik, export report, export detail, dan mark payroll sebagai `paid`.
- `employee` tidak punya akses payroll admin.
- `employee` bisa melihat dan mengunduh payslip miliknya sendiri jika payroll sudah `paid`.
- Transisi status payroll dari `pending` ke `paid` harus konsisten di UI dan API.

## Stage 1 Done Criteria
- `manager` tidak melihat payroll admin menu/quick action, tidak bisa direct URL payroll admin, dan API payroll admin tetap `403`.
- `manager`, `hr`, dan `finance` bisa mengakses self-service personal (`My Profile`, `My Attendance`, `My Payroll`) dari akun yang sama.
- `hr` bisa generate draft payroll, duplicate month diblok di UI, detail payroll tidak menampilkan statistik sensitif atau `Mark as Paid`.
- `finance` tidak bisa create payroll, tetapi bisa buka detail payroll, melihat statistik, mengakses payroll settings, export payroll report, export Excel, dan mark payroll menjadi `paid`.
- Setelah payroll `paid`, finance melihat info auto notification, bukan tombol manual send.
- `employee` bisa buka `My Payroll`, detail, dan download PDF miliknya sendiri, tetapi semua route payroll admin tetap deny.
- `bun run test:guards` dan `bun run e2e` harus hijau.

## FE Guard Direct URL Checklist
- Jalankan sesudah login sesuai role dan paste URL langsung di browser.
- Semua kasus `deny` harus kembali ke dashboard (`/admin/dashboard`) atau login bila sesi invalid.

### Manager (`yudhis@teamsync.com`)
- Allow: `/admin/dashboard`, `/admin/teams`, `/admin/employees`, `/admin/projects`, `/admin/attendances`, `/admin/my-profile`, `/admin/attendance/my-attendances`, `/admin/my-payroll`, `/admin/my-payslips`
- Deny: `/admin/payroll`, `/admin/payroll/create`, `/admin/payroll/1`, `/admin/payroll/settings`

### HR (`tasyia@teamsync.com`)
- Allow: `/admin/dashboard`, `/admin/payroll`, `/admin/payroll/create`, `/admin/payroll/1`, `/admin/teams`, `/admin/employees`, `/admin/attendances`, `/admin/my-profile`, `/admin/attendance/my-attendances`, `/admin/my-payroll`, `/admin/my-payslips`
- Deny: `/admin/payroll/settings`

### Finance (`dwimeta@teamsync.com`)
- Allow: `/admin/dashboard`, `/admin/payroll`, `/admin/payroll/1`, `/admin/payroll/settings`, `/admin/attendances`, `/admin/my-profile`, `/admin/attendance/my-attendances`, `/admin/my-payroll`, `/admin/my-payslips`
- Deny: `/admin/payroll/create`, `/admin/teams`, `/admin/projects`

### Employee (`agung@teamsync.com`)
- Allow: `/admin/dashboard`, `/admin/my-profile`, `/admin/my-team`, `/admin/attendance/my-attendances`, `/admin/attendance/clock`, `/admin/my-payroll`, `/admin/my-payslips`
- Deny: `/admin/employees`, `/admin/teams`, `/admin/attendances`, `/admin/payroll`, `/admin/payroll/create`

## Automated UI E2E (Bun + Docker BE)
- Install browser dependency once:
  ```bash
  cd /Users/hyarax/Documents/project/team-sync/team-sync-fe
  bun run e2e:install
  ```
- Run full payroll role journey automation:
  ```bash
  bun run e2e
  ```
- Open Playwright HTML report:
  ```bash
  bun run e2e:report
  ```
- Environment overrides yang didukung:
  ```bash
  E2E_BE_DIR=../team-sync-be
  E2E_BE_COMPOSE_CMD="docker compose"
  E2E_FE_BASE_URL=http://127.0.0.1:4173
  VITE_API_BASE_URL=http://127.0.0.1:8000/api/v1
  ```
- Artifacts:
  - `team-sync-fe/playwright-artifacts/manager-deny-payroll.png`
  - `team-sync-fe/playwright-artifacts/hr-pending-created.png`
  - `team-sync-fe/playwright-artifacts/finance-paid.png`
  - `team-sync-fe/playwright-artifacts/employee-my-payroll.png`

## CI Automation (GitHub Actions)
- Workflow: `.github/workflows/payroll-ui-e2e.yml`
- Trigger:
  - manual `workflow_dispatch`
  - `pull_request` yang menyentuh `team-sync-fe/**` atau `team-sync-be/**`
  - `push` ke branch `main` dengan path yang sama
- Job menjalankan:
  - `FE Guard Smoke` precheck lebih dulu
  - install dependency FE via Bun + restore cache
  - install Playwright Chromium + Linux deps + restore browser cache
  - buat `team-sync-be/.env` dari `.env.example`
  - start `mysql`, `redis`, `web`, generate app key, dan wait `/up`
  - `bun run e2e:prepare:be` lalu `bun run e2e:ui`
- Artifact CI yang di-upload:
  - `playwright-report`
  - `test-results`
  - `playwright-artifacts`
  - `backend-compose.log`
  - `backend-compose-ps.log`
  - `backend-health-check.txt`

## Fast FE Guard Gate (CI)
- Workflow: `.github/workflows/fe-guard-smoke.yml`
- Tujuan: guard regression cepat di FE tanpa menunggu full Playwright E2E.
- Command yang dijalankan:
  ```bash
  cd team-sync-fe
  bun run test:guards
  ```
- Coverage utama:
  - `routerGuard.integration.test.js`
  - sidebar + quick actions visibility by role
  - payroll admin smoke (dashboard/create/detail)
  - employee workspace smoke (profile, attendance, my payroll, payslip detail)

## Latest QA Sign-Off (2026-04-08)
- Full automation `bun run e2e` lulus untuk 4 role (`manager`, `hr`, `finance`, `employee`) dalam satu run.
- QA role-by-role tambahan lulus:
  - `HR`: generate draft `pending`, duplicate month guard aktif, tidak bisa approve/mark-as-paid.
  - `Finance`: tidak bisa generate, bisa review/export/approve/mark-as-paid, status final `paid`.
  - `Manager`: payroll admin tetap deny (`403`), self-service personal tetap usable.
  - `Employee`: my payroll list/detail/download milik sendiri lulus, admin payroll endpoint tetap `403`.
- Catatan eksekusi:
  - Saat test dijalankan paralel dengan proses seeding yang belum selesai, sempat terjadi false-fail login (`/auth/login`). Re-run setelah seed selesai menghasilkan pass.
