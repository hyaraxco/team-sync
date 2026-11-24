# TEA-13: Sandbox coverage BE/FE/RBAC

Status: COMPLETED. Acceptance enam fase pada head final lulus; PR103 merged dan checks BE/FE pasca-merge lulus. Catatan kandidat di bawah adalah bukti historis, bukan hasil head final.
Bead: team-sync-3ij
Baseline: 8cfd4fa744d5446316b943e0ee7b978cc8bf83b5
Branch: chore/team-sync-3ij-isolated-coverage

## Acceptance

- Buktikan network eksternal off, namespace loopback terisolasi dari host, env allowlist dummy, source/toolchain read-only, scratch-only writes, CPU/RAM/PID/file/disk/wall limits.
- Instrumentasi PHP coverage dan Vitest V8 matching version. Denominator FE mencakup seluruh src JS/Vue aplikasi, termasuk file tidak terimpor; exclusions test/fixture dijelaskan.
- Laporkan full BE, FE, build BE/FE, accessibility, dan role E2E secara terpisah, dengan command, SHA, runtime, limits, pass/fail/blocked dan denominator coverage.
- Fixture hanya di scratch disposable. Jangan gunakan wrapper E2E yang menjalankan migrate:fresh, DB development, paid history, secret host, socket Docker di container, atau install otomatis saat pengujian offline.
- Promosikan hanya laporan nonrahasia terpilih setelah proses berhenti, lalu cleanup. PR scoped, author/contributor hyaraxco tanpa co-author tambahan; merge memerlukan approval final dan checks SHA terbaru. Bead tetap terbuka sampai checks pasca-merge diverifikasi.

## Inventaris teramati

- OrbStack Docker Engine 29.8.2 Linux ARM64, kernel 7.2.12, cgroup v2, overlayfs; VM 8 CPU dan sekitar 8 GiB RAM. Lima 2.3.0 tersedia tanpa instance.
- Image lokal: oven/bun:1.3.13 dan mysql/mysql-server:8.0. Belum ada image PHP/browser Linux.
- Host PHP 8.4.28 tanpa Xdebug/PCOV, Bun 1.3.13, Node 26.13.1. Composer vendor tersedia; Pest 4.4.5, PHPUnit 12.5.16, php-code-coverage 12.5.6.
- Vitest terpasang 4.1.12, Playwright 1.59.1, tanpa @vitest/coverage-v8. Binary esbuild/Rollup dan Chromium cache lokal hanya macOS ARM64.
- Probe OS tanpa aplikasi: network namespace hanya lo tanpa route, read-only root menolak write, tmpfs 4 MiB terisi penuh dan menolak write berikutnya; wall timeout menghentikan sleep. cpu.max, memory.max, memory.swap.max dan pids.max terbaca. Enforcement agregat dan jalur aplikasi belum diverifikasi.
- Container probe sudah terhapus. Tidak ada suite aplikasi dijalankan selama inventaris.

## Keputusan user

User menyetujui provisioning image/toolchain Linux ARM64, PHP 8.4 + Xdebug, Node/Bun, browser Linux, dependency lockfile; @vitest/coverage-v8 tepat 4.1.12 dan pin Vitest 4.1.12. Tidak ada instalasi host atau perubahan DB development. Provisioning online terpisah dari pengujian offline. Antislop during dipilih untuk sesi ini. Skill ponytail tidak tersedia; prinsip perubahan minimum mengikuti instruksi sesi.

## Pelaksanaan

