# Payroll Role E2E QA Runbook

> [!NOTE]
> **Executable Test**: [PayrollRoleJourneyTest.php](../../../team-sync-be/tests/Feature/Payroll/PayrollRoleJourneyTest.php)
>
> Ekspektasi payroll direkonsiliasi pada 2026-11-23 terhadap `RolePermissionSeeder.php`, router payroll, dan quick actions. Ini pemeriksaan sumber, bukan hasil E2E baru. Sign-off historis ada di bagian terakhir.

## Tujuan
- Memverifikasi fitur payroll berjalan sesuai role `manager`, `hr`, `finance`, dan `employee`.
- Memastikan status payroll konsisten dari draft `pending` sampai `paid`.
- Mencatat gap implementasi employee payslip bila handoff backend belum tersedia.

## Persiapan

Perintah seed dan `qa:payroll-ready` di panduan lama dapat mereset payroll. Jangan jalankan pada database development atau production. `team-sync-wlk` melacak guard yang harus membuktikan target E2E terpisah sebelum perintah normal digunakan.

Sampai `team-sync-wlk` lulus, jangan seed data payroll ke backend Compose default. Untuk role test yang mereset data, gunakan hanya sandbox SQLite terjaga di [`../../../scripts/sandbox/README.md`](../../../scripts/sandbox/README.md). Akun di bawah hanya identitas fixture yang sudah ada, bukan izin untuk seed database.

Uji perilaku payroll di repository dapat dijalankan melalui test Pest yang tercantum di atas, dengan database test yang dikelola harness Pest.

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
   - Sidebar **tidak** menampilkan menu `Payroll`.
   - Dashboard quick action **tidak** menampilkan `Process Payroll`.
   - Akses `/admin/payroll`, `/admin/payroll/create`, dan detail payroll diarahkan kembali atau ditolak.
   - API payroll utama (generate, approve, settings) harus `403`.
   - `/admin/payroll/readiness` dan `/admin/payroll/thr` tetap dapat dibuka untuk koordinasi read-only (`payroll-readiness-view`, `thr-list`).
   - Tidak ada action generate/approve/process THR; API mutasi THR harus `403`.
3. `Finance`
   - Login berhasil.
   - Sidebar menampilkan menu `Payroll`.
   - Dashboard quick action menampilkan `Process Payroll` (`payroll-create`).
   - Masuk ke `/admin/payroll`, statistik payroll sensitif tampil.
   - Action generate payroll dan `Export Payroll Report` tersedia sesuai permission.
   - `Payroll Settings` bisa diakses dan disimpan untuk role finance.
   - Buka `/admin/payroll/create`, periksa readiness, lalu generate bulan uji. Setelah job selesai, status menjadi `pending`; duplicate month harus ditolak.
   - Buka detail payroll `pending`, periksa statistik dan selesaikan seluruh approval yang diwajibkan. `Mark as Paid` hanya dijalankan setelah status `approved`.
   - `Export Payroll Report` dan export Excel detail dianggap valid untuk role ini.
   - Jalankan `Mark as Paid` lalu pastikan status berubah menjadi `paid` di dashboard, detail, dan API.
   - Setelah `paid`, detail payroll menampilkan informasi bahwa notifikasi employee sudah dikirim otomatis.
   - API generate tidak ditolak oleh permission Finance; validasi input, readiness, dan status tetap berlaku.
   - Finance memiliki operasi THR generate/approve/process; verifikasi transisi yang sesuai dengan data uji.
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
- `hr` tidak punya operasi payroll/THR atau akses dashboard/detail/settings payroll; readiness dan THR tetap read-only untuk koordinasi.
- `finance` memiliki operasi payroll/THR, termasuk generate, review, settings, approval, export, dan pembayaran; prasyarat status/approval tetap berlaku.
- `employee` tidak punya akses payroll admin.
- `employee` bisa melihat dan mengunduh payslip miliknya sendiri jika payroll sudah `paid`.
- Transisi status payroll `processing`, `pending`, `approved`, lalu `paid` harus konsisten di UI dan API.

## Stage 1 Done Criteria
- `manager` does not see payroll admin menu/actions, cannot open admin URLs, and receives API `403`.
- `manager`, `hr`, and `finance` can access their own employee self-service pages.
- `hr` cannot generate or open payroll operations; only permission-allowed readiness/THR read-only pages are available.
- `finance` can perform payroll operations allowed by its permissions, including generate, approve, export and pay.
- After payroll is paid, finance sees the supported notification state, not an inert manual-send action.
- `employee` can access only their own payroll details/downloads; payroll admin remains denied.
- Run `bun run test:guards` as appropriate. Run Playwright E2E only after `team-sync-wlk` passes its isolated-target checks, or use the guarded SQLite role sandbox.

## FE Guard Direct URL Checklist
- Jalankan sesudah login sesuai role dan paste URL langsung di browser.
- Semua kasus `deny` harus kembali ke dashboard (`/admin/dashboard`) atau login bila sesi invalid.

