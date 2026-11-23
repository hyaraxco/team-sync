# TOPSIS Evidence & Release Reconciliation — team-sync-lzo

**Status:** IN_PROGRESS (continuation 2026-11-23). PR #92 merged as `026b972`; the ticket remains open until the remaining acceptance criteria are met.
**Branch:** test/team-sync-lzo-complete-evidence

## Continuation plan

1. Extend independent fixed normalized/weighted references to all five R1 candidates.
2. Assert observable default/partial/negative/zero weight results through the ranking endpoint and retain dummy numerical response evidence.
3. Obtain affected/full BE results and retained dummy endpoint responses from exact-head CI. Fresh FE/build/accessibility and role/ranking E2E remain pending; local target execution requires all mandatory sandbox controls, not merely a disposable database.
4. Self-review, publish a scoped PR with user attribution, and obtain final approval before merge. Archive and close only after complete acceptance and verified merge/checks.

Scope: existing TOPSIS tests, endpoint fixtures, BE CI artifact retention and this plan. No new product features or payroll/security fixes. Preserve the unrelated untracked release-policy document.

### Continuation changes (not yet runtime-verified)

- Full 5x5 normalized and weighted fixed reference matrices, independently checked with Python standard-library arithmetic against the R1 sample.
- Endpoint fixtures freeze time; default/custom/partial scenarios assert three differentiated candidates and fixed coefficients. Default/custom distances use independent fixed references so an echoed weight change alone cannot pass. Partial weights assert all normalized values; negative and all-zero scenarios assert zero contributions/distances/coefficients rather than only an HTTP status.
- `TOPSIS_EVIDENCE_DIR` optionally captures only default/custom dummy endpoint JSON. BE CI uploads those two files as `topsis-endpoint-<sha>` for 30 days, failing if evidence is missing. No development credentials, environment dumps or database exports are included.
- Local PHP/FE/E2E execution remains unperformed: complete OS-enforced network/environment/filesystem/resource isolation is not established. CI outcomes must be read on the new head before these assertions are reported as passed.

### Verified baseline and static checks

- PR #92 merged as `026b9721a985874050dbbc74012932bf54dd4834`. Post-merge BE CI run `45109948733` passed on that SHA: 1687 tests / 7515 assertions, 75.16s, including frozen Bun install and asset build. This baseline does not validate the continuation diff.
- `git diff --check` passed. Trusted Python standard-library calculations rechecked all 50 fixed matrix cells within `1e-8` and the default/custom endpoint distance references within `1e-6`. No application code ran locally.
- Before merge: require successful new-head BE CI and GitGuardian, inspect and retain the dummy endpoint evidence, and obtain user approval for this separate PR. Ticket closure still requires the fresh FE/role E2E evidence below.

## AC#1 — Jalankan suite TOPSIS nyata

Catatan di bawah adalah bukti eksekusi historis 2026-11-22, bukan rerun setelah koreksi review 2026-11-23.

- Perintah: `./vendor/bin/pest tests/Unit/TopsisServiceTest.php tests/Unit/TopsisIntermediateTest.php tests/Feature/Performance/PerformanceTopsisControllerTest.php tests/Feature/Performance/TopsisCandidatePipelineTest.php`
- Runtime: PHP 8.4.28 CLI, revision `02fdcf0`, 2026-11-22T16:32Z
- Hasil: Unit 19 passed (565 assertions); Feature 15 passed (55 assertions)
- Endpoint ranking diuji oleh `PerformanceTopsisControllerTest` dengan 3 kandidat terdiferensiasi (Yudhis > Agung > Tasyia), default dan custom weights, plus permission matrix. Output numerik endpoint yang dapat diperiksa belum disimpan sebagai bukti tiket. Angka full suite historis: **1687 passed / 7514 assertions** (`composer test`); ini bukan pengganti output endpoint.

## AC#2 — Fixed-value intermediates dari sampel R1

Ditambahkan `tests/Unit/TopsisIntermediateTest.php` (9 test, 81 assertions):
- Denominator normalisasi per kriteria (fixed 9-desimal dari perhitungan JS independen)
- Normalized & weighted matrix K17 (fixed values)
- **D+/D− untuk semua 5 kandidat** terhadap rekomputasi independen — bukan estimasi plan arsip
- Jurnal-rounded K17: D+=0.0093, D−=0.0220 (R1 pp.90-91). Estimasi plan arsip (0.0048/0.0115) **tidak dipakai** dan tercatat salah di test
- Sumber: Arundaa, Sopacua & Tamonob, IJIDS 3(2) 2024, pp.85-94, Table 2 & 3
- Batas historis PR #92: normalized/weighted hanya mencakup K17. Continuation memperluas ke lima kandidat; hasil runtime head baru masih harus diperoleh.