1. Buat image toolchain disposable dari manifest/lockfile terpilih, tanpa .env, .git, storage, cache aplikasi, atau data development; provisioning dependency tanpa lifecycle aplikasi.
2. Terapkan provider coverage dan denominator eksplisit; tambahkan runner sandbox fail-closed serta bukti kontrol runnable.
3. Jalankan BE/FE/browser dalam satu container aggregate cgroup dan namespace loopback khusus, tanpa published port atau network eksternal. Gunakan env -i dan root/toolchain read-only. Temp/cache/home/output hanya pada scratch bounded; proses non-root tanpa capability.
4. Siapkan DB SQLite kosong di scratch dengan migrate dan seed fixture terarah. Jangan menjalankan e2e-prepare-be.sh atau DemoDataSeeder untuk reset live.
5. Jalankan kontrol terlebih dahulu, kemudian suite terpisah. Catat kegagalan sebagai fail/blocked, bukan lulus atau bukti suite gagal dari runtime yang hilang. Tidak memperbaiki bug domain di luar tiket.
6. Pilih laporan bounded nonrahasia, cleanup, checkpoint dan PR. Arsip plan hanya setelah acceptance terpenuhi; catat batas verifikasi.

## Referensi API

- Docker network none: https://docs.docker.com/engine/network/drivers/none/
- Docker resource constraints: https://docs.docker.com/engine/containers/resource_constraints/
- Vitest 4.1.12 coverage: https://github.com/vitest-dev/vitest/blob/v4.1.12/docs/guide/coverage.md
- Vitest 4.1.12 install prompt opt-out: VITEST_SKIP_INSTALL_CHECKS=1.

## Hasil

Self-check host dan kontrol Linux lulus: CPU throttling agregat, OOM child cgroup, penolakan PID, ENOSPC byte/inode, file/open limits, env allowlist, namespace berbeda dan watchdog cleanup. Full run kandidat development selesai: BE 1695 test tanpa failure/error tetapi warning `.env` hilang; FE denominator 199 file, build BE/FE dan accessibility login exit 0 (0 violations, 1 incomplete). Role E2E 48 passed, 5 failed, 8 skipped; dua locator ambigu serta ekspektasi HR/Finance tidak cocok hasil default backend, dicatat pada team-sync-4ok tanpa mengubah permission/assertion.

Source runtime semula berupa salinan writable di scratch; bukti tersebut bukan acceptance batas source read-only. Kandidat 3eedd545cf042301fc3ea83e62ac5503ba37b6da melakukan cutover ke `/source` image immutable, output/cache/DB melalui symlink scratch, dan `.env` kosong. Smoke build BE lulus. Acceptance startup menemukan direktori dependency kosong dari Docker context menelan symlink; koreksi mengganti hanya direktori dependency baked sebelum membuat symlink. Verifikasi terarah dilakukan sebelum commit kandidat dan full acceptance berikutnya. Persetujuan provisioning bukan approval final PR/merge. Plan on_going tetap ignored sampai acceptance terpenuhi.

Koreksi dependency dan output screenshot terverifikasi: role source read-only 48 passed, 5 assertion failures, 8 skipped, 0 errors; tidak ada EROFS atau container tiket tersisa. Review statis host/kolektor/fixture tidak menemukan blocker tambahan. Branch direbase lalu disquash menjadi satu commit author/contributor hyaraxco tanpa co-author tambahan. PR draft: https://github.com/hyaraxco/team-sync/pull/103, head `050ee9d9dc473baa621d140170e0f1d6fcf3baa0`. Full acceptance seluruh fase serta checks CI berjalan pada head ini; angka kandidat lama bukan klaim hasil head baru. GitGuardian Security Checks head terbaru lulus; BE/FE CI masih berjalan. Approval final/merge/penutupan bead belum dilakukan.

BE pada head PR final selesai 1697 test, 7694 assertions, 0 failure/error/skipped; Clover 14054/18510 statements dari 424 file. CI BE/FE/GitGuardian head yang sama lulus. Rangkaian lokal terinterupsi sebelum FE memiliki terminal status; induk dihentikan dan container dibersihkan. Percobaan fase terpisah tertahan sebelum membuat container karena `docker image inspect` timeout 15 detik; `docker version` juga timeout 20 detik. Tidak dilakukan restart/reset daemon. Bukti head dan blocker: https://github.com/hyaraxco/team-sync/pull/103#issuecomment-7098946980. Bead berstatus blocked dan PR tetap draft. Lanjutkan fase tersisa secara serial setelah Q1 selesai; jangan menganggap hasil parsial sebagai passed atau menutup tiket sebelum approval, merge serta checks pasca-merge.

