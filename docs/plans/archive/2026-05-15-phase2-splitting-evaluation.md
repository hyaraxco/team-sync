# Phase 2 Store/View Splitting Evaluation

**Date**: 2026-05-15  
**Status**: EVALUATED - DEFER SPLITTING  
**Plan Items**: 2.5 Split Mega-Stores, 2.6 Extract Mega-View Components

---

## Decision

Defer store and mega-view splitting in Phase 2. The current import graph shows broad coupling and many tests mock the existing store modules directly. Splitting now would add high regression risk without a clear reuse boundary.

This follows the approved plan criteria: split only for proven reuse/testability, not line count alone.

---

## Store Findings

| Store | Usage | Decision |
| --- | --- | --- |
| `src/stores/payroll.js` | 41 references across payroll admin views, staff payslip views, staff creation, benchmarks, and many tests | Defer. Cross-module use exists, but consumers depend on the aggregate store surface. Split needs compatibility design and dedicated tests. |
| `src/stores/analytics.js` | 28 references, mainly analytics dashboard and analytics subcomponents | Defer. All analytics components use the same aggregate store; no independent sub-store boundary proven yet. |
| `src/stores/performanceReview.js` | 38 references across review cycle, templates, calibration, staff creation, benchmarks, and tests | Defer. Store is broadly shared; splitting could break performance flows and tests. |

---

## Mega-View Findings

| View | Decision |
| --- | --- |
| `src/views/admin/payroll/PayrollDetail.vue` | Defer. Extraction should happen after a component boundary map is created around modals/actions/tables and covered by smoke tests. |
| `src/views/admin/performance/ReviewDetail.vue` | Defer. Review sections/responses/actions need prop/event contracts defined first to avoid prop drilling. |

---

## Recommended Follow-Up

1. Add characterization tests for current store APIs before splitting.
2. Create a boundary map for each mega-view before extracting components.
3. Prefer composables for isolated logic before introducing sub-stores.
4. Split one module at a time in dedicated PRs with focused regression tests.
