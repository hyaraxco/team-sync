# UI/UX Standardization Plan

## Goal
Standardize the frontend user interface to enforce:
1. **Currency Formalism**: Use `IDR` rather than `Rp`.
2. **Language Normalization**: Ensure all UI templates use English exclusively (translating any remaining Indonesian terminology).
3. **Component Reusability & Consistency**: Propagate the high-fidelity UI aesthetics introduced in the Finance Dashboard across all other Analytics modules (Attendance, Leave, Project, Workforce) keeping the app lightweight and semantic.

## Proposed Changes

---

### [Component Reusability & Analytics Standardization]

#### [MODIFY] `src/components/admin/analytics/AttendanceAnalyticsEnhanced.vue`
#### [MODIFY] `src/components/admin/analytics/LeaveAnalyticsEnhanced.vue`
#### [MODIFY] `src/components/admin/analytics/ProjectAnalyticsEnhanced.vue`
#### [MODIFY] `src/components/admin/analytics/WorkforceAnalyticsEnhanced.vue`
- Evolve generic `<div class="bg-white rounded-lg shadow p-6">` wrappers to follow the standardized Systematic Design token: `bg-white rounded-[20px] border border-[#DCDEDD] hover:shadow-md transition-shadow duration-300 p-6`.
- Consolidate text headings to `text-[20px]/[24px] tracking-tight font-bold text-[#202020]` to match Semantic UX properties.
- Refactor instances of `<apexchart>` to `<VueApexCharts>` ensuring a singular standard dependency syntax.

---

### [Currency Parsing Standardization]

#### [MODIFY] `src/utils/formatUtils.js`
- Overhaul `formatRupiah` and `formatRupiahCompact`. Rather than delegating the currency symbol parsing to the default standard JS locale rules (which outputs `Rp`), map the numbers and manually construct prefix: `` `IDR ${new Intl.NumberFormat('id-ID', ...).format(val)}` ``.

#### [MODIFY] `src/components/admin/analytics/MetricCard.vue`
- Update the default underlying `currency` format case branch logic to explicitly print `IDR ${formattedValue}`.

---

### [Indonesian -> English Terminology Translations]

#### [MODIFY] `src/views/admin/payroll/PayrollCreate.vue`
#### [MODIFY] `src/views/admin/payroll/PayrollSettings.vue`
#### [MODIFY] `src/views/admin/performance/ReviewCycleDetail.vue`
- Conduct complete search-and-replace for localized action phrases observed internally: `"ubah"` -> `"Edit"`, `"simpan"` -> `"Save"`, `"batal"` -> `"Cancel"`, `"kembali"` -> `"Back"`, `"tambah"` -> `"Add"`, `"gaji"` -> `"Payroll"`, `"karyawan"` -> `"Employee"`.

## Verification Plan

### Automated Tests
- Run `bun run lint` in `team-sync-fe` to verify Vue template syntax validity after formatting and class replacements.
- Execute frontend unit tests (`bun run test`) to ensure utility logic parses cleanly.

### Manual Verification
- Review the `IDR` changes directly in the Finance/Payroll Dashboard and verify no visual anomalies occur due to longer string sizes.
- Verify remaining analytics panels map correctly matching MetricCards and High-Fidelity charts.
