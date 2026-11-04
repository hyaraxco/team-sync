# Documentation Cleanup

**Status:** COMPLETED — 2026-11-04 (local documentation changes; not committed)
**Scope:** Dokumentasi root, frontend, dan backend; tidak mengubah kode aplikasi.

## Plan

- [x] Inventaris dokumentasi dan perubahan lokal yang sudah ada.
- [x] Pisahkan panduan aktif, roadmap, dan dokumen historis.
- [x] Rapikan titik masuk FE/BE dan tautan dokumentasi.
- [x] Verifikasi tautan lokal dan pastikan perubahan hanya dokumentasi.

## Hasil

- Ganti template Laravel generik dengan README operasional backend.
- Tambahkan README frontend sebagai titik masuk setup dan pengujian.
- Perbaiki panduan E2E dan keterangan dark mode di design system.
- Arsipkan desain enowxai yang workflow-nya tidak ada pada checkout.
- Tambahkan indeks future/archive dan perbaiki tautan payroll serta PRD.
- Perjelas bahwa arsip tidak otomatis berarti pekerjaan selesai.
- Pertahankan plan lokal permission/Strix dengan catatan status belum terverifikasi.

## Verifikasi

- 32 dokumen Markdown non-archive: 0 tautan file lokal rusak (anchor dan URL
  eksternal tidak diuji).
- `git diff --check`: lulus, hanya peringatan konversi LF/CRLF.
- Tautan historis `file:///Users/...` dan komponen yang sudah berganti nama
  pada arsip lama tidak ditulis ulang seolah referensinya masih berlaku.
- Suite aplikasi tidak dijalankan karena perubahan hanya dokumentasi.
- Tidak ada commit, push, atau perubahan kode aplikasi pada tugas ini.

## Follow-up: Frontend Folder Cleanup

User memperluas cakupan ke folder hasil build/tes di dalam repo frontend.

- Hapus output lokal generated: `dist/`, `playwright-artifacts/`,
  `playwright-report/`, dan `test-results/` (semuanya di-ignore, tidak tracked).
- Hapus lima `_debug-staff*.spec.ts` yang hanya mencetak log tanpa assertion.
- Hapus `team-sync-fe/package-lock.json` lokal; lockfile yang dipakai `bun.lock`.
- Pertahankan design system, suite E2E regresi, fixtures/helpers, dan script
  persiapan backend yang masih dirujuk package scripts.
- Perbarui dokumentasi struktur frontend; tidak mengubah kode runtime aplikasi.
- Verifikasi: empat direktori output dan npm lockfile sudah tidak ada;
  15 spec regresi tetap tersimpan; `git diff --check` lulus.
- Listing Playwright belum dapat dijalankan: Bun tidak tersedia di PATH dan
  dependency lokal `@playwright/test` belum terpasang. Tidak melakukan install,
  menjalankan E2E, atau melakukan reset database.

## Safety

- Pertahankan perubahan lokal `team-sync-be/docker-compose.yml`.
- Jangan menyatakan pekerjaan selesai hanya karena plan lama berada di archive.
- Pertahankan plan permission cleanup dan Strix yang masih memiliki penutupan belum terverifikasi.
- Arsipkan desain lama yang bernilai konteks; hapus hanya duplikat/boilerplate yang terbukti tidak relevan.
