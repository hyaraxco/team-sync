# Team Sync HRIS

Team Sync adalah HRIS untuk mengelola staf, kehadiran, penggajian, cuti, proyek, ulasan kinerja, dan analitik. Fitur penggajian mencakup BPJS, PTKP, dan perhitungan PPh 21 untuk kebutuhan tenaga kerja Indonesia.

## Fitur

### Manajemen staf

- Profil pribadi, informasi pekerjaan, rekening bank, dan kontak darurat.
- Riwayat pekerjaan, promosi, dan dokumen karyawan.

### Kehadiran dan absensi

- Clock-in/clock-out dengan geolocation.
- Pengaturan jam kerja, dengan contoh jadwal 09:00 hingga 17:00.
- Toleransi keterlambatan 30 menit untuk full-time dan 20 menit untuk part-time.
- Status `present`, `late`, `absent`, `half_day`, `sick_leave`, dan `annual_leave`.
- Lokasi kerja `office`, `remote`, dan `hybrid`.

### Penggajian

- PPh 21 dengan metode TER 2024 dan perhitungan tahunan pada Desember.
- Komponen BPJS: JHT, JKK, JKM, JP, dan Kesehatan.
- Peringatan ketika potongan melebihi 50%.
- Siklus payroll: `processing → pending → approved → paid`.
- Ekspor slip gaji ke PDF/Excel.

### Cuti

- Cuti tahunan, sakit, pribadi, darurat, melahirkan, ayah, dan kedukaan.
- Persetujuan dengan status `pending → approved/rejected`.
- Saldo cuti tahunan dan kalender cuti.

### Kinerja dan proyek

- Template ulasan kinerja yang dapat disesuaikan.
- TOPSIS untuk pemeringkatan karyawan berdasarkan beberapa kriteria.
- Status proyek meliputi `draft`, `planning`, `active`, `on_hold`, dan `completed`.
- Alur tugas utama: `todo → in_progress → review → done`.

### Analitik dan laporan

- Dashboard sesuai akses HR, manager, finance, dan staf.
- Laporan kehadiran, produktivitas, dan penggajian.
- Ekspor Excel/PDF dan grafik ApexCharts.

## Arsitektur

Backend Laravel menyediakan API di `/api/v1`; frontend Vue mengaksesnya melalui Pinia stores.

### Backend: `team-sync-be/`

- Laravel 12, Laravel Sanctum, dan Spatie Laravel Permission.
- MySQL untuk development/production; SQLite in-memory untuk pengujian.
- Laravel Scout dan Meilisearch untuk pencarian; Redis untuk cache.
- Database queue untuk pekerjaan latar belakang dan notifikasi.
- Pest untuk pengujian; Laravel Pint dan Prettier PHP untuk formatting.
- Alur aplikasi: Controller → Service → Repository → Interface.

### Frontend: `team-sync-fe/`

- Vue 3.5 dengan Composition API dan `<script setup>`, JavaScript, serta Vite 7.
- Pinia untuk state per domain; Vue Router 4 untuk routing.
- Tailwind CSS 3, Lucide Vue Next, dan ApexCharts.
- Axios hanya dipanggil dari stores; Luxon menangani tanggal dan waktu.
- Vitest untuk unit/component tests; Playwright untuk E2E.
- Bun sebagai package manager; pertahankan `bun.lock`, jangan tambahkan lockfile npm.

### Struktur direktori

```text
team-sync/
├── team-sync-be/
│   ├── app/Services/        # Logika bisnis
│   ├── app/Repositories/    # Akses data
│   ├── app/Models/          # Model Eloquent
│   ├── app/Notifications/   # Notifikasi melalui queue
│   ├── app/DTOs/            # Data lintas layer
│   ├── routes/api.php       # Route API
│   └── database/migrations/
├── team-sync-fe/
│   ├── src/views/           # Admin dan staff-member
│   ├── src/stores/          # State dan pemanggilan API
│   ├── src/components/
│   ├── src/router/
│   └── e2e/
├── docs/                   # Panduan, referensi, dan riwayat rencana
└── .github/workflows/      # Workflow CI
```