## AC#3 — Tie ordering, konvensi, boundary repository

- **Tie permutation**: 6 permutasi input dengan 2 kandidat CC identik → urutan stabil `['K20','K09','K31']` (lexicographic staff_member_id) di semua permutasi
- **Single-candidate convention**: normalized=1.0 (positif) / 0.0 (nol), weighted=weight, ideals collapse, D+=D−=0, CC=1.0 konvensional (rasio 0/0 tak terdefinisi) — didokumentasikan eksplisit di test
- **Zero-column convention**: kolom semua-nol → normalisasi 0 via fallback denominator 1.0
- **Repository boundary** (`TopsisCandidatePipelineTest`, 6 test): feedback floor 10 (3/10→30), cap cycle-max (15/15→100), bukan positive → 0, feedback luar window cycle dikecualikan, tenure cap 60 bulan (80→100), hanya review `completed` jadi kandidat
- Koreksi review PR #92: fixture constructive feedback dibekukan di dalam cycle dan timestamp tersimpan diperiksa, sehingga filter tanggal tidak menutupi kerusakan filter tipe. Test lulus pada CI head koreksi `81c5d3c` dan merge `026b972`; bukan rerun lokal.
- Batas historis PR #92: assertion partial/negative/all-zero weights lemah. Continuation menambahkan nilai bobot, kontribusi dan koefisien yang dimaksud; hasil runtime head baru masih harus diperoleh.

## AC#4 — Rekonsiliasi plan arsip vs merge/CI

- **PR #79** (permission cleanup): terverifikasi MERGED 2026-06-01T17:03:13Z, merge commit `f5fd20c2`; Checks API pada merge commit: `be-tests` success, `fe-tests` success, `auto-tag` success. Plan `2026-06-01-permission-gate-cleanup.md` kini ter-arsip; angka 1147 FE / 1644 BE di plan adalah rekam historis, bukan sign-off berjalan.
- **Strix**: tidak ada di `.github/workflows/` saat ini (hanya be-tests, fe-tests, release), tidak ada di registry Actions (BE Tests, FE Tests, Release, Copilot×3, Dependabot), tidak ada workflow `strix`. Checklist plan Strix masih `- [ ]` untuk secrets/verify. Status: **plan Strix tetap tidak aktif dan tidak bisa dinyatakan selesai**; pemeriksaan secrets/SARIF tetap tidak terverifikasi (membutuhkan akses secret). Peluncur lokal Strix tercakup tiket `team-sync-5y4` (deferred).
- **Plan arsip `2026-05-21-topsis-academic-hardening.md`**: nilai D+/D− di line 345-355 adalah estimasi yang berbeda dari jurnal (0.0048/0.0115 vs aktual 0.0093/0.0220) — sudah diberi catatan; assertion yang benar kini ada di `TopsisIntermediateTest`.

## AC#5/#6 — Bukti E2E dan role QA

- E2E Playwright penuh **tidak dijalankan** pada pengerjaan ini (butuh environment seeded + backend live). Full BE Pest suite dijalankan sebagai verifikasi terprogram; E2E fresh sign-off tetap terbuka sebagai tindak lanjut.
- Dokumen QA legacy dikoreksi sesuai least-privilege berjalan:
  - `docs/testing/be/payroll-role-e2e-qa.md`: Finance melakukan generate/review/export/approve/mark-as-paid. HR tidak memiliki operasi payroll/THR, tetapi mempertahankan readiness/THR read-only. Hasil QA 2026-04-08 dipulihkan sebagai bukti historis pembagian role lama, bukan sign-off saat ini.
  - `docs/testing/fe/manual-testing-guide.md`: checklist aktif diselaraskan dengan Finance sebagai operator dan HR sebagai read-only coordination/negative mutation check.
- Scope koreksi PR bukan penutupan tiket: FE/BE terkini, role E2E termasuk ranking, dan output numerik endpoint tetap belum lengkap. Jangan arsipkan plan ini sebagai COMPLETED dari BE-only checks.

## Batas verifikasi

1. E2E role-based fresh sign-off belum dijalankan (membutuhkan lingkungan terisolasi + seed penuh) — AC#5 terpenuhi parsial, dicatat jujur.
2. Repository-secret availability untuk Strix tidak diperiksa (butuh otorisasi).
3. Angka 1687/7514 adalah snapshot 2026-11-22 pada `02fdcf0` + perubahan branch ini.
4. Local rerun 2026-11-23 belum dijalankan karena seluruh kontrol sandbox wajib belum tersedia. CI `026b972` hanya baseline; continuation memerlukan checks pada head baru. Hasil CI adalah bukti CI, bukan sertifikasi seluruh kontrol sandbox security-audit.
