# Analytics endpoint regression contract

`AnalyticsEndpointGapTest` requires HTTP 200 and `success: true`; HTTP 500 is never an accepted result. The four seeded fixtures use a frozen reporting date and assert complete numeric trend rows:

| Endpoint | Fixture expectation |
|---|---|
| Attendance correction frequency | Four corrections, two approved, one rejected, one pending; 50% approval and four requester corrections, including the reporting day's final second. |
| Leave approval turnaround | Approved/rejected durations truncate to one and four whole hours; average 2.5 hours, minimum one, maximum four, two processed. Pending leave is excluded. |
| Payroll cost per employee | Approved/paid cost Rp 10.000.004 across three distinct employees; average 3333334.67. Multiple payroll rows do not multiply headcount; pending payroll is excluded. |
| Payroll processing time | Approved/paid durations average 2.5 whole hours across two payrolls. Aggregate approved steps to `MAX(approved_at)` per payroll before joining, so two steps do not multiply the payroll count. Pending, rejected steps, missing approval timestamps, and dates outside the reporting window are excluded. |

Month grouping uses MySQL `DATE_FORMAT` or SQLite `strftime`. Duration aggregation preserves MySQL `TIMESTAMPDIFF(HOUR, ...)` whole-hour truncation; SQLite subtracts integer epoch seconds and truncates division by 3600. SQL aggregation stays in the database. The two payroll endpoints use `PayrollStatus` enum values, not removed model constants. Per-employee division uses a real numerator so SQLite cannot truncate before rounding.

Approval completion timestamps belong to `payroll_approvals`, not `payrolls`. Payrolls without timestamped approved steps are excluded from processing-time statistics; no synthetic completion timestamp is inferred.

Run the affected tests only against a disposable test database after clearing config:

```sh
php artisan config:clear
php artisan test tests/Feature/Analytics/AnalyticsEndpointGapTest.php
```

The existing offline sandbox runs the complete BE suite with coverage and separately builds backend assets. Local sandbox proof uses SQLite; it does not establish that the MySQL branch was executed. No development database reset or dependency installation is needed for these checks.