### Manager (`yudhis@teamsync.com`)
- Allow: `/admin/dashboard`, `/admin/teams`, `/admin/projects`, `/admin/attendances`, `/admin/my-profile`, `/admin/attendance/my-attendances`, `/admin/my-payroll`, `/admin/my-payslips`
- Deny: `/admin/staff-members`, `/admin/payroll`, `/admin/payroll/create`, `/admin/payroll/1`, `/admin/payroll/settings`, `/admin/payroll/readiness`, `/admin/payroll/thr`

### HR (`tasyia@teamsync.com`)
- Allow: `/admin/dashboard`, `/admin/payroll/readiness`, `/admin/payroll/thr`, `/admin/teams`, `/admin/staff-members`, `/admin/attendances`, `/admin/my-profile`, `/admin/attendance/my-attendances`, `/admin/my-payroll`, `/admin/my-payslips`
- Deny: `/admin/payroll`, `/admin/payroll/create`, `/admin/payroll/1`, `/admin/payroll/settings`, `/admin/payroll/approval-matrix`, `/admin/payroll/adjustments`, `/admin/payroll/comparison`

### Finance (`dwimeta@teamsync.com`)
- Allow: `/admin/dashboard`, `/admin/payroll`, `/admin/payroll/create`, `/admin/payroll/1`, `/admin/payroll/settings`, `/admin/payroll/readiness`, `/admin/payroll/thr`, `/admin/payroll/approval-matrix`, `/admin/payroll/adjustments`, `/admin/payroll/comparison`, `/admin/projects`, `/admin/my-profile`, `/admin/attendance/my-attendances`, `/admin/my-payroll`, `/admin/my-payslips`
- Deny: `/admin/staff-members`, `/admin/teams`, `/admin/attendances`

### Employee (`agung@teamsync.com`)
- Allow: `/admin/dashboard`, `/admin/my-profile`, `/admin/my-team`, `/admin/attendance/my-attendances`, `/admin/attendance/clock`, `/admin/my-payroll`, `/admin/my-payslips`
- Deny: `/admin/staff-members`, `/admin/teams`, `/admin/attendances`, `/admin/payroll`, `/admin/payroll/create`, `/admin/payroll/readiness`, `/admin/payroll/thr`

## Automated UI E2E

The normal wrapper now uses the pinned `teamsync-e2e-isolated` Compose target. Its guard checks the canonical Compose file and SHA before Docker access; the Compose file supplies E2E-only credentials, project-scoped volumes, and masks the backend `.env`. Guard/refusal tests and Compose configuration parsing passed. No Docker services, migrations, or database reset were run during this change.

Use `bun run e2e` only with this approved target. Do not override `E2E_BE_DIR`, Compose files, or database settings to point at development or production. For reset-capable role tests without MySQL, use the guarded SQLite sandbox in [`../../../scripts/sandbox/README.md`](../../../scripts/sandbox/README.md).

`bun run e2e:install` installs the Playwright browser only; it does not prepare or reset the backend.

## CI Automation historis (GitHub Actions)

Workflow `payroll-ui-e2e.yml` dan `fe-guard-smoke.yml` di bawah tidak ada pada checkout 2026-11-23. Konfigurasi berikut dipertahankan sebagai referensi lama, bukan gate aktif atau bukti sign-off. Workflow aktif ada di `.github/workflows/be-tests.yml` dan `.github/workflows/fe-tests.yml`; keduanya bukan pengganti bukti role E2E.

- Workflow historis: `.github/workflows/payroll-ui-e2e.yml`
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

## Fast FE Guard Gate historis (CI)
- Workflow historis: `.github/workflows/fe-guard-smoke.yml`
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

## QA Sign-Off historis (2026-04-08, pembagian role lama)

Hasil asli berikut dipertahankan sebagai catatan historis. Pembagian payroll HR/Finance saat itu sudah usang dan bukan ekspektasi saat ini. E2E baru belum dijalankan dalam rekonsiliasi 2026-11-23; checklist aktif di atas masih memerlukan sign-off baru.

- Full automation `bun run e2e` lulus untuk 4 role (`manager`, `hr`, `finance`, `employee`) dalam satu run.
- QA role-by-role tambahan lulus:
  - `HR`: generate draft `pending`, duplicate month guard aktif, tidak bisa approve/mark-as-paid.
  - `Finance`: tidak bisa generate, bisa review/export/approve/mark-as-paid, status final `paid`.
  - `Manager`: payroll admin tetap deny (`403`), self-service personal tetap usable.
  - `Employee`: my payroll list/detail/download milik sendiri lulus, admin payroll endpoint tetap `403`.
- Catatan eksekusi:
  - Saat test dijalankan paralel dengan proses seeding yang belum selesai, sempat terjadi false-fail login (`/auth/login`). Re-run setelah seed selesai menghasilkan pass.
