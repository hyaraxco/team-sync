# Team Sync Frontend

Vue 3 SPA dengan Composition API, Vite, Pinia, dan Tailwind CSS. Kode aplikasi
menggunakan JavaScript; seluruh API call berada di Pinia stores.

## Setup Lokal

Prasyarat: Node `^20.19.0 || >=22.12.0`, Bun, dan backend yang berjalan.
Jalankan dari `team-sync-fe/`:

```bash
bun install
cp .env.example .env
# Sesuaikan VITE_API_BASE_URL dengan URL API backend.
bun run dev
```

Default API pada `.env.example`: `http://localhost:8000/api/v1`.
Gunakan hostname yang konsisten untuk frontend dan backend saat menguji auth.
Setup API, queue, dan scheduler ada di [Backend README](../team-sync-be/README.md).

## Perintah

```bash
bun run test           # Vitest, satu kali eksekusi
bun run test:watch     # Vitest watch mode
bun run lint
bun run build
bun run preview
```

Untuk Playwright, ikuti [E2E runbook](e2e/README.md). Persiapan E2E melakukan
reset/seed database: gunakan database test khusus, bukan data kerja atau produksi.
Jumlah tes pada plan lama bukan hasil verifikasi terbaru.

## Dokumentasi Terkait

### Struktur yang Dipertahankan

- `docs/`: design system frontend, bukan output generated.
- `e2e/`: source tes Playwright, fixtures, dan helpers.
- `scripts/`: tooling yang dipanggil oleh package scripts, termasuk persiapan backend E2E.

### Output Generated

`dist/`, `playwright-artifacts/`, `playwright-report/`, dan `test-results/`
boleh dibersihkan saat hasil lama tidak lagi diperlukan. Folder ini di-ignore
Git dan akan dibuat lagi saat build/tes. Menghapusnya juga menghapus screenshot,
trace, dan report lokal; simpan bukti yang dibutuhkan sebelum cleanup.
Gunakan `bun.lock`; jangan menyimpan `package-lock.json` dari npm.

### Panduan

- [Aturan frontend dan struktur komponen](AGENTS.md)
- [Design system](docs/design-system.md)
- [Manual QA per role](../docs/testing/fe/manual-testing-guide.md)
- [Integrasi BE–FE](../docs/references/be-fe-integration-patterns.md)
- [Indeks dokumentasi dan arsip](../docs/README.md)
