# Plan: Correct stale JP ceiling and select BPJS rates by contribution period

**Ticket:** team-sync-mbr (P1, BUG)
**Branch:** fix/team-sync-mbr-bpjs-period-rates
**Created:** 2026-11-21

## Problem

`bpjs_rates.component` has a UNIQUE constraint allowing only one row per component. `TaxCalculationService::loadConfig()` loads `BpjsRate::all()->keyBy('component')` without date filtering. `calculateBpjs()` takes only `grossMonthly`, no period. The seeded JP ceiling (Rp 10.042.300) is historical, not the current 2026 value.

JP ceiling history (from BPJS circulars):
- Until Feb 2025: Rp 10.042.300
- Mar 2025 to Feb 2026: Rp 10.547.400
- Mar 2026 onward: Rp 11.086.300

## Changes

### 1. Migration: allow multiple rows per component with period uniqueness

- Drop UNIQUE on `component`
- Make `effective_date` NOT NULL (default `2026-01-01` for existing timeless rows)
- Add UNIQUE on `(component, effective_date)`
- Keep `valid_until` nullable (open-ended periods)

### 2. BpjsRate model: add `forPeriod` scope

Query: select the row for a given component whose `effective_date` is the latest one that is <= the target date, and whose `valid_until` is either null or >= target date.

### 3. TaxCalculationService: period-aware loading

- `loadConfig(?string $contributionPeriod)`: accepts `Y-m` string, filters rates using `forPeriod` scope
- Remove caching by invalidating when period changes
- `calculateBpjs(float $grossMonthly, ?string $contributionPeriod = null)`: passes period to loadConfig
- `calculateMonthlyPph21`: uses BPJS rates for the given period
- `validateBpjsRates`: update to handle multiple rows per component
- `getBpjsCapWarnings`: accept optional period

### 4. Callers: pass contribution period

- `PayrollRepository::buildPayrollDetailRow`: pass `$startOfMonth->format('Y-m')` to `calculateBpjs()`
- `ThrCalculationService`: pass payment date period

### 5. BpjsRateSeeder: seed period-specific JP ceilings

Three JP rows with different `effective_date`/`valid_until`:
- JP (until Feb 2025): effective_date=2020-01-01, valid_until=2025-02-28, max=10.042.300
- JP (Mar 2025 to Feb 2026): effective_date=2025-03-01, valid_until=2026-02-28, max=10.547.400
- JP (Mar 2026+): effective_date=2026-03-01, valid_until=null, max=11.086.300

Other components (JHT, JKK, JKM, Kesehatan) remain single-row with effective_date set.

### 6. Tests

- Wage below/on/above JP ceiling for each period
- Feb 2025 payroll uses old ceiling, Mar 2025 uses new ceiling
- Feb 2026 payroll uses 2025 ceiling, Mar 2026 uses 2026 ceiling
- Historical recalculation: past payroll month selects correct period rate
- Missing period configuration: behavior when no rate matches
- Existing BPJS tests updated for new signature

### 7. Documentation

- Update README JP ceiling reference with period-qualified values

## Verification

- `composer test` (all 1478+ tests pass)
- Payroll generation with different salary months uses correct JP ceiling

---

## Status: COMPLETED (2026-11-22)

- **PR #85** squash-merged ke `main` sebagai `ba73f57d` (2026-11-22T09:06:01Z).
- Verifikasi: be-tests pass pada head SHA `05358f96` sebelum merge; BE Tests pass di `main` pasca-merge (run 45005880840); suite lokal 1659 Pest tests / 6194 assertions.
- Semua acceptance criteria teknis terpenuhi kecuali satu batas: autentikasi circular BPJS resmi pemberi kerja (B/0456/02/2025, B/1226/02/2026) tetap tugas operator sebelum nilai ceiling dipakai untuk payroll produksi. Nilai di seeder bersumber dari tiket dan tetap perlu konfirmasi notis resmi.
- Batas verifikasi lain: root AGENTS.md section Payroll dan README telah diperbarui; workflow `auto-tag` gagal di `main` karena tag `v0.0.9` sudah ada (pre-existing sejak commit #84), di luar scope tiket ini.
- Tiket Beads `team-sync-mbr` ditutup dengan bukti merge.
