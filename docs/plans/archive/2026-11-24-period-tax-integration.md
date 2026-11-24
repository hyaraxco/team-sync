# Pajak periode gabungan dan rekonsiliasi terakhir

Status: COMPLETED (implementasi dan acceptance lokal); PR/CI serta persetujuan merge masih gate terpisah. Baseline: `dc24691365b75e56a3542d5508427218df06ff7f`. Beads: `team-sync-62b`, `team-sync-d9d`, `team-sync-v93`. Integrasi dimiliki sesi utama; keputusan simulasi mengikuti `docs/references/payroll-simulation-decisions.md`, bukan bukti produksi.

## Scope dan kontrak

- Agregasi pendapatan aplikasi: gaji setelah pengurangan unpaid/absence, overtime disetujui, koreksi upah, THR, serta BPJS pemberi kerja yang termasuk bruto menurut PMK168. JHT/JP pemberi kerja bukan bruto; BPJS kontribusi tetap memakai upah tersendiri. Tidak menambahkan modul bonus/natura yang belum ada.
- Waktu pengakuan cash adalah peristiwa lebih awal due/kredit; janji THR H-10 berbeda dari deadline H-7 dan tanggal evaluasi hari raya. Histori paid tidak ditulis ulang.
- Histori eksplisit mempunyai sumber/provenance, coverage periode, penghasilan/deduksi, pemotongan/refund aktual dan fakta akhir kerja/kewajiban subjektif terpisah. Unknown tidak diisi nol. Input sintetis disediakan melalui batas validasi; produksi tetap membutuhkan verifikasi manusia.
- Kalkulator periode terakhir memakai penghasilan aktual tahun/bagian tahun, biaya jabatan, iuran pegawai, PTKP, tarif Pasal17 dan pemotongan sebelumnya. Annualisasi hanya untuk awal/akhir kewajiban subjektif. Refund signed dan tenggat akhir bulan berikutnya dipertahankan, termasuk pengecualian DTP.
- Approval berlaku hanya terhadap income/history/policy yang dihitung saat ini; perubahan memerlukan refresh/reapproval, seluruh langkah eksplisit approved sebelum paid. Koreksi setelah paid menjadi transaksi terpisah.
- Alokasi draft diurutkan menurut `recognized_at = min(due_at, credit_at jika diketahui)`, lalu tipe/ID sumber stabil sebagai tie-breaker; key ini disimpan, bukan memakai ID ledger/urutan generate. Setiap sumber memperoleh TER kumulatif gross sumber settled dan draft sebelumnya dikurangi withholding settled dan reservasi draft sebelumnya. Insert sumber terdahulu, perubahan tanggal atau nominal menghitung ulang sumber terdampak dan membatalkan approvalnya. Transfer urutan terbalik diblokir sampai draft yang diakui lebih dahulu diselesaikan atau direalokasi dengan reapproval.
- Net payroll/THR, metadata, API/PDF/export dan command recalculation harus memakai hasil persisted yang sama.

## Kepemilikan file

- Integrator: worktree `/tmp/team-sync-period-tax`; `PayrollRepository.php` sepenuhnya, integrasi THR, command recalculation, resource/output, dokumentasi dan tes integrasi.
- OpenCode G937p: worktree `/tmp/team-sync-tax-calculator`; `TaxCalculationService.php` dan tes kalkulator `TaxFinalPeriodTest.php` saja. Kontrak `calculateFinalPeriodPph21(array $facts, ?string $ptkpStatus, bool $hasNpwp): array`.
- OpenCode G4kzg: worktree `/tmp/team-sync-tax-approval`; service approval baru, model/migrasi approval dan tes freshness saja; tidak boleh mengedit `PayrollRepository.php`.
- OpenCode G0iu3: worktree `/tmp/team-sync-tax-history`; service ledger, model/migrasi history, command import dan tes history saja. Semua writer terpisah; integrator menggabungkan hanya slice yang dilaporkan selesai.

## Acceptance dan verifikasi

1. Fixture independen TER boundary, employer benefits/exempt pension, THR/gaji kedua urutan pembayaran, withholding terdahulu, due berbeda bulan kredit.
2. December, joiner/leaver resident, perubahan kewajiban subjektif, refund/DTP/deadline, history hilang dan repeat generation.
3. Regresi approval rejected/reopen/threshold/monetary edits serta deteksi perubahan sebelum transfer.
4. Isolated SQLite/sandbox offline dari toolchain lokal; tidak memakai DB development, `.env` host, provisioning, atau retained worktree lain. Jalankan suite terdampak dan smoke CLI aktual sesudah integrasi; kegagalan ditangani, bukan disembunyikan.
5. Update dokumentasi, archive plan sesudah acceptance, checkpoint terverifikasi, PR dan checks SHA terbaru. Tidak merge tanpa persetujuan isi final; tidak release/deploy atau tutup bead sebelum merge terverifikasi.

## Hasil dan batas verifikasi

- Sandbox offline, filesystem source read-only, network none, SQLite disposable: 423 tes backend lulus, 2.977 assertions. Scope: Feature Payroll/THR/recalculate command dan Unit Payroll/THR.
- Vitest THR management: 3 tes lulus. Build frontend Vite lulus. Browser Chromium login Finance dan API simulasi THR nyata lulus pada viewport desktop/mobile; preview menyebut gross-only tanpa tax/net palsu.
- Regresi meliputi withholding settled vs reservasi, urutan generate, deteksi perubahan credit-date, refund/final employment, policy snapshot dan approval rejected/partial/reopen. Penguatan tes import sesudah suite utama: 10 tes history lulus, 35 assertions. Kasus dua employee berbeda menulis baris pertama lalu gagal di dalam transaksi karena draft aktif employee kedua; tidak ada row history tersisa. Kasus duplicate preflight dan penolakan overwrite tetap terpisah.
- Pembacaan PDF tetap memakai nilai persisted. Tidak mengulang audit layout PDF yang sudah merged pada PR117. Tidak memakai development DB atau `.env` host.
- SQLite browser login memicu error endpoint analytics `DATE_FORMAT`; preview THR tetap lulus. Tidak menyatakan analytics SQLite kompatibel.
- Full Pest, full Vitest, frozen backend asset build dan accessibility dijalankan gate CI pada SHA PR terbaru; hasil belum tersedia ketika plan diarsipkan. Tidak merge, menutup bead, release atau deploy tanpa gate yang berlaku.
- Review susulan: `taxable_adjustment_amount` memakai bigint/cast integer; total koreksi legacy dibulatkan sekali, sumber audit pecahan tetap utuh. Impor menolak nominal pecahan; kontrak `withheld` employee-funded dan DTP kredit terpisah dijelaskan. `writeResult` eager-load versi pengaturan. Tes terarah history/adjustment/settlement/final-period: 21 lulus, 95 assertions. CI pada head sebelumnya lulus; perubahan ini memerlukan CI/review ulang.

