# Team Sync Docs

Dokumentasi proyek disederhanakan menjadi lima area utama: requirements aktif, plans, testing, references, dan project context. Generated artifacts, audit sementara, backup, dan dokumen duplikat sudah dipangkas agar folder ini tetap mudah dipakai.

## Struktur

```text
docs/
├── requirements/       # PRD/requirements aktif
├── plans/
│   ├── on_going/       # Spec/plan/backlog yang masih aktif atau belum ditutup
│   └── archive/        # Plan/desain historis yang sudah selesai/superseded
├── testing/
│   ├── be/             # Backend/RBAC/role QA runbooks
│   └── fe/             # Frontend/manual QA artifacts
├── references/         # Domain guides dan deployment checklist
├── project-context.md  # Context engineering untuk AI agents
└── README.md           # File ini
```

## Source of Truth Aktif

| Area | File | Status |
|---|---|---|
| Role/dashboard/sidebar/settings alignment | [requirements/2026-05-07-final-role-dashboard-sidebar-settings-prd.md](requirements/2026-05-07-final-role-dashboard-sidebar-settings-prd.md) | PRD final aktif |
| Project context for agents | [project-context.md](project-context.md) | Context aktif |
| Payroll domain guide | [references/payroll.md](references/payroll.md) | Guide aktif |
| Attendance domain guide | [references/attendance.md](references/attendance.md) | Guide aktif |
| Employee self-service guide | [references/employee.md](references/employee.md) | Guide aktif |

## Plans

### On-Going

File di sini adalah pekerjaan yang masih backlog, belum disentuh, atau masih menjadi spec aktif untuk fase berikutnya.

| File | Domain | Type | Catatan |
|---|---|---|---|
| [2026-04-26-payroll-attendance-fe-plan.md](plans/on_going/2026-04-26-payroll-attendance-fe-plan.md) | Payroll × Attendance | FE Plan | Plan FE gap; validasi ulang terhadap code sebelum implementasi |
| [analytics-spec.md](plans/on_going/analytics-spec.md) | Analytics | Spec | Spec analytics/future metrics |
| [payroll-attendance-spec.md](plans/on_going/payroll-attendance-spec.md) | Payroll × Attendance | Spec | Kontrak bisnis Attendance for Fair Payroll Phase 1 |
| [payroll-phase-3-plan.md](plans/on_going/payroll-phase-3-plan.md) | Payroll | Plan | Backlog payroll phase 3 |
| [performance-analytics-implementation-plan.md](plans/on_going/performance-analytics-implementation-plan.md) | Performance × Analytics | Implementation | Plan awal; cek current code sebelum eksekusi |
| [performance-management-spec.md](plans/on_going/performance-management-spec.md) | Performance | Spec | Spec performance management |

### Archive

Folder [plans/archive/](plans/archive/) menyimpan dokumen selesai, superseded, atau historis. Pakai sebagai referensi konteks, bukan kontrak implementasi terbaru.

## Requirements

| File | Description |
|---|---|
| [2026-05-07-final-role-dashboard-sidebar-settings-prd.md](requirements/2026-05-07-final-role-dashboard-sidebar-settings-prd.md) | PRD final untuk strict least-privilege role, dashboard, sidebar, settings, analytics, dan data exposure alignment |

## Testing

| File | Domain |
|---|---|
| [testing/be/payroll-role-e2e-qa.md](testing/be/payroll-role-e2e-qa.md) | Payroll role QA runbook |
| [testing/be/2026-04-21-rbac-e2e-audit.md](testing/be/2026-04-21-rbac-e2e-audit.md) | RBAC E2E audit |
| [testing/fe/manual-testing-guide.md](testing/fe/manual-testing-guide.md) | Manual testing guide per role |

## References

| File | Description |
|---|---|
| [references/DEPLOYMENT_CHECKLIST.md](references/DEPLOYMENT_CHECKLIST.md) | Deployment checklist role/permission rename |
| [references/attendance.md](references/attendance.md) | Attendance domain guide |
| [references/employee.md](references/employee.md) | Employee/self-service domain guide |
| [references/payroll.md](references/payroll.md) | Payroll domain guide and cross-links |

## Conventions

- **New active plan/spec** → `docs/plans/on_going/YYYY-MM-DD-<topic>-<type>.md`
- **Completed/superseded plan** → move to `docs/plans/archive/`
- **PRD/requirements** → `docs/requirements/YYYY-MM-DD-<topic>.md`
- **QA runbook/audit** → `docs/testing/be/` or `docs/testing/fe/`
- **Reference/guide** → `docs/references/`
- Jangan simpan generated artifacts atau temporary audit report di `docs/` kecuali memang akan jadi source of truth.
- Cross-domain features: simpan di domain pemilik bisnis dan tambahkan referensi silang.
