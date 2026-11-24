# b0v: keputusan timing dan withholding untuk simulasi

Status: COMPLETED untuk slice keputusan simulasi pada 24 November 2026. Baseline: `0d5bc4b30f88f74259f6fd299af68e859caeecd2`. Review dan merge PR terpisah; status ini tidak menutup bead `team-sync-b0v` atau menyatakan gate produksi terpenuhi.

## Scope dan batas

Menetapkan asumsi simulasi skripsi untuk timing payroll, THR, riwayat withholding, alur pembayaran dan fakta penghentian kerja sebelum implementasi pajak pada `team-sync-62b`/`team-sync-d9d`. Tidak mengubah aplikasi, database atau formula pajak; tidak menambahkan modul penghasilan. `team-sync-v93` tetap memiliki integritas approval. Plan taxable-gross lama yang dilarang tidak dibaca atau disentuh.

Konfirmasi user melalui formulir 24 November 2026: konteks `simulation`, riwayat `synthetic_only`; user meminta jadwal dan sumber mengikuti contoh umum perusahaan Indonesia. Ini izin memilih kebijakan contoh, bukan bukti adanya perusahaan, pemilik Finance/HR, kontrak, atau riwayat pajak riil.

## Langkah dan acceptance

- [x] Baca bead b0v dan handoff G45lo; pastikan tidak ada pekerjaan paralel/duplikat.
- [x] Periksa source timing/history pada main; bedakan field aplikasi dari bukti pembayaran atau pemotongan aktual.
- [x] Peroleh konfirmasi simulasi dan riwayat sintetis dari user.
- [x] Verifikasi teks resmi yang relevan; tandai cakupan/amendemen yang belum terbukti.
- [x] Catat matriks lima area keputusan, kebijakan contoh, effective period, pemilik skenario dan batas produksi.
- [x] Verifikasi konsistensi dokumen dan siapkan handoff ke bead62b/d9d/v93; publikasi melalui PR docs-only, tanpa auto-merge.

## Hasil

Referensi permanen: [Keputusan simulasi payroll dan withholding](../../references/payroll-simulation-decisions.md). Publikasi: [PR 118](https://github.com/hyaraxco/team-sync/pull/118), menunggu persetujuan isi final.

Lima area keputusan telah dicatat: gaji tanggal 25, THR dengan janji/target H-10 terpisah dari batas H-7, refresh/reapproval sebelum transfer dan mark-paid setelah kredit berhasil, ledger sintetis dengan opening eksplisit, serta penghentian kerja terpisah dari kewajiban subjektif. Semua merupakan kebijakan skenario, bukan bukti kontrak atau ledger perusahaan riil. Referensi menjadi satu sumber matriks kebijakan; plan tidak menggandakannya.

## Bukti dan verifikasi

- Field source timing/history diperiksa pada baseline di atas; pembacaan PDF menggunakan nilai tersimpan sudah diperbaiki oleh PR 117.
- Teks resmi PMK 168/2023 Pasal 15/19, PP 36/2021 Pasal 53-57, raster Permenaker 6/2016 halaman 5-6, dan SE M/3/HK.04.00/III/2026 halaman 1-2 diperiksa. Audit semua amendemen/konsolidasi dan perbandingan seluruh naskah lama tidak dilakukan; batas ini ada di referensi.
- Pemeriksaan docs-only mencakup lima area matriks, path source, tautan resmi, batas data sintetis/produksi dan larangan unknown=0. Tidak ada executable yang diubah; suite aplikasi, build dan accessibility N/A.
- Tidak ada akses DB development, environment atau provisioning. Plan lama yang dilarang tidak dibaca atau disentuh.

## Handoff dan pekerjaan yang belum selesai

`team-sync-62b` memiliki agregasi dan output pajak, `team-sync-d9d` memiliki history/rekonsiliasi/refund, `team-sync-v93` memiliki integritas approval. Implementasi pajak belum dikerjakan dalam slice ini. `team-sync-b0v` tetap `in_progress`: kesiapan produksi memerlukan bukti perusahaan riil dan konfirmasi penanggung jawab sesuai acceptance bead. Penyelesaian dokumentasi simulasi tidak sama dengan penyelesaian tiket produksi atau persetujuan merge PR.

