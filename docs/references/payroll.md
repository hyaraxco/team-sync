# Payroll Docs

## Dokumen Utama
- [Payroll Role E2E QA Runbook](../testing/be/payroll-role-e2e-qa.md)
  - Runbook manual QA, checklist guard per role, dan referensi automation payroll v1.
- [Keputusan simulasi payroll](payroll-simulation-decisions.md): asumsi dataset sintetis, bukan bukti kontrak atau ledger produksi.

## PPh 21 periode gabungan

Implementasi `team-sync-62b`, `team-sync-d9d`, dan `team-sync-v93` memakai satu ledger `payroll_tax_entries` untuk detail gaji dan THR. Pengakuan cash memakai tanggal lebih awal antara jatuh tempo dan kredit, dalam `Asia/Jakarta`. Gaji memakai `payday_day` versi pengaturan payroll; THR mempunyai `income_due_date` tersendiri, bukan `payment_deadline`. `planned_credit_date` adalah rencana, sedangkan `payment_date` dicatat saat konfirmasi kredit.

| Sumber | Perlakuan |
| --- | --- |
| `original_salary - deduction_amount`, overtime yang disetujui, `taxable_adjustment_amount` | Pendapatan cash kena pajak. Koreksi upah harus diklasifikasikan; edit langsung net tidak lagi menjadi sumber penghasilan. |
| BPJS Kesehatan, JKK, JKM pemberi kerja | Ditambahkan ke bruto pajak, bukan cash yang diterima pegawai. |
| JHT dan JP pemberi kerja | Tidak ditambahkan ke bruto pajak. |
| JHT dan JP pegawai | Dikurangkan pada rekonsiliasi periode terakhir; bukan pengurang bruto TER bulanan. |
| BPJS Kesehatan pegawai | Potongan take-home, bukan pengurang penghasilan neto fiskal. |
| `gross_thr_amount` | Digabung dengan sumber lain dalam masa pengakuannya; tidak lagi memakai selisih pajak tahunan terpisah. |

