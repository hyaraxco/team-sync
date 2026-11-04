# Team Sync Documentation

Titik masuk dokumentasi FE, BE, domain, pengujian, dan riwayat pengembangan.
Panduan penggunaan/development dipisahkan dari rencana historis agar arsip
tidak terbaca sebagai daftar pekerjaan atau kontrak aplikasi terbaru.

## Mulai di Sini

| Kebutuhan | Dokumen |
|---|---|
| Setup proyek | [README root](../README.md) |
| Backend: setup dan perintah | [Backend README](../team-sync-be/README.md) |
| Frontend: setup dan perintah | [Frontend README](../team-sync-fe/README.md) |
| Aturan kontribusi / agent | [Root](../AGENTS.md), [BE](../team-sync-be/AGENTS.md), [FE](../team-sync-fe/AGENTS.md) |
| Desain antarmuka frontend | [Design system](../team-sync-fe/docs/design-system.md) |
| Kebutuhan produk | [PRD](references/prd.md) — baseline historis, bukan release sign-off |

## Panduan Domain dan Integrasi

| Area | Dokumen |
|---|---|
| Kehadiran | [Attendance](references/attendance.md) |
| Payroll | [Payroll](references/payroll.md) |
| Employee self-service | [Employee](references/employee.md) |
| Integrasi BE–FE | [Patterns](references/be-fe-integration-patterns.md), [quick reference](references/be-fe-integration-summary.md) |
| Authorization | [Patterns dan anti-patterns](references/authorization-leak-or-grouped-middleware.md) |

## Pengujian

| Dokumen | Penggunaan |
|---|---|
| [Frontend E2E](../team-sync-fe/e2e/README.md) | Setup dan eksekusi Playwright |
| [Manual QA](testing/fe/manual-testing-guide.md) | Checklist per role |
| [Payroll role QA](testing/be/payroll-role-e2e-qa.md) | Skenario payroll dan permission |
| [RBAC audit 2026-04-21](testing/be/2026-04-21-rbac-e2e-audit.md) | Bukti audit historis; bukan hasil pengujian terbaru |

## Plans dan Riwayat

- **Aktif / belum ditutup:** `plans/on_going/`. Folder ini saat ini di-ignore
  oleh Git; isinya lokal dan dapat berbeda antar-checkout.
  Saat cleanup 2026-11-04 terdapat permission-gate cleanup (implementasi/tes
  tercatat, penutupan PR/CI belum diverifikasi) dan Strix CI (workflow tidak
  ada di checkout; perlu rekonsiliasi). Tidak dinyatakan selesai otomatis.
- **Belum dijadwalkan:** [Future plans](plans/future/README.md).
- **Historis / superseded:** [Archive](plans/archive/README.md).
  Keberadaan dokumen di arsip **bukan bukti semua checklist sudah selesai**.
- **Kolaborasi agent:** [Party mode](party-mode.md).

## Penempatan Dokumen

```text
docs/
├── references/          # Panduan domain dan integrasi lintas FE/BE
├── testing/be/          # Runbook dan bukti QA backend
├── testing/fe/          # Runbook dan bukti QA frontend
├── deployment/         # Contoh konfigurasi deployment
└── plans/
    ├── on_going/       # Plan lokal aktif / belum ditutup
    ├── future/         # Usulan belum dijadwalkan
    └── archive/        # Desain, audit, dan plan historis
```

- Setup khusus repo tetap di README FE/BE; aturan agent tetap di AGENTS.md.
- Design system tetap di `team-sync-fe/docs/`; petunjuk tes dekat suite terkait.
- Plan baru: `plans/on_going/YYYY-MM-DD-<topic>.md`; setelah penutupan
  diverifikasi, arsipkan dengan status dan alasan yang jelas.
- Jangan hapus bukti domain/QA hanya karena lama. Hapus duplikat atau template
  yang tidak digunakan setelah memeriksa referensinya.
- Jangan simpan kode pengujian, log, credentials, atau generated artifacts di docs.
