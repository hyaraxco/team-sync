# Hapus Komponen Analitik Enhanced Tak Terpakai dan Bersihkan Dependensi FE (team-sync-5ns)

Status: COMPLETED
Date: 2026-11-23
PR: #98
Merge SHA: 2f009289ce1c3c74fabe8e8fc6906df605c9ea34

## Problem & Context

4 file komponen "Enhanced" analitik di frontend tidak diimpor atau digunakan di mana pun:
- `WorkforceAnalyticsEnhanced.vue` (219 baris)
- `AttendanceAnalyticsEnhanced.vue` (190 baris)
- `LeaveAnalyticsEnhanced.vue` (215 baris)
- `ProjectAnalyticsEnhanced.vue` (216 baris)

Komponen-komponen tersebut memiliki broken import dan dead code (~840 baris). Selain itu, terdapat dependensi tak terpakai di `package.json` yang memperbesar bundle/install footprint:
- `jspdf`, `jspdf-autotable`
- `vue3-emoji-picker`
- `lodash-es` (hanya dipakai satu fungsi di `Header.vue`)
- `lucide` (proyek menggunakan `lucide-vue-next`)
- `concurrently` (script dev FE menggunakan docker/bun/vite mandiri)

## Scope & Changes

1. Hapus 4 file komponen mati di `team-sync-fe/src/components/admin/analytics/`:
   - `WorkforceAnalyticsEnhanced.vue`
   - `AttendanceAnalyticsEnhanced.vue`
   - `LeaveAnalyticsEnhanced.vue`
   - `ProjectAnalyticsEnhanced.vue`
2. Bersihkan dependensi tak terpakai di `package.json` dan perbarui `bun.lock`:
   - `jspdf`, `jspdf-autotable`, `vue3-emoji-picker`, `lodash-es`, `lucide`, `concurrently`.
3. Migrasi pemakaian `lodash-es` (`join`) di `Header.vue` ke native array formatting (`formatUserRoles`).
4. Verifikasi `data-lucide="graduation-cap"` di `TeamDetail.vue` sudah ditangani (telah digantikan oleh `EmptyState` pada PR #96 / commit 8125bd0).

## Verification & Outcomes

- PR #98 merged ke `main` di SHA `2f009289ce1c3c74fabe8e8fc6906df605c9ea34`.
- Post-merge CI run 45142399642 (FE Tests) lulus: frozen Bun install, Vitest (148 test files, 1156 tests), build & accessibility audit.
- GitGuardian pass.
- Backend Pest suite (1693 tests, 7671 assertions) unaffected and verified passing.
