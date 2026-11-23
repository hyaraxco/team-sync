# TEA-13: bukti sandbox coverage terisolasi

Laporan ini menyimpan bukti development historis, bukan status gate terkini. Hasil acceptance pada head integrasi terbaru, SHA/image/manifest dan checks merge dicatat di [PR103](https://github.com/hyaraxco/team-sync/pull/103). Angka kandidat di bawah tidak dipindahkan menjadi hasil head baru.

## Reproduksi

Jalankan dari checkout commit yang akan diuji. Provisioning online memerlukan persetujuan terpisah; runner offline tidak memasang dependency atau browser.

```sh
docker build --build-arg SOURCE_SHA="$(git rev-parse HEAD)" \
    -f scripts/sandbox/Dockerfile -t team-sync-tea13-toolchain:local .
python3 -I scripts/sandbox/probe.py --self-check
sh scripts/sandbox/run.sh team-sync-tea13-toolchain:local --phase all --seconds 1800
```

Command setiap fase tercatat dalam output JSONL runner. `all` melanjutkan fase independen setelah exit nonzero, tetapi membatalkan suite jika kontrol isolasi gagal. Exit total nonzero tidak menyatakan semua fase gagal.

Untuk acceptance offline tanpa recipe sementara, gunakan recipe yang sama dengan `--network=none --pull=false --build-arg TOOLCHAIN_IMAGE=<image-toolchain-lokal>` dan `SOURCE_SHA` commit final. Recipe membandingkan seluruh manifest/lock dependency terhadap toolchain yang dipakai ulang dan menghentikan build bila berbeda; provisioning stage tidak dijalankan pada jalur ini.

## Batas eksekusi

- Linux ARM64 pada OrbStack Docker Engine 29.8.2, cgroup v2. BE, FE dan browser satu namespace loopback khusus dan satu cgroup per fase; network `none`, tanpa port publish, mount host atau socket Docker.
- Source aplikasi, test dan konfigurasi dimuat langsung dari image `/source`; dependency dan toolchain `/opt` read-only. Hanya output/cache/DB/laporan di scratch. `.env` image kosong; aplikasi menerima `env -i` dengan allowlist nilai dummy.
- Suite: 2 CPU agregat, RAM 6 GiB, swap 0, PID 256; scratch tmpfs 4 GiB dengan 131072 inode; file per proses 256 MiB, open files 4096, core dump 0; watchdog dikonfigurasi per fase. Batas descriptor 1024 sebelumnya memblokir build accessibility dengan `EMFILE`; kenaikan ke 4096 tetap memerlukan proof penolakan descriptor dan acceptance pada SHA baru.
- Probe terpisah dengan batas lebih kecil membuktikan CPU throttling, OOM child, penolakan PID, ENOSPC byte/inode, file/open limits, namespace berbeda dan cleanup watchdog. Flag OOM hanya diizinkan untuk proof sukses yang sengaja memicu OOM, bukan suite.
- Kolektor PID 1 menghentikan dan reap descendant sebelum membaca laporan bounded dari directory descriptor, menolak symlink/hardlink/FIFO/oversize/XML entity. Artefak wajib yang hilang menghasilkan collection error. Ringkasan parsed dan hash disimpan, bukan DB, token, screenshot atau HTML mentah.

## Hasil development, bukan acceptance final

Run development sebelum cutover source read-only menghasilkan hasil berikut. Source saat itu disalin ke scratch writable; angka ini tidak membuktikan acceptance source immutable dan tidak mewakili commit kandidat final.

| Fase | Hasil development | Batas |
|---|---|---|
| BE | 1695 test, 7678 assertions, 0 failure/error, exit 0 | 1587 test ditandai warning karena `.env` tidak ada; kandidat berikut memakai file kosong |
| FE | 1156 test, 0 failure/error/skipped, exit 0 | Coverage 199 file aplikasi |
| Build BE | Exit 0 | Frozen dependency provisioning terpisah |
| Build FE | Exit 0 | Warning chunk besar tetap dilaporkan |
| Accessibility | 0 violations, 39 passes, 1 incomplete, exit 0 | Hanya redirect root ke login, Playwright + axe-core transitif; bukan Selenium CLI atau seluruh halaman authenticated |
| Role E2E | 48 passed, 5 failed, 8 tidak berjalan, exit 1 | Default RBAC asli, 61 kasus pada tiga spec navigation/button/payroll |

Failure role development: dua locator ambigu (`All Staff Members`, `Payroll Settings`); tombol `Add Project` untuk HR tidak ditemukan; dua assertion mengharapkan Finance dialihkan dari `/admin/projects` tetapi URL tetap di halaman tersebut. Temuan dicatat pada bead `team-sync-4ok`; tidak ada perubahan permission backend atau assertion untuk membuat hasil hijau. Delapan kasus serial yang tidak berjalan bukan bukti lulus.

## Denominator coverage

BE mencakup `app/**/*.php`; file di luar `app/` dikecualikan. Development Clover: 424 file, 14034/18491 statements dan 1127/1849 methods. Ini line/statement coverage Xdebug, bukan bukti branch/path coverage atau object isolation.

FE mencakup `src/**/*.{js,vue}`, termasuk file yang tidak terimpor; exclusions: `src/tests/**`, `src/**/*.test.js`, `src/**/*.spec.js`. Development report mencakup 199 file, 12 file tanpa statement ter-cover.

| Metrik FE development | Covered | Total | Persentase |
|---|---:|---:|---:|
| Lines | 8657 | 13521 | 64.02% |
| Statements | 9039 | 14312 | 63.15% |
| Functions | 1437 | 3226 | 44.54% |
| Branches | 5663 | 11023 | 51.37% |

## Provenance dan batas penyerahan

Baseline aplikasi awal: `8cfd4fa744d5446316b943e0ee7b978cc8bf83b5`. Kandidat cutover `3eedd545cf042301fc3ea83e62ac5503ba37b6da` gagal startup karena direktori dependency kosong dari konteks Docker menelan symlink. Build BE tetap exit 0; fase lainnya tidak diklaim sebagai kegagalan test aplikasi. Koreksi checkpoint `d259e838eda68dc00e319d1f0d766e88ac8c9950` menyelesaikan seluruh fase: BE 1695 test/7678 assertions tanpa failure/error/skipped, FE 1156 test tanpa failure/error/skipped, build BE/FE dan accessibility exit 0. Coverage BE 14033/18491 statements dari 424 file; FE sama dengan denominator tabel di atas. Image ID `sha256:8b84ca371a7305886a8704ce0fc82a649bebc36b0b66ec87506eae7d2221da75`, manifest source `302e83a9ddcef1b7a7ea2b0f777e2f8912ccb8ae3f127979cdd907a701ee2094`.

Pada run source read-only tersebut, role menghasilkan 3 assertion failures, 12 errors dan 46 skipped: screenshot mencoba menulis ke direktori artefak lokal yang ikut masuk image. Hasil ini bukan bukti 12 regresi aplikasi. Koreksi mengecualikan `playwright-artifacts` dari konteks dan mengganti direktori baked dengan symlink scratch. Run role sesudah koreksi selesai: 48 passed, 5 failed, 8 skipped, 0 errors, tidak ada `EROFS`; taxonomy sama dengan hasil development di atas, termasuk dua redirect Finance. Image koreksi `sha256:fa7702781dad8c2d5430c175abc78b24878b5248c5b00dd465ed822c6062d5d0` mengidentifikasi kandidat belum di-commit, bukan SHA PR final. Tidak ada container `tea13-*` tersisa setelah cleanup.

Branch telah direbase tanpa konflik ke baseline integrasi `b9de08f735eb5744c9318bdd8999164f9c1f6635`, termasuk perbaikan payslip dan Dockerfile frontend dari pihak lain. Hasil kandidat sebelumnya tidak diklaim mewakili SHA integrasi baru.

Runtime development teramati: PHP 8.4.28, Xdebug 3.5.3, Node 22.23.5/V8 12.4.254.21-node.57, Bun 1.3.13, Vitest dan provider V8 4.1.12, Playwright 1.59.1, Chromium 147.0.7727.0.

Container/scratch dihapus per fase. Tidak ada DB development di-mount atau direset; fixture menerima SQLite scratch kosong, migrate lalu seed terarah, tanpa `migrate:fresh` atau wrapper prep development. Image provisioning tetap lokal untuk reproduksi.

Checks wajib untuk scope: BE Pest + frozen Bun install/build; FE Vitest + build/accessibility serta security checks yang aktif. Gate terkini mengikuti SHA dan status di PR103; penutupan bead memerlukan acceptance, merge serta checks pasca-merge terverifikasi. Tidak ada klaim seluruh sistem aman. Empat gap endpoint analytics SQLite dari agent parallel adalah batas yang dilaporkan, bukan pengujian independen atau bukti yang diberikan persentase coverage.
