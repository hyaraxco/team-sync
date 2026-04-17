# Payroll Phase 3 Plan

## Ringkasan
- Payroll phase 1 dan phase 2 sudah menutup baseline utama:
  - role matrix realistis
  - guard FE dan backend konsisten
  - QA manual dan automation sudah hidup
  - settings, audit trail, resend notification, advanced report, approval layer, dan advanced period rules sudah selesai
- Phase 3 difokuskan bukan untuk memperlebar payroll tanpa arah, tetapi untuk membawa payroll dari "fitur operasional stabil" menjadi "fitur operasional matang dan terintegrasi".
- Prioritas phase 3 yang dipakai:
  1. `Payroll Reconciliation & Exception Handling`
  2. `Attendance-to-Payroll Readiness Dashboard`
  3. `Employee Notification & Handoff Completion`
  4. `Payroll Settings Versioning`
  5. `Payroll Reopen / Correction Workflow`
  6. `Advanced Approval Matrix`
  7. `Payroll Analytics & Finance Insights`

## Status Awal Phase 3
- Baseline wajib sebelum masuk phase 3:
  - payroll phase 2 hijau di backend suite, FE suite, dan Playwright role journey
  - runbook QA payroll v1 tetap dipertahankan sebagai acceptance baseline
  - role matrix tetap:
    - `manager` tanpa akses payroll
    - `hr` generate dan monitor draft
    - `finance` approve, process, report, settings
    - `employee` akses `My Payroll`

## Backlog Prioritas

### 1. Payroll Reconciliation & Exception Handling
- Tambahkan area untuk mendeteksi anomali payroll sebelum pembayaran final.
- Scope awal:
  - employee tanpa bank account valid
  - payroll detail dengan salary `0`
  - payroll detail dengan deduction tidak wajar
  - employee active tanpa attendance yang lolos readiness minimum tetapi tetap butuh review
- Tujuan:
  - finance tidak hanya melihat angka payroll, tetapi juga exception yang perlu dibersihkan sebelum paid
- Acceptance:
  - payroll detail atau dashboard menandai exception penting
  - finance bisa filter exception
  - payroll paid tidak bisa lanjut jika ada critical exception yang belum ditangani

### 2. Attendance-to-Payroll Readiness Dashboard
- Naikkan readiness dari sekadar endpoint check menjadi workspace operasional yang lebih jelas.
- Scope awal:
  - daftar employee active vs attendance readiness
  - ringkasan siapa yang belum punya attendance minimal
  - indikator cut-off status
  - CTA ke attendance workspace untuk memperbaiki data
- Tujuan:
  - HR tahu lebih awal kenapa payroll belum bisa dibuat
- Acceptance:
  - HR melihat alasan readiness per periode
  - blocker attendance bisa diidentifikasi sebelum klik generate
  - direct link ke attendance domain tersedia

### 3. Employee Notification & Handoff Completion
- Rapikan handoff setelah payroll `paid` agar experience employee lebih lengkap.
- Scope awal:
  - link notification/email langsung ke detail `My Payroll`
  - status notifikasi per payroll period
  - bukti kapan notifikasi otomatis dikirim
  - fallback empty state jika payslip belum siap
- Tujuan:
  - mengurangi gap antara finance paid flow dan employee consume flow
- Acceptance:
  - employee masuk dari email/notifikasi ke halaman yang benar
  - finance bisa melihat apakah notifikasi terkirim
  - audit log payroll dan experience employee konsisten

### 4. Payroll Settings Versioning
- Settings payroll yang sekarang sudah aktif perlu histori yang lebih kuat.
- Scope awal:
  - snapshot setting setiap kali berubah
  - label version / effective date
  - payroll draft menyimpan reference ke version setting yang dipakai
- Tujuan:
  - investigasi payroll lama tidak ambigu walau settings global sudah berubah
