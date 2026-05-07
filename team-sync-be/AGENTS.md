# BACKEND KNOWLEDGE BASE (team-sync-be)

## OVERVIEW

Laravel 12 REST API. PHP 8.2+. Sanctum auth. Repository pattern with interface binding. Queued notifications via database driver.

## STRUCTURE

```
app/
├── Console/              # Artisan commands
├── Constants/            # App-wide constants
├── DTOs/                 # Data transfer objects (cross-layer)
│   └── Performance/      # Performance-specific DTOs
├── Enums/                # 20 enums (all fixed option sets)
├── Exports/              # Excel exports (Maatwebsite)
├── Helpers/              # Utility functions
├── Http/
│   ├── Controllers/      # 34 controllers (thin — delegate to services)
│   ├── Middleware/        # Custom: EnsureProjectMembership
│   ├── Requests/         # Form request validation
│   └── Resources/        # API resource transformers
├── Interfaces/           # 19 repository contracts
├── Jobs/                 # GeneratePayrollJob (queued)
├── Models/               # 44 Eloquent models
├── Notifications/        # 25+ notification classes (queued)
│   └── Performance/      # Performance-specific notifications
├── Providers/            # Service providers (interface bindings)
├── Repositories/         # 19 repository implementations
└── Services/             # Business logic
    ├── Analytics/        # Snapshot generation, aggregation
    ├── Attendance/       # Check-in/out, policy enforcement
    ├── Payroll/          # Generation, tax calc, BPJS
    └── Performance/      # Reviews, goals, TOPSIS ranking
```

## WHERE TO LOOK

| Task | Location | Notes |
|------|----------|-------|
| Add API endpoint | `routes/api.php` → Controller → Service → Repository | All routes under `/api/v1` |
| Add model | `app/Models/` + migration in `database/migrations/` | Always include rollback |
| Add notification | `app/Notifications/` | Must be queued; test with queue worker running |
| Validation rules | `app/Http/Requests/` | FormRequest classes |
| API response shape | `app/Http/Resources/` | JsonResource transformers |
| Permission checks | Route-level via `PermissionMiddleware::using()` | Spatie permissions |
| Payroll tax logic | `app/Services/Payroll/` | Indonesian tax: BPJS, PTKP, brackets |
| Excel exports | `app/Exports/` | Maatwebsite/Excel package |
| Search/indexing | Uses Laravel Scout + Meilisearch | Config in `config/scout.php` |

## CONVENTIONS

- **Layering**: Controller (thin) → Service (logic) → Repository (data) → Interface (contract). Never call Repository from Controller directly
- **DTOs** for passing structured data between layers — not arrays
- **FormRequest** classes for all validation — never validate in controllers
- **JsonResource** for all API responses — never return raw models
- **Queued notifications**: All notification classes use `ShouldQueue`. Queue worker MUST be running
- **Migrations**: Always reversible. 69 existing migrations — never modify old ones, create new
- **Factories + Seeders**: `database/factories/` and `database/seeders/` for test data
- **Config files**: `config/permission.php` (Spatie), `config/sanctum.php`, `config/scout.php` (Meilisearch), `config/telescope.php`
- **Formatting**: Prettier with `@prettier/plugin-php` + Laravel Pint

## ANTI-PATTERNS

- **NEVER** put business logic in controllers — extract to Services
- **NEVER** call Eloquent directly from controllers — go through Repository
- **NEVER** modify existing migrations — create new ones
- **NEVER** return raw model instances from API — use Resources
- **NEVER** skip queue worker when testing notifications
- **DO NOT** add loose scripts to project root (`test-request.php` is legacy)
- **DO NOT** log to `server.log` in root — use `storage/logs/`

## COMMANDS

```bash
php artisan serve                          # Dev server
php artisan queue:work --queue=default,meetings --timeout=600  # Queue worker incl. meeting jobs
php artisan schedule:work                  # Scheduler for meeting reminders
php artisan test                           # Pest tests
./vendor/bin/pest                          # Direct Pest
php artisan migrate                        # Run migrations
php artisan migrate:rollback               # Rollback last batch
php artisan make:model Name -mfsr          # Model + migration + seeder + factory + resource
php artisan make:controller NameController  # New controller
docker compose up -d queue scheduler       # Queue + scheduler via Docker
```

## NOTES

- **`composer.json` dev script** runs server + queue + logs + Vite concurrently via `concurrently`
- **Telescope** installed for debugging (`config/telescope.php`)
- **`DEDUCTION_WARNING_RATIO = 0.5`** in PayrollRepository — business rule, not arbitrary
- **`EnsureProjectMembership`** is the only custom middleware — guards project-scoped routes
- **Performance subdomain** has its own DTOs (`DTOs/Performance/`) and notifications (`Notifications/Performance/`)
