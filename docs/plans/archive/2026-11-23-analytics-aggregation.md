# team-sync-dzo — Analytics aggregation accuracy (utilization, team performance, project KPI)

Date: 2026-11-23
Ticket: `team-sync-dzo` (P1)
Branch: `fix/team-sync-dzo-analytics-aggregation`

## Problem (source-confirmed)

| Location | Defect |
|---|---|
| `AnalyticsRepository::getProjectResourceUtilization` | `utilization_rate` was `AVG(CASE WHEN task IS NOT NULL THEN 1 ELSE 0 END)` over join rows, so members with more tasks weighed more than members with one task. |
| `AnalyticsRepository::getTeamPerformanceData` | `attendances` and `project_tasks` were both LEFT JOINed in one query; each attendance row was repeated once per task row, distorting both rates. |
| `AnalyticsRepository::getProjectKpis` | Counted `status = 'in_progress'`, a value that no longer exists in `ProjectStatus` (`ACTIVE = 'active'`), so active projects were undercounted. |
| `getProjectResourceUtilization` join (found during the fix) | Filtered on `project_tasks.start_date`, a column that does not exist in any migration. The endpoint failed at query time instead of returning data. |

## Changes

1. `getProjectKpis`: filter with `ProjectStatus::ACTIVE->value` and `ProjectStatus::ON_HOLD->value` (on-hold projects are still counted as active, matching the previous intent).
2. `getProjectResourceUtilization`: utilization is now `members_with_tasks / total_members * 100` with a zero-member guard and `* 100.0` for portable division.
3. `getProjectResourceUtilization`: removed the dead `project_tasks.start_date` condition. Tasks are counted when created inside the period, or still open/due inside it, using real datetime bounds.
4. `getTeamPerformanceData`: attendance and task aggregates are grouped per member in subqueries (`leftJoinSub`) and summed per team, so rows cannot multiply.

## Tests

`tests/Feature/Analytics/AnalyticsRepositoryAggregationTest.php` (new, 3 tests / 8 assertions) with deliberately uneven data:

- 3 members, tasks 5/1/0: utilization must be 66.7 (row averaging reported 85.7).
- 2 members, attendances 4/1 and tasks 2/3: attendance 80.0 and completion 80.0 (cartesian join reported 81.8 / 63.6).
- active + on_hold projects count as 2 while completed and draft do not.

Verified the three tests fail on the pre-fix repository and pass after the fix.

## Verification status — COMPLETED

- PR #97 merged by hyaraxco on 2026-11-23T08:18:36Z as `63e03256aab16509b144cded90e31c539a66428f` (squashed head `85f60b4`, noreply author).
- Post-merge BE Tests run `45137578719` on `main`: success.
- GitGuardian passed on PR head `85f60b4`. No FE workflow was triggered: backend-only change, N/A.
- Local full suite: 1693 passed (7671 assertions). `php artisan test tests/Feature/Analytics` — 29 passed.
- `./vendor/bin/pint --test` — pass on both changed files.
- Local `main` fast-forwarded to `63e0325`; branch deleted locally and remotely.
- Final test file holds 6 tests (13 assertions): the 3 AC cases plus past-due, no-due-date, and empty-team boundaries.

## Limits

- CI runs SQLite `:memory:` only; the query shapes were not exercised against MySQL in this PR.
- `AnalyticsEndpointGapTest` still accepts HTTP 500 responses. Tightening it is `team-sync-arn`, not this ticket.
- Soft-deleted `attendances` / `project_tasks` rows were already counted before this change and still are.