Dasar: [PMK 168/2023](https://jdih.kemenkeu.go.id/dok/pmk-168-tahun-2023), Pasal 5, 8, 10, 15, 19, dan 21. Tidak ada modul bonus/natura baru. Upah kontribusi BPJS tetap terpisah dari bruto pajak. Metadata menyimpan komponen, masa, sumber, pemotongan aktual terdahulu, reservasi draft, dan hash konteks.

Urutan alokasi memakai waktu pengakuan lalu tipe/ID sumber stabil, bukan urutan generate. TER dihitung dari bruto kumulatif sumber sebelumnya dalam masa yang sama, dikurangi withholding settled dan reservasi draft sebelumnya. Sumber lebih awal yang ditambahkan atau berubah menghitung ulang draft terdampak dan membatalkan approval lama. Transfer tidak boleh mendahului draft yang diakui lebih awal. Hasil persisted yang sama memasok net, API, PDF dan ekspor.

## BPJS PPU swasta

Payroll standar memakai upah pokok ditambah tunjangan tetap. `job_information.monthly_salary` tetap menjadi nominal gaji pada payroll, bukan bukti komposisi BPJS. Jangan masukkan lembur, tunjangan makan/transport berbasis kehadiran, bonus, atau koreksi kena pajak ke dasar iuran. Potongan absen tidak mengurangi dasar BPJS yang tercatat. JHT, JKK dan JKM memakai upah kontribusi penuh. JP memakai plafon pada periode kontribusi. Kesehatan menaikkan dasar ke UMK yang berlaku (UMP jika tidak ada UMK), lalu menerapkan plafon periode itu.

Dasar: [PP 44/2015 Pasal 16–19](https://www.bpjsketenagakerjaan.go.id/assets/uploads/peraturan/15122015_104557_PP%2044%20Tahun%202015.pdf), [PP 46/2015 Pasal 16–17](https://www.bpjsketenagakerjaan.go.id/assets/uploads/peraturan/15122015_104556_PP%2046%20Tahun%202015.pdf), [PP 45/2015 Pasal 28–29](https://www.bpjsketenagakerjaan.go.id/assets/uploads/peraturan/15122015_104556_PP%2045%20Tahun%202015.pdf), dan [Perpres 75/2019 Pasal 32–33](https://peraturan.go.id/files/ps75-2019.pdf). JKK dibayar perusahaan dan dipilih berdasarkan kelas I–V yang ditetapkan BPJS Ketenagakerjaan, bukan jabatan pegawai. Baris JKK lama tanpa kelas tetap ada untuk histori, tetapi tidak bisa dipakai menghitung payroll.

Jalankan migrasi dan seed tarif sebagai bagian dari rollout. Finance memverifikasi sendiri penetapan kelas yang berlaku pada bulan tersebut, klasifikasi pemberi kerja, pendaftaran tiap pekerja pada kelima program, dasar upah dari perjanjian, dan keputusan UMK/UMP menurut lokasi dan tahun. Simpan dokumen sumber di penyimpanan privat; gunakan nomor referensi internal yang tidak memuat kredensial atau identitas pribadi. Impor satu file per bulan menggunakan akses operator yang berwenang:

```sh
php artisan migrate
php artisan db:seed --class=BpjsRateSeeder
php artisan payroll:import-bpjs 2026-04 /absolute/private/verified-bpjs.json
```

Contoh struktur dengan angka dan referensi sintetis (jangan pakai untuk payroll nyata):

```json
{
    "employer_class": "ordinary",
    "jkk_class": 3,
    "jkk_assignment_ref": "SYNTHETIC-JKK",
    "employees": {
        "42": {
            "coverage": "private_salaried_ppu_all_five",
            "basic_wage": 5000000,
            "fixed_allowances": 1000000,
            "health_floor": 7000000,
            "floor_type": "umk",
            "region": "Synthetic jurisdiction",
            "floor_ref": "SYNTHETIC-DECREE"
        }
    }
}
```

Key `employees` adalah `staff_member_id`. Gunakan rupiah bulat dan isi `fixed_allowances: 0` hanya jika ketiadaan tunjangan tetap sudah dikonfirmasi. Aplikasi memeriksa format dan kelengkapan, bukan keaslian bukti. Readiness menolak input atau tarif efektif yang hilang. Impor ulang mengganti input bulan yang belum disetujui; hitung ulang dan review draft sebelum pembayaran. Draft approved lama yang belum punya perhitungan BPJS terverifikasi harus dibuka ulang dan disetujui kembali. Payroll paid tidak dihitung ulang.

[Perpres 59/2024 Pasal 32 ayat 4–5, sebagaimana dijelaskan DJSN](https://djsn.go.id/berita/press-release-djsn-terkait-perpres-nomor-59-tahun-2024-tentang-perubahan-ketiga-atas-perpres-nomor-82-tahun-2018-tentang-jaminan-kesehatan), mengecualikan usaha mikro/kecil dari lantai UMK/UMP dan mengatur penetapan lantai tersendiri. Kalkulator ini menolak klasifikasi tersebut sampai kelayakan dan lantai pengganti berotoritas dapat diverifikasi. Tidak ada fallback nol. Keringanan JKK industri padat karya [PP 7/2025](https://peraturan.bpk.go.id/Details/314624/pp-no-7-tahun-2025) sebagaimana diubah oleh [PP 36/2025](https://peraturan.bpk.go.id/Details/327872/pp-no-36-tahun-2025) bersyarat dan terbatas periode, bukan tarif normal. Jangan masukkan keringanan tersebut atau diskon BPU sebagai tarif PPU biasa. Rekompensasi pendanaan JKP menurut [PP 6/2025](https://jdih.kemnaker.go.id/asset/data_puu/2025pp006.pdf) bukan potongan baru pegawai; implementasi ini tidak menilai kelayakan atau menghitung alokasi dana JKP. Tidak ada data produksi yang diperiksa.

## Periode terakhir dan riwayat aktual

Periode terakhir adalah Desember atau bulan berakhirnya pekerjaan/kewajiban subjektif yang dinyatakan eksplisit. Berhenti bekerja tidak otomatis mengakhiri kewajiban subjektif Indonesia. Kalkulator memakai penghasilan aktual tahun/bagian tahun, biaya jabatan, iuran pensiun pegawai, donasi yang dapat dikurangkan, PTKP, tarif Pasal 17, dan pemotongan sebelumnya. Annualisasi hanya berlaku pada awal/akhir kewajiban subjektif di dalam tahun.

Saldo negatif menjadi refund pada net payroll/THR, dengan metadata tenggat akhir bulan berikutnya. Refund tidak melebihi withholding pegawai yang dapat dikembalikan; kelebihan pajak ditanggung pemerintah dicatat terpisah sebagai nonrefundable. Metadata tenggat bukan bukti refund telah ditransfer.

Riwayat yang hilang atau belum settled memblokir perhitungan akhir, bukan dianggap nol. Record legacy `paid` bukan bukti withholding aktual. Impor JSON harus dilakukan sebelum membuat draft aktif:

```sh
php artisan payroll:import-tax-history /absolute/path/verified-history.json
```

File berisi array objek `employee_id`, `year`, `provenance`, `employment_start_date`, `employment_end_date` (boleh null), `subjective_start_date`, `subjective_end_date` (boleh null), dan `history`. Key `history` berupa `YYYY-MM`; setiap bulan wajib mempunyai `gross_income`, `employee_pension_contributions`, `deductible_donations`, `withheld`, `government_borne_tax`, dan `provenance`. Nominal harus integer rupiah; hanya `withheld` boleh negatif untuk refund aktual. `withheld` adalah pemotongan yang didanai pegawai, tidak termasuk DTP; `government_borne_tax` adalah kredit terpisah, bukan subset `withheld`. Kalkulator mengurangkan kedua kredit tepat sekali. Bulan nol harus eksplisit dan bersumber. Jangan impor bulan yang juga ditangani sumber ledger internal. Impor atomik menolak duplikasi employee/tahun, overlap, draft aktif, dan penggantian tahun yang sudah settled.

Tanggal akhir kerja/kewajiban subjektif yang baru terverifikasi setelah pembayaran sebelumnya dapat diperbarui tanpa mengganti history:

```sh
php artisan payroll:import-tax-history /absolute/path/verified-history.json --update-profile
```

Gunakan history yang sama persis dengan impor tersimpan (atau array kosong jika seluruh riwayat berasal dari ledger internal). Pembaruan memakai transaksi yang menghitung ulang sumber belum dibayar dan membatalkan approval terdampak. Tanggal awal dan nominal history yang sudah menjadi dasar settlement tidak boleh diganti; perubahan tanggal akhir tidak boleh menyentuh masa settled. Provenance tambahan dipertahankan bersama sumber sebelumnya. Kegagalan perhitungan membatalkan seluruh pembaruan.
Replay profil dengan provenance terbaru yang sama tidak menambah teks audit, mengubah timestamp, atau membatalkan approval yang masih berlaku.

DTP nonzero pada sumber aktif ditolak, bukan dibebankan kepada pegawai. Kredit `government_borne_tax` tetap didukung pada history impor untuk rekonsiliasi akhir; input DTP sumber aktif memerlukan implementasi alokasi bulanan terlebih dahulu.

Koreksi upah yang bersumber dari perhitungan legacy pecahan dibulatkan ke rupiah terdekat sekali pada total koreksi pegawai sebelum disimpan sebagai `taxable_adjustment_amount` integer. Kolom nominal legacy lain tidak dimigrasikan ulang dalam perubahan ini.
`adjustment_total_amount` pada API detail/slip memakai total integer persisted yang diterapkan, bukan menjumlah ulang audit. Setiap item `amount_delta` tetap menyimpan angka sumber legacy.

Ekspor XLSX memuat upah lembur, koreksi kena pajak signed, serta selisih pembulatan terhadap net persisted. Gaji pokok ditambah lembur/koreksi, dikurangi potongan, lalu ditambah pembulatan harus sama dengan gaji bersih. Layout berubah menjadi 20 kolom; konsumen berbasis indeks kolom perlu menyesuaikan.

## Approval, recalculation, dan pembayaran

Approval menyimpan revisi serta snapshot policy/nominal. Semua langkah yang berlaku harus berstatus approved; rejected atau sebagian approved tidak cukup. Reopen, perubahan monetary, policy, threshold, atau konteks pajak membuat keputusan lama superseded tanpa menghapus auditnya. Edit detail dan transisi memakai transaksi/lock bersama.

Policy yang sudah dirujuk approval tidak dapat dihapus (HTTP 422 dan foreign key restrict). Nonaktifkan melalui `is_active=false` agar policy tidak dipakai untuk langkah berikutnya; approval saat ini dan revisi superseded tetap tersimpan. Policy yang belum pernah dirujuk tetap dapat dihapus.

```sh
php artisan payroll:recalculate-tax --month=2026-04 --dry-run
php artisan payroll:recalculate-tax --month=2026-04
```

Command hanya menerima pending/processing, menghitung pajak, BPJS dan net bersama, serta me-rollback seluruh perubahan pada dry-run. Payroll memerlukan versi pengaturan terverifikasi. Tepat sebelum mark-paid, sistem mengevaluasi tanggal kredit aktual dan freshness. Perubahan pengakuan/perhitungan mengembalikan draft untuk review/reapproval, bukan langsung membayar nominal baru. Sumber settled dan histori paid tidak ditulis ulang; koreksi berikutnya harus bersumber terpisah.

THR simulation hanya menampilkan entitlement bruto. Pajak/net memerlukan generated source IDs dan konteks periode lengkap; preview tidak menampilkan nilai belum dihitung sebagai nol.

## Batas verifikasi dan produksi

Fixture sintetis tidak memverifikasi kontrak, pembayaran bank, atau bukti potong perusahaan. Produksi membutuhkan rekonsiliasi ledger, fakta HR/kewajiban subjektif, dan penanggung jawab yang memverifikasi input. Tidak ada release/deploy otomatis. Smoke browser THR memakai SQLite; endpoint analytics lain melaporkan `DATE_FORMAT` yang tidak tersedia di SQLite, di luar jalur preview yang diperiksa.

## Arsip
- [Payroll Phase 3 Plan](../plans/archive/payroll-phase-3-plan.md) — Rencana historis reconciliation, readiness dashboard, settings versioning, dan operasi payroll; bukan backlog aktif.
- [Payroll × Attendance Spec](../plans/archive/payroll-attendance-spec.md) — Desain historis Attendance for Fair Payroll Phase 1; cocokkan dengan implementasi saat ini.
- [Payroll Phase 2 Backlog Plan](../plans/archive/2026-04-26-payroll-phase-2-backlog-plan.md) — ✅ Semua item selesai.
- [HRIS Patch Overview](../plans/archive/2026-04-26-hris-patch-overview.md) — ✅ Semua patch selesai.

## Cara Pakai
- Buka runbook QA jika ingin verifikasi flow payroll end-to-end untuk `manager`, `hr`, `finance`, dan `employee`.
- Buka arsip untuk latar belakang keputusan, bukan untuk menyimpulkan status pekerjaan saat ini.
