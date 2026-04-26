---
description: Dokumen ini merangkum workflow aplikasi Team Sync dari sisi bisnis, pengembangan, testing, dan CI.
---

## 1) Workflow Bisnis Payroll per Role

### Manager
1. Login ke dashboard.
2. Tidak memiliki akses ke menu dan route payroll admin.
3. Tetap dapat mengakses self-service personal seperti My Profile, My Attendance, dan My Payroll.

### HR
1. Login dan buka menu Payroll.
2. Generate payroll bulan berjalan melalui halaman create payroll.
3. Payroll baru masuk status pending.
4. HR memonitor draft payroll, tetapi tidak melakukan mark as paid.

### Finance
1. Login dan buka menu Payroll.
2. Review payroll pending, lihat statistik sensitif, dan jalankan export report.
3. Jalankan aksi Mark as Paid setelah validasi selesai.
4. Setelah status paid, sistem menandai bahwa notifikasi employee sudah dikirim otomatis.

### Employee
1. Login tanpa akses payroll admin.
2. Mengakses My Payroll hanya untuk data milik sendiri.
3. Melihat detail payslip dan download PDF ketika payroll sudah paid.

### Transisi Status Payroll
- Pending: draft payroll setelah HR generate.
- Paid: final setelah finance melakukan approval akhir (mark as paid).

## 2) Workflow Attendance ke Payroll (Phase 1)
1. Attendance dan leave tercatat pada periode attendance aktif.
2. Sistem menghitung effective working days berdasarkan policy, jadwal kerja, dan holiday.
3. Validasi leave entitlement menentukan paid atau unpaid impact.
4. Klasifikasi attendance harian menghasilkan komponen perhitungan payroll.
5. Saat periode locked, perubahan pasca-lock tidak mengubah payroll lama.
6. Koreksi pasca-lock dicatat sebagai payroll adjustment untuk periode berikutnya.

## 3) Workflow Pengembangan Lokal

### Backend (team-sync-be)
1. Start container utama:
   - docker compose up -d mysql redis web
2. Pastikan app key sudah tersedia.
3. Jalankan migration dan seed sesuai kebutuhan domain.
4. Jika fitur melibatkan notifikasi/antrian, jalankan queue worker:
   - docker compose up -d queue
   - atau php artisan queue:work

### Frontend (team-sync-fe)
1. Install dependency:
   - bun install --frozen-lockfile
2. Jalankan dev server:
   - bun run dev
3. Integrasikan FE dengan API backend lokal sesuai env URL.

### Workflow E2E Lokal
1. Siapkan backend data E2E payroll.
2. Jalankan:
   - bun run e2e
3. Buka report:
   - bun run e2e:report

## 4) Workflow Testing

### Fast Guard (Frontend)
- Tujuan: validasi cepat role guard, route guard, dan smoke komponen penting.
- Command:
  - bun run test:guards

### Frontend Test Suite
- Command:
  - bun run test

### Backend Test Suite
- Command:
  - php artisan test

### Root Playwright (Generic)
- Command:
  - npx playwright test

## 5) Workflow CI Aktif

### A. FE Guard Smoke
- File: .github/workflows/fe-guard-smoke.yml
- Trigger:
  - workflow_dispatch
  - pull_request (path team-sync-fe atau workflow file)
  - push ke main (path team-sync-fe atau workflow file)
- Aksi utama:
  - setup Bun
  - install dependency FE
  - jalankan bun run test:guards

### B. Payroll UI E2E
- File: .github/workflows/payroll-ui-e2e.yml
- Trigger:
  - workflow_dispatch
  - pull_request (path team-sync-fe, team-sync-be, atau workflow file)
  - push ke main (path terkait)
- Urutan job:
  1. fe-guard-smoke precheck
  2. setup FE dependencies dan Playwright Chromium
  3. start backend docker services (mysql, redis, web)
  4. health check endpoint backend
  5. prepare dataset backend untuk E2E
  6. jalankan bun run e2e:ui
  7. upload artifacts (playwright-report, test-results, playwright-artifacts)

### C. Root Playwright Tests
- File: .github/workflows/playwright.yml
- Trigger:
  - push/pull_request ke main/master
- Aksi utama:
  - npm ci
  - playwright install --with-deps
  - npx playwright test

## 6) Workflow PR yang Direkomendasikan
1. Mulai dari perubahan kecil per domain (attendance, payroll, employee, atau FE guard).
2. Jalankan test yang paling relevan terlebih dulu.
3. Jika menyentuh role access payroll, jalankan minimal:
   - bun run test:guards
4. Jika menyentuh alur lintas FE-BE payroll, jalankan:
   - bun run e2e
5. Pastikan CI workflow terkait berubah status menjadi hijau sebelum merge.