- Acceptance:
  - finance bisa melihat riwayat perubahan settings
  - payroll detail tahu settings version yang dipakai saat generate
  - perubahan settings baru tidak memengaruhi payroll lama

### 5. Payroll Reopen / Correction Workflow
- Saat ini payroll `approved` dan `paid` sudah ketat, tetapi belum ada workflow correction yang resmi.
- Scope awal:
  - reopen hanya untuk finance
  - hanya payroll `approved` atau `paid` tertentu
  - alasan reopen wajib
  - semua correction masuk audit trail
- Tujuan:
  - correction dilakukan secara resmi, bukan lewat edit tersembunyi
- Acceptance:
  - reopen punya state transition yang jelas
  - audit trail menyimpan actor, reason, dan timestamp
  - employee notification behavior untuk corrected payroll disepakati

### 6. Advanced Approval Matrix
- Approval layer sekarang masih single-step practical approval.
- Scope awal:
  - optional second approver berdasarkan threshold
  - approval policy berdasarkan total payroll amount
  - approval per batch, bukan per employee
- Tujuan:
  - cocok untuk perusahaan yang perlu segregation of duties lebih ketat
- Acceptance:
  - policy approval bisa dijelaskan dengan jelas
  - role approval tidak bentrok dengan final payment
  - state machine dan guard FE tetap deterministik

### 7. Payroll Analytics & Finance Insights
- Report export sudah kuat, tetapi insight operasional belum banyak.
- Scope awal:
  - tren payroll bulanan
  - total deductions trend
  - payroll growth vs headcount
  - average salary trend
- Tujuan:
  - dashboard finance tidak hanya transaksional, tetapi juga informatif
- Acceptance:
  - analytics hanya tampil untuk role sensitif
  - source angka konsisten dengan payroll report
  - FE tidak mem-fetch analytics jika permission tidak ada

## Perubahan Interface / Kontrak yang Diprediksi
- `Reconciliation`
  - kemungkinan perlu field exception status di payroll detail atau endpoint khusus exceptions
- `Readiness Dashboard`
  - kemungkinan perlu endpoint readiness summary per employee
- `Notification Handoff`
  - notification payload/link employee bisa berubah ke deep link detail payroll
- `Settings Versioning`
  - payroll kemungkinan menyimpan `payroll_setting_version_id` atau snapshot metadata
- `Reopen Workflow`
  - state payroll bertambah
  - action FE dan audit event bertambah
- `Advanced Approval Matrix`
  - approval state machine bisa bertambah dari 1 step menjadi multi-step
- `Analytics`
  - endpoint stats lanjutan kemungkinan terpisah dari statistics summary yang sekarang

## Test Plan
- `Reconciliation & Exception Handling`
  - critical exception diblok sebelum paid
  - non-critical exception tetap terdeteksi dan terlihat
- `Readiness Dashboard`
  - HR melihat blocker readiness per employee
  - link ke attendance workspace benar
- `Notification Handoff`
  - deep link employee payroll valid
  - finance melihat status delivery minimal
- `Settings Versioning`
  - payroll lama tetap pakai version lama
  - histori settings tidak hilang
- `Reopen Workflow`
  - hanya role berwenang yang bisa reopen
  - state transition dan audit trail tervalidasi
- `Advanced Approval Matrix`
  - threshold rule memicu approval tambahan dengan benar
  - route/UI mengikuti state approval yang baru
- `Analytics`
  - permission guard aman
  - angka konsisten dengan report

## Asumsi dan Default
- Phase 3 dimulai setelah phase 2 dianggap stabil, bukan paralel dengan perubahan besar role matrix.
- `Attendance` dan `Employee` domain akan semakin terkait ke payroll, jadi dokumentasi domain harus dipisah rapi dari sekarang.
- Tidak semua item phase 3 wajib dieksekusi sekaligus; urutan default tetap dimulai dari yang paling membantu operasi harian HR dan Finance.
