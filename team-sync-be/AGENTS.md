# BACKEND KNOWLEDGE BASE (team-sync-be)

## OVERVIEW

Laravel 12 REST API. PHP 8.2+. Sanctum auth. Repository pattern with interface binding. Queued notifications via database driver. Indonesian HRIS domain (BPJS, PPh 21, attendance policies).

## STRUCTURE

```
app/
├── Console/              # Artisan commands
├── Constants/            # App-wide constants (CacheConstants, etc.)
├── DTOs/                 # 10+ data transfer objects (cross-layer)
│   └── Performance/      # Performance-specific DTOs
├── Enums/                # 20 enums (all fixed option sets)
├── Exports/              # Excel exports (Maatwebsite)
├── Helpers/              # Utility functions
├── Http/
│   ├── Controllers/      # 40 controllers (thin — delegate to services)
│   ├── Middleware/        # Custom: EnsureProjectMembership, PermissionMiddleware
│   ├── Requests/         # Form request validation
│   └── Resources/        # API resource transformers
├── Interfaces/           # 25 repository contracts
├── Jobs/                 # GeneratePayrollJob (queued, unique, 3 retries, 10min timeout)
├── Models/               # 53 Eloquent models (17 soft-deletable)
├── Notifications/        # 32 notification classes (all ShouldQueue)
│   └── Performance/      # Performance-specific notifications
├── Providers/            # Service providers (interface bindings)
├── Repositories/         # 25 repository implementations
└── Services/             # Business logic
    ├── Analytics/        # Snapshot generation, aggregation
    ├── Attendance/       # Check-in/out, policy enforcement, hybrid resolver
    ├── Payroll/          # Generation, tax calc, BPJS, reconciliation
    └── Performance/      # Reviews, goals, TOPSIS ranking

database/
├── factories/            # Model factories for testing
├── migrations/           # 88 migrations (NEVER modify old ones)
└── seeders/              # Role, permission, demo data, E2E seeders

tests/
├── Feature/              # Integration tests (endpoints, flows, permissions)
├── Unit/                 # Unit tests (services, DTOs, helpers, jobs)
└── Concerns/             # Test traits (ActivatesLicense)
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
| Payroll tax logic | `app/Services/Payroll/` | Indonesian tax: BPJS, PTKP, TER 2024 |
| Attendance policies | `app/Services/Attendance/` | HybridScheduleResolver, AttendanceClassifier |
| Performance/TOPSIS | `app/Services/Performance/` + `TopsisService.php` | Multi-criteria ranking |
| Excel exports | `app/Exports/` | Maatwebsite/Excel package |
| PDF exports | Uses `barryvdh/laravel-dompdf` | Payslip PDF generation |
| Search/indexing | Laravel Scout + Meilisearch | Config in `config/scout.php` |

## MODELS (53)

Key models by domain:

- **Staff**: User, StaffMemberProfile, JobInformation, BankInformation, EmergencyContact
- **Attendance**: Attendance, AttendanceCorrection, AttendancePeriod, AttendancePolicy, AttendancePolicyMismatch, HolidayCalendar, HybridScheduleOverride, HybridWorkSchedule
- **Leave**: LeaveRequest, LeaveEntitlement
- **Payroll**: Payroll, PayrollDetail, PayrollAdjustment, PayrollApproval, PayrollApprovalPolicy, PayrollSetting, PayrollSettingVersion, PayrollActivityLog, PayrollNotificationDelivery, PayrollReconciliationResolution, BpjsRate, PtkpAmount, TaxBracket
- **THR**: ThrPayroll, ThrPayrollDetail
- **Performance**: PerformanceReviewCycle, PerformanceReview, PerformanceReviewResponse, PerformanceReviewSection, PerformanceReviewTemplate, PerformanceGoal, PerformanceGoalUpdate, PerformanceFeedback, PerformanceOutcomeRule, ReviewerRule
- **Project**: Project, ProjectTask, ProjectTaskAttachment, ProjectTaskComment, ProjectTaskStatusLog, ProjectTeam
- **Team**: Team, TeamMember
- **Other**: Meeting, OvertimeRecord, License, Company, AnalyticsSnapshot

## CONVENTIONS

- **Layering**: Controller (thin) → Service (logic) → Repository (data) → Interface (contract). Never skip layers
- **DTOs** for passing structured data between layers — not arrays
- **FormRequest** classes for all validation — never validate in controllers
- **JsonResource** for all API responses — never return raw models
- **Queued notifications**: All notification classes use `ShouldQueue`. Queue worker MUST be running
- **Migrations**: Always reversible. 88 existing migrations — never modify old ones, create new
- **Factories + Seeders**: `database/factories/` and `database/seeders/` for test data
- **Formatting**: Laravel Pint + Prettier with `@prettier/plugin-php`
- **4-space indentation** everywhere

## ANTI-PATTERNS

- **NEVER** put business logic in controllers — extract to Services
- **NEVER** call Eloquent directly from controllers — go through Repository
- **NEVER** modify existing migrations — create new ones
- **NEVER** return raw model instances from API — use Resources
- **NEVER** skip queue worker when testing notifications
- **NEVER** commit `.env` files or `server.log`
- **DO NOT** add loose scripts to project root (`test-request.php` is legacy)
- **DO NOT** log to `server.log` in root — use `storage/logs/`
- **DO NOT** run `./vendor/bin/pest` without `config:clear` first (use `composer test`)

## COMMANDS

```bash
composer dev                               # Server + queue + scheduler + logs (PREFERRED)
composer test                              # Clears config, runs Pest (1478 tests)
./vendor/bin/pint                          # PHP formatting
php artisan migrate                        # Run migrations
php artisan migrate:rollback               # Rollback last batch
php artisan make:model Name -mfsr          # Model + migration + seeder + factory + resource
php artisan queue:work --queue=default,meetings --timeout=600  # Manual queue worker
php artisan schedule:work                  # Manual scheduler
docker compose up -d queue scheduler       # Queue + scheduler via Docker
```

## NOTES

- **`composer dev`** runs server + queue (default,meetings) + scheduler + logs + Vite concurrently — preferred over manual `artisan serve`
- **Telescope** installed for debugging (`config/telescope.php`)
- **`DEDUCTION_WARNING_RATIO = 0.5`** in PayrollRepository — triggers when deductions exceed 50%
- **`EnsureProjectMembership`** is the only custom middleware — guards project-scoped routes
- **Performance subdomain** has its own DTOs (`DTOs/Performance/`) and notifications (`Notifications/Performance/`)
- **Payroll reconciliation**: Exception detection before payment (missing bank, zero salary, abnormal deductions)
- **Payroll settings versioning**: `PayrollSetting` → `PayrollSettingVersion` (immutable versions)
- **Attendance periods**: `open → review → locked` lifecycle, tied to payroll generation
- **Feature flags**: `feature.enabled:{module}` middleware gates analytics and performance routes
- **GeneratePayrollJob**: queued, unique per salary_month (1hr lock), 3 retries, 10min timeout
- **Constants** (`app/Constants/CacheConstants.php`): `PAYROLL_BULK_INSERT_CHUNK_SIZE = 500`, `DEFAULT_PAGINATION_SIZE = 50`
- **Meilisearch + OrbStack**: Docker containers need `NO_PROXY=localhost,127.0.0.1,mysql,redis,meilisearch,phpmyadmin,*.orb.internal` to avoid proxy routing
- **E2E seeders**: `database/seeders/E2E/` — used by `e2e-prepare-be.sh` to reset state before Playwright runs
