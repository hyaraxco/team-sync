# 1. SQL Column Fixes & Frontend Design Plan

## Goal
Resolve `Internal Server Error: Unknown column` for `ph21_amount` and `gross_salary` in `AnalyticsRepository.php`. Both errors trigger 500s on the Payroll Analytics endpoints limiting the Finance dashboard. Apply `frontend-design` principles to elevate the Dashboard UI while adhering to the previously established design systems.

## Proposed Changes

### 1. Backend (`team-sync-be`)

> [!CAUTION]
> TDD cycle will be strictly followed for these fixes.

We will replace the occurrences of the incorrect column names:
- **`ph21_amount`** should be **`pph21_amount`** (matching `2026_04_14_100300_add_tax_bpjs_to_payroll_details_table.php`).
- **`gross_salary`** should be **`original_salary`** (matching the `payroll_details` base column).

#### [MODIFY] `app/Repositories/AnalyticsRepository.php`
- Replace `ph21_amount` with `pph21_amount` at L235, L1023, L1072.
- Replace `gross_salary` with `original_salary` everywhere inside the CASE statements around L1506-1510.

#### [MODIFY] `app/Console/Commands/SeedEmployeeIdentityAndGeneratePayrollCommand.php`
- Replace `ph21_amount` with `pph21_amount` in the command seeder logic.

### 2. Frontend (`team-sync-fe`)

> [!NOTE]
> We will upgrade the visual fidelity of the Finance dashboard following `frontend-design` using existing brand colors and aesthetics.

#### [MODIFY] `src/components/admin/analytics/PayrollAnalyticsEnhanced.vue`
- Incorporate existing UI design systematics: Add robust borders (`border-[#DCDEDD]`), distinct rounding (`rounded-[20px]`), and elevated cards.
- Add micro-animations (e.g. `hover:shadow-md transition-all duration-300`) to MetricCards and trend wrappers.
- Redefine typography usage to feel more structured, differentiating titles (`text-brand-dark`) from subtitles via high-contrast hierarchy.

## Verification Plan
### Automated Tests
- `bun run e2e` (if feasible locally).
- `php artisan test --filter PayrollAnalyticsTest` to forcefully trigger the TDD cycle.
- The 500 Server Errors will resolve into 200 OK across the frontend application.
