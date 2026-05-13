# Team Sync HRIS

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP 8.2+](https://img.shields.io/badge/PHP-8.2+-777BB4.svg)](https://php.net/)
[![Vue 3.5+](https://img.shields.io/badge/Vue-3.5+-4FC08D.svg)](https://vuejs.org/)
[![Laravel 12](https://img.shields.io/badge/Laravel-12-FF2D20.svg)](https://laravel.com/)

**Team Sync** adalah sistem HRIS (Human Resource Information System) modern untuk mengelola sumber daya manusia di lingkungan kerja Indonesia. Sistem ini mencakup manajemen staf, kehadiran, penggajian, cuti, proyek, ulasan kinerja, dan analitik dengan dukungan penuh untuk konteks peraturan Indonesia (BPJS, PTKP, PPh 21).

## 🚀 Fitur Utama

### 👥 Manajemen Staf
- Profil karyawan lengkap dengan data pribadi dan pekerjaan
- Informasi bank dan kontak darurat
- Riwayat pekerjaan dan promosi
- Manajemen dokumen karyawan

### 📊 Kehadiran & Absensi
- Clock-in/clock-out dengan geolocation
- Pengaturan jam kerja fleksibel (9:00-17:00)
- Toleransi keterlambatan: 30 menit (full-time), 20 menit (part-time)
- Status kehadiran: present, late, absent, half_day, sick_leave, annual_leave
- Dukungan kerja remote, hybrid, dan office

### 💰 Sistem Penggajian
- Perhitungan PPh 21 metode TER 2024
- BPJS lengkap: JHT, JKK, JKM, JP, Kesehatan
- Deduction warning (peringatan jika potongan >50%)
- Siklus status: processing → pending → approved → paid
- Ekspor slip gaji ke PDF/Excel

### 🏖️ Manajemen Cuti
- Jenis cuti: annual, sick, personal, emergency, maternity, paternity, compassionate
- Workflow approval: pending → approved/rejected
- Saldo cuti tahunan otomatis
- Kalender cuti terintegrasi

### 📈 Kinerja & Proyek
- Template ulasan kinerja yang dapat dikustomisasi
- Sistem penilaian TOPSIS (multi-criteria decision analysis)
- Manajemen proyek dengan status: draft → planning → active → on_hold → completed
- Task management dengan status: todo → in_progress → review → done

### 📊 Analitik & Laporan
- Dashboard real-time untuk HR, manager, dan karyawan
- Laporan kehadiran, produktivitas, dan penggajian
- Ekspor data ke Excel dan PDF
- Chart interaktif dengan ApexCharts

## 🏗️ Arsitektur Teknis

### Backend (`team-sync-be/`)
- **Framework**: Laravel 12 (PHP 8.2+)
- **Database**: MySQL (dev/prod), SQLite :memory: (testing)
- **Authentication**: Laravel Sanctum (SPA cookie-based)
- **Authorization**: Spatie Laravel Permission (role-based)
- **Search**: Laravel Scout + Meilisearch
- **Queue**: Database driver dengan worker
- **Cache**: Redis
- **Testing**: Pest PHP
- **Formatting**: Laravel Pint + Prettier PHP

### Frontend (`team-sync-fe/`)
- **Framework**: Vue 3.5 (Composition API dengan `<script setup>`)
- **Build Tool**: Vite 7
- **State Management**: Pinia (21 stores berdasarkan domain)
- **Routing**: Vue Router 4
- **Styling**: Tailwind CSS 3
- **HTTP Client**: Axios (hanya dari stores)
- **Date/Time**: Luxon
- **Charts**: ApexCharts (terdaftar global sebagai `VueApexCharts`)
- **Icons**: Lucide Vue Next
- **Testing**: Vitest + Playwright E2E
- **Package Manager**: Bun (bukan npm)

## 📁 Struktur Proyek

```
team-sync/
├── team-sync-be/          # Laravel 12 API backend
│   ├── app/
│   │   ├── Services/      # Business logic (Payroll/, Attendance/, etc.)
│   │   ├── Repositories/  # Data access layer
│   │   ├── Models/        # 53 Eloquent models
│   │   ├── Notifications/ # 32 queued notification classes
│   │   └── DTOs/          # Data Transfer Objects
│   ├── routes/api.php     # API routes (/api/v1/*)
│   └── database/migrations # 88 migrations
├── team-sync-fe/          # Vue 3 SPA frontend
│   ├── src/
│   │   ├── views/         # Admin vs staff-member views
│   │   ├── stores/        # 21 Pinia stores (satu per domain)
│   │   ├── components/    # Common/admin/staff-member components
│   │   └── router/        # Modular routing
│   └── e2e/               # Playwright E2E tests
├── docs/                  # Documentation
└── .github/workflows/     # CI/CD pipelines
```

## 🛠️ Instalasi & Setup

### Prasyarat
- PHP 8.2+ dengan ekstensi: mbstring, pdo_mysql, tokenizer, xml, ctype, json
- MySQL 8.0+ atau MariaDB 10.4+
- Node.js 20.19+ atau 22.12+
- Bun (package manager)
- Composer 2.5+
- Redis 7.0+

### 1. Clone Repository
```bash
git clone https://github.com/hyaraxco/team-sync.git
cd team-sync
```

### 2. Setup Backend
```bash
cd team-sync-be
cp .env.example .env
# Edit .env dengan konfigurasi database dan Redis

composer install
php artisan key:generate
php artisan migrate --seed
php artisan storage:link
```

### 3. Setup Frontend
```bash
cd ../team-sync-fe
cp .env.example .env
# Edit .env dengan URL API backend

bun install
```

### 4. Jalankan Queue Worker & Scheduler
```bash
# Di terminal terpisah (backend directory)
php artisan queue:work --queue=default,meetings
php artisan schedule:work
```

### 5. Jalankan Development Servers
```bash
# Backend (port 8000)
cd team-sync-be
composer dev

# Frontend (port 5173)
cd ../team-sync-fe
bun run dev
```

Akses aplikasi di `http://localhost:5173`

## 🧪 Testing

### Backend Tests (Pest)
```bash
cd team-sync-be
composer test
```

### Frontend Unit Tests (Vitest)
```bash
cd team-sync-fe
bun run test
```

### E2E Tests (Playwright)
```bash
cd team-sync-fe
bun run e2e
```

## 👥 Role & Permission

### Hierarchy (Least-Privilege)
- **Staff**: Self-service only (attendance, leave, payroll, goals)
- **Manager**: Team-scoped (team pulse, projects, performance reviews)
- **HR**: Workforce-wide (attendance, leave, performance, NO payroll)
- **Finance**: Payroll/THR owner (generate, approve, process, settings)
- **Superadmin**: All permissions

### Middleware Protection
- `auth:sanctum` untuk semua API routes
- `role:` middleware untuk role-based access
- `EnsureProjectMembership` untuk project-scoped routes

## 🔧 Development Commands

### Backend
```bash
cd team-sync-be
composer dev                    # Server + queue + scheduler + logs
php artisan migrate             # Run migrations
php artisan migrate:rollback    # Rollback last migration
./vendor/bin/pint               # PHP code formatting
php artisan tinker              # Interactive PHP shell
```

### Frontend
```bash
cd team-sync-fe
bun run dev                     # Development server
bun run build                   # Production build
bun run preview                 # Preview production build
bun run lint                    # ESLint checking
```

### Cache Management
```bash
cd team-sync-be
php artisan config:clear        # Clear config cache
php artisan route:clear         # Clear route cache
php artisan cache:clear         # Clear application cache
php artisan optimize:clear      # Clear all caches
```

## 📊 Konfigurasi Indonesia

### Currency & Formatting
- **Mata uang**: IDR (tanpa desimal)
- **Format**: `Rp 10.000.000` (gunakan `number_format($value, 0, ',', '.')`)
- **Tanggal**: `Y-m-d` (API), `Y-m-d H:i:s` (datetime), `Y-m` (payroll month)

### Timezone
- Default: WIB (Asia/Jakarta)
- Configurable per-company
- Timestamps disimpan UTC, dievaluasi dalam timezone perusahaan

### BPJS Rates (Database-driven)
| Komponen | Karyawan | Perusahaan | Cap |
|----------|----------|------------|-----|
| JHT | 2% | 3.7% | Tidak ada |
| JKK | - | 0.24% | Tidak ada |
| JKM | - | 0.30% | Tidak ada |
| JP | 1% | 2% | Rp 10.042.300 |
| Kesehatan | 1% | 4% | Rp 12.000.000 |

### PPh 21
- Metode: TER 2024 (Jan-Nov), annualized di Desember
- Surcharge 20% jika tidak ada NPWP
- `JABATAN_RATE = 0.05`, `JABATAN_MAX_MONTHLY = 500_000`

## 🚀 Deployment

### Environment Variables
Pastikan variabel berikut diatur di production:

**Backend (.env):**
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_HOST=localhost
DB_DATABASE=team_sync
DB_USERNAME=username
DB_PASSWORD=password

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

QUEUE_CONNECTION=database
SESSION_DRIVER=redis
CACHE_DRIVER=redis

MEILISEARCH_HOST=http://127.0.0.1:7700
MEILISEARCH_KEY=masterKey
```

**Frontend (.env):**
```env
VITE_API_BASE_URL=https://your-domain.com/api/v1
VITE_APP_NAME="Team Sync HRIS"
```

### Production Build
```bash
# Backend
cd team-sync-be
composer install --no-dev --optimize-autoloader
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Frontend
cd team-sync-fe
bun run build
```

### Supervisor Configuration
Buat file `/etc/supervisor/conf.d/team-sync.conf`:
```ini
[program:team-sync-queue]
command=php /var/www/team-sync/team-sync-be/artisan queue:work --sleep=3 --tries=3 --queue=default,meetings
directory=/var/www/team-sync/team-sync-be
autostart=true
autorestart=true
user=www-data
numprocs=1
redirect_stderr=true
stdout_logfile=/var/log/team-sync-queue.log
```

## 📚 Dokumentasi Tambahan

- [AGENTS.md](./AGENTS.md) - Panduan lengkap untuk AI agents
- [team-sync-be/AGENTS.md](./team-sync-be/AGENTS.md) - Backend conventions
- [team-sync-fe/AGENTS.md](./team-sync-fe/AGENTS.md) - Frontend conventions
- [docs/](./docs/) - Plans, references, testing documentation

## 🤝 Kontribusi

1. Fork repository
2. Buat feature branch (`git checkout -b feature/amazing-feature`)
3. Commit changes (`git commit -m 'Add amazing feature'`)
4. Push ke branch (`git push origin feature/amazing-feature`)
5. Buat Pull Request

### Coding Standards
- **PHP**: 4-space indentation, Laravel Pint, PSR-12
- **JavaScript**: ESLint dengan konfigurasi Vue 3
- **Vue**: Composition API dengan `<script setup>` only
- **Git**: Conventional commits

## 📄 License

Proyek ini dilisensikan di bawah MIT License - lihat file [LICENSE](LICENSE) untuk detail.

## 🆘 Support

- **Issues**: [GitHub Issues](https://github.com/hyaraxco/team-sync/issues)
- **Discussions**: [GitHub Discussions](https://github.com/hyaraxco/team-sync/discussions)
- **Email**: support@hyarax.co

---

**Team Sync HRIS** © 2026 hyaraxco. Dibangun dengan ❤️ untuk HR Indonesia.