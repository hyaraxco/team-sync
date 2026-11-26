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

## Staff Search Index Privacy Cleanup

`StaffMemberProfile` search documents contain only the profile ID, code, name,
and email. Older indexed documents may still contain phone and identity-number
fields. A cleanup import can update existing documents by profile ID, replacing
their fields with the current searchable document and removing stale phone and
identity-number fields. This command is for an explicitly authorized,
non-production index only; it is not part of setup or deployment.

Before importing, explicitly select the intended non-production Scout index in
its environment configuration, verify its host and index name, and ensure its
credentials cannot access production. Never run this procedure against
production. Production cleanup requires separate explicit authorization and a
reviewed maintenance plan.

After verifying the target as non-production, run the import from `team-sync-be/`:

```bash
php artisan scout:import "App\Models\StaffMemberProfile"
```

This updates documents in the selected index and removes fields no longer
returned by `toSearchableArray()`. Do not target production without separate
authorization and a reviewed maintenance plan.

The repository's current test count in the archive is historical, not a result
for the current checkout.

## Dokumentasi Terkait

- [Aturan backend dan struktur layer](AGENTS.md)
- [Indeks dokumentasi](../docs/README.md)
- [Integrasi BE–FE](../docs/references/be-fe-integration-patterns.md)
- [Payroll dan QA](../docs/references/payroll.md)
- [Panduan attendance](../docs/references/attendance.md)
- [Security policy proyek](../SECURITY.md)
