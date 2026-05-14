# Team Sync Docs

Dokumentasi proyek disederhanakan menjadi tiga area: plans, testing, dan references.
Project context untuk AI agents ada di root `AGENTS.md`.

## Struktur

```text
docs/
├── plans/
│   ├── on_going/       # Spec/plan/backlog yang masih aktif atau belum ditutup
│   └── archive/        # Plan/desain historis yang sudah selesai/superseded
├── testing/
│   ├── be/             # Backend/RBAC/role QA runbooks
│   └── fe/             # Frontend/manual QA artifacts
├── references/         # Domain guides & technical references
└── README.md           # File ini
```

## Source of Truth Aktif

| Area | File | Status |
|---|---|---|
| Project context for agents | [../AGENTS.md](../AGENTS.md) | Context aktif (root) |
| Payroll domain guide | [references/payroll.md](references/payroll.md) | Guide aktif |
| Attendance domain guide | [references/attendance.md](references/attendance.md) | Guide aktif |
| Employee self-service guide | [references/employee.md](references/employee.md) | Guide aktif |

## Plans

### On-Going

Kosong — semua plan sudah selesai dan di-archive.

### Archive

Folder [plans/archive/](plans/archive/) menyimpan dokumen selesai, superseded, atau historis. Pakai sebagai referensi konteks, bukan kontrak implementasi terbaru.

| File | Completed |
|---|---|
| [2026-05-10-security-hardening.md](plans/archive/2026-05-10-security-hardening.md) | 2026-05-10 — 6 security fixes |
| [2026-05-10-bug-fixes.md](plans/archive/2026-05-10-bug-fixes.md) | 2026-05-10 — 6 bug fixes |
| [2026-05-10-architecture-cleanup.md](plans/archive/2026-05-10-architecture-cleanup.md) | 2026-05-10 — 4 architecture cleanup tasks |
| [2026-05-10-testing-gaps.md](plans/archive/2026-05-10-testing-gaps.md) | 2026-05-10 — 5 testing gap tasks |
| [2026-05-10-unit-testing-gaps.md](plans/archive/2026-05-10-unit-testing-gaps.md) | 2026-05-10 — Service & composable unit tests |

## Testing

| File | Domain |
|---|---|
| [testing/be/payroll-role-e2e-qa.md](testing/be/payroll-role-e2e-qa.md) | Payroll role QA runbook |
| [testing/be/2026-04-21-rbac-e2e-audit.md](testing/be/2026-04-21-rbac-e2e-audit.md) | RBAC E2E audit |
| [testing/fe/manual-testing-guide.md](testing/fe/manual-testing-guide.md) | Manual testing guide per role |

## References

| File | Description |
|---|---|
| [references/attendance.md](references/attendance.md) | Attendance domain guide |
| [references/employee.md](references/employee.md) | Employee/self-service domain guide |
| [references/payroll.md](references/payroll.md) | Payroll domain guide and cross-links |
| [references/authorization-leak-or-grouped-middleware.md](references/authorization-leak-or-grouped-middleware.md) | Authorization patterns & anti-patterns |
| [references/be-fe-integration-patterns.md](references/be-fe-integration-patterns.md) | **BE-FE integration patterns** (API contract, error handling, auth, state mgmt, file uploads, permissions) |
| [references/be-fe-integration-summary.md](references/be-fe-integration-summary.md) | Quick reference for BE-FE integration |

## Conventions

- **New active plan/spec** → `docs/plans/on_going/YYYY-MM-DD-<topic>-<type>.md`
- **Completed/superseded plan** → move to `docs/plans/archive/`
- **QA runbook/audit** → `docs/testing/be/` or `docs/testing/fe/`
- **Reference/guide** → `docs/references/`
- Jangan simpan generated artifacts atau temporary audit report di `docs/` kecuali memang akan jadi source of truth.
