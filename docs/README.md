# Team Sync Docs

## Structure

```
docs/
├── plans/
│   ├── on_going/       ← Active specs, implementation plans, backlogs
│   └── archive/        ← Completed plans & designs (date-prefixed)
├── testing/
│   ├── be/             ← Backend QA runbooks & audit docs
│   └── fe/             ← Frontend QA artifacts
├── references/         ← Domain guides, checklists, reference docs
└── README.md           ← This file
```

## Plans

### On-Going
| File | Domain | Type |
|------|--------|------|
| [analytics-spec.md](plans/on_going/analytics-spec.md) | Analytics | Spec |
| [payroll-attendance-spec.md](plans/on_going/payroll-attendance-spec.md) | Payroll × Attendance | Spec |
| [payroll-attendance-implement.md](plans/on_going/payroll-attendance-implement.md) | Payroll × Attendance | Implementation |
| [payroll-attendance-plans.md](plans/on_going/payroll-attendance-plans.md) | Payroll × Attendance | Plans |
| [performance-management-spec.md](plans/on_going/performance-management-spec.md) | Performance | Spec |
| [performance-analytics-implementation-plan.md](plans/on_going/performance-analytics-implementation-plan.md) | Performance | Implementation |
| [payroll-phase-2-backlog-plan.md](plans/on_going/payroll-phase-2-backlog-plan.md) | Payroll | Backlog |
| [payroll-phase-3-plan.md](plans/on_going/payroll-phase-3-plan.md) | Payroll | Plan |

### Archive
Completed plans and designs, sorted by date. See [plans/archive/](plans/archive/).

## Testing
### Test Documentation
| File | Domain |
|------|--------|
| [payroll-role-e2e-qa.md](testing/be/payroll-role-e2e-qa.md) | Payroll QA runbook |
| [2026-04-21-rbac-e2e-audit.md](testing/be/2026-04-21-rbac-e2e-audit.md) | RBAC E2E audit |

### Executable Tests (Code)
| Type | Location |
|------|----------|
| Backend (Pest) | [team-sync-be/tests](../team-sync-be/tests/) |
| Frontend (E2E) | [team-sync-fe/e2e](../team-sync-fe/e2e/) |
| Frontend (Unit) | [team-sync-fe/src/tests](../team-sync-fe/src/tests/) |

## References
| File | Description |
|------|-------------|
| [DEPLOYMENT_CHECKLIST.md](references/DEPLOYMENT_CHECKLIST.md) | Deployment checklist |
| [attendance.md](references/attendance.md) | Attendance domain guide |
| [employee.md](references/employee.md) | Employee domain guide |
| [payroll.md](references/payroll.md) | Payroll domain guide |

## Conventions
- **New plan/spec?** → `plans/on_going/YYYY-MM-DD-<topic>-<type>.md`
- **Plan completed?** → Move to `plans/archive/`
- **QA runbook?** → `testing/be/` or `testing/fe/`
- **Reference/guide?** → `references/`
- Cross-domain features: simpan di domain pemilik bisnis, beri referensi silang.
