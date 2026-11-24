# TOPSIS Evidence & Release Reconciliation — team-sync-lzo

**Status:** HISTORICAL RECORD, reconciled 2026-11-24. Beads `team-sync-lzo` is closed; subsequent cycle-window work merged in PR #104 under `team-sync-6g5` (also closed). Residual readiness consistency and boundary-verification work is tracked separately as `team-sync-c94`. Tracker closure is not certification that every evidence gap below was independently resolved.
**Historical branch:** test/team-sync-lzo-complete-evidence

## Reconciliation as of 2026-11-24

The continuation sections below retain what was known during their original execution; their pending statements are historical, not the current tracker state.

- PR #92 merged as `026b972`. PR #93 subsequently merged as `41a06724f92bf02d0390341eced22af96eb4c4ba`.
- Independently observed PR #93 post-merge BE run [45118829577](https://github.com/hyaraxco/team-sync/actions/runs/45118829577) passed 1687 tests / 7661 assertions in 102.67 seconds, including config clear and frozen Bun install/build. The endpoint artifact upload step passed; archive contents were not independently inspected in this review.
- The later `lzo` owner report records ranking browser 2/2 and other performance flows passing. Preserve this as reported historical browser evidence, not a current-c94 rerun. Its role/payroll/project failures were separate follow-ups, not automatically harmless harness defects.
- PR #104 merged as `73463f1f42373d09f92fd0f8d419ee8fdffe74dc`. Independently inspected post-merge BE run [45193628215](https://github.com/hyaraxco/team-sync/actions/runs/45193628215) passed 1700 tests / 7705 assertions in 77.12 seconds, config clear, frozen asset install/build and endpoint upload. The merge fixed cycle query bounds and separately made same-due-date goal completion on time. The latter can change recalculated goal scores from 80 to 100 for a single otherwise completed goal; it did not rewrite history.
- PR #103 sandbox integration merged as `452a7c48b2450a984765753d645ac6355791ca49`, with observed successful post-merge BE/FE runs [45206288248](https://github.com/hyaraxco/team-sync/actions/runs/45206288248) and [45206288286](https://github.com/hyaraxco/team-sync/actions/runs/45206288286). Owner [exact-head acceptance](https://github.com/hyaraxco/team-sync/pull/103#issuecomment-7105691845) reports BE 1703 tests / 7714 assertions, FE 1167 and roles 61/61 on `541456791e7fe13353a16081d2d3a16dd73d8945`. Its login-only accessibility has one incomplete; it does not certify authenticated pages or all object isolation.
- `team-sync-c94` owns readiness/ranking date-rule consistency, cap-query-sensitive boundary regressions and evidence reconciliation. Its [ongoing plan](2026-11-24-topsis-readiness-followup.md) records observed isolated SQLite before/after: exactly two same-day readiness failures before the fix, then 1722 passed / 7878 assertions and one skip after, plus a backend asset build pass. Isolated MySQL, observed denominator mutations and a current-source ranking browser run remain unexecuted; SQLite and historical browser results do not replace them.
- Strix secrets, scan execution and SARIF enforcement remain outside this proof. No release or deployment is implied by these merges.

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
