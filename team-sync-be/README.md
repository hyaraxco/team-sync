# Team Sync Backend

Laravel 12 API untuk Team Sync HRIS. PHP 8.2+, Composer, MySQL, Redis,
dan Meilisearch sesuai konfigurasi `.env`. API aplikasi berada di `/api/v1`.

## Setup Lokal

Jalankan dari `team-sync-be/`:

```bash
composer install
cp .env.example .env
# Sesuaikan database dan layanan lokal sebelum migrasi.
php artisan key:generate
php artisan migrate
php artisan storage:link
bun install
composer dev
```

`composer dev` memerlukan Node/Bun dan menjalankan API server, queue listener
`default,meetings`, scheduler, logs, serta Vite **milik backend**. Vue SPA di
`../team-sync-fe/` tetap dijalankan terpisah; lihat [Frontend README](../team-sync-fe/README.md).
Seeder hanya dijalankan pada database development/test yang memang disiapkan
untuk data contoh, bukan pada database produksi.

Aset backend memakai Vite 8 dan Laravel Vite Plugin 3. Gunakan Node.js
`^20.19.0 || >=22.12.0` dan Bun dengan `bun.lock`; jangan membuat npm lockfile.
CI memeriksa `bun install --frozen-lockfile --ignore-scripts` dan `bun run build`
sebelum menjalankan tes PHP.

## Queue dan Scheduler

Notifikasi memakai queued jobs (`QUEUE_CONNECTION=database`). Tanpa worker,
`/api/v1/my-notifications` dapat terlihat kosong walaupun emitter sudah dipanggil.

- Pembuatan meeting mengantrekan `BroadcastMeetingJob` pada queue `meetings`.
- `meetings:send-reminders` dijadwalkan setiap menit untuk meeting yang dimulai
  dalam 15 menit; scheduler diperlukan agar reminder masuk antrean.
- Di production, jalankan worker di bawah process supervisor dan scheduler
  melalui cron `schedule:run` setiap menit.

Alternatif manual, masing-masing di terminal terpisah (tidak perlu jika sudah
memakai `composer dev`):

```bash
php artisan serve
php artisan queue:work --queue=default,meetings --tries=3 --timeout=600
php artisan schedule:work
```

Konfigurasi Docker dapat berbeda menurut checkout lokal. Periksa service yang
tersedia dengan `docker compose config --services` sebelum menyalakan worker;
jangan mengasumsikan service `queue` dan `scheduler` selalu tersedia.

## Pengujian dan Formatting

```bash
composer test
./vendor/bin/pint --test
```

`composer test` membersihkan config sebelum menjalankan Pest. Jumlah tes di
arsip adalah catatan historis, bukan hasil pengujian checkout saat ini.

## Dokumentasi Terkait

- [Aturan backend dan struktur layer](AGENTS.md)
- [Indeks dokumentasi](../docs/README.md)
- [Integrasi BE–FE](../docs/references/be-fe-integration-patterns.md)
- [Payroll dan QA](../docs/references/payroll.md)
- [Panduan attendance](../docs/references/attendance.md)
- [Security policy proyek](../SECURITY.md)
