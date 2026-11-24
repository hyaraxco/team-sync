# PR121 review fixes

Status: COMPLETED (local implementation and targeted verification). Baseline: 7a7f3841e4dbf5de7dc2f4c0b816d57406c26145. PR121 update and latest-head CI tracked separately. No merge, release, deploy, or development database access.

1. Permit verified employment/subjective end-date updates after earlier settlements without rewriting paid entries or imported history. Recalculate affected unpaid sources and invalidate approvals atomically; retain provenance and reject retrospective changes to settled final periods.
2. Reject unsupported nonzero government tax credits at the active-source boundary; retain historical-credit reconciliation and add rejection coverage.
3. Preserve approval revisions when retiring policies. Keep existing policy API usable and protect historical rows at the database boundary.
4. Export overtime, applied corrections and rounding so earnings minus deductions reconcile to persisted net. Update affected column contracts.
5. Run targeted backend tests in the existing offline, read-only Docker sandbox with disposable SQLite. Update payroll reference/changelog, commit scoped changes, push PR121 and check latest-head CI. No merge.

Ownership: parent owns tax-year import/model, period/integration services, tax-history tests, documentation and delivery. Policy repair owns policy repository method/controller, policy protection migration and approval tests. Export repair owns PayrollExport and export tests.

## Verification

- Offline Docker, read-only source, disposable SQLite: 52 tests passed on the first targeted run. Tax-year departure refresh preserves settled rows/history and supersedes approved decisions. Invalid retrospective end dates and replacement history are rejected. DTP rejection and historical final credits pass. API and database policy deletion protection pass.
- Initial XLSX regression failed on a duplicate bank-account fixture before its second export case; employee-specific account numbers fixed fixture isolation. Corrected export suite: 4 passed, 55 assertions, including actual XLSX generation/load with earnings, signed corrections/refunds, rounding, headings, totals and styles. Across targeted runs all 53 selected tests passed. Actual CLI help exposes `--update-profile`.
- No development database, host environment file, merge, release or deployment. Latest-head CI remains a separate gate after push.
