# TOPSIS Evidence & Release Reconciliation — team-sync-lzo

**Status:** COMPLETED (2026-11-22) — ke archive
**Branch:** fix/team-sync-lzo-topsis-evidence

## AC#1 — Jalankan suite TOPSIS nyata

- Perintah: `./vendor/bin/pest tests/Unit/TopsisServiceTest.php tests/Unit/TopsisIntermediateTest.php tests/Feature/Performance/PerformanceTopsisControllerTest.php tests/Feature/Performance/TopsisCandidatePipelineTest.php`
- Runtime: PHP 8.4.28 CLI, revision `02fdcf0`, 2026-11-22T16:32Z
- Hasil: Unit 19 passed (565 assertions); Feature 15 passed (55 assertions)
- Endpoint ranking diuji oleh `PerformanceTopsisControllerTest` dengan 3 kandidat terdiferensiasi (Yudhis > Agung > Tasyia), default dan custom weights, plus permission matrix. Output tercatat pada sesi eksekusi; angka final: full suite **1687 passed / 7514 assertions** (`composer test`).

## AC#2 — Fixed-value intermediates dari sampel R1

Ditambahkan `tests/Unit/TopsisIntermediateTest.php` (9 test, 81 assertions):
- Denominator normalisasi per kriteria (fixed 9-desimal dari perhitungan JS independen)
- Normalized & weighted matrix K17 (fixed values)
- **D+/D− untuk semua 5 kandidat** terhadap rekomputasi independen — bukan estimasi plan arsip
- Jurnal-rounded K17: D+=0.0093, D−=0.0220 (R1 pp.90-91). Estimasi plan arsip (0.0048/0.0115) **tidak dipakai** dan tercatat salah di test
- Sumber: Arundaa, Sopacua & Tamonob, IJIDS 3(2) 2024, pp.85-94, Table 2 & 3

## AC#3 — Tie ordering, konvensi, boundary repository

- **Tie permutation**: 6 permutasi input dengan 2 kandidat CC identik → urutan stabil `['K20','K09','K31']` (lexicographic staff_member_id) di semua permutasi
- **Single-candidate convention**: normalized=1.0 (positif) / 0.0 (nol), weighted=weight, ideals collapse, D+=D−=0, CC=1.0 konvensional (rasio 0/0 tak terdefinisi) — didokumentasikan eksplisit di test
- **Zero-column convention**: kolom semua-nol → normalisasi 0 via fallback denominator 1.0
- **Repository boundary** (`TopsisCandidatePipelineTest`, 6 test): feedback floor 10 (3/10→30), cap cycle-max (15/15→100), bukan positive → 0, feedback luar window cycle dikecualikan, tenure cap 60 bulan (80→100), hanya review `completed` jadi kandidat

## AC#4 — Rekonsiliasi plan arsip vs merge/CI

- **PR #79** (permission cleanup): terverifikasi MERGED 2026-06-01T17:03:13Z, merge commit `f5fd20c2`; Checks API pada merge commit: `be-tests` success, `fe-tests` success, `auto-tag` success. Plan `2026-06-01-permission-gate-cleanup.md` kini ter-arsip; angka 1147 FE / 1644 BE di plan adalah rekam historis, bukan sign-off berjalan.
- **Strix**: tidak ada di `.github/workflows/` saat ini (hanya be-tests, fe-tests, release), tidak ada di registry Actions (BE Tests, FE Tests, Release, Copilot×3, Dependabot), tidak ada workflow `strix`. Checklist plan Strix masih `- [ ]` untuk secrets/verify. Status: **plan Strix tetap tidak aktif dan tidak bisa dinyatakan selesai**; pemeriksaan secrets/SARIF tetap tidak terverifikasi (membutuhkan akses secret). Peluncur lokal Strix tercakup tiket `team-sync-5y4` (deferred).
- **Plan arsip `2026-05-21-topsis-academic-hardening.md`**: nilai D+/D− di line 345-355 adalah estimasi yang berbeda dari jurnal (0.0048/0.0115 vs aktual 0.0093/0.0220) — sudah diberi catatan; assertion yang benar kini ada di `TopsisIntermediateTest`.

## AC#5/#6 — Bukti E2E dan role QA

- E2E Playwright penuh **tidak dijalankan** pada pengerjaan ini (butuh environment seeded + backend live). Full BE Pest suite dijalankan sebagai verifikasi terprogram; E2E fresh sign-off tetap terbuka sebagai tindak lanjut.
- Dokumen QA legacy dikoreksi sesuai least-privilege berjalan:
  - `docs/testing/be/payroll-role-e2e-qa.md`: HR **tidak** lagi generator payroll; Finance melakukan generate/review/export/approve/mark-as-paid
  - `docs/testing/fe/manual-testing-guide.md`: Payroll Full Cycle direvisi (Finance generate; HR jadi negative check 403)

## Batas verifikasi

1. E2E role-based fresh sign-off belum dijalankan (membutuhkan lingkungan terisolasi + seed penuh) — AC#5 terpenuhi parsial, dicatat jujur.
2. Repository-secret availability untuk Strix tidak diperiksa (butuh otorisasi).
3. Angka 1687/7514 adalah snapshot 2026-11-22 pada `02fdcf0` + perubahan branch ini.
