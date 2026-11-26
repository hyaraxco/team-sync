# TEA-15: Restrict performance goal creation and mutation to owner or authorized team

Status: COMPLETED
Branch: `fix/team-sync-w6e-goal-authorization`

## Actual Gaps Fixed

After baseline analysis, most authorization controls were already correctly enforced on main (server-derived `assigned_by`, policy checks on CRUD, `validated()` stripping identity fields). Two genuine gaps remained:

### 1. FE self-service create missing `staff_member_id`

`MyGoals.vue` did not send `staff_member_id` in the create payload. `CreateGoalRequest` requires it → self-service create returned 422. Fixed by injecting `currentEmployeeId` from auth store into the payload.

### 2. Team goals listing scoped by `assigned_by` instead of led-team membership

`getGoalsForManager()` queried `where('assigned_by', $managerId)` — only showing goals the manager personally assigned, not all goals of their led-team members. HR/superadmin without profiles got no results.

Replaced with `getGoalsForTeam(User)`: queries goals of staff with active membership (via `teamMembers` or `jobInformation`) in the actor's led teams. HR/superadmin get company-wide listing (no team filter).

## Changes

| File | Change |
|------|--------|
| `team-sync-fe/src/views/admin/performance/MyGoals.vue` | Add `staff_member_id: currentEmployeeId.value` to create payload |
| `team-sync-fe/src/tests/admin/performance/MyGoals.smoke.test.js` | Test: createGoal receives `staff_member_id` from auth store |
| `team-sync-be/app/Interfaces/PerformanceGoalRepositoryInterface.php` | Rename `getGoalsForManager` → `getGoalsForTeam(User)` |
| `team-sync-be/app/Repositories/PerformanceGoalRepository.php` | Led-team membership query replacing `assigned_by` filter |
| `team-sync-be/app/Http/Controllers/PerformanceGoalController.php` | Pass `Auth::user()` to `getGoalsForTeam()` |
| `team-sync-be/tests/Feature/Performance/TeamGoalsListingScopeTest.php` | 5 tests: manager led-team, job info, departed, HR/superadmin |

## Not Changed (already enforced on main)

- `assigned_by` override in controller store (server-derived)
- `created_by` from `Auth::id()` in GoalDto
- Policy checks on show/update/delete/progress
- `validated()` strips identity fields from update/progress requests
- Existing GoalAuthorizationTest coverage