## Instalasi lokal

### Prasyarat

- PHP **8.4.x**, minimal **8.4.1**, dengan patch keamanan terbaru pada cabang tersebut.
- Ekstensi PHP sesuai kebutuhan Composer, termasuk `pdo_mysql` untuk MySQL dan `pdo_sqlite` untuk pengujian SQLite.
- Composer 2.5+, Bun, dan Node.js `^20.19.0 || >=22.12.0`.
- MySQL 8.0+ atau MariaDB 10.4+, Redis 7.0+, serta Meilisearch bila memakai pencarian tersebut.

`composer.json` mendeklarasikan PHP `^8.2`, tetapi lockfile saat ini lebih ketat: beberapa paket Symfony memerlukan PHP `>=8.4.1`, sedangkan PhpSpreadsheet membatasi PHP `<8.5.0`. Batas ini berlaku pada dependensi terkunci, bukan hanya Pest. Setelah instalasi, periksa ekstensi dan runtime dengan `composer check-platform-reqs`.

Contoh perintah memakai Bash. Setelah clone, setiap blok berikut dimulai dari root repository; blok setup mengembalikan direktori ke root di akhir.

### Clone

```bash
git clone https://github.com/hyaraxco/team-sync.git
cd team-sync
```

### Backend

Gunakan database development. Sebelum migrasi dan seeding, sesuaikan `DB_*`, koneksi Redis, dan URL aplikasi pada `.env`. Seeder berisi data contoh dan tidak untuk database produksi.

```bash
cd team-sync-be
cp .env.example .env
# Sesuaikan .env sebelum melanjutkan.
composer install
composer check-platform-reqs
bun install
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
cd ..
```

Dependensi Bun backend terpisah dari frontend. `composer dev` memerlukannya untuk menjalankan Vite backend dan process runner.

### Frontend

Atur `VITE_API_BASE_URL` pada `.env`; nilai contoh adalah `http://localhost:8000/api/v1`. Gunakan hostname yang konsisten untuk frontend dan backend saat menguji autentikasi.

```bash
cd team-sync-fe
cp .env.example .env
bun install
cd ..
```

### Server development

Buka dua terminal, masing-masing dari root repository.

Terminal backend:

```bash
cd team-sync-be
composer dev
```

Terminal frontend:

```bash
cd team-sync-fe
bun run dev
```

API berjalan di `http://localhost:8000`; buka Vue SPA di `http://localhost:5173`.