Docker kembali merespons `/_ping`; Q1 diselesaikan dari observasi langsung, tanpa restart/reset runtime. Pada head lama `050ee9d9dc473baa621d140170e0f1d6fcf3baa0`, fase serial FE selesai 1157 test/148 file, build BE/FE exit 0. Coverage FE 199 file: lines 8659/13521, statements 9041/14312, functions 1437/3226, branches 5663/11023; exclusions tetap kode test. Accessibility tertahan sebelum browser oleh `EMFILE` saat build Vite pada batas 1024 descriptor. Role selesai 48 passed/5 failed/8 skipped/0 errors, exit 1 tanpa OOM/watchdog; seluruh container tiket dibersihkan. Tidak mengubah assertion role atau permission produksi.

Koreksi minimum tersquash lokal menjadi `780cffe62b5f30b54b077e45e709ac0a13bdf8b7`: descriptor soft/hard 4096, static proof memeriksa nilai tersebut dan pressure proof membuka sampai `EMFILE`; batas CPU/RAM/PID/disk/network tetap. Self-check host konstruksi/watchdog lulus. Build image kandidat memakai network none dan cache provisioning yang sudah tersedia. Seluruh acceptance dan CI harus cocok dengan SHA baru sebelum approval/merge; hasil head lama tidak dipindahkan menjadi klaim SHA baru. Bukti runtime final disimpan dalam komentar PR agar tidak mengubah manifest source sesudah run. Plan ini tetap lokal/ignored sampai gate selesai.

Image koreksi selesai `sha256:4276f23dbd53717cc630fb71e59c7e55e52dcd03f558c7c0d43d100b3a696a4d`. Accessibility SHA `780cffe62b5f30b54b077e45e709ac0a13bdf8b7` exit 0: 0 violations, 39 passes, 1 incomplete, hanya root redirect login. Proof Linux termasuk EMFILE 4096 dan watchdog lulus; BE coverage baru berjalan serial. Fetch origin memverifikasi `origin/main`, parent HEAD, dan merge-base semuanya `b9d37ecff5ee212c62a41b7ed99988cd94a96731`; baseline lama `b9de08f…` bukan baseline head sekarang dan tidak memerlukan rebase ulang. CI wajib tetap harus cocok SHA terbaru. Ringkasan tersimpan di komentar https://github.com/hyaraxco/team-sync/pull/103#issuecomment-7103571511.

Acceptance final SHA `780cffe62b5f30b54b077e45e709ac0a13bdf8b7` selesai serial pada image koreksi; keenam collector mencatat manifest `b240f08a795725dd6f70b07a5902143a837456d1e61d338f976bf9f9e181bf2e`, tanpa collection errors. BE: 1696 passed/1 error/7691 assertions, collision tanggal fixture attendance (team-sync-d1u); Clover statements 14052/18510, methods 1126/1849, 424 file app/. FE: 1157 tests/148 test files passed, coverage 199 file src JS/Vue termasuk yang tidak terimpor; lines 8657/13521, statements 9039/14312, functions 1437/3226, branches 5663/11023. Build BE/FE exit 0. Accessibility login: 0 violations/39 passes/1 incomplete. Role: 48 passed/5 failed/8 skipped/0 errors; dua locator ambigu serta ekspektasi HR/Finance, tetap scope team-sync-4ok. Tidak mengubah permission/assertion atau mengulang BE merah sampai hijau.

Tidak ada OOM/watchdog pada fase aplikasi; pressure proof OOM child dan watchdog timeout adalah kontrol yang disengaja. Cleanup terverifikasi: tidak ada container tea13 tersisa. CI BE/FE/GitGuardian head ini lulus, tetapi tidak menghapus hasil lokal merah. Bukti final dan batas verifikasi: https://github.com/hyaraxco/team-sync/pull/103#issuecomment-7103953905. Tidak ada merge, release/deploy, atau penutupan bead. Plan tetap on_going karena gate belum terpenuhi.

