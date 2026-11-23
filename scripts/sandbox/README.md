# Sandbox coverage TEA-13

Provisioning memerlukan persetujuan download/dependency. Pengujian tidak memasang paket atau browser dan tidak memakai Docker Compose development.

```sh
# Online provisioning; konteks Docker allowlist tidak menyertakan env/data/cache lokal.
docker build --build-arg SOURCE_SHA="$(git rev-parse HEAD)" \
    -f scripts/sandbox/Dockerfile -t team-sync-tea13-toolchain:local .

# Offline source refresh memakai toolchain lokal yang sudah diprovision.
# Manifest/lock dependency wajib sama; provisioning stage tidak dijalankan.
docker build --network=none --pull=false \
    --build-arg TOOLCHAIN_IMAGE=team-sync-tea13-toolchain:local \
    --build-arg SOURCE_SHA="$(git rev-parse HEAD)" \
    -f scripts/sandbox/Dockerfile -t team-sync-tea13-acceptance:local .

# Host orchestration trusted. Image lokal dipatok ke ID immutable sebelum eksekusi.
python3 -I scripts/sandbox/probe.py --self-check
sh scripts/sandbox/run.sh team-sync-tea13-toolchain:local --phase all --seconds 1800
```

Pilihan fase: `be`, `fe`, `build-be`, `build-fe`, `a11y`, `roles`, atau `all`. Setiap fase memakai container dan scratch baru. Exit nonzero tetap dilaporkan per fase; runner melanjutkan fase independen. Kegagalan kontrol membatalkan seluruh suite.

## Kontrol

Container Linux ARM64 tanpa jaringan selain loopback namespace khusus, tanpa port publish, host mount, socket Docker, capability, atau privilege escalation. Source aplikasi/test/config dimuat langsung dari `/source` read-only; dependency `/opt` dan toolchain juga read-only. Hanya output Laravel/build, cache tool, bukti browser, DB dan laporan diarahkan ke `/scratch` melalui symlink image eksplisit. `.env` image kosong; tidak ada env host yang diteruskan ke aplikasi: wrapper menggunakan `env -i` dengan nilai dummy eksplisit.

Cache optimizer Vite (`node_modules/.vite`) dan cache konfigurasi (`.vite-temp`) memakai target scratch terpisah. Startup membuat direktori target dan menolak cache yang tidak resolve ke `/scratch/cache`; dependency tetap read-only saat source terbaru memicu optimasi ulang.

Seluruh proses BE/FE/browser berbagi satu cgroup: 2 CPU, RAM 6 GiB, swap 0, PID 256. Scratch tmpfs 4 GiB dan 131072 inode termasuk home/cache/temp/DB/output. Per-process file limit 256 MiB, open files 4096, core dump 0. Batas descriptor 1024 sebelumnya memblokir build Vite dengan `EMFILE`; 4096 tetap finite dan diuji sampai penolakan descriptor berikutnya. Host watchdog menghapus seluruh container saat batas waktu fase tercapai, termasuk descendant yang keluar dari process group. Log Docker dinonaktifkan; output host dibatasi 4 MiB per fase dan di-escape JSON.

Sebelum aplikasi berjalan, probe terpisah dengan batas kecil membuktikan CPU throttling agregat, OOM cgroup, penolakan PID, ENOSPC byte/inode, file/open limits, namespace terpisah, root read-only, allowlist env, dan watchdog cleanup. Fase suite memeriksa ulang nilai kontrol sebelum menjalankan kode aplikasi. Perangkat kernel `/dev` bukan penyimpanan regular file; mount writable di luar scratch diperiksa untuk UID aplikasi.

## Coverage dan fixture

- BE: Xdebug 3.5.3, scope PHPUnit `app/**/*.php`; laporan Clover menyertakan denominator. File di luar `app/` bukan bagian coverage aplikasi BE. `config:clear` dijalankan sebelum suite.
- FE: Vitest dan `@vitest/coverage-v8` tepat 4.1.12. Include `src/**/*.{js,vue}` memasukkan file yang tidak terimpor; exclude `src/tests/**`, `src/**/*.test.js`, `src/**/*.spec.js`. Laporan menyertakan statements/functions/branches/lines beserta total, covered, dan persentase. Tidak ada ambang coverage baru.
- Role E2E memakai spec asli navigation/button/payroll, headless Chromium Linux dan default RBAC backend, tanpa mock izin. Seeder hanya menerima file SQLite kosong di scratch dalam `testing`, kemudian migrate dan seed terarah. Tidak memanggil `migrate:fresh`, wrapper reset, atau seeder reset payroll. Data yang tidak diminta fixture tidak dipalsukan untuk membuat assertion lulus.
- Accessibility menjalankan axe-core yang sudah menjadi dependency transitif melalui browser Playwright pada build root URL. Ini bukan Selenium CLI dan tidak membuktikan aksesibilitas seluruh halaman authenticated; incomplete checks dicatat terpisah.

## Laporan dan cleanup

Runner mengeluarkan JSONL provenance, command/limits, proof, output, status, dan cleanup. Kolektor PID 1 menunggu suite lalu membunuh dan reap seluruh descendant sebelum membaca laporan melalui directory descriptor tepercaya, `O_NOFOLLOW`, `O_NONBLOCK`, regular-file/single-link dan batas ukuran. XML DTD/entity ditolak. Hanya ringkasan parsed bounded `TEA13_REPORT:` yang keluar, bukan recursive copy scratch, screenshot, raw HTML, token, atau DB. Hash laporan mentah dicatat untuk identitas, bukan bukti file mentah disimpan.

Container dan scratch dihapus setelah setiap fase. Image/toolchain provisioning dapat dipakai ulang secara lokal sampai bukti verifikasi disimpan; hapus image tiket setelah selesai bila tidak diperlukan. Jangan menghapus image/service user lain. Commit seluruh kandidat sebelum build acceptance final, lalu gunakan SHA commit tersebut sebagai label. Manifest `/opt/source-sha256.txt` dan image ID mengikat isi yang diuji; build development berlabel baseline dengan perubahan belum di-commit bukan bukti acceptance commit final. Tidak ada klaim sandbox sepenuhnya aman atau coverage menjamin tidak ada bug.