`composer dev` menjalankan API, queue listener `default,meetings`, scheduler, logs, dan Vite **milik backend**. Jangan menambahkan worker kedua tanpa kebutuhan. Jika log watcher Pail tidak didukung platform yang digunakan, jalankan proses secara manual sesuai [Backend README](team-sync-be/README.md#queue-dan-scheduler).

## Pengujian

Jalankan setiap blok dari root repository. Jumlah tes yang tercatat dalam plan lama adalah bukti historis, bukan hasil checkout saat ini.

### Backend

```bash
cd team-sync-be
composer test
cd ..
```

`composer test` membersihkan config sebelum menjalankan Pest.

### Frontend

```bash
cd team-sync-fe
bun run test
cd ..
```

### E2E

**Peringatan: `bun run e2e` menjalankan persiapan backend yang memanggil `php artisan migrate:fresh`, lalu melakukan seeding. Perintah ini mereset database backend yang dikonfigurasi. Gunakan database test khusus, jangan database kerja atau produksi.**

Persiapan juga memerlukan Docker/Compose dengan service `mysql`, `redis`, dan `web`. Periksa konfigurasi checkout sebelum menjalankannya. Ikuti [E2E runbook](team-sync-fe/e2e/README.md) untuk isolasi database, URL, dan prasyarat browser.

Setelah lingkungan test terisolasi siap:

```bash
cd team-sync-fe
bun run e2e:install
bun run e2e
cd ..
```

## Role dan permission

| Role | Lingkup akses |
|---|---|
| Staff | Self-service untuk kehadiran, cuti, payroll, dan goals milik sendiri |
| Manager | Tim, proyek, dan ulasan kinerja dalam lingkupnya |
| HR | Staf, kehadiran, cuti, dan kinerja lintas perusahaan; tanpa operasi payroll |
| Finance | Pengelolaan payroll/THR: generate, approve, process, dan settings |
| Superadmin | Seluruh permission |

Route terproteksi memakai `auth:sanctum`; route login, pemulihan akun, dan first-boot setup memiliki akses publik sesuai kontrolnya. Permission Spatie, policy, dan `EnsureProjectMembership` mengatur tindakan serta akses proyek. Lihat [aturan domain dan akses](AGENTS.md) sebelum mengubah pembagian role.

## Perintah development

Jalankan perintah dari direktori pada kolom pertama.

| Direktori | Perintah | Kegunaan |
|---|---|---|
| `team-sync-be/` | `php artisan migrate` | Menjalankan migrasi |
| `team-sync-be/` | `php artisan migrate:rollback` | Membatalkan batch migrasi terakhir; periksa dampak data terlebih dahulu |
| `team-sync-be/` | `./vendor/bin/pint` | Formatting PHP |
| `team-sync-be/` | `php artisan tinker` | Console Laravel |
| `team-sync-fe/` | `bun run build` | Build frontend |
| `team-sync-fe/` | `bun run preview` | Preview hasil build |
| `team-sync-fe/` | `bun run lint` | Pemeriksaan ESLint |

Setelah mengubah `.env`, bersihkan config. Gunakan pembersihan route/cache hanya sesuai kebutuhan:

```bash
cd team-sync-be
php artisan config:clear
php artisan route:clear
php artisan cache:clear
php artisan optimize:clear
cd ..
```

### Strix lokal

Strix adalah tooling keamanan opsional, bukan prasyarat menjalankan HRIS. Ikuti [panduan resmi Strix](https://docs.strix.ai/quickstart) untuk instalasi, konfigurasi model, dan runtime container. [Catatan setup lokal GPT 6.1 SOL](docs/plans/archive/2026-11-04-local-strix-gpt-6-1-sol.md) mencatat konfigurasi satu mesin; profil privat dan launcher yang di-ignore tidak tersedia otomatis pada clone baru.

Simpan API key di luar Git. Strix memasang target direktori lokal sebagai mount yang dapat ditulis: gunakan checkout sementara terisolasi tanpa kredensial. Keberhasilan koneksi model bukan bukti pemindaian selesai. Periksa status akhir dan laporan; batas biaya hanya estimasi sampai pricing model/proxy terverifikasi, dan budget stop bukan hasil audit lengkap.

## Konteks Indonesia

### Format dan zona waktu

- Nominal IDR menggunakan bilangan bulat tanpa desimal: `Rp 10.000.000`, dengan pola `'Rp '.number_format($value, 0, ',', '.')`.
- Tanggal memakai `Y-m-d`, datetime `Y-m-d H:i:s`, dan input bulan payroll `Y-m`.
- Zona waktu mengikuti konfigurasi perusahaan, default `Asia/Jakarta` (WIB). Timestamp disimpan UTC; kebijakan kehadiran memakai zona waktu perusahaan.

### BPJS

Rate dibaca dari database. Nilai referensi yang dicatat proyek:

| Komponen | Karyawan | Perusahaan | Batas upah |
|---|---|---|---|
| JHT | 2% | 3.7% | Tanpa batas |
| JKK | - | 0.24% | Tanpa batas |
| JKM | - | 0.30% | Tanpa batas |
| JP | 1% | 2% | Rp 10.042.300 |
| Kesehatan | 1% | 4% | Rp 12.000.000 |

Angka ini bukan jaminan kepatuhan untuk periode berjalan. Verifikasi rate dan batas yang berlaku sebelum memproses payroll.

### PPh 21

- TER 2024 untuk Januari hingga November, dengan perhitungan tahunan pada Desember.
- Surcharge 20% bila NPWP tidak tersedia, sesuai aturan yang dicatat proyek.
- Konstanta `JABATAN_RATE = 0.05` dan `JABATAN_MAX_MONTHLY = 500_000`.

Lihat [referensi payroll](docs/references/payroll.md) dan verifikasi aturan periode berjalan sebelum penggunaan operasional.

## Deployment

Bagian ini adalah panduan konfigurasi, bukan bukti kesiapan produksi. Gunakan runtime yang memenuhi lockfile dan isi `.env` sebelum membangun cache konfigurasi. Simpan kredensial di luar Git; pertahankan `APP_KEY` pada instalasi yang sudah memiliki data.

### Environment

Mulai dari `.env.example` dan sesuaikan host, database, domain autentikasi, serta koneksi layanan. Contoh pengaturan backend berikut belum siap pakai: isi `APP_KEY`, `DB_USERNAME`, `DB_PASSWORD`, dan `MEILISEARCH_KEY` dengan nilai deployment yang benar.

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com
APP_KEY=

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=team_sync
DB_USERNAME=
DB_PASSWORD=

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

QUEUE_CONNECTION=database
SESSION_DRIVER=redis
CACHE_STORE=redis

MEILISEARCH_HOST=http://127.0.0.1:7700
MEILISEARCH_KEY=
```

Frontend:

```env
VITE_API_BASE_URL=https://your-domain.com/api/v1
VITE_APP_NAME="Team Sync HRIS"
```

### Build

Kedua blok dimulai dari root repository dan mengembalikannya di akhir.

Backend:

```bash
cd team-sync-be
composer install --no-dev --optimize-autoloader
composer check-platform-reqs --no-dev
php artisan config:cache
php artisan route:cache
php artisan view:cache
cd ..
```

Frontend:

```bash
cd team-sync-fe
bun install
bun run build
cd ..
```

### Queue dan scheduler

Jalankan worker melalui process supervisor. Contoh `/etc/supervisor/conf.d/team-sync.conf`; sesuaikan path dan user dengan server:

```ini
[program:team-sync-queue]
command=php /var/www/team-sync/team-sync-be/artisan queue:work --sleep=3 --tries=3 --timeout=600 --queue=default,meetings
directory=/var/www/team-sync/team-sync-be
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/team-sync-queue.log
```

Aktifkan scheduler melalui cron `php artisan schedule:run` setiap menit dari direktori backend. Worker queue tidak menggantikan scheduler; reminder meeting memerlukan keduanya. Rincian tersedia di [Backend README](team-sync-be/README.md#queue-dan-scheduler).

## Dokumentasi

- [Indeks dokumentasi](docs/README.md): panduan domain, pengujian, dan status arsip.
- [Backend README](team-sync-be/README.md): setup API, queue, scheduler, dan pengujian.
- [Frontend README](team-sync-fe/README.md): setup SPA dan tooling frontend.
- [Aturan proyek](AGENTS.md), [backend](team-sync-be/AGENTS.md), dan [frontend](team-sync-fe/AGENTS.md): konvensi kontribusi serta batas arsitektur.

## Kontribusi

1. Fork repository dan buat branch dari `main`.
2. Ikuti konvensi proyek, jalankan pemeriksaan yang relevan, dan catat hasilnya.
3. Buat commit dengan Conventional Commits, lalu ajukan pull request.

Gunakan indentasi empat spasi, Laravel Pint untuk PHP, ESLint untuk JavaScript, serta Composition API dengan `<script setup>` untuk Vue. Pemanggilan API tetap berada di Pinia stores.

## Lisensi

Metadata proyek menyebut MIT License. File lisensi root belum tersedia; [teks MIT](https://opensource.org/licenses/MIT) bukan pengganti file lisensi proyek.

## Dukungan

- [GitHub Issues](https://github.com/hyaraxco/team-sync/issues)
- [GitHub Discussions](https://github.com/hyaraxco/team-sync/discussions)
- Email: support@hyarax.co

© 2026 hyaraxco.