## Acceptance integrasi latest main

Baseline `8beebf8063463dac6e15c0a9eb6f7179ea5bb256` mencakup fixture deterministik PR107 dan koreksi role PR109. Head final tersquash `2aa6e3b679f183aee3677cdc28813bb0222a8018` menambahkan cache optimizer Vite scratch permanen beserta assertion startup. Image offline `sha256:77394525ae154ab070a2e98fa4d4938520aebd46f8b7bbea9914ad2e53ba1798`, UID1000:1000, lockfile dependency sama dengan toolchain tersedia. Acceptance enam fase serial dan CI head tersebut harus selesai sebelum merge; hasil belum diklaim lulus. Tidak ada rewrite setelah acceptance; bukti final dipublikasikan di PR tanpa mengubah source yang diuji.

Kandidat `2aa6e3b` hanya menyelesaikan BE (1703 test/7714 assertion lulus), bukan acceptance final. Concern recipe sementara diselesaikan dengan stage `TOOLCHAIN_IMAGE` pada Dockerfile committed, validasi enam manifest/lock, dan framing laporan historis. Final baru `541456791e7fe13353a16081d2d3a16dd73d8945` dibangun offline dari recipe committed, image `sha256:d1634fb107b9bcce2640010694b788ee5e2195a979eefeaca2970acf8a49c11d` UID1000. CI BE/FE/security head tersebut lulus; enam fase offline masih berjalan serial dan tetap wajib sebelum merge.

## Penutupan terverifikasi

Head acceptance `541456791e7fe13353a16081d2d3a16dd73d8945` di atas latest main `8beebf8063463dac6e15c0a9eb6f7179ea5bb256`. Keenam fase serial lulus pada image `sha256:d1634fb107b9bcce2640010694b788ee5e2195a979eefeaca2970acf8a49c11d`, manifest source `e1adaa59fdadd64ac4d56c644661ceaeb09d0154094189f6be558af067886f87`: BE 1703 test/7714 assertion, FE 1167 test, build BE/FE exit 0, role E2E 61/61 tanpa failure/error/skipped. Axe root/login: 0 violations, 39 passes, 1 incomplete. Semua collector tanpa error; aplikasi tanpa OOM/watchdog; tidak ada container tiket tersisa.

Coverage BE: statements 14049/18504, methods 1128/1850, 424 file app PHP; excludes di luar app/. FE: statements 9041/14312 (63.17%), lines 8658/13521 (64.03%), functions 1438/3226 (44.57%), branches 5662/11023 (51.36%); 199 file termasuk tidak terimpor, excludes src/tests/** dan file *.test.js/*.spec.js. Bukti command/runtime/limits/hash lengkap: https://github.com/hyaraxco/team-sync/pull/103#issuecomment-7105691845.

User mengotorisasi autopilot finish PR103 dan verifikasi latest main sebelum merge. PR https://github.com/hyaraxco/team-sync/pull/103 merged pada https://github.com/hyaraxco/team-sync/commit/452a7c48b2450a984765753d645ac6355791ca49 oleh hyaraxco. Author hyaraxco; trailer coauthor juga akun hyaraxco, bukan contributor tambahan. Checks pasca-merge lulus: BE https://github.com/hyaraxco/team-sync/actions/runs/45206288248 dan FE https://github.com/hyaraxco/team-sync/actions/runs/45206288286. Main lokal fast-forward; branch tiket lokal/remote dihapus, worktree dan data user dipertahankan.

Batas: axe hanya root/login dan memiliki 1 incomplete; coverage bukan bukti exhaustive object isolation. Empat gap analytics SQLite laporan agent parallel tetap di luar scope dan tidak diklaim diverifikasi/diperbaiki. Tidak ada reset DB development, akses secret host, release atau deploy. Plan ini arsip lokal ignored; bukti final publik berada di PR, tanpa mengubah source sesudah acceptance.
